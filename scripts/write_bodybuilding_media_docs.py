import json
from collections import defaultdict
from datetime import date
from pathlib import Path

rows = json.load(open(r"E:\OCTA_FIT\scripts\bodybuilding_inventory.json", encoding="utf-8-sig"))
today = date.today().isoformat()
groups = [
    "full-body",
    "hamstrings",
    "shoulders",
    "forearms",
    "triceps",
    "biceps",
    "glutes",
    "calves",
    "chest",
    "back",
    "quads",
    "core",
]


def asset_for(slug: str) -> str:
    rest = slug[3:]
    for group in groups:
        if rest == group or rest.startswith(group + "-"):
            folder = group.replace("-", "_")
            file_name = slug.replace("-", "_") + ".gif"
            return f"assets/exercises/bodybuilding/{folder}/{file_name}"
    raise KeyError(slug)


by_group = defaultdict(list)
for row in rows:
    by_group[row["category"]].append(row)

lines = [
    "# Bodybuilding exercise media sources",
    "",
    "Every animation in this library was drawn for OCTA FIT.",
    "No third-party exercise GIF, stock clip, or watermarked image was downloaded.",
    "",
    "License / usage: original work created for the OCTA FIT project.",
    "The files may be stored in the app and shown as the bodybuilding exercise demonstration.",
    "",
    f"Date obtained: {today}",
    "",
    "Format: looping GIF, 480×340, 12 frames, local Flutter asset.",
    "",
]
order = [
    "bb-chest",
    "bb-back",
    "bb-shoulders",
    "bb-biceps",
    "bb-triceps",
    "bb-forearms",
    "bb-quads",
    "bb-hamstrings",
    "bb-glutes",
    "bb-calves",
    "bb-core",
    "bb-full-body",
]
for category in order:
    lines.append(f"## {category}")
    lines.append("")
    for row in by_group[category]:
        lines.append(f"Exercise: {row['name_en']} ({row['name_ar']})")
        lines.append("Source: Original animation generated for OCTA FIT")
        lines.append(f"Asset filename: {asset_for(row['slug'])}")
        lines.append("License / usage note: Original project artwork. Not copied from an external exercise library.")
        lines.append(f"Date obtained: {today}")
        lines.append("")

path = Path(r"E:\OCTA_FIT\docs\BODYBUILDING_MEDIA_SOURCES.md")
path.parent.mkdir(parents=True, exist_ok=True)
path.write_text("\n".join(lines), encoding="utf-8")
print(path, "exercises", len(rows))
