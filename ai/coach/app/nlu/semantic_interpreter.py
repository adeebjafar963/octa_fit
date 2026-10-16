from __future__ import annotations

import logging
import os
from typing import Any, Literal

from app.nlu.entities import extract_day_index, extract_days_per_week, extract_duration_minutes
from app.nlu.normalize import has_substring, normalize_text, tokenize
from app.nlu.schema import (
    ContextRequirements,
    InterpretRequest,
    InterpretResponse,
    OperationSpec,
    OperationTarget,
)
from app.providers.mock_provider import detect_language, is_medical

logger = logging.getLogger("octa_fit.coach.nlu")

# Semantic feature lexicons (concept-level tokens — NOT sentence-specific rules).
_SAFETY = {
    "emergency", "chest pain", "cant breathe", "طوارئ", "ألم صدر", "ألم شديد", "كسر",
}
_MEDICAL = {"diagnos", "injury", "fracture", "إصابة", "تشخيص", "دواء", "prescription"}

_NUTRITION_LOOKUP = {
    "calorie", "calories", "kcal", "protein", "carb", "macro", "سعرات", "سعرة", "سعره",
    "بروتين", "كالوري", "قديش", "كم سعر", "كم سعرة", "how many calories", "calories in",
}
_FOOD_ENTITY = {
    "apple", "chicken", "rice", "egg", "eggs", "tuna", "banana", "milk", "yogurt",
    "oats", "salmon", "cheese", "potato", "lentil", "bean", "dates", "peanut",
    "تفاح", "تفاحه", "دجاج", "رز", "أرز", "بيض", "تونه", "موز", "حليب",
    "زبادي", "شوفان", "سلمون", "جبن", "عدس", "بطاطا", "تمر", "فروج",
}

_PLAN_CREATE = {
    "program", "plan", "routine", "schedule", "workout plan", "training plan",
    "خطة", "برنامج", "روتين", "جدول", "حصص", "حصة", "جلسة", "جلسات", "نظام",
    "اعمللي", "بدي برنامج", "give me a", "رتب", "قسم",
}
_EXPLAIN = {
    "what is", "what's", "explain", "define", "شو هو", "شو يعني", "ما هو", "ما هي", "ليش", "why",
}
_SPORTS_TERMS = {
    "progressive overload", "hypertrophy", "rest between", "warm up", "warmup", "cooldown",
    "فشل عضلي", "بين المجموعات", "بين الجولات",
}

_DIFFICULTY_UP = {
    "harder", "difficult", "challenging", "intensify", "tougher", "stronger", "more challenging",
    "اصعب", "اقوى", "صعب", "زيد", "ارفع", "تحدي", "نار", "اتعب", "شده", "صعوبه", "صعوبة", "مستوى",
    "challenge", "level up", "raise", " tougher",
}
_REPLACE = {
    "replace", "substitute", "swap", "alternative", "instead",
    "بدل", "بدّل", "بدلي", "غير", "غيّر", "بديل", "شيل", "alternative",
}
_LAST_EXERCISE = {"last exercise", "آخر تمرين", "اخر تمرين", "last movement", "آخر حركة", "اخر حركة"}

_DISCIPLINE_MMA = {"mma", "mixed martial", "فنون قتالية", "فنون قتاليه", "قتال مختلط", "combat"}
_DISCIPLINE_GYM = {"gym", "نادي", "صالة", "بالنادي", "fitness center", "health club"}
_DISCIPLINE_HOME = {"home", "بالبيت", "منزل", "at home", "bodyweight home"}
_DISCIPLINE_BB = {"bodybuilding", "تضخيم", "بناء عضل", "hypertrophy", "bulk", "bulking"}
_DISCIPLINE_CAL = {"calisthenics", "باركور", "bodyweight", "وزن الجسم"}

_PERSONALIZE = {"for me", "يناسبني", "حسب ملفي", "based on my", "personalized", "لي انا"}


