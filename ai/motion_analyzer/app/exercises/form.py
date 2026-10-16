from __future__ import annotations

from app.exercises.config import ExerciseConfig


def build_observations(
    exercise: str,
    config: ExerciseConfig,
    repetitions: int,
    metrics: dict[str, float],
    frames: int,
) -> tuple[list[str], list[str], str, float]:
    """Return observations, tips, movement_quality, confidence."""
    if frames < 10 or repetitions == 0:
        return (
            [
                "Unable to confidently analyze this video.",
                "Try recording again with your full body visible.",
                "Camera positioning may affect accuracy.",
            ],
            [
                "Place the camera so your full body is visible.",
                "Record a few clear repetitions at a steady pace.",
            ],
            "unknown",
            0.35,
        )

    primary = metrics.get(config.primary_metric, 0.0)
    minimum_key = f"minimum_{config.primary_metric.replace('average_', '')}"
    minimum = metrics.get(minimum_key, primary)

    observations: list[str] = [
        f"Detected approximately {repetitions} repetition(s) for {exercise.replace('_', ' ')}.",
    ]
    tips: list[str] = []

    if exercise == "squat":
        if minimum <= config.lowered_max:
            observations.append(
                "Your knee angle appears to have entered a depth range consistent with squatting."
            )
        else:
            observations.append(
                "Your knee angle appears to stay outside the deeper squat range in many frames."
            )
            tips.append("Try lowering a bit more if comfortable and controlled.")
        tips.append("Keep your chest more stable during the descent.")
    elif exercise in {"push_up", "biceps_curl", "shoulder_press"}:
        observations.append(
            "Elbow angle changes consistent with the selected exercise pattern were detected."
        )
        if primary > 130:
            tips.append("Try achieving a fuller controlled range of motion when comfortable.")
        tips.append("Avoid using momentum; control both the lowering and lifting phases.")
    elif exercise == "lunge":
        observations.append(
            "Front-knee angle changes consistent with lunging appear to have been detected."
        )
        tips.append("Keep the front knee tracking roughly over the foot.")

    tips.append("Camera positioning may affect accuracy.")
    observations.append("This is educational fitness feedback, not a clinical assessment.")

    quality = "good"
    confidence = 0.75
    if repetitions >= 8 and minimum <= config.lowered_max:
        quality = "excellent"
        confidence = 0.85
    elif repetitions <= 2:
        quality = "fair"
        confidence = 0.60

    return observations, tips, quality, confidence
