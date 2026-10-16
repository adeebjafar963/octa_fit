from fastapi.testclient import TestClient

from app.main import app
from app.providers.mock_provider import MockCoachProvider, detect_language
from app.providers.base import ChatRequest

client = TestClient(app)


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "healthy"
    assert body["service"] == "octa-fit-coach"


def test_chat_valid_english() -> None:
    response = client.post(
        "/chat",
        json={"message": "How can I build muscle?", "conversation": [], "language": "en"},
    )
    assert response.status_code == 200
    body = response.json()
    assert body["success"] is True
    assert body["provider"] == "mock"
    assert "protein" in body["message"].lower() or "train" in body["message"].lower()


def test_chat_arabic() -> None:
    response = client.post(
        "/chat",
        json={"message": "كيف أبني العضلات؟", "conversation": [], "language": "ar"},
    )
    assert response.status_code == 200
    body = response.json()
    assert body["language"] == "ar"
    assert "عضل" in body["message"] or "تدريب" in body["message"]


def test_chat_invalid_empty() -> None:
    response = client.post("/chat", json={"message": " ", "conversation": []})
    assert response.status_code == 422


def test_medical_redirect() -> None:
    response = client.post(
        "/chat",
        json={"message": "My knee hurts, what injury do I have?", "language": "en"},
    )
    assert response.status_code == 200
    assert "can't diagnose" in response.json()["message"].lower()


def test_detect_language() -> None:
    assert detect_language("Hello coach") == "en"
    assert detect_language("أريد خطة تدريب") == "ar"


def test_mock_provider_direct() -> None:
    import asyncio

    provider = MockCoachProvider()
    result = asyncio.run(provider.generate(ChatRequest(message="beginner workout plan"), "en"))
    assert result.provider == "mock"
    assert "beginner" in result.message.lower() or "3 days" in result.message.lower()