def interpret_message(request: InterpretRequest) -> InterpretResponse:
    text = request.message.strip()
    language: Literal["en", "ar"] = detect_language(text, request.language)
    norm = normalize_text(text)
    tokens = tokenize(text)

    # Layer A — deterministic entities
    day = extract_day_index(text)
    duration = extract_duration_minutes(text)
    days_pw = extract_days_per_week(text)
    discipline = _detect_discipline(norm)
    equipment = _detect_equipment(norm, discipline)
    goal = _detect_goal(norm)

    # Safety first
    if has_substring(norm, _SAFETY):
        return _build("safety", "safety", "emergency", "explain", confidence=0.95, language=language)
    if is_medical(text) or has_substring(norm, _MEDICAL):
        return _build("safety", "safety", "medical", "explain", confidence=0.95, language=language)

    # Active plan modification (semantic operation detection)
    if request.has_active_training_plan:
        mod = _detect_plan_modification(norm, day)
        if mod is not None:
            return mod

    # Nutrition lookup
    if _is_nutrition_lookup(norm, tokens):
        caps = ["nutrition_lookup"]
        return InterpretResponse(
            intent="nutrition_lookup",
            domain="nutrition",
            sub_intent="calorie_lookup" if "protein" not in norm and "بروتين" not in norm else "macro_lookup",
            operation=OperationSpec(type="lookup"),
            entities={"food_hint": _food_hint(norm)},
            constraints={"quantity_grams": _quantity_hint(norm)},
            context_requirements=ContextRequirements(nutrition_data=True),
            capabilities=caps,
            response_mode="direct",
            confidence=0.88,
            language=language,
        )

    # Nutrition plan create (before training — "نظام غذائي للتضخيم")
    if _is_nutrition_plan(norm):
        return InterpretResponse(
            intent="nutrition_plan_create",
            domain="nutrition",
            sub_intent=goal or "general",
            operation=OperationSpec(type="create"),
            entities={"goal": goal},
            constraints={"goal": goal} if goal else {},
            context_requirements=ContextRequirements(profile=True),
            capabilities=["nutrition_plan"],
            response_mode="structured",
            confidence=0.86,
            language=language,
        )

    body_part = _detect_body_part(norm)
    # Exercise recommendation (not full plan)
    if body_part and not _is_plan_request(norm, tokens):
        return InterpretResponse(
            intent="training_exercise_recommendation",
            domain="training",
            sub_intent="body_part",
            operation=OperationSpec(type="recommend"),
            entities={"body_part": body_part, "equipment_mode": equipment},
            constraints={"body_part": body_part},
            context_requirements=ContextRequirements(),
            capabilities=["grounded_exercises"],
            response_mode="structured",
            confidence=0.84,
            language=language,
        )

    # Ambiguous bare program
    if _is_ambiguous_program(norm, discipline, equipment, days_pw, duration):
        return InterpretResponse(
            intent="ambiguous_request",
            domain="system",
            sub_intent="program_type",
            operation=OperationSpec(type="clarify"),
            entities={},
            constraints={},
            context_requirements=ContextRequirements(),
            capabilities=[],
            response_mode="clarification",
            confidence=0.4,
            language=language,
        )

    # Training plan create — skip when nutrition diet context
    if (not _is_nutrition_plan(norm)) and (
        _is_plan_request(norm, tokens) or _is_implicit_plan_ask(norm, discipline, equipment)
    ):
        if discipline is None and equipment == "gym":
            discipline = "bodybuilding"
        constraints: dict[str, Any] = {}
        if days_pw is not None:
            constraints["days"] = days_pw
        if duration is not None:
            constraints["duration_minutes"] = duration
        if discipline:
            constraints["discipline"] = discipline
        if equipment:
            constraints["equipment_mode"] = equipment
        if goal:
            constraints["goal"] = goal
        needs_profile = has_substring(norm, _PERSONALIZE) or not (discipline and days_pw)
        return InterpretResponse(
            intent="training_plan_create",
            domain="training",
            sub_intent=discipline or "general",
            operation=OperationSpec(type="create"),
            entities={"discipline": discipline, "goal": goal, "equipment_mode": equipment},
            constraints=constraints,
            context_requirements=ContextRequirements(profile=needs_profile, memory=needs_profile),
            capabilities=["training_plan"],
            response_mode="structured",
            confidence=0.82 if discipline else 0.65,
            language=language,
        )

    # General sports knowledge
    if _is_general_sports(norm, tokens):
        return _build(
            "general_sports_knowledge",
            "sports_knowledge",
            "explain",
            "explain",
            confidence=0.8,
            language=language,
            knowledge=True,
        )

    # Gym/environment-only statement
    if equipment and not _is_plan_request(norm, tokens):
        return InterpretResponse(
            intent="training_plan_create",
            domain="training",
            sub_intent=discipline or "bodybuilding",
            operation=OperationSpec(type="create"),
            entities={"equipment_mode": equipment, "discipline": discipline or "bodybuilding"},
            constraints={"equipment_mode": equipment},
            context_requirements=ContextRequirements(profile=True),
            capabilities=["training_plan"],
            response_mode="structured",
            confidence=0.7,
            language=language,
        )

    return _build("general_conversation", "conversation", None, "explain", confidence=0.45, language=language)


