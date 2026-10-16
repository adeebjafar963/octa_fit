"""Phase 11B — grounded OCTA FIT exercise candidates for the coach pipeline."""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field, field_validator


class GroundedExercise(BaseModel):
    """Trusted exercise reference rooted in the OCTA FIT database."""

    model_config = ConfigDict(extra="ignore")

    exercise_id: int = Field(gt=0)
    name: str
    name_en: str | None = None
    name_ar: str | None = None
    slug: str | None = None
    discipline: str | None = None
    level: str | None = None
    category: str | None = None
    category_id: int | None = None
    equipment: str | None = None
    primary_muscles: list[str] = Field(default_factory=list)
    secondary_muscles: list[str] = Field(default_factory=list)
    exercise_type: str | None = None
    movement_pattern: str | None = None
    has_media: bool = False
    is_active: bool = True
    grounded: bool = True
    flutter_route: str | None = None

    @field_validator("grounded")
    @classmethod
    def must_be_grounded(cls, value: bool) -> bool:
        if value is not True:
            raise ValueError("exercise must be grounded against OCTA FIT database")
        return value


def parse_grounded_exercises(raw: list[Any] | None) -> list[GroundedExercise]:
    """Parse candidate list from Laravel; drop invalid rows (never invent IDs)."""
    if not isinstance(raw, list):
        return []

    out: list[GroundedExercise] = []
    seen: set[int] = set()
    for item in raw:
        if not isinstance(item, dict):
            continue
        try:
            exercise = GroundedExercise.model_validate(item)
        except Exception:
            continue
        if exercise.exercise_id in seen:
            continue
        seen.add(exercise.exercise_id)
        out.append(exercise)

    return out


def validate_claimed_ids(
    claimed_ids: list[int],
    allowed: list[GroundedExercise],
) -> tuple[list[GroundedExercise], list[dict[str, Any]]]:
    """
    AI-claimed IDs are untrusted.
    Only IDs present in the trusted candidate set are accepted.
    """
    by_id = {e.exercise_id: e for e in allowed}
    valid: list[GroundedExercise] = []
    rejected: list[dict[str, Any]] = []
    seen: set[int] = set()

    for raw_id in claimed_ids:
        if raw_id in seen:
            continue
        seen.add(raw_id)
        exercise = by_id.get(raw_id)
        if exercise is None:
            rejected.append({"id": raw_id, "reason": "ungrounded_or_unknown"})
            continue
        valid.append(exercise)

    return valid, rejected
