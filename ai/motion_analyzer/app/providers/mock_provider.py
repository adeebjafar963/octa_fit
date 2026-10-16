from __future__ import annotations

from pathlib import Path

from app.exercises.config import EXERCISE_CONFIGS
from app.models.schemas import AnalyzeResponse
from app.providers.base import MotionProvider

# Deterministic mock results keyed by exercise.
# Graduation UI does not present these metrics as measured joint angles.
_MOCK_RESULTS: dict[str, AnalyzeResponse] = {
    "squat": AnalyzeResponse(
        exercise="squat",
        repetitions=8,
        duration_seconds=24.5,
        confidence=0.80,
        movement_quality="good",
        observations=[
            "Movement pattern appears consistent with a squat.",
            "Camera positioning may affect posture observations.",
        ],
        tips=[
            "Keep your trunk more stable during the descent.",
            "Control the downward movement instead of dropping quickly.",
        ],
        metrics={},
        provider="mock",
        frames_analyzed=120,
    ),
    "deadlift": AnalyzeResponse(
        exercise="deadlift",
        repetitions=6,
        duration_seconds=22.0,
        confidence=0.78,
        movement_quality="good",
        observations=[
            "Movement pattern appears consistent with a hip-hinge pull.",
            "Setup and camera angle may affect trunk observations.",
        ],
        tips=[
            "Brace the trunk before starting the pull.",
            "Keep the lifting path organized and controlled.",
        ],
        metrics={},
        provider="mock",
        frames_analyzed=115,
    ),
}


class MockMotionProvider(MotionProvider):
    name = "mock"

    async def analyze(self, exercise: str, video_path: Path) -> AnalyzeResponse:
        # Video path is accepted for interface parity; mock output is deterministic.
        _ = video_path
        if exercise not in EXERCISE_CONFIGS:
            return AnalyzeResponse(
                success=False,
                exercise=exercise,
                repetitions=0,
                duration_seconds=0.0,
                confidence=0.0,
                movement_quality="unknown",
                observations=["Unsupported exercise."],
                tips=[],
                metrics={},
                provider=self.name,
                message="Unsupported exercise.",
            )
        return _MOCK_RESULTS[exercise].model_copy(deep=True)
