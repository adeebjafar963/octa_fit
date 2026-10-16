import pytest

from app.nlu.schema import InterpretRequest
from app.nlu.semantic_interpreter import interpret_message


@pytest.mark.parametrize(
    "message,expected_mod,expected_day",
    [
        ("خلي اليوم الثالث أصعب", "increase_difficulty", 3),
        ("زيد صعوبة اليوم الثالث", "increase_difficulty", 3),
        ("الحصة التالتة بدي ياها أقوى", "increase_difficulty", 3),
        ("ارفع مستوى اليوم 3", "increase_difficulty", 3),
        ("make day 3 harder", "increase_difficulty", 3),
        ("make the third session more challenging", "increase_difficulty", 3),
        ("خلينا نخلي الحصة التالتة أقوى شوي", "increase_difficulty", 3),
        ("شو رأيك نرفع مستوى اليوم الثالث؟", "increase_difficulty", 3),
    ],
)
def test_group_a_increase_difficulty_paraphrases(message: str, expected_mod: str, expected_day: int) -> None:
    req = InterpretRequest(message=message, has_active_training_plan=True)
    result = interpret_message(req)
    assert result.intent == "training_plan_modify"
    assert result.operation is not None
    assert result.operation.modification == expected_mod
    assert result.operation.target is not None
    assert result.operation.target.index == expected_day


@pytest.mark.parametrize(
    "message",
    [
        "التفاحة كم سعرة؟",
        "قديش كالوري بالتفاحة؟",
        "كم طاقة فيها تفاحة؟",
        "how many calories are in an apple?",
        "calories in one apple?",
        "قديش كالوري تقريباً بتعطي تفاحة متوسطة؟",
    ],
)
def test_group_b_food_calorie_lookup(message: str) -> None:
    result = interpret_message(InterpretRequest(message=message))
    assert result.intent == "nutrition_lookup"
    assert result.domain == "nutrition"


@pytest.mark.parametrize(
    "message",
    [
        "بدي برنامج للجيم",
        "اعمللي برنامج بالنادي",
        "بدي تمرين بالصالة الرياضية",
        "بدي gym workout",
        "أنا رايح عالنادي، شو برنامج مناسب إلي؟",
    ],
)
def test_group_c_gym_context(message: str) -> None:
    result = interpret_message(InterpretRequest(message=message))
    assert result.intent == "training_plan_create"
    assert result.constraints.get("equipment_mode") == "gym" or result.entities.get("equipment_mode") == "gym"


@pytest.mark.parametrize(
    "message",
    [
        "بدي MMA",
        "بدي فنون قتالية مختلطة",
        "اعمللي خطة MMA",
        "I want an MMA program",
        "بدي أربع حصص MMA طويلة",
    ],
)
def test_group_d_mma(message: str) -> None:
    result = interpret_message(InterpretRequest(message=message))
    assert result.intent == "training_plan_create"
    assert result.entities.get("discipline") == "mma" or result.constraints.get("discipline") == "mma"


@pytest.mark.parametrize(
    "message,expected_day",
    [
        ("بدل آخر تمرين باليوم الثاني", 2),
        ("غير آخر تمرين باليوم 2", 2),
        ("replace the last exercise on day 2", 2),
        ("ما بدي آخر حركة باليوم الثاني، عطيني بديل", 2),
    ],
)
def test_group_e_replace_exercise(message: str, expected_day: int) -> None:
    req = InterpretRequest(message=message, has_active_training_plan=True)
    result = interpret_message(req)
    assert result.intent == "training_plan_modify"
    assert result.operation is not None
    assert result.operation.modification == "replace_last_exercise"
    assert result.operation.target is not None
    assert result.operation.target.index == expected_day


@pytest.mark.parametrize(
    "message,expected_minutes",
    [
        ("ساعتين", 120),
        ("120 دقيقة", 120),
        ("خلّي الجلسات تقريباً ساعتين", 120),
        ("make every session two hours", 120),
    ],
)
def test_group_f_duration(message: str, expected_minutes: int) -> None:
    result = interpret_message(InterpretRequest(message=message, has_active_training_plan=True))
    assert result.constraints.get("duration_minutes") == expected_minutes or (
        result.intent == "training_plan_modify" and result.constraints.get("duration_minutes") == expected_minutes
    )


def test_interpret_endpoint() -> None:
    from fastapi.testclient import TestClient
    from app.main import app

    c = TestClient(app)
    response = c.post(
        "/interpret",
        json={"message": "خلينا نخلي الحصة التالتة أقوى شوي", "has_active_training_plan": True},
    )
    assert response.status_code == 200
    body = response.json()
    assert body["intent"] == "training_plan_modify"
    assert body["operation"]["modification"] == "increase_difficulty"
