#!/usr/bin/env python3
"""Re-sync the skill's design tokens from the app, after `npm run tokens` changed them.

    python3 .claude/skills/minivora-design/scripts/sync_tokens.py

Copies resources/css/tokens.css (generated from design/tokens/tokens.json) into assets/tokens.css
and design/tokens/tokens.json into assets/tokens.json, so decks always match the live app.
"""
from pathlib import Path

skill = Path(__file__).resolve().parent.parent
repo = skill.parents[2]
src_css = repo / "resources/css/tokens.css"
src_json = repo / "design/tokens/tokens.json"
if not src_css.exists():
    raise SystemExit(f"not found: {src_css} (run from inside the Minivora repo)")
lines = src_css.read_text().splitlines()
header = [
    "/* Minivora design tokens: a copy of resources/css/tokens.css (generated from design/tokens/tokens.json).",
    "   Re-sync with: python3 .claude/skills/minivora-design/scripts/sync_tokens.py */",
]
(skill / "assets/tokens.css").write_text("\n".join(header + lines[2:]) + "\n")
(skill / "assets/tokens.json").write_text(src_json.read_text())
print("synced tokens.css and tokens.json from the app")