def _detect_plan_modification(norm: str, day: int | None) -> InterpretResponse | None:
    harder = has_substring(norm, _DIFFICULTY_UP)
    replace = has_substring(norm, _REPLACE) and has_substring(
        norm, _LAST_EXERCISE | {"اخر حركه", "عطيني بديل"}
    )
    replace = replace or (
        has_substring(norm, {"ما بدي", "dont want", "remove"}) and has_substring(norm, {"آخر", "اخر", "last"})
    )

    if harder and day is not None:
        return InterpretResponse(
            intent="training_plan_modify",
            domain="training",
            sub_intent="increase_difficulty",
            operation=OperationSpec(
                type="modify",
                target=OperationTarget(type="day", index=day),
                modification="increase_difficulty",
            ),
            entities={"day": day},
            context_requirements=ContextRequirements(active_plan=True),
            capabilities=["training_modification"],
            response_mode="structured",
            confidence=0.9,
            language="ar" if any("\u0600" <= c <= "\u06FF" for c in norm) else "en",
        )

    if replace:
        return InterpretResponse(
            intent="training_plan_modify",
            domain="training",
            sub_intent="replace_exercise",
            operation=OperationSpec(
                type="replace",
                target=OperationTarget(type="day", index=day, reference="last_exercise"),
                modification="replace_last_exercise",
            ),
            entities={"day": day},
            context_requirements=ContextRequirements(active_plan=True),
            capabilities=["training_modification"],
            response_mode="structured",
            confidence=0.88,
            language="ar" if any("\u0600" <= c <= "\u06FF" for c in norm) else "en",
        )

    duration = extract_duration_minutes(norm)
    duration_cues = {
        "make it", "make my", "change", "خليها", "بدلها", "instead", "every session", "every day",
        "كل حصة", "الجلسات", "ساعتين", "two hours", "plan", "خلي", "تقريبا",
    }
    if duration and (has_substring(norm, duration_cues) or len(norm.split()) <= 3):
        return InterpretResponse(
            intent="training_plan_modify",
            domain="training",
            sub_intent="change_duration",
            operation=OperationSpec(
                type="modify",
                modification="update_duration",
            ),
            constraints={"duration_minutes": duration},
            context_requirements=ContextRequirements(active_plan=True),
            capabilities=["training_modification"],
            response_mode="structured",
            confidence=0.85,
            language="ar" if any("\u0600" <= c <= "\u06FF" for c in norm) else "en",
        )

    return None


