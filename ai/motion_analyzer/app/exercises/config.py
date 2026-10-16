from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

ExerciseId = Literal[
    "squat",
    "deadlift",
]

SUPPORTED_EXERCISES: tuple[str, ...] = (
    "squat",
    "deadlift",
)


@dataclass(frozen=True)
class ExerciseConfig:
    exercise_id: str
    standing_min: float
    standing_max: float
    lowered_min: float
    lowered_max: float
    primary_metric: str


# Centralized thresholds for repetition state machines (degrees).
# Kept for production analyzer compatibility — graduation demo does not surface these as claimed measurements.
EXERCISE_CONFIGS: dict[str, ExerciseConfig] = {
    "squat": ExerciseConfig(
        exercise_id="squat",
        standing_min=150.0,
        standing_max=180.0,
        lowered_min=60.0,
        lowered_max=110.0,
        primary_metric="average_knee_angle",
    ),
    "deadlift": ExerciseConfig(
        exercise_id="deadlift",
        standing_min=150.0,
        standing_max=180.0,
        lowered_min=70.0,
        lowered_max=120.0,
        primary_metric="average_hip_angle",
    ),
}


def is_supported_exercise(exercise: str) -> bool:
    return exercise in EXERCISE_CONFIGS
