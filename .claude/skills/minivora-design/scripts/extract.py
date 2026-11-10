#!/usr/bin/env python3
"""Pull the content out of a source document so it can be re-told as a Minivora deck.

    python3 extract.py input.(pdf|pptx|docx|md|txt|html) -o work/          # writes work/outline.json + work/media/*

outline.json is a list of "units" (one per PDF page, PPTX slide, or DOCX/MD heading section):
    {"index": 1, "title": "...", "blocks": [{"kind": "heading|paragraph|bullet|table|image|notes", ...}]}
plus "source", "kind" and a "page_images" list when PDF pages were rasterised (for visual reference).
It never decides the design; Claude reads outline.json and writes deck.json (see SKILL.md).
"""
from __future__ import annotations

import argparse
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path


def clean(s: str) -> str:
    s = (s or "").replace(" ", " ").replace("\x0b", " ")
    return re.sub(r"[ \t]+", " ", s).strip()


# ----------------------------------------------------------------------------- PDF

def from_pdf(path: Path, media: Path, rasterise: bool) -> dict:
    import pdfplumber

    units = []
    with pdfplumber.open(str(path)) as pdf:
        for i, page in enumerate(pdf.pages, 1):
            blocks = []
            tables = []
            try:
                tables = page.extract_tables() or []
            except Exception:
                tables = []
            for t in tables:
                rows = [[clean(c or "") for c in r] for r in t if any((c or "").strip() for c in r)]
                if rows:
                    blocks.append({"kind": "table", "rows": rows})
            # Words with their size let us guess headings (biggest text on the page).
            words = page.extract_words(extra_attrs=["size"], keep_blank_chars=False, use_text_flow=True) or []
            lines: list[dict] = []
            for w in words:
                if lines and abs(lines[-1]["top"] - w["top"]) < 3 and abs(lines[-1]["size"] - w["size"]) < 0.6:
                    lines[-1]["text"] += " " + w["text"]
                else:
                    lines.append({"text": w["text"], "top": w["top"], "size": round(w["size"], 1)})
            sizes = sorted({l["size"] for l in lines}, reverse=True)
            body = max(((s, sum(1 for l in lines if l["size"] == s)) for s in sizes), key=lambda x: x[1])[0] if sizes else 0
            title = ""
            para: list[str] = []

            def flush():
                if para:
                    blocks.append({"kind": "paragraph", "text": clean(" ".join(para))})
                    para.clear()

            for l in lines:
                t = clean(l["text"])
                if not t:
                    continue
                if l["size"] >= body + 2:
                    flush()
                    if not title:
                        title = t
                    blocks.append({"kind": "heading", "text": t, "size": l["size"]})
                elif re.match(r"^([•▪●◦\-–*]|\d+[.)])\s+", t):
                    flush()
                    blocks.append({"kind": "bullet", "text": re.sub(r"^([•▪●◦\-–*]|\d+[.)])\s+", "", t)})
                else:
                    para.append(t)
                    if t.endswith((".", ":", "!", "?")):
                        flush()
            flush()
            for j, im in enumerate(page.images or []):
                try:
                    bbox = (max(im["x0"], 0), max(im["top"], 0), min(im["x1"], page.width), min(im["bottom"], page.height))
                    if (bbox[2] - bbox[0]) < 80 or (bbox[3] - bbox[1]) < 80:
                        continue
                    out = media / f"p{i:03d}_img{j + 1}.png"
                    page.crop(bbox).to_image(resolution=150).save(str(out))
                    blocks.append({"kind": "image", "path": str(out), "w": round(bbox[2] - bbox[0]), "h": round(bbox[3] - bbox[1])})
                except Exception as e:  # images are a bonus; never fail the extraction for one
                    print(f"note: skipped an image on page {i}: {e}", file=sys.stderr)
            units.append({"index": i, "title": title, "blocks": blocks})

    page_images = []
    if rasterise and shutil.which("pdftoppm"):
        prev = media / "pages"
        prev.mkdir(exist_ok=True)
        subprocess.run(["pdftoppm", "-png", "-r", "60", str(path), str(prev / "page")], check=False)
        page_images = sorted(str(p) for p in prev.glob("page*.png"))
    return {"kind": "pdf", "units": units, "page_images": page_images}


# ----------------------------------------------------------------------------- PPTX

