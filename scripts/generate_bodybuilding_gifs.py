"""Original bodybuilding exercise loops for OCTA FIT.

Each GIF is drawn for this project. Nothing is copied from a third-party
exercise library. Motions follow the exercise slug so a bench press is a
lying press, not a push-up or a shoulder press.
"""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(r"E:\OCTA_FIT\mobile\assets\exercises\bodybuilding")
W, H = 480, 340
FRAMES = 12
BG = (14, 15, 18)
SKIN = (232, 214, 196)
BODY = (226, 228, 232)
ACCENT = (255, 122, 26)
BAR = (176, 180, 188)
BENCH = (58, 62, 70)
CABLE = (120, 124, 132)


def ease(t: float) -> float:
    return (1 - math.cos(t * math.tau)) / 2


def mix(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def pt(x: float, y: float) -> tuple[float, float]:
    return (x, y)


def line(d: ImageDraw.ImageDraw, a, b, color, width=10):
    d.line([a, b], fill=color, width=width)


def joint(d: ImageDraw.ImageDraw, p, r=7, color=SKIN):
    d.ellipse((p[0] - r, p[1] - r, p[0] + r, p[1] + r), fill=color)


def head(d: ImageDraw.ImageDraw, neck, angle: float, facing: int = 1):
    hx = neck[0] + math.cos(angle) * 18 * facing
    hy = neck[1] + math.sin(angle) * 18
    d.ellipse((hx - 14, hy - 14, hx + 14, hy + 14), fill=SKIN)
    return (hx, hy)


def bar_at(d, a, b, plates=True):
    line(d, a, b, BAR, 6)
    if not plates:
        return
    for p in (a, b):
        d.rounded_rectangle((p[0] - 7, p[1] - 16, p[0] + 7, p[1] + 16), radius=3, fill=BAR)


def dumbbell(d, p, angle=0.0, heavy=False):
    length = 28 if not heavy else 34
    dx = math.cos(angle) * length / 2
    dy = math.sin(angle) * length / 2
    a = (p[0] - dx, p[1] - dy)
    b = (p[0] + dx, p[1] + dy)
    line(d, a, b, BAR, 5)
    r = 8 if not heavy else 11
    for q in (a, b):
        d.ellipse((q[0] - r, q[1] - r, q[0] + r, q[1] + r), fill=ACCENT)


def bench(d, x, y, w=170, h=16):
    d.rounded_rectangle((x, y, x + w, y + h), radius=4, fill=BENCH)
    d.rectangle((x + 18, y + h, x + 28, y + h + 28), fill=BENCH)
    d.rectangle((x + w - 36, y + h, x + w - 26, y + h + 28), fill=BENCH)


def cable_post(d, x):
    d.rectangle((x, 36, x + 10, H - 28), fill=CABLE)
    d.rectangle((x - 16, 36, x + 26, 48), fill=CABLE)


def figure(
    d,
    hip,
    torso,
    hip_angle,
    knee,
    shoulder,
    elbow,
    facing=1,
    both_arms=True,
    arm_color=ACCENT,
):
    """Side-view stick figure. Angles are radians from +x, y grows downward."""
    hx, hy = hip
    shoulder_pt = (
        hx + math.cos(torso) * 78 * facing,
        hy + math.sin(torso) * 78,
    )
    neck = (
        hx + math.cos(torso) * 92 * facing,
        hy + math.sin(torso) * 92,
    )
    knee_pt = (
        hx + math.cos(hip_angle) * 70,
        hy + math.sin(hip_angle) * 70,
    )
    ankle = (
        knee_pt[0] + math.cos(knee) * 68,
        knee_pt[1] + math.sin(knee) * 68,
    )
    elbow_pt = (
        shoulder_pt[0] + math.cos(shoulder) * 52 * facing,
        shoulder_pt[1] + math.sin(shoulder) * 52,
    )
    wrist = (
        elbow_pt[0] + math.cos(elbow) * 48 * facing,
        elbow_pt[1] + math.sin(elbow) * 48,
    )
    line(d, hip, shoulder_pt, BODY, 12)
    line(d, hip, knee_pt, BODY, 11)
    line(d, knee_pt, ankle, BODY, 10)
    line(d, shoulder_pt, elbow_pt, arm_color, 10)
    line(d, elbow_pt, wrist, arm_color, 9)
    if both_arms:
        off = 10
        s2 = (shoulder_pt[0], shoulder_pt[1] + off)
        e2 = (elbow_pt[0], elbow_pt[1] + off)
        w2 = (wrist[0], wrist[1] + off)
        line(d, s2, e2, BODY, 8)
        line(d, e2, w2, BODY, 7)
    joint(d, hip)
    joint(d, knee_pt)
    joint(d, shoulder_pt)
    joint(d, elbow_pt, 6)
    head(d, neck, torso, facing)
    d.line([(ankle[0] - 12, ankle[1]), (ankle[0] + 16, ankle[1])], fill=BODY, width=4)
    return {"hip": hip, "shoulder": shoulder_pt, "elbow": elbow_pt, "wrist": wrist, "ankle": ankle, "knee": knee_pt}


def blank() -> Image.Image:
    return Image.new("RGB", (W, H), BG)


def save_gif(frames: list[Image.Image], path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    paletted = [frame.convert("P", palette=Image.Palette.ADAPTIVE, colors=32) for frame in frames]
    paletted[0].save(
        path,
        save_all=True,
        append_images=paletted[1:],
        duration=90,
        loop=0,
        optimize=True,
        disposal=2,
    )


def frames_for(draw_fn) -> list[Image.Image]:
    out = []
    for i in range(FRAMES):
        t = ease(i / FRAMES)
        img = blank()
        draw_fn(ImageDraw.Draw(img), t)
        out.append(img)
    return out


def draw_bench_press(d, t, incline=0.0, bells=False, close=False):
    bench(d, 120, 168)
    hip_y = 176
    arm = mix(-0.15, -2.6, t)
    fig = figure(
        d,
        (210, hip_y),
        mix(-0.15, -0.55, incline),
        math.pi * 0.15,
        math.pi * 0.55,
        arm,
        arm - 0.2,
        facing=1,
        both_arms=not bells,
    )
    y = fig["wrist"][1]
    if bells:
        dumbbell(d, fig["wrist"])
        dumbbell(d, (fig["wrist"][0] - 36, y + 8))
    else:
        span = 46 if close else 70
        bar_at(d, (fig["wrist"][0] - span, y), (fig["wrist"][0] + span, y))


def draw_pushup(d, t):
    drop = mix(0, 28, t)
    figure(
        d,
        (250, 150 + drop),
        -0.08,
        math.pi * 0.92,
        math.pi * 0.08,
        math.pi * 0.55 + mix(0, 0.7, t),
        math.pi * 0.15,
        facing=1,
        both_arms=False,
    )


def draw_dip(d, t):
    drop = mix(0, 36, t)
    figure(
        d,
        (240, 120 + drop),
        -math.pi / 2,
        math.pi * 0.55,
        math.pi * 0.95,
        math.pi * 0.15,
        math.pi * mix(0.85, 0.25, t),
        facing=1,
    )
    d.rectangle((150, 250, 164, H - 20), fill=BAR)
    d.rectangle((330, 250, 344, H - 20), fill=BAR)


def draw_cable_fly(d, t):
    cable_post(d, 40)
    cable_post(d, 430)
    open_arm = mix(1.15, 0.15, t)
    fig = figure(
        d,
        (240, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        -open_arm,
        -open_arm,
        facing=1,
        both_arms=False,
    )
    line(d, (50, 90), fig["wrist"], CABLE, 2)
    line(d, (430, 90), (fig["wrist"][0] - 50, fig["wrist"][1] + 6), CABLE, 2)


def draw_seated_row(d, t):
    cable_post(d, 70)
    d.rounded_rectangle((250, 200, 340, 230), radius=6, fill=BENCH)
    fig = figure(
        d,
        (280, 188),
        -math.pi / 2 + mix(0.15, 0.0, t),
        math.pi / 2,
        math.pi / 2,
        mix(0.15, 2.4, t),
        mix(0.05, 1.5, t),
        facing=-1,
    )
    line(d, (80, 150), fig["wrist"], CABLE, 2)


def draw_fly(d, t):
    bench(d, 130, 190)
    spread = mix(1.2, 0.15, t)
    fig = figure(
        d,
        (230, 198),
        -0.05,
        math.pi * 0.2,
        math.pi * 0.5,
        -spread,
        -spread,
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"])


def draw_deadlift(d, t, stiff=False):
    hinge = mix(1.05, 0.15, t)
    knee = mix(0.7, 0.15, t) if not stiff else 0.25
    fig = figure(
        d,
        (250, mix(210, 168, t)),
        -math.pi / 2 + hinge,
        math.pi / 2 + knee,
        math.pi / 2 - knee * 0.3,
        math.pi * 0.35,
        math.pi * 0.55,
        facing=1,
    )
    y = fig["wrist"][1]
    bar_at(d, (fig["wrist"][0] - 78, y), (fig["wrist"][0] + 40, y))


def draw_row(d, t, one_arm=False):
    fig = figure(
        d,
        (230, 150),
        -0.35,
        math.pi * 0.15,
        math.pi * 0.72,
        mix(0.9, 2.2, t),
        mix(0.2, 1.3, t),
        facing=1,
        both_arms=not one_arm,
    )
    if one_arm:
        dumbbell(d, fig["wrist"], heavy=True)
    else:
        bar_at(d, (fig["wrist"][0] - 60, fig["wrist"][1]), (fig["wrist"][0] + 50, fig["wrist"][1]))


def draw_pulldown(d, t):
    cable_post(d, 400)
    fig = figure(
        d,
        (230, 210),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(-2.4, -0.8, t),
        mix(-2.2, -0.4, t),
        facing=1,
    )
    y = fig["wrist"][1]
    bar_at(d, (fig["wrist"][0] - 50, y), (fig["wrist"][0] + 70, y), plates=False)
    line(d, (405, 42), (fig["wrist"][0] + 20, y), CABLE, 2)


def draw_pullup(d, t, underhand=False):
    d.rectangle((120, 48, 360, 58), fill=BAR)
    rise = mix(0, -42, t)
    fig = figure(
        d,
        (240, 210 + rise),
        -math.pi / 2,
        math.pi * 0.62,
        math.pi * 0.95,
        -2.3,
        mix(-1.2, -0.3, t),
        facing=1,
    )
    color = ACCENT if underhand else BAR
    line(d, (fig["wrist"][0] - 10, 53), (fig["wrist"][0] + 24, 53), color, 6)


def draw_shrug(d, t):
    lift = mix(0, -16, t)
    fig = figure(
        d,
        (240, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        math.pi * 0.15,
        math.pi * 0.55,
        facing=1,
    )
    dumbbell(d, (fig["wrist"][0], fig["wrist"][1] + lift), heavy=True)


def draw_press(d, t, seated=False):
    if seated:
        d.rounded_rectangle((150, 210, 250, 250), radius=8, fill=BENCH)
    fig = figure(
        d,
        (230, 200 if seated else 188),
        -math.pi / 2,
        math.pi / 2 if not seated else math.pi * 0.55,
        math.pi / 2 if not seated else math.pi * 0.15,
        mix(-0.4, -2.7, t),
        mix(0.4, -2.5, t),
        facing=1,
    )
    bar_at(d, (fig["wrist"][0] - 55, fig["wrist"][1]), (fig["wrist"][0] + 55, fig["wrist"][1]))


def draw_lateral(d, t):
    fig = figure(
        d,
        (240, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(0.4, -0.1, t),
        mix(0.5, -0.05, t),
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"])
    dumbbell(d, (fig["shoulder"][0] - 70, mix(fig["shoulder"][1] + 40, fig["shoulder"][1], t)))


def draw_curl(d, t, preacher=False, hammer=False, incline=False):
    if preacher:
        d.polygon([(150, 120), (250, 120), (230, 230), (140, 230)], fill=BENCH)
    torso = -1.15 if incline else -math.pi / 2
    fig = figure(
        d,
        (250 if not incline else 210, 190),
        torso,
        math.pi / 2,
        math.pi / 2,
        -0.2 if not preacher else -0.8,
        mix(0.7, -1.1, t),
        facing=1,
        both_arms=not preacher,
    )
    if hammer:
        dumbbell(d, fig["wrist"], angle=math.pi / 2)
    else:
        dumbbell(d, fig["wrist"])


def draw_pushdown(d, t):
    cable_post(d, 70)
    fig = figure(
        d,
        (250, 188),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        math.pi * 0.05,
        mix(-0.8, 0.7, t),
        facing=-1,
    )
    line(d, (80, 42), fig["wrist"], CABLE, 2)
    bar_at(d, (fig["wrist"][0] - 20, fig["wrist"][1]), (fig["wrist"][0] + 36, fig["wrist"][1]), plates=False)


def draw_overhead_ext(d, t):
    fig = figure(
        d,
        (240, 196),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        -2.6,
        mix(-1.5, -2.5, t),
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"])


def draw_skull(d, t):
    bench(d, 130, 176)
    fig = figure(
        d,
        (220, 184),
        -0.1,
        math.pi * 0.2,
        math.pi * 0.55,
        -0.4,
        mix(-1.5, -0.2, t),
        facing=1,
    )
    bar_at(d, (fig["wrist"][0] - 40, fig["wrist"][1]), (fig["wrist"][0] + 50, fig["wrist"][1]), plates=False)


def draw_squat(d, t, front=False):
    depth = mix(0, 1, t)
    fig = figure(
        d,
        (240, mix(170, 210, depth)),
        -math.pi / 2 + mix(0, 0.35, depth),
        math.pi / 2 + mix(0.1, 0.85, depth),
        math.pi / 2 - mix(0, 0.9, depth),
        -2.2,
        -2.0,
        facing=1,
    )
    y = fig["shoulder"][1] - (18 if front else -8)
    bar_at(d, (fig["shoulder"][0] - 70, y), (fig["shoulder"][0] + 40, y))


def draw_lunge(d, t):
    depth = mix(0, 1, t)
    figure(
        d,
        (230, mix(168, 200, depth)),
        -math.pi / 2,
        math.pi / 2 + mix(0, 0.7, depth),
        math.pi / 2 - mix(0, 0.8, depth),
        math.pi * 0.1,
        math.pi * 0.55,
        facing=1,
        both_arms=False,
    )
    d.line([(300, mix(250, 210, depth)), (360, mix(250, 230, depth))], fill=BODY, width=10)


def draw_leg_ext(d, t):
    d.rounded_rectangle((140, 170, 250, 230), radius=8, fill=BENCH)
    fig = figure(
        d,
        (190, 178),
        -math.pi / 2,
        math.pi * 0.15,
        mix(1.2, 0.05, t),
        math.pi * 0.2,
        math.pi * 0.1,
        facing=1,
        both_arms=False,
    )
    d.ellipse((fig["ankle"][0] - 10, fig["ankle"][1] - 8, fig["ankle"][0] + 22, fig["ankle"][1] + 8), fill=BAR)


def draw_leg_curl(d, t):
    bench(d, 120, 150, 200, 18)
    fig = figure(
        d,
        (200, 158),
        -0.05,
        math.pi * 0.95,
        mix(0.2, 2.2, t),
        math.pi * 0.4,
        math.pi * 0.1,
        facing=1,
        both_arms=False,
    )


def draw_hip_thrust(d, t, bridge=False):
    if not bridge:
        bench(d, 120, 150, 90, 18)
    lift = mix(30, 0, t)
    figure(
        d,
        (240, 175 + lift),
        mix(0.4, -0.05, t),
        math.pi * 0.55,
        math.pi * 0.9,
        math.pi * 0.9,
        math.pi * 0.2,
        facing=1,
        both_arms=False,
    )


def draw_kickback(d, t):
    cable_post(d, 70)
    fig = figure(
        d,
        (230, 180),
        -math.pi / 2 + 0.3,
        mix(0.4, -0.4, t),
        math.pi * 0.15,
        math.pi * 0.15,
        math.pi * 0.5,
        facing=1,
        both_arms=False,
    )
    line(d, (80, 180), fig["ankle"], CABLE, 2)


def draw_calf(d, t, toes_up=False, mode="stand"):
    if mode == "seat":
        d.rounded_rectangle((130, 150, 290, 196), radius=8, fill=BENCH)
        fig = figure(
            d,
            (180, 158),
            -math.pi / 2,
            math.pi * 0.2,
            mix(1.15, 0.85, t),
            math.pi * 0.15,
            math.pi * 0.45,
            facing=1,
            both_arms=False,
        )
        d.rounded_rectangle(
            (fig["knee"][0] - 24, fig["knee"][1] - 8, fig["knee"][0] + 16, fig["knee"][1] + 8),
            radius=3,
            fill=BAR,
        )
        d.rectangle((fig["ankle"][0] - 16, 268, fig["ankle"][0] + 36, 280), fill=BENCH)
        return
    if mode == "donkey":
        d.rounded_rectangle((150, 110, 250, 136), radius=6, fill=BENCH)
        figure(
            d,
            (190, 145),
            -0.35,
            math.pi * 0.35,
            mix(0.55, 0.2, t),
            math.pi * 0.85,
            math.pi * 0.2,
            facing=1,
            both_arms=False,
        )
        d.rectangle((140, 268, 320, 280), fill=BENCH)
        return
    if mode == "sled":
        d.rounded_rectangle((250, 90, 360, 240), radius=8, fill=BENCH)
        fig = figure(
            d,
            (170, 175),
            -0.8,
            0.35,
            mix(0.55, 0.15, t),
            math.pi * 0.8,
            math.pi * 0.15,
            facing=1,
            both_arms=False,
        )
        d.rounded_rectangle(
            (fig["ankle"][0] - 6, fig["ankle"][1] - 14, fig["ankle"][0] + 24, fig["ankle"][1] + 8),
            radius=3,
            fill=BAR,
        )
        return
    rise = mix(0, -22, t) if mode != "single" else mix(0, -18, t)
    fig = figure(
        d,
        (240, 188 + rise),
        -math.pi / 2,
        math.pi / 2 if mode != "single" else math.pi * 0.35,
        math.pi / 2 if mode != "single" else math.pi * 0.9,
        math.pi * 0.15,
        math.pi * 0.5,
        facing=1,
        both_arms=mode != "single",
    )
    d.rectangle((150, 268, 330, 278), fill=BENCH)
    if mode == "single":
        dumbbell(d, fig["wrist"])
    if toes_up:
        d.line([(fig["ankle"][0], 272), (fig["ankle"][0] + 20, mix(272, 250, t))], fill=ACCENT, width=6)


def draw_crunch(d, t):
    figure(
        d,
        (250, 200),
        mix(0.1, -0.8, t),
        math.pi * 0.55,
        math.pi * 0.15,
        math.pi * 0.8,
        math.pi * 0.2,
        facing=1,
        both_arms=False,
    )


def draw_leg_raise(d, t, hanging=False):
    if hanging:
        d.rectangle((140, 42, 340, 52), fill=BAR)
    figure(
        d,
        (240, 120 if hanging else 150),
        -math.pi / 2 if hanging else 0.15,
        mix(1.4, 0.1, t) if not hanging else mix(1.3, -0.2, t),
        math.pi * 0.1,
        -2.4 if hanging else math.pi,
        -2.2 if hanging else math.pi,
        facing=1,
        both_arms=False,
    )


def draw_plank(d, t, side=False):
    sway = math.sin(t * math.tau) * 3
    if side:
        figure(
            d,
            (240, 180 + sway),
            -0.05,
            math.pi,
            math.pi,
            -math.pi / 2,
            -math.pi / 2,
            facing=1,
            both_arms=False,
        )
    else:
        figure(
            d,
            (250, 160 + sway),
            -0.02,
            math.pi * 0.98,
            math.pi * 0.02,
            math.pi * 0.5,
            math.pi * 0.02,
            facing=1,
            both_arms=False,
        )


def draw_ab_wheel(d, t):
    reach = mix(0, 90, t)
    fig = figure(
        d,
        (180, 190),
        mix(-1.2, -0.15, t),
        math.pi * 0.7,
        math.pi * 0.2,
        mix(-0.4, 0.1, t),
        mix(-0.2, 0.05, t),
        facing=1,
        both_arms=False,
    )
    d.ellipse((fig["wrist"][0] - 12 + reach * 0.1, fig["wrist"][1] - 12, fig["wrist"][0] + 12, fig["wrist"][1] + 12), outline=BAR, width=4)


def draw_pallof(d, t):
    cable_post(d, 70)
    fig = figure(
        d,
        (240, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(2.4, 0.1, t),
        mix(2.2, 0.05, t),
        facing=1,
        both_arms=False,
    )
    line(d, (80, 150), fig["wrist"], CABLE, 2)


def draw_dead_bug(d, t):
    figure(
        d,
        (240, 180),
        0.05,
        mix(1.2, 0.2, t),
        math.pi * 0.05,
        mix(-2.2, -0.8, 1 - t),
        -2.4,
        facing=1,
        both_arms=False,
    )


def draw_swing(d, t):
    fig = figure(
        d,
        (240, mix(200, 170, t)),
        mix(-0.4, -1.45, t),
        mix(1.1, 0.4, t),
        math.pi / 2,
        mix(1.1, -2.2, t),
        mix(0.8, -2.0, t),
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"], heavy=True)


def draw_clean(d, t):
    fig = figure(
        d,
        (240, mix(200, 168, t)),
        mix(-0.5, -1.57, t),
        mix(1.0, 0.3, t),
        math.pi / 2 - mix(0, 0.4, t),
        mix(0.8, -2.3, t),
        mix(0.4, -1.8, t),
        facing=1,
    )
    bar_at(d, (fig["wrist"][0] - 50, fig["wrist"][1]), (fig["wrist"][0] + 40, fig["wrist"][1]))


def draw_thruster(d, t):
    if t < 0.5:
        draw_squat(d, t * 2)
    else:
        draw_press(d, (t - 0.5) * 2)


def draw_burpee(d, t):
    if t < 0.45:
        draw_pushup(d, t / 0.45)
    else:
        figure(
            d,
            (240, mix(188, 150, (t - 0.45) / 0.55)),
            -math.pi / 2,
            math.pi / 2,
            math.pi / 2,
            -2.6,
            -2.4,
            facing=1,
        )


def draw_carry(d, t):
    step = math.sin(t * math.tau) * 10
    fig = figure(
        d,
        (240 + step, 186),
        -math.pi / 2,
        math.pi / 2 + step / 80,
        math.pi / 2,
        math.pi * 0.12,
        math.pi * 0.5,
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"], heavy=True)
    dumbbell(d, (fig["wrist"][0] - 36, fig["wrist"][1]), heavy=True)


def draw_hang(d, t):
    sway = math.sin(t * math.tau) * 8
    d.rectangle((140, 48, 340, 58), fill=BAR)
    figure(
        d,
        (240 + sway, 200),
        -math.pi / 2,
        math.pi * 0.55,
        math.pi * 0.95,
        -2.35,
        -2.2,
        facing=1,
    )


def draw_wrist(d, t, reverse=False):
    d.rounded_rectangle((150, 190, 280, 210), radius=6, fill=BENCH)
    curl = mix(0.4, -0.6, t)
    if reverse:
        curl = -curl
    fig = figure(
        d,
        (190, 150),
        -math.pi / 2,
        math.pi * 0.2,
        math.pi * 0.9,
        math.pi * 0.05,
        curl,
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"])


def draw_face_pull(d, t):
    cable_post(d, 400)
    fig = figure(
        d,
        (200, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(-0.2, -1.8, t),
        mix(0.4, -1.2, t),
        facing=1,
        both_arms=False,
    )
    line(d, (405, 80), fig["wrist"], CABLE, 2)


def draw_arnold(d, t):
    fig = figure(
        d,
        (240, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(-0.6, -2.6, t),
        mix(-1.4, -2.4, t),
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"], angle=mix(math.pi / 2, 0, t))


def draw_landmine(d, t):
    d.ellipse((90, 250, 120, 270), fill=BAR)
    fig = figure(
        d,
        (280, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(0.8, -1.6, t),
        mix(0.5, -1.3, t),
        facing=-1,
        both_arms=False,
    )
    line(d, (105, 255), fig["wrist"], BAR, 6)


def draw_good_morning(d, t):
    fig = figure(
        d,
        (240, mix(175, 190, t)),
        mix(-1.57, -0.45, t),
        math.pi / 2 + mix(0, 0.25, t),
        math.pi / 2,
        -2.3,
        -2.1,
        facing=1,
    )
    bar_at(d, (fig["shoulder"][0] - 60, fig["shoulder"][1]), (fig["shoulder"][0] + 30, fig["shoulder"][1]))


def draw_nordic(d, t):
    d.rectangle((300, 230, 390, 246), fill=BENCH)
    figure(
        d,
        (250, 210),
        mix(-1.2, -0.2, t),
        math.pi * 0.05,
        math.pi * 0.9,
        math.pi * 0.85,
        math.pi * 0.2,
        facing=1,
        both_arms=False,
    )


def draw_abduction(d, t):
    d.rounded_rectangle((160, 150, 280, 210), radius=10, fill=BENCH)
    spread = mix(0.2, 0.9, t)
    figure(
        d,
        (210, 168),
        -math.pi / 2,
        math.pi / 2 - spread,
        math.pi / 2,
        math.pi * 0.2,
        math.pi * 0.05,
        facing=1,
        both_arms=False,
    )


def draw_sled(d, t):
    shift = mix(0, 40, t)
    d.rounded_rectangle((300 - shift, 150, 430 - shift, 250), radius=8, fill=BENCH)
    figure(
        d,
        (220 - shift * 0.3, 190),
        -1.0,
        math.pi * 0.85,
        math.pi * 0.2,
        math.pi * 0.15,
        math.pi * 0.05,
        facing=1,
        both_arms=False,
    )


def draw_renegade(d, t):
    draw_pushup(d, 0.15)
    if t > 0.4:
        dumbbell(d, (300, mix(210, 150, (t - 0.4) / 0.6)), heavy=True)


def draw_snatch(d, t):
    fig = figure(
        d,
        (240, mix(198, 170, t)),
        mix(-0.6, -1.57, t),
        mix(0.9, 0.35, t),
        math.pi / 2,
        mix(0.9, -2.8, t),
        mix(0.5, -2.7, t),
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"], heavy=True)


def draw_leg_press(d, t):
    d.rounded_rectangle((250, 80, 360, 250), radius=8, fill=BENCH)
    fig = figure(
        d,
        (190, 170),
        -0.9,
        mix(2.2, 0.35, t),
        mix(2.4, 0.2, t),
        math.pi * 0.85,
        math.pi * 0.15,
        facing=1,
        both_arms=False,
    )
    d.rounded_rectangle(
        (fig["ankle"][0] - 8, fig["ankle"][1] - 18, fig["ankle"][0] + 28, fig["ankle"][1] + 10),
        radius=4,
        fill=BAR,
    )


def draw_back_extension(d, t):
    d.polygon([(150, 150), (250, 130), (250, 160), (150, 180)], fill=BENCH)
    figure(
        d,
        (200, 155),
        mix(0.15, -0.9, t),
        math.pi * 0.15,
        math.pi * 0.55,
        math.pi * 0.9,
        math.pi * 0.2,
        facing=1,
        both_arms=False,
    )


def draw_pinch(d, t):
    fig = figure(
        d,
        (240, 188),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        math.pi * 0.15,
        math.pi * 0.45,
        facing=1,
        both_arms=False,
    )
    squeeze = mix(16, 10, t)
    d.rounded_rectangle(
        (fig["wrist"][0] - 6, fig["wrist"][1] - squeeze, fig["wrist"][0] + 10, fig["wrist"][1] + squeeze),
        radius=3,
        fill=BAR,
    )


def draw_frog(d, t):
    lift = mix(24, 0, t)
    figure(
        d,
        (240, 190 + lift),
        mix(0.35, -0.05, t),
        math.pi * 0.35,
        math.pi * 1.15,
        math.pi,
        math.pi * 0.2,
        facing=1,
        both_arms=False,
    )


def draw_seated_curl_leg(d, t):
    d.rounded_rectangle((140, 150, 260, 210), radius=8, fill=BENCH)
    fig = figure(
        d,
        (200, 168),
        -math.pi / 2,
        math.pi * 0.15,
        mix(0.3, 1.8, t),
        math.pi * 0.2,
        math.pi * 0.05,
        facing=1,
        both_arms=False,
    )
    d.ellipse((fig["ankle"][0] - 8, fig["ankle"][1] - 8, fig["ankle"][0] + 18, fig["ankle"][1] + 8), fill=BAR)


def draw_straight_pulldown(d, t):
    cable_post(d, 400)
    fig = figure(
        d,
        (220, 190),
        -math.pi / 2,
        math.pi / 2,
        math.pi / 2,
        mix(-2.5, -0.35, t),
        mix(-2.45, -0.3, t),
        facing=1,
        both_arms=False,
    )
    bar_at(d, (fig["wrist"][0] - 20, fig["wrist"][1]), (fig["wrist"][0] + 70, fig["wrist"][1]), plates=False)
    line(d, (405, 42), fig["wrist"], CABLE, 2)


def draw_rear_delt(d, t):
    d.rounded_rectangle((150, 120, 250, 200), radius=8, fill=BENCH)
    fig = figure(
        d,
        (210, 150),
        -0.2,
        math.pi * 0.2,
        math.pi * 0.7,
        mix(-0.2, 0.9, t),
        mix(-0.15, 0.85, t),
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"])


def draw_bench_dip(d, t):
    bench(d, 250, 150, 140, 16)
    drop = mix(0, 28, t)
    figure(
        d,
        (230, 145 + drop),
        -math.pi / 2 + 0.15,
        math.pi * 0.55,
        math.pi * 0.95,
        math.pi * 0.95,
        mix(0.2, 1.1, t),
        facing=-1,
        both_arms=False,
    )


def draw_single_hinge(d, t):
    fig = figure(
        d,
        (230, mix(200, 175, t)),
        mix(-0.7, -1.35, t),
        math.pi * 0.35,
        math.pi * 0.15,
        math.pi * 0.55,
        math.pi * 0.2,
        facing=1,
        both_arms=False,
    )
    dumbbell(d, fig["wrist"])
    d.line([(250, 175), (310, mix(210, 150, t))], fill=BODY, width=9)


MOTIONS = {
    "bb-chest-barbell-bench-press": lambda d, t: draw_bench_press(d, t, 0.0),
    "bb-chest-incline-barbell-bench-press": lambda d, t: draw_bench_press(d, t, 0.85),
    "bb-chest-incline-dumbbell-press": lambda d, t: draw_bench_press(d, t, 0.85, bells=True),
    "bb-chest-flat-dumbbell-press": lambda d, t: draw_bench_press(d, t, 0.0, bells=True),
    "bb-chest-machine-chest-press": lambda d, t: draw_bench_press(d, t, 0.05),
    "bb-chest-cable-chest-fly": draw_cable_fly,
    "bb-chest-pec-deck-machine-fly": draw_fly,
    "bb-chest-weighted-chest-dip": draw_dip,
    "bb-chest-push-up": draw_pushup,
    "bb-back-conventional-deadlift": lambda d, t: draw_deadlift(d, t, False),
    "bb-back-pull-up": lambda d, t: draw_pullup(d, t, False),
    "bb-back-lat-pulldown": draw_pulldown,
    "bb-back-barbell-bent-over-row": lambda d, t: draw_row(d, t, False),
    "bb-back-one-arm-dumbbell-row": lambda d, t: draw_row(d, t, True),
    "bb-back-seated-cable-row": draw_seated_row,
    "bb-back-chest-supported-t-bar-row": lambda d, t: draw_row(d, t, False),
    "bb-back-straight-arm-pulldown": draw_straight_pulldown,
    "bb-back-barbell-shrug": draw_shrug,
    "bb-shoulders-standing-overhead-press": lambda d, t: draw_press(d, t, False),
    "bb-shoulders-seated-dumbbell-shoulder-press": lambda d, t: draw_press(d, t, True),
    "bb-shoulders-dumbbell-lateral-raise": draw_lateral,
    "bb-shoulders-cable-lateral-raise": draw_lateral,
    "bb-shoulders-reverse-pec-deck-fly": draw_rear_delt,
    "bb-shoulders-cable-face-pull": draw_face_pull,
    "bb-shoulders-arnold-press": draw_arnold,
    "bb-shoulders-landmine-press": draw_landmine,
    "bb-biceps-barbell-curl": draw_curl,
    "bb-biceps-ez-bar-curl": draw_curl,
    "bb-biceps-incline-dumbbell-curl": lambda d, t: draw_curl(d, t, incline=True),
    "bb-biceps-hammer-curl": lambda d, t: draw_curl(d, t, hammer=True),
    "bb-biceps-cable-curl": draw_curl,
    "bb-biceps-preacher-curl": lambda d, t: draw_curl(d, t, preacher=True),
    "bb-biceps-chin-up": lambda d, t: draw_pullup(d, t, True),
    "bb-triceps-close-grip-bench-press": lambda d, t: draw_bench_press(d, t, 0.0, close=True),
    "bb-triceps-triceps-rope-pushdown": draw_pushdown,
    "bb-triceps-overhead-cable-triceps-extension": draw_overhead_ext,
    "bb-triceps-lying-triceps-extension-skull-crusher": draw_skull,
    "bb-triceps-dumbbell-overhead-extension": draw_overhead_ext,
    "bb-triceps-parallel-bar-dip": draw_dip,
    "bb-triceps-bench-dip": draw_bench_dip,
    "bb-forearms-barbell-wrist-curl": lambda d, t: draw_wrist(d, t, False),
    "bb-forearms-barbell-reverse-wrist-curl": lambda d, t: draw_wrist(d, t, True),
    "bb-forearms-reverse-curl": draw_curl,
    "bb-forearms-farmers-carry": draw_carry,
    "bb-forearms-dead-hang": draw_hang,
    "bb-forearms-plate-pinch-hold": draw_pinch,
    "bb-quads-barbell-back-squat": lambda d, t: draw_squat(d, t, False),
    "bb-quads-front-squat": lambda d, t: draw_squat(d, t, True),
    "bb-quads-leg-press": draw_leg_press,
    "bb-quads-hack-squat": lambda d, t: draw_squat(d, t, False),
    "bb-quads-bulgarian-split-squat": draw_lunge,
    "bb-quads-walking-lunge": draw_lunge,
    "bb-quads-leg-extension": draw_leg_ext,
    "bb-quads-goblet-squat": lambda d, t: draw_squat(d, t, True),
    "bb-hamstrings-romanian-deadlift": lambda d, t: draw_deadlift(d, t, True),
    "bb-hamstrings-lying-leg-curl": draw_leg_curl,
    "bb-hamstrings-seated-leg-curl": draw_seated_curl_leg,
    "bb-hamstrings-stiff-leg-dumbbell-deadlift": lambda d, t: draw_deadlift(d, t, True),
    "bb-hamstrings-barbell-good-morning": draw_good_morning,
    "bb-hamstrings-nordic-hamstring-curl": draw_nordic,
    "bb-hamstrings-single-leg-romanian-deadlift": draw_single_hinge,
    "bb-hamstrings-45-degree-back-extension": draw_back_extension,
    "bb-glutes-barbell-hip-thrust": lambda d, t: draw_hip_thrust(d, t, False),
    "bb-glutes-glute-bridge": lambda d, t: draw_hip_thrust(d, t, True),
    "bb-glutes-sumo-deadlift": lambda d, t: draw_deadlift(d, t, False),
    "bb-glutes-dumbbell-step-up": draw_lunge,
    "bb-glutes-cable-glute-kickback": draw_kickback,
    "bb-glutes-hip-abduction-machine": draw_abduction,
    "bb-glutes-dumbbell-reverse-lunge": draw_lunge,
    "bb-glutes-banded-frog-pump": draw_frog,
    "bb-calves-standing-calf-raise": lambda d, t: draw_calf(d, t, False, "stand"),
    "bb-calves-seated-calf-raise": lambda d, t: draw_calf(d, t, False, "seat"),
    "bb-calves-leg-press-calf-press": lambda d, t: draw_calf(d, t, False, "sled"),
    "bb-calves-single-leg-dumbbell-calf-raise": lambda d, t: draw_calf(d, t, False, "single"),
    "bb-calves-donkey-calf-raise": lambda d, t: draw_calf(d, t, False, "donkey"),
    "bb-calves-tibialis-raise": lambda d, t: draw_calf(d, t, True),
    "bb-core-hanging-leg-raise": lambda d, t: draw_leg_raise(d, t, True),
    "bb-core-cable-crunch": draw_crunch,
    "bb-core-plank": lambda d, t: draw_plank(d, t, False),
    "bb-core-side-plank": lambda d, t: draw_plank(d, t, True),
    "bb-core-ab-wheel-rollout": draw_ab_wheel,
    "bb-core-pallof-press": draw_pallof,
    "bb-core-weighted-decline-sit-up": draw_crunch,
    "bb-core-dead-bug": draw_dead_bug,
    "bb-full-body-trap-bar-deadlift": lambda d, t: draw_deadlift(d, t, False),
    "bb-full-body-barbell-power-clean": draw_clean,
    "bb-full-body-barbell-thruster": draw_thruster,
    "bb-full-body-dumbbell-snatch": draw_snatch,
    "bb-full-body-kettlebell-swing": draw_swing,
    "bb-full-body-dumbbell-renegade-row": draw_renegade,
    "bb-full-body-sled-push": draw_sled,
    "bb-full-body-burpee": draw_burpee,
}


def renderer(slug: str):
    motion = MOTIONS.get(slug)
    if motion is None:
        raise KeyError(slug)
    return motion


def main() -> None:
    import json

    rows = json.load(open(r"E:\OCTA_FIT\scripts\bodybuilding_inventory.json", encoding="utf-8-sig"))
    slugs = [(row["slug"], row["category"]) for row in rows]
    missing = [slug for slug, _ in slugs if slug not in MOTIONS]
    if missing:
        raise SystemExit("Missing motions: " + ", ".join(missing))
    if not slugs:
        raise SystemExit("No bodybuilding exercises returned")

    manifest = []
    for slug, category in slugs:
        folder = category.removeprefix("bb-").replace("-", "_")
        filename = slug.replace("-", "_") + ".gif"
        path = ROOT / folder / filename
        save_gif(frames_for(renderer(slug)), path)
        manifest.append({"slug": slug, "category": category, "file": str(path.relative_to(ROOT.parent.parent.parent)).replace("\\", "/")})
        print(path.name, path.stat().st_size)

    out = Path(r"E:\OCTA_FIT\scripts\bodybuilding_media_manifest.json")
    out.write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    print("COUNT", len(manifest))


if __name__ == "__main__":
    main()
