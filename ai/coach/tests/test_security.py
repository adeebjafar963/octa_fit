import os

from fastapi.testclient import TestClient

from app.main import app


client = TestClient(app)


def test_chat_open_when_internal_token_unset(monkeypatch) -> None:
    monkeypatch.delenv("INTERNAL_TOKEN", raising=False)
    response = client.post(
        "/chat",
        json={"message": "How can I build muscle?", "conversation": [], "language": "en"},
    )
    assert response.status_code == 200


def test_chat_rejects_missing_token_when_configured(monkeypatch) -> None:
    monkeypatch.setenv("INTERNAL_TOKEN", "secret-test-token")
    response = client.post(
        "/chat",
        json={"message": "How can I build muscle?", "conversation": [], "language": "en"},
    )
    assert response.status_code == 401


def test_chat_accepts_matching_token(monkeypatch) -> None:
    monkeypatch.setenv("INTERNAL_TOKEN", "secret-test-token")
    response = client.post(
        "/chat",
        headers={"X-Internal-Token": "secret-test-token"},
        json={"message": "How can I build muscle?", "conversation": [], "language": "en"},
    )
    assert response.status_code == 200
    assert response.json()["success"] is True


def test_health_remains_open_without_token(monkeypatch) -> None:
    monkeypatch.setenv("INTERNAL_TOKEN", "secret-test-token")
    response = client.get("/health")
    assert response.status_code == 200