def from_pptx(path: Path, media: Path) -> dict:
    from pptx import Presentation
    from pptx.enum.shapes import MSO_SHAPE_TYPE, PP_PLACEHOLDER

    prs = Presentation(str(path))
    units = []
    for i, slide in enumerate(prs.slides, 1):
        blocks = []
        title = ""
        if slide.shapes.title is not None and slide.shapes.title.has_text_frame:
            title = clean(slide.shapes.title.text_frame.text)

        def walk(shapes):
            for sh in shapes:
                if sh.shape_type == MSO_SHAPE_TYPE.GROUP:
                    walk(sh.shapes)
                    continue
                if sh.shape_type == MSO_SHAPE_TYPE.PICTURE:
                    try:
                        ext = sh.image.ext
                        out = media / f"s{i:03d}_{sh.shape_id}.{ext}"
                        out.write_bytes(sh.image.blob)
                        blocks.append({"kind": "image", "path": str(out), "w": int(sh.width or 0) // 9525, "h": int(sh.height or 0) // 9525})
                    except Exception:
                        pass
                    continue
                if getattr(sh, "has_table", False) and sh.has_table:
                    rows = [[clean(c.text) for c in r.cells] for r in sh.table.rows]
                    blocks.append({"kind": "table", "rows": rows})
                    continue
                if getattr(sh, "has_chart", False) and sh.has_chart:
                    ch = sh.chart
                    try:
                        cats = list(ch.plots[0].categories)
                        series = [{"name": s.name, "values": list(s.values)} for s in ch.plots[0].series]
                        blocks.append({"kind": "chart", "categories": [str(c) for c in cats], "series": series})
                    except Exception:
                        pass
                    continue
                if not getattr(sh, "has_text_frame", False) or not sh.has_text_frame:
                    continue
                is_title = sh.is_placeholder and sh.placeholder_format.type in (PP_PLACEHOLDER.TITLE, PP_PLACEHOLDER.CENTER_TITLE)
                if is_title:
                    continue
                is_sub = sh.is_placeholder and sh.placeholder_format.type == PP_PLACEHOLDER.SUBTITLE
                for p in sh.text_frame.paragraphs:
                    t = clean("".join(r.text for r in p.runs))
                    if not t:
                        continue
                    if is_sub:
                        blocks.append({"kind": "subtitle", "text": t})
                    elif p.level > 0 or len(sh.text_frame.paragraphs) > 1:
                        blocks.append({"kind": "bullet", "text": t, "level": p.level})
                    else:
                        blocks.append({"kind": "paragraph", "text": t})

        walk(slide.shapes)
        if slide.has_notes_slide:
            n = clean(slide.notes_slide.notes_text_frame.text)
            if n:
                blocks.append({"kind": "notes", "text": n})
        units.append({"index": i, "title": title, "blocks": blocks})
    return {"kind": "pptx", "units": units, "page_images": []}


# ----------------------------------------------------------------------------- DOCX

def from_docx(path: Path, media: Path) -> dict:
    import docx

    d = docx.Document(str(path))
    units = [{"index": 1, "title": "", "blocks": []}]

    def cur():
        return units[-1]["blocks"]

    body = d.element.body
    tables = {t._tbl: t for t in d.tables}
    paras = {p._p: p for p in d.paragraphs}
    for child in body.iterchildren():
        if child in paras:
            p = paras[child]
            t = clean(p.text)
            if not t:
                continue
            style = (p.style.name or "").lower() if p.style is not None else ""
            if style.startswith("title") or style.startswith("heading 1"):
                units.append({"index": len(units) + 1, "title": t, "blocks": []})
            elif style.startswith("heading"):
                cur().append({"kind": "heading", "text": t, "level": int(re.sub(r"\D", "", style) or 2)})
            elif "list" in style or p._p.pPr is not None and p._p.pPr.numPr is not None:
                cur().append({"kind": "bullet", "text": t})
            else:
                cur().append({"kind": "paragraph", "text": t})
        elif child in tables:
            rows = [[clean(c.text) for c in r.cells] for r in tables[child].rows]
            cur().append({"kind": "table", "rows": rows})
    for k, rel in enumerate(d.part.rels.values()):
        if "image" in rel.reltype:
            try:
                blob = rel.target_part.blob
                ext = Path(rel.target_ref).suffix or ".png"
                out = media / f"doc_img{k + 1}{ext}"
                out.write_bytes(blob)
                units[-1]["blocks"].append({"kind": "image", "path": str(out)})
            except Exception:
                pass
    units = [u for u in units if u["blocks"] or u["title"]]
    for i, u in enumerate(units, 1):
        u["index"] = i
    return {"kind": "docx", "units": units, "page_images": []}


# ----------------------------------------------------------------------------- Markdown / text / HTML

def from_markdown(text: str) -> dict:
    units = [{"index": 1, "title": "", "blocks": []}]
    para: list[str] = []
    table: list[list[str]] = []

    def flush():
        if para:
            units[-1]["blocks"].append({"kind": "paragraph", "text": clean(" ".join(para))})
            para.clear()
        if table:
            rows = [r for r in table if not all(re.fullmatch(r":?-{2,}:?", c) for c in r)]
            units[-1]["blocks"].append({"kind": "table", "rows": rows})
            table.clear()

    for line in text.splitlines():
        s = line.rstrip()
        m = re.match(r"^(#{1,6})\s+(.*)", s)
        if m:
            flush()
            lvl = len(m.group(1))
            if lvl <= 2:
                units.append({"index": len(units) + 1, "title": clean(m.group(2)), "blocks": []})
            else:
                units[-1]["blocks"].append({"kind": "heading", "text": clean(m.group(2)), "level": lvl})
        elif re.match(r"^\s*([-*+]|\d+[.)])\s+", s):
            flush()
            units[-1]["blocks"].append({"kind": "bullet", "text": clean(re.sub(r"^\s*([-*+]|\d+[.)])\s+", "", s))})
        elif s.strip().startswith("|"):
            if para:
                flush()
            table.append([clean(c) for c in s.strip().strip("|").split("|")])
        elif re.match(r"^\s*!\[[^\]]*\]\(([^)]+)\)", s):
            flush()
            units[-1]["blocks"].append({"kind": "image", "path": re.match(r"^\s*!\[[^\]]*\]\(([^)]+)\)", s).group(1)})
        elif s.startswith(">"):
            flush()
            units[-1]["blocks"].append({"kind": "quote", "text": clean(s.lstrip("> "))})
        elif not s.strip():
            flush()
        else:
            para.append(s)
    flush()
    units = [u for u in units if u["blocks"] or u["title"]]
    for i, u in enumerate(units, 1):
        u["index"] = i
    return {"kind": "markdown", "units": units, "page_images": []}


def from_html(text: str) -> dict:
    t = re.sub(r"(?is)<(script|style).*?</\1>", "", text)
    t = re.sub(r"(?i)<h[12][^>]*>", "\n## ", t)
    t = re.sub(r"(?i)<h[3-6][^>]*>", "\n### ", t)
    t = re.sub(r"(?i)<li[^>]*>", "\n- ", t)
    t = re.sub(r"(?i)</(p|div|h\d|li|tr)>", "\n\n", t)
    t = re.sub(r"<[^>]+>", "", t)
    import html as _h
    return from_markdown(_h.unescape(t))


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("input")
    ap.add_argument("-o", "--out", default="minivora-work", help="work folder (default ./minivora-work)")
    ap.add_argument("--no-raster", action="store_true", help="PDF: skip page thumbnails")
    a = ap.parse_args()
    src = Path(a.input).resolve()
    out = Path(a.out).resolve()
    media = out / "media"
    media.mkdir(parents=True, exist_ok=True)
    ext = src.suffix.lower()
    if ext == ".pdf":
        data = from_pdf(src, media, not a.no_raster)
    elif ext in (".pptx", ".pptm", ".potx"):
        data = from_pptx(src, media)
    elif ext in (".ppt", ".key", ".odp", ".doc", ".odt", ".rtf"):
        # Legacy formats: convert with LibreOffice first.
        if not shutil.which("soffice"):
            sys.exit(f"{ext} needs LibreOffice (soffice) to convert first")
        target = "pptx" if ext in (".ppt", ".key", ".odp") else "docx"
        subprocess.run(["soffice", "--headless", "--convert-to", target, "--outdir", str(out), str(src)], check=True, capture_output=True)
        conv = out / (src.stem + "." + target)
        data = from_pptx(conv, media) if target == "pptx" else from_docx(conv, media)
    elif ext == ".docx":
        data = from_docx(src, media)
    elif ext in (".md", ".markdown", ".txt"):
        data = from_markdown(src.read_text(errors="replace"))
    elif ext in (".html", ".htm"):
        data = from_html(src.read_text(errors="replace"))
    else:
        sys.exit(f"unsupported input type: {ext}")
    data["source"] = str(src)
    (out / "outline.json").write_text(json.dumps(data, ensure_ascii=False, indent=2))
    n_img = sum(1 for u in data["units"] for b in u["blocks"] if b["kind"] == "image")
    words = sum(len(str(b.get("text", "")).split()) for u in data["units"] for b in u["blocks"])
    print(f"wrote {out / 'outline.json'}: {len(data['units'])} units, ~{words} words, {n_img} images, {len(data['page_images'])} page thumbnails")


if __name__ == "__main__":
    main()
