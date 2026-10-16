from pathlib import Path
import re

root = Path(r"E:\OCTA_FIT\mobile\lib\presentation")
skip = {"home_screen.dart", "login_screen.dart", "splash_screen.dart", "main_shell.dart", "training_home_screen.dart"}
changed = []

for f in root.rglob("*.dart"):
    if f.name in skip:
        continue
    text = f.read_text(encoding="utf-8")
    orig = text

    def repl(m: re.Match[str]) -> str:
        inner = m.group(1).strip()
        return f"appBar: OctaAppBar(title: {inner})"

    text = re.sub(
        r"appBar:\s*AppBar\(\s*title:\s*Text\((.+?)\)\s*\)",
        repl,
        text,
        flags=re.S,
    )

    if text != orig:
        if "presentation/shared/widgets/app_widgets.dart" not in text:
            lines = text.splitlines(True)
            idx = max(i for i, line in enumerate(lines) if line.startswith("import "))
            lines.insert(
                idx + 1,
                "import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';\n",
            )
            text = "".join(lines)
        f.write_text(text, encoding="utf-8")
        changed.append(str(f))

print(f"updated {len(changed)}")
for c in changed:
    print(c)
