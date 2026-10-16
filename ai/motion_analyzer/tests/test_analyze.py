from pathlib import Path

from fastapi.testclient import TestClient

from app.exercises.config import EXERCISE_CONFIGS
from app.exercises.repetition import RepetitionCounter
from app.main import app
from app.utils.geometry import calculate_angle

client = TestClient(app)


def _tiny_video_bytes() -> bytes:
    # Not a real video container; mock provider ignores content.
    return b"\x00\x00\x00\x18ftypmp42" + b"\x00" * 64


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "healthy"
    assert body["service"] == "octa-fit-motion-analyzer"
    assert body["provider"] == "mock"
    assert "squat" in body["supported_exercises"]


def test_analyze_mock_squat() -> None:
    response = client.post(
        "/analyze",
        data={"exercise": "squat"},
        files={"video": ("demo.mp4", _tiny_video_bytes(), "video/mp4")},
    )
    assert response.status_code == 200
    body = response.json()
    assert body["success"] is True
    assert body["exercise"] == "squat"
    assert body["repetitions"] == 8
    assert body["confidence"] == 0.80
    assert body["movement_quality"] == "good"
    assert body["provider"] == "mock"


def test_analyze_mock_deadlift() -> None:
    response = client.post(
        "/analyze",
        data={"exercise": "deadlift"},
        files={"video": ("demo.mp4", _tiny_video_bytes(), "video/mp4")},
    )
    assert response.status_code == 200
    assert response.json()["exercise"] == "deadlift"
    assert response.json()["repetitions"] == 6


def test_unsupported_exercise() -> None:
    response = client.post(
        "/analyze",
        data={"exercise": "cartwheel"},
        files={"video": ("demo.mp4", _tiny_video_bytes(), "video/mp4")},
    )
    assert response.status_code == 422


def test_missing_video() -> None:
    response = client.post("/analyze", data={"exercise": "squat"})
    assert response.status_code == 422


def test_empty_video() -> None:
    response = client.post(
        "/analyze",
        data={"exercise": "squat"},
        files={"video": ("demo.mp4", b"", "video/mp4")},
    )
    assert response.status_code == 422


def test_unsupported_format() -> None:
    response = client.post(
        "/analyze",
        data={"exercise": "squat"},
        files={"video": ("demo.txt", b"hello", "text/plain")},
    )
    assert response.status_code == 422


def test_calculate_angle_right_angle() -> None:
    angle = calculate_angle((0, 0), (0, 1), (1, 1))
    assert abs(angle - 90.0) < 0.001


def test_calculate_angle_straight() -> None:
    angle = calculate_angle((0, 0), (1, 0), (2, 0))
    assert abs(angle - 180.0) < 0.001


def test_repetition_state_machine() -> None:
    cfg = EXERCISE_CONFIGS["squat"]
    counter = RepetitionCounter(cfg)
    # standing
    counter.update(170)
    # down
    counter.update(90)
    # up -> 1 rep
    assert counter.update(165) == 1
    counter.update(85)
    assert counter.update(160) == 2


def test_analyze_deadlift_mock() -> None:
    response = client.post(
        "/analyze",
        data={"exercise": "deadlift"},
        files={"video": ("demo.mp4", _tiny_video_bytes(), "video/mp4")},
    )
    assert response.status_code == 200
    assert response.json()["repetitions"] == 6
