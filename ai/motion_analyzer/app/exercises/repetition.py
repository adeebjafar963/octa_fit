from __future__ import annotations

from enum import Enum

from app.exercises.config import ExerciseConfig


class RepState(str, Enum):
    UP = "up"
    DOWN = "down"


class RepetitionCounter:
    """
    Deterministic up/down state machine for exercise repetitions.

    Starts in UP. Transition UP→DOWN when angle enters lowered range,
    then DOWN→UP when angle returns to standing range counts one rep.
    """

    def __init__(self, config: ExerciseConfig) -> None:
        self.config = config
        self.state = RepState.UP
        self.count = 0
        self._angles: list[float] = []

    def update(self, angle: float) -> int:
        self._angles.append(angle)
        cfg = self.config

        if self.state == RepState.UP:
            if cfg.lowered_min <= angle <= cfg.lowered_max:
                self.state = RepState.DOWN
        elif self.state == RepState.DOWN:
            if cfg.standing_min <= angle <= cfg.standing_max:
                self.state = RepState.UP
                self.count += 1

        return self.count

    @property
    def angles(self) -> list[float]:
        return list(self._angles)

    def metrics(self) -> dict[str, float]:
        if not self._angles:
            return {
                self.config.primary_metric: 0.0,
                f"minimum_{self.config.primary_metric.replace('average_', '')}": 0.0,
            }
        key = self.config.primary_metric
        min_key = f"minimum_{key.replace('average_', '')}"
        return {
            key: round(sum(self._angles) / len(self._angles), 1),
            min_key: round(min(self._angles), 1),
        }
