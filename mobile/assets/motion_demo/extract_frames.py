import imageio.v3 as iio
from PIL import Image
from pathlib import Path
import shutil

desktop = Path.home() / "Desktop"
dest = Path(r"E:\OCTA_FIT\mobile\assets\motion_demo")
frames_dir = dest / "frames"
frames_dir.mkdir(parents=True, exist_ok=True)

sources = {}
for p in desktop.glob("*.mp4"):
    name = p.name
    if "203403" in name:
        sources["203403"] = p
    elif "203808" in name:
        sources["203808"] = p

print("found", {k: v.name for k, v in sources.items()})

for key, src in sources.items():
    target = dest / f"video_{key}.mp4"
    shutil.copy2(src, target)
    print("copied", src, "->", target, "bytes", target.stat().st_size)

    # Extract frames using imageio-ffmpeg
    try:
        all_frames = list(iio.imiter(target))
    except Exception as e:
        print("imiter failed", key, e)
        all_frames = []
        for i in range(0, 40):
            try:
                all_frames.append(iio.imread(target, index=i))
            except Exception:
                break

    n = len(all_frames)
    if n == 0:
        print("no frames", key)
        continue
    print(key, "frames", n, "shape", all_frames[0].shape)
    idxs = sorted({0, n // 8, n // 4, n // 3, n // 2, (2 * n) // 3, (3 * n) // 4, (7 * n) // 8, n - 1})
    for i in idxs:
        Image.fromarray(all_frames[i]).save(frames_dir / f"{key}_f{i:03d}.jpg", quality=90)
        print("saved", f"{key}_f{i:03d}.jpg")
