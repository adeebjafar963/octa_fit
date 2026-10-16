from __future__ import annotations

from pathlib import Path

from app.exercises.config import EXERCISE_CONFIGS
from app.exercises.form import build_observations
from app.exercises.repetition import RepetitionCounter
from app.models.schemas import AnalyzeResponse
from app.providers.base import MotionProvider
from app.providers.mock_provider import MockMotionProvider
from app.utils.geometry import calculate_angle


class MediaPipeMotionProvider(MotionProvider):
    """
    OpenCV + MediaPipe pose processing.

    Falls back to a controlled insufficient-detection response (not random mock success)
    when pose cannot be extracted. On import/runtime failure, callers should fall back
    to MockMotionProvider via build_provider or analyze wrapper.
    """

    name = "mediapipe"

    def __init__(self) -> None:
        self._fallback = MockMotionProvider()

    async def analyze(self, exercise: str, video_path: Path) -> AnalyzeResponse:
        config = EXERCISE_CONFIGS.get(exercise)
        if config is None:
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

        try:
            import cv2  # type: ignore
            import mediapipe as mp  # type: ignore
        except Exception:
            # Soft failure: return mock-style educational result with provider note.
            result = await self._fallback.analyze(exercise, video_path)
            return result.model_copy(
                update={
                    "provider": "mediapipe_unavailable_fallback_mock",
                    "observations": [
                        "MediaPipe/OpenCV were unavailable; returning deterministic educational sample output.",
                        *result.observations,
                    ],
                }
            )

        mp_pose = mp.solutions.pose
        counter = RepetitionCounter(config)
        frames = 0
        pose_hits = 0
        fps = 30.0

        try:
            capture = cv2.VideoCapture(str(video_path))
            if not capture.isOpened():
                return _insufficient(exercise, self.name, "Unable to open the uploaded video.")

            reported_fps = capture.get(cv2.CAP_PROP_FPS)
            if reported_fps and reported_fps > 1:
                fps = float(reported_fps)

            with mp_pose.Pose(
                static_image_mode=False,
                model_complexity=0,
                enable_segmentation=False,
                min_detection_confidence=0.5,
                min_tracking_confidence=0.5,
            ) as pose:
                # Cap frames for graduation-demo performance.
                max_frames = 300
                while frames < max_frames:
                    ok, frame = capture.read()
                    if not ok:
                        break
                    frames += 1
                    # Sample every other frame
                    if frames % 2 == 1:
                        continue

                    rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                    result = pose.process(rgb)
                    if not result.pose_landmarks:
                        continue
                    pose_hits += 1
                    lm = result.pose_landmarks.landmark
                    angle = _primary_angle(exercise, lm, mp_pose)
                    if angle is not None:
                        counter.update(angle)

            capture.release()
        except Exception:
            return _insufficient(
                exercise,
                self.name,
                "Motion analysis failed while processing this video.",
            )

        duration = round(frames / fps, 1) if fps > 0 else 0.0
        if pose_hits < 5 or frames < 10:
            return _insufficient(
                exercise,
                self.name,
                "Unable to detect enough body movement from this video.",
            )

        metrics = counter.metrics()
        observations, tips, quality, confidence = build_observations(
            exercise,
            config,
            counter.count,
            metrics,
            pose_hits,
        )

        return AnalyzeResponse(
            success=True,
            exercise=exercise,
            repetitions=counter.count,
            duration_seconds=duration,
            confidence=confidence,
            movement_quality=quality,  # type: ignore[arg-type]
            observations=observations,
            tips=tips,
            metrics=metrics,
            provider=self.name,
            frames_analyzed=pose_hits,
        )


def _landmark_xy(lm, index: int) -> tuple[float, float]:
    point = lm[index]
    return float(point.x), float(point.y)


def _primary_angle(exercise: str, lm, mp_pose) -> float | None:
    try:
        if exercise in {"squat", "lunge", "deadlift"}:
            # Prefer right side hip-knee-ankle
            return calculate_angle(
                _landmark_xy(lm, mp_pose.PoseLandmark.RIGHT_HIP.value),
                _landmark_xy(lm, mp_pose.PoseLandmark.RIGHT_KNEE.value),
                _landmark_xy(lm, mp_pose.PoseLandmark.RIGHT_ANKLE.value),
            )
        # Elbow-driven movements
        return calculate_angle(
            _landmark_xy(lm, mp_pose.PoseLandmark.RIGHT_SHOULDER.value),
            _landmark_xy(lm, mp_pose.PoseLandmark.RIGHT_ELBOW.value),
            _landmark_xy(lm, mp_pose.PoseLandmark.RIGHT_WRIST.value),
        )
    except Exception:
        return None


def _insufficient(exercise: str, provider: str, message: str) -> AnalyzeResponse:
    return AnalyzeResponse(
        success=False,
        exercise=exercise,
        repetitions=0,
        duration_seconds=0.0,
        confidence=0.2,
        movement_quality="unknown",
        observations=[
            message,
            "Please record the exercise with your full body visible.",
            "Camera positioning may affect accuracy.",
        ],
        tips=[
            "Use good lighting and keep the camera steady.",
            "Record a few clear repetitions.",
        ],
        metrics={},
        provider=provider,
        frames_analyzed=0,
        message=message,
    )