def _is_nutrition_lookup(norm: str, tokens: list[str]) -> bool:
    if _is_nutrition_plan(norm):
        return False
    if "meal plan" in norm or "nutrition plan" in norm or "خطة غذائية" in norm or "نظام غذائي" in norm:
        return False
    if has_substring(
        norm,
        {
            "how much protein should",
            "protein should i",
            "how many calories should",
            "should i eat",
            "لازم آكل",
            "أحتاج",
            "daily target",
        },
    ):
        return False
    has_macro = has_substring(norm, _NUTRITION_LOOKUP)
    has_food = has_substring(norm, _FOOD_ENTITY)
    has_qty = bool(extract_duration_minutes(norm)) is False and any(
        c.isdigit() for c in norm.split()
    )
    return has_macro or (has_food and ("كم" in norm or "how" in norm or "calor" in norm or "protein" in norm))


_NUTRITION_PLAN = {
    "meal plan", "nutrition plan", "diet plan", "meal prep", "bulking diet", "cutting diet",
    "خطة غذائية", "نظام غذائي", "جدول أكل", "خطة أكل", "نظام اكل",
}


def _is_nutrition_plan(norm: str) -> bool:
    if has_substring(norm, _NUTRITION_PLAN):
        return True
    diet_goal = has_substring(norm, {"تضخيم", "تنشيف", "bulk", "bulking", "cut", "cutting"})
    wants = has_substring(norm, {"بدي", "اعمللي", "عطيني", "want", "need", "give me", "create"})
    diet_cue = has_substring(norm, {"غذا", "اكل", "أكل", "diet", "meal", "nutrition"})
    training_cue = has_substring(norm, {"برنامج", "تمرين", "workout", "gym", "جيم", "mma", "نادي", "تدريب"})
    return bool(diet_goal and wants and diet_cue and not training_cue)


_BODY_PARTS = {
    "chest": ("chest", "صدر"),
    "shoulders": ("shoulder", "shoulders", "اكتاف", "أكتاف", "كتف"),
    "back": ("back", "ظهر"),
    "legs": ("legs", "leg", "ارجل", "أرجل", "رجل"),
    "arms": ("arms", "biceps", "triceps", "باي", "تراي"),
    "abs": ("abs", "core", "بطن"),
}


def _detect_body_part(norm: str) -> str | None:
    for part, tokens in _BODY_PARTS.items():
        if has_substring(norm, set(tokens)):
            return part
    return None


def _is_ambiguous_program(
    norm: str,
    discipline: str | None,
    equipment: str | None,
    days_pw: int | None,
    duration: int | None,
) -> bool:
    asks = has_substring(norm, {"بدي", "اعمللي", "عطيني", "want", "need", "give me", "make me"}) and has_substring(
        norm, {"برنامج", "program", "plan", "خطة", "روتين"}
    )
    if not asks:
        return False
    if discipline or equipment or days_pw or duration:
        return False
    if _detect_body_part(norm):
        return False
    return True


def _is_implicit_plan_ask(norm: str, discipline: str | None, equipment: str | None) -> bool:
    if _is_nutrition_plan(norm):
        return False
    if _detect_body_part(norm) and not _is_plan_request(norm, []):
        return False
    wants = has_substring(norm, {"بدي", "want", "need", "give me", "i want", "اعطيني", "بدنا", "make me", "build", "رتب", "قسم"})
    # Want + MMA/gym/etc. is plan create; bare "I want a workout" has no discipline/equipment.
    return wants and (discipline is not None or equipment is not None)


def _is_plan_request(norm: str, tokens: list[str]) -> bool:
    if _is_nutrition_plan(norm):
        return False
    if norm.startswith(("how many", "what is", "what's", "why", "كم", "ما هو", "شو")):
        if not has_substring(norm, _PLAN_CREATE):
            return False
    # Exclude bare "نظام" without training — nutrition handled separately
    return has_substring(norm, _PLAN_CREATE - {"نظام"}) or has_substring(norm, {"نظام تدريب", "برنامج", "خطة", "program", "plan", "routine"})


def _is_general_sports(norm: str, tokens: list[str]) -> bool:
    if has_substring(norm, _PLAN_CREATE):
        return False
    if _is_nutrition_plan(norm):
        return False
    if has_substring(norm, _NUTRITION_LOOKUP) and has_substring(norm, _FOOD_ENTITY):
        return False
    if has_substring(
        norm,
        {"how much protein should", "protein should i", "how many calories should", "should i eat"},
    ):
        return False
    return norm.startswith(("what is", "what's", "why", "how ", "شو", "ما هو", "ما هي", "ليش", "كيف")) or has_substring(
        norm, _SPORTS_TERMS | _EXPLAIN
    )


