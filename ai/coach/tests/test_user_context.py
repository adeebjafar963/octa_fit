"""Phase 11A — CoachUserContext parsing and normalization tests."""

from app.context.user_context import parse_user_context
from app.providers.base import ChatRequest


def test_unavailable_context() -> None:
    ctx = parse_user_context({"available": False})
    assert ctx.available is False
    assert ctx.summary_for_prompt() == {"available": False}


def test_parse_nested_11a_schema() -> None:
    raw = {
        "schema_version": "11a.1",
        "available": True,
        "profile_complete": True,
        "identity": {"user_id": 42, "locale": "ar", "unit_system": "metric"},
        "body": {"sex": "male", "age": 28, "height_cm": 180, "weight_kg": 82},
        "goals": {"primary_goal": "muscle_gain", "secondary_goals": ["strength"]},
        "training": {
            "fitness_level": "intermediate",
            "training_experience": "1_3_years",
            "training_frequency_per_week": 4,
            "session_duration_minutes": 60,
            "training_location": "gym",
            "equipment": ["barbell", "dumbbells"],
            "activity_level": "moderately_active",
        },
        "nutrition": {
            "dietary_preference": "omnivore",
            "meals_per_day": 4,
            "preferences": {
                "liked": ["chicken"],
                "disliked": ["tuna"],
                "allergies": ["peanuts"],
                "intolerances": [],
                "dietary_restrictions": [],
            },
        },
        "recovery": {
            "sleep_hours": 7.5,
            "injury_status": "historical",
            "injury_areas": ["shoulder"],
            "injury_notes": "old rotator cuff strain",
        },
        "hydration": {"goal_ml": 3000, "consumed_ml": 1200},
        "workout": {
            "recent_training_types": ["bodybuilding"],
            "completed_count_30d": 8,
        },
        "active_plan": None,
        "capabilities": {
            "exercise_linking": False,
            "nutrition_engine": False,
            "rag": False,
            "plan_versioning": False,
        },
    }

    ctx = parse_user_context(raw)
    assert ctx.schema_version == "11a.1"
    assert ctx.available is True
    assert ctx.identity.user_id == 42
    assert ctx.identity.locale == "ar"
    assert ctx.body.weight_kg == 82.0
    assert ctx.goals.primary_goal == "muscle_gain"
    assert ctx.training.equipment == ["barbell", "dumbbells"]
    assert ctx.nutrition.meals_per_day == 4
    assert ctx.hard_allergy_exclusions() == ["peanuts"]
    assert "tuna" in ctx.nutrition.preferences.disliked
    assert ctx.recovery.sleep_hours == 7.5
    assert ctx.workout["recent_training_types"] == ["bodybuilding"]
    assert ctx.capabilities.exercise_linking is False

    summary = ctx.summary_for_prompt()
    assert summary["primary_goal"] == "muscle_gain"
    assert summary["food_allergies"] == ["peanuts"]
    assert summary["meals_per_day"] == 4


def test_parse_flat_aliases_backward_compatible() -> None:
    raw = {
        "available": True,
        "user_id": 7,
        "gender": "female",
        "age": 24,
        "weight_kg": 60,
        "primary_goal": "fat_loss",
        "fitness_level": "beginner",
        "training_frequency_per_week": 3,
        "equipment": ["bodyweight"],
        "injury_status": "none",
        "locale": "en",
        "dietary_preference": "vegetarian",
        "meals_per_day": 3,
        "sleep_hours": 8,
    }
    ctx = parse_user_context(raw)
    assert ctx.body.sex == "female"
    assert ctx.body.weight_kg == 60.0
    assert ctx.goals.primary_goal == "fat_loss"
    assert ctx.training.fitness_level == "beginner"
    assert ctx.nutrition.meals_per_day == 3
    assert ctx.recovery.sleep_hours == 8.0
    assert ctx.identity.user_id == 7


def test_chat_request_parsed_user_context() -> None:
    request = ChatRequest(
        message="Give me a plan",
        user_context={
            "available": True,
            "weight_kg": 80,
            "primary_goal": "strength",
            "fitness_level": "advanced",
        },
    )
    ctx = request.parsed_user_context()
    assert ctx.available is True
    assert ctx.body.weight_kg == 80.0
    assert ctx.goals.primary_goal == "strength"


def test_allergies_are_distinct_from_dislikes() -> None:
    ctx = parse_user_context(
        {
            "available": True,
            "nutrition": {
                "preferences": {
                    "disliked": ["eggs"],
                    "allergies": ["peanuts"],
                }
            },
        }
    )
    assert ctx.hard_allergy_exclusions() == ["peanuts"]
    assert ctx.nutrition.preferences.disliked == ["eggs"]
    assert "eggs" not in ctx.hard_allergy_exclusions()


def test_parse_memory_context_11h() -> None:
    raw = {
        "schema_version": "11a.1",
        "available": True,
        "identity": {"user_id": 9, "locale": "en"},
        "body": {},
        "goals": {"primary_goal": "muscle_gain"},
        "training": {"equipment": ["barbell"]},
        "nutrition": {"preferences": {"allergies": [], "disliked": ["tuna"]}},
        "recovery": {},
        "memory_context": {
            "schema_version": "11h.1",
            "count": 1,
            "authority_note": "supporting_context_current_request_and_profile_win",
            "memories": [
                {
                    "category": "equipment",
                    "key": "available_equipment",
                    "value": {"mode": "dumbbell", "items": ["dumbbells"]},
                    "source": "explicit",
                    "confidence": 1.0,
                    "active": True,
                }
            ],
        },
        "capabilities": {"coach_memory": True},
    }
    ctx = parse_user_context(raw)
    assert ctx.memory_context.schema_version == "11h.1"
    assert ctx.memory_context.count == 1
    assert ctx.memory_context.memories[0].key == "available_equipment"
    assert ctx.capabilities.coach_memory is True
    summary = ctx.summary_for_prompt()
    assert summary["memory_context"]["schema_version"] == "11h.1"
    assert summary["memory_context"]["memories"][0]["key"] == "available_equipment"


def test_mock_provider_echoes_memory_keys() -> None:
    import asyncio

    from app.providers.mock_provider import MockCoachProvider

    provider = MockCoachProvider()
    req = ChatRequest(
        message="How much water should I drink?",
        conversation=[],
        user_context={
            "available": True,
            "identity": {"user_id": 1, "locale": "en"},
            "body": {"weight_kg": 80},
            "goals": {"primary_goal": "muscle_gain"},
            "training": {"fitness_level": "intermediate"},
            "nutrition": {"preferences": {}},
            "recovery": {},
            "memory_context": {
                "schema_version": "11h.1",
                "memories": [
                    {
                        "category": "coaching_preference",
                        "key": "answer_style",
                        "value": {"style": "concise"},
                        "source": "explicit",
                        "active": True,
                    }
                ],
            },
        },
    )
    resp = asyncio.run(provider.generate(req, "en"))
    assert "coach_memory=answer_style" in resp.message
