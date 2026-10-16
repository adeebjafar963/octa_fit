"""Phase 11B — exercise grounding / candidate parsing tests."""

from app.context.exercises import (
    GroundedExercise,
    parse_grounded_exercises,
    validate_claimed_ids,
)
from app.providers.base import ChatRequest
from app.providers.mock_provider import MockCoachProvider


def _candidate(exercise_id: int, name: str = "Barbell Bench Press") -> dict:
    return {
        "exercise_id": exercise_id,
        "name": name,
        "name_en": name,
        "name_ar": name,
        "slug": f"bb-chest-{exercise_id}",
        "discipline": "bodybuilding",
        "level": "intermediate",
        "category": "bb-chest",
        "equipment": "barbell",
        "primary_muscles": ["chest"],
        "grounded": True,
        "is_active": True,
        "flutter_route": f"/training/exercises/{exercise_id}",
    }


def test_parse_grounded_exercises_dedupes_and_skips_invalid() -> None:
    raw = [
        _candidate(10),
        _candidate(10),  # duplicate
        {"exercise_id": 0, "name": "bad", "grounded": True},  # invalid id
        {"name": "missing id", "grounded": True},
        _candidate(11, "Incline Dumbbell Press"),
    ]
    parsed = parse_grounded_exercises(raw)
    assert [e.exercise_id for e in parsed] == [10, 11]
    assert all(e.grounded for e in parsed)


def test_validate_claimed_ids_rejects_ungrounded() -> None:
    allowed = parse_grounded_exercises([_candidate(17), _candidate(25)])
    valid, rejected = validate_claimed_ids([17, 25, 999999], allowed)
    assert [e.exercise_id for e in valid] == [17, 25]
    assert rejected == [{"id": 999999, "reason": "ungrounded_or_unknown"}]


def test_chat_request_parses_candidates() -> None:
    request = ChatRequest(
        message="Give me a chest exercise",
        exercise_candidates=[_candidate(42)],
    )
    candidates = request.parsed_exercise_candidates()
    assert len(candidates) == 1
    assert candidates[0].exercise_id == 42
    assert candidates[0].flutter_route == "/training/exercises/42"


def test_mock_provider_only_returns_candidate_ids() -> None:
    import asyncio

    provider = MockCoachProvider()
    request = ChatRequest(
        message="Give me a chest workout",
        language="en",
        exercise_candidates=[_candidate(101, "Bench Press"), _candidate(102, "Fly")],
    )
    result = asyncio.run(provider.generate(request, "en"))
    ids = [row["exercise_id"] for row in result.grounded_exercises]
    assert ids
    assert set(ids).issubset({101, 102})
    assert 999999 not in ids
    assert "101" in result.message or "Bench Press" in result.message


def test_grounded_exercise_requires_positive_id() -> None:
    try:
        GroundedExercise(exercise_id=-1, name="Nope", grounded=True)
        assert False, "expected validation error"
    except Exception:
        pass
