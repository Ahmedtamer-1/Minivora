#!/usr/bin/env python3
"""Assemble an animated PowerPoint from export.mjs --layers output.

    python3 build_pptx.py layers/manifest.json -o deck.pptx [--auto]

Each slide = the Minivora background (aurora, hero, chrome) as a picture, then one picture per
animated element placed exactly where it sits in the HTML, each with a native PowerPoint entrance
effect that matches the web motion:
    rise  -> Fade + float up        (mv-rise)
    pop   -> Fade + zoom from 92%   (mv-pop)
    scale -> Fade + zoom from 82%   (mv-scale)
    left  -> Fade + slide from left (mv-from-left)
    fade / blur / words -> Fade
Effects run on their own, staggered like the web version (no clicks needed), and slides change with
a Fade transition. Every picture carries its text as alt text; speaker notes come along.
--auto also advances slides on a timer (kiosk mode).
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path

from lxml import etree
from pptx import Presentation
from pptx.oxml.ns import qn
from pptx.util import Emu

P = "http://schemas.openxmlformats.org/presentationml/2006/main"


def px(v: float, k: float) -> Emu:
    return Emu(int(round(v * k)))


def effect_xml(ctn_id: int, spid: int, anim: str, delay: int, first: bool) -> tuple[str, int]:
    """One entrance effect. Returns (xml, next free cTn id)."""
    ids = iter(range(ctn_id, ctn_id + 20))
    me = next(ids)
    node = "afterEffect" if first else "withEffect"
    preset = {"rise": (42, 0), "pop": (53, 16), "scale": (53, 16), "left": (47, 0)}.get(anim, (10, 0))
    dur = {"rise": 700, "pop": 550, "scale": 900, "left": 700, "words": 800, "blur": 800}.get(anim, 600)
    tgt = f'<p:tgtEl><p:spTgt spid="{spid}"/></p:tgtEl>'
    parts = [
        f'<p:set><p:cBhvr><p:cTn id="{next(ids)}" dur="1" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst></p:cTn>{tgt}'
        '<p:attrNameLst><p:attrName>style.visibility</p:attrName></p:attrNameLst></p:cBhvr><p:to><p:strVal val="visible"/></p:to></p:set>',
        f'<p:animEffect transition="in" filter="fade"><p:cBhvr><p:cTn id="{next(ids)}" dur="{dur}"/>{tgt}</p:cBhvr></p:animEffect>',
    ]

    def anim_attr(attr: str, frm: str, to: str):
        # Decelerating ease to echo cubic-bezier(0.22, 1, 0.36, 1).
        parts.append(
            f'<p:anim calcmode="lin" valueType="num"><p:cBhvr additive="base"><p:cTn id="{next(ids)}" dur="{dur}" decel="100000" fill="hold"/>{tgt}'
            f'<p:attrNameLst><p:attrName>{attr}</p:attrName></p:attrNameLst></p:cBhvr><p:tavLst>'
            f'<p:tav tm="0"><p:val><p:strVal val="{frm}"/></p:val></p:tav><p:tav tm="100000"><p:val><p:strVal val="{to}"/></p:val></p:tav>'
            "</p:tavLst></p:anim>"
        )

    if anim == "rise":
        anim_attr("ppt_y", "#ppt_y+0.03", "#ppt_y")
    elif anim in ("pop", "scale"):
        f = "0.92" if anim == "pop" else "0.82"
        anim_attr("ppt_w", f"#ppt_w*{f}", "#ppt_w")
        anim_attr("ppt_h", f"#ppt_h*{f}", "#ppt_h")
        if anim == "pop":
            anim_attr("ppt_y", "#ppt_y+0.02", "#ppt_y")
    elif anim == "left":
        anim_attr("ppt_x", "#ppt_x-0.025", "#ppt_x")

    xml = (
        f'<p:par><p:cTn id="{me}" presetID="{preset[0]}" presetClass="entr" presetSubtype="{preset[1]}" fill="hold" nodeType="{node}">'
        f'<p:stCondLst><p:cond delay="{delay}"/></p:stCondLst><p:childTnLst>{"".join(parts)}</p:childTnLst></p:cTn></p:par>'
    )
    return xml, next(ids)


def add_timing(slide, shapes: list[tuple[int, str]]):
    if not shapes:
        return
    nid = 5
    effects = []
    for i, (spid, anim) in enumerate(shapes):
        # Stagger like the CSS: 120ms lead-in, then 90ms per element (a little slower reads better in PPT).
        xml, nid = effect_xml(nid, spid, anim, 120 + i * 110 if i else 120, first=(i == 0))
        effects.append(xml)
    timing = (
        f'<p:timing xmlns:p="{P}"><p:tnLst><p:par><p:cTn id="1" dur="indefinite" restart="never" nodeType="tmRoot"><p:childTnLst>'
        '<p:seq concurrent="1" nextAc="seek"><p:cTn id="2" dur="indefinite" nodeType="mainSeq"><p:childTnLst>'
        '<p:par><p:cTn id="3" fill="hold"><p:stCondLst><p:cond delay="indefinite"/><p:cond evt="onBegin" delay="0"><p:tn val="2"/></p:cond></p:stCondLst><p:childTnLst>'
        '<p:par><p:cTn id="4" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst><p:childTnLst>'
        + "".join(effects)
        + "</p:childTnLst></p:cTn></p:par></p:childTnLst></p:cTn></p:par>"
        "</p:childTnLst></p:cTn>"
        '<p:prevCondLst><p:cond evt="onPrev" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:prevCondLst>'
        '<p:nextCondLst><p:cond evt="onNext" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:nextCondLst></p:seq>'
        "</p:childTnLst></p:cTn></p:par></p:tnLst></p:timing>"
    )
    _insert_after_clrmap(slide, etree.fromstring(timing))


def add_transition(slide, advance_ms: int | None):
    adv = f' advTm="{advance_ms}"' if advance_ms else ""
    el = etree.fromstring(f'<p:transition xmlns:p="{P}" spd="med"{adv}><p:fade/></p:transition>')
    _insert_after_clrmap(slide, el, before_timing=True)


def _insert_after_clrmap(slide, el, before_timing=False):
    sld = slide._element
    clr = sld.find(qn("p:clrMapOvr"))
    anchor = clr if clr is not None else sld.find(qn("p:cSld"))
    if before_timing:
        anchor.addnext(el)
        return
    tr = sld.find(qn("p:transition"))
    (tr if tr is not None else anchor).addnext(el)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("manifest")
    ap.add_argument("-o", "--out", default="deck.pptx")
    ap.add_argument("--auto", type=float, default=0, help="seconds per slide for self-running decks (0 = click to advance)")
    a = ap.parse_args()
    mpath = Path(a.manifest).resolve()
    root = mpath.parent
    m = json.loads(mpath.read_text())

    prs = Presentation()
    prs.slide_width, prs.slide_height = Emu(12192000), Emu(6858000)
    k = prs.slide_width / m["width"]
    blank = prs.slide_layouts[6]
    for s in m["slides"]:
        slide = prs.slides.add_slide(blank)
        bg = slide.shapes.add_picture(str(root / s["bg"]), 0, 0, prs.slide_width, prs.slide_height)
        bg._element.nvPicPr.cNvPr.set("descr", "Minivora background")
        bg.name = "Minivora background"
        anims = []
        for i, l in enumerate(s["layers"]):
            pic = slide.shapes.add_picture(str(root / l["png"]), px(l["x"], k), px(l["y"], k), px(l["w"], k), px(l["h"], k))
            pic.name = f"{l['anim']} {i + 1}"
            if l.get("text"):
                pic._element.nvPicPr.cNvPr.set("descr", l["text"])
            anims.append((pic.shape_id, l["anim"]))
        if s.get("notes"):
            slide.notes_slide.notes_text_frame.text = s["notes"]
        add_transition(slide, int(a.auto * 1000) if a.auto else None)
        add_timing(slide, anims)
    out = Path(a.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    prs.save(str(out))
    print(f"wrote {out} ({len(m['slides'])} slides, {sum(len(s['layers']) for s in m['slides'])} animated layers)")


if __name__ == "__main__":
    main()
