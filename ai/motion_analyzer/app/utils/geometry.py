"""Shared geometry helpers for pose angle calculations."""

from __future__ import annotations

import math
from typing import Sequence


Point = Sequence[float]


def calculate_angle(point_a: Point, point_b: Point, point_c: Point) -> float:
    """
    Angle at point_b formed by points a-b-c, in degrees (0–180).

    Uses 2D coordinates (x, y). Extra dimensions are ignored.
    """
    ax, ay = float(point_a[0]), float(point_a[1])
    bx, by = float(point_b[0]), float(point_b[1])
    cx, cy = float(point_c[0]), float(point_c[1])

    ba = (ax - bx, ay - by)
    bc = (cx - bx, cy - by)

    ba_norm = math.hypot(ba[0], ba[1])
    bc_norm = math.hypot(bc[0], bc[1])
    if ba_norm == 0.0 or bc_norm == 0.0:
        return 0.0

    cos_angle = (ba[0] * bc[0] + ba[1] * bc[1]) / (ba_norm * bc_norm)
    cos_angle = max(-1.0, min(1.0, cos_angle))
    return math.degrees(math.acos(cos_angle))
