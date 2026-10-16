from fastapi.testclient import TestClient

from app.main import app
from app.providers.mock_provider import detect_language

client = TestClient(app)


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "healthy"
    assert body["service"] == "octa-fit-physiotherapist"
    assert body["provider"] == "mock"


def test_assess_low_risk_soreness() -> None:
    response = client.post(
        "/assess",
        json={
            "body_area": "legs",
            "symptoms": "My legs are sore after yesterday's workout",
            "pain_level": 3,
            "duration": "1 day",
            "language": "en",
        },
    )
    assert response.status_code == 200
    body = response.json()
    assert body["success"] is True
    assert body["risk_level"] == "low"
    assert body["requires_urgent_care"] is False
    assert isinstance(body["self_care"], list)


def test_assess_moderate_knee() -> None:
    response = client.post(
        "/assess",
        json={
            "body_area": "knee",
            "symptoms": "Mild pain after squats",
            "pain_level": 4,
            "trigger": "Squatting",
            "language": "en",
        },
    )
    assert response.status_code == 200
    assert response.json()["risk_level"] in {"moderate", "low", "high"}


def test_assess_red_flag_urgent() -> None:
    response = client.post(
        "/assess",
        json={
            "body_area": "knee",
            "symptoms": "Severe pain after an injury and I cannot bear weight",
            "pain_level": 9,
            "language": "en",
        },
    )
    assert response.status_code == 200
    body = response.json()
    assert body["requires_urgent_care"] is True
    assert body["risk_level"] == "urgent"


def test_assess_arabic() -> None:
    response = client.post(
        "/assess",
        json={
            "body_area": "lower_back",
            "symptoms": "أشعر بتيبس في أسفل الظهر بعد التمرين",
            "pain_level": 2,
            "language": "ar",
        },
    )
    assert response.status_code == 200
    assert response.json()["language"] == "ar"


def test_assess_invalid() -> None:
    response = client.post("/assess", json={"body_area": "knee", "symptoms": ""})
    assert response.status_code == 422


def test_follow_up_chat() -> None:
    response = client.post(
        "/chat",
        json={
            "message": "Can I train tomorrow?",
            "assessment": {
                "body_area": "legs",
                "symptoms": "muscle soreness after workout",
                "pain_level": 2,
            },
            "conversation": [],
            "language": "en",
        },
    )
    assert response.status_code == 200
    assert "train" in response.json()["summary"].lower() or "training" in response.json()["summary"].lower()


def test_detect_language() -> None:
    assert detect_language("hello") == "en"
    assert detect_language("ألم في الركبة") == "ar"