def _detect_discipline(norm: str) -> str | None:
    if _is_nutrition_plan(norm):
        return None
    if has_substring(norm, _DISCIPLINE_MMA):
        return "mma"
    if has_substring(norm, _DISCIPLINE_BB):
        return "bodybuilding"
    if has_substring(norm, _DISCIPLINE_CAL):
        return "calisthenics"
    return None


def _detect_equipment(norm: str, discipline: str | None) -> str | None:
    if has_substring(norm, _DISCIPLINE_GYM | {"للجيم", "جيم"}):
        return "gym"
    if has_substring(norm, _DISCIPLINE_HOME):
        return "home"
    if discipline == "mma" and "gym" in norm:
        return "gym"
    return None


def _detect_goal(norm: str) -> str | None:
    if has_substring(norm, {"bulk", "bulking", "hypertrophy", "تضخيم", "بناء عضل"}):
        return "hypertrophy"
    if has_substring(norm, {"cut", "cutting", "fat loss", "تنشيف"}):
        return "fat_loss"
    if has_substring(norm, {"strength", "قوة"}):
        return "strength"
    return None


def _food_hint(norm: str) -> str | None:
    for f in _FOOD_ENTITY:
        if f in norm:
            return f
    return None


def _quantity_hint(norm: str) -> float | None:
    import re

    m = re.search(r"(\d+(?:\.\d+)?)\s*(?:g|grams?|غرام|جرام|غم|غ)\b", norm)
    return float(m.group(1)) if m else None


def _build(
    intent: str,
    domain: str,
    sub_intent: str | None,
    op_type: str,
    *,
    confidence: float,
    language: Literal["en", "ar"],
    knowledge: bool = False,
) -> InterpretResponse:
    ctx = ContextRequirements(knowledge=knowledge)
    caps = ["rag_knowledge"] if knowledge else []
    return InterpretResponse(
        intent=intent,
        domain=domain,
        sub_intent=sub_intent,
        operation=OperationSpec(type=op_type),
        context_requirements=ctx,
        capabilities=caps,
        response_mode="concise" if knowledge else "direct",
        confidence=confidence,
        language=language,
    )


async def interpret_with_openai(request: InterpretRequest) -> InterpretResponse | None:
    """Optional Layer B — OpenAI structured intent when API key is set."""
    api_key = os.getenv("OPENAI_API_KEY", "").strip()
    if not api_key:
        return None
    try:
        import httpx

        schema_hint = (
            "Return ONLY valid JSON matching: intent, domain, sub_intent, operation{type,target,modification}, "
            "entities, constraints, context_requirements, capabilities[], response_mode, confidence."
        )
        user = (
            f"Message: {request.message}\n"
            f"Has active training plan: {request.has_active_training_plan}\n"
            f"Active plan summary: {request.active_plan_summary or {}}\n"
            f"{schema_hint}"
        )
        async with httpx.AsyncClient(timeout=20.0) as client:
            resp = await client.post(
                "https://api.openai.com/v1/chat/completions",
                headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
                json={
                    "model": os.getenv("OPENAI_MODEL", "gpt-4o-mini"),
                    "messages": [
                        {
                            "role": "system",
                            "content": (
                                "You are a strict NLU parser for a fitness coach. "
                                "Interpret user intent ONLY. Never invent IDs, nutrition values, or profile data."
                            ),
                        },
                        {"role": "user", "content": user},
                    ],
                    "temperature": 0.1,
                    "response_format": {"type": "json_object"},
                },
            )
        if resp.status_code >= 400:
            return None
        import json

        content = resp.json()["choices"][0]["message"]["content"]
        data = json.loads(content)
        return InterpretResponse.model_validate(data)
    except Exception:
        logger.exception("openai_interpret_failed")
        return None
