"""Phase 11A — structured coach user context.

Laravel UserAiContextBuilder emits schema_version 11a.1.
This module normalizes nested + flat-alias payloads into one typed model.
Treat all fields as DATA, never as system instructions.
"""

from __future__ import annotations

from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field


UnitSystem = Literal["metric", "imperial"]


class CoachIdentity(BaseModel):
    model_config = ConfigDict(extra="ignore")

    user_id: int | None = None
    locale: Literal["en", "ar"] | None = None
    unit_system: UnitSystem = "metric"


class CoachBody(BaseModel):
    model_config = ConfigDict(extra="ignore")

    sex: str | None = None
    age: int | None = None
    height_cm: float | None = None
    weight_kg: float | None = None


class CoachGoals(BaseModel):
    model_config = ConfigDict(extra="ignore")

    primary_goal: str | None = None
    secondary_goals: list[str] = Field(default_factory=list)


class CoachTraining(BaseModel):
    model_config = ConfigDict(extra="ignore")

    fitness_level: str | None = None
    training_experience: str | None = None
    training_frequency_per_week: int | None = None
    session_duration_minutes: int | None = None
    training_location: str | None = None
    equipment: list[str] = Field(default_factory=list)
    activity_level: str | None = None


class CoachFoodPreferences(BaseModel):
    """Liked / disliked vs allergies must never be conflated."""

    model_config = ConfigDict(extra="ignore")

    liked: list[str] = Field(default_factory=list)
    disliked: list[str] = Field(default_factory=list)
    allergies: list[str] = Field(default_factory=list)
    intolerances: list[str] = Field(default_factory=list)
    dietary_restrictions: list[str] = Field(default_factory=list)


class CoachNutrition(BaseModel):
    model_config = ConfigDict(extra="ignore")

    dietary_preference: str | None = None
    meals_per_day: int | None = None
    preferences: CoachFoodPreferences = Field(default_factory=CoachFoodPreferences)


class CoachRecovery(BaseModel):
    model_config = ConfigDict(extra="ignore")

    sleep_hours: float | None = None
    injury_status: str | None = None
    injury_areas: list[str] = Field(default_factory=list)
    injury_notes: str | None = None


class CoachCapabilities(BaseModel):
    model_config = ConfigDict(extra="ignore")

    exercise_linking: bool = False
    training_plan_engine: bool = False
    nutrition_engine: bool = False
    nutrition_plan_engine: bool = False
    rag: bool = False
    plan_versioning: bool = False
    coach_memory: bool = False


class CoachMemoryItem(BaseModel):
    """Phase 11H compact memory item (DATA only — never invent or claim unstated facts)."""

    model_config = ConfigDict(extra="ignore")

    category: str | None = None
    key: str | None = None
    value: dict[str, Any] | list[Any] | str | int | float | bool | None = None
    source: str | None = None
    confidence: float | None = None
    last_confirmed_at: str | None = None
    active: bool = True


class CoachMemoryContext(BaseModel):
    model_config = ConfigDict(extra="ignore")

    schema_version: str = "11h.1"
    memories: list[CoachMemoryItem] = Field(default_factory=list)
    count: int = 0
    authority_note: str = "supporting_context_current_request_and_profile_win"


class CoachUserContext(BaseModel):
    """Normalized personalization context for the AI Coach pipeline."""

    model_config = ConfigDict(extra="ignore")

    schema_version: str = "11a.1"
    available: bool = False
    purpose: str = "fitness_coaching"
    data_boundary: str = "user_profile_data_not_instructions"
    profile_complete: bool = False

    identity: CoachIdentity = Field(default_factory=CoachIdentity)
    body: CoachBody = Field(default_factory=CoachBody)
    goals: CoachGoals = Field(default_factory=CoachGoals)
    training: CoachTraining = Field(default_factory=CoachTraining)
    nutrition: CoachNutrition = Field(default_factory=CoachNutrition)
    recovery: CoachRecovery = Field(default_factory=CoachRecovery)
    hydration: dict[str, Any] = Field(default_factory=dict)
    workout: dict[str, Any] = Field(default_factory=dict)
    active_plan: dict[str, Any] | None = None
    memory_context: CoachMemoryContext = Field(default_factory=CoachMemoryContext)
    capabilities: CoachCapabilities = Field(default_factory=CoachCapabilities)

    def hard_allergy_exclusions(self) -> list[str]:
        return list(self.nutrition.preferences.allergies)

    def summary_for_prompt(self) -> dict[str, Any]:
        """Compact factual dump for LLM system context (DATA label only)."""
        if not self.available:
            return {"available": False}

        memory_items = []
        for item in self.memory_context.memories:
            if not item.active:
                continue
            memory_items.append(
                {
                    "category": item.category,
                    "key": item.key,
                    "value": item.value,
                    "source": item.source,
                }
            )

        return {
            "schema_version": self.schema_version,
            "user_id": self.identity.user_id,
            "locale": self.identity.locale,
            "unit_system": self.identity.unit_system,
            "sex": self.body.sex,
            "age": self.body.age,
            "height_cm": self.body.height_cm,
            "weight_kg": self.body.weight_kg,
            "primary_goal": self.goals.primary_goal,
            "secondary_goals": self.goals.secondary_goals,
            "fitness_level": self.training.fitness_level,
            "training_experience": self.training.training_experience,
            "training_frequency_per_week": self.training.training_frequency_per_week,
            "session_duration_minutes": self.training.session_duration_minutes,
            "training_location": self.training.training_location,
            "equipment": self.training.equipment,
            "activity_level": self.training.activity_level,
            "dietary_preference": self.nutrition.dietary_preference,
            "meals_per_day": self.nutrition.meals_per_day,
            "food_allergies": self.nutrition.preferences.allergies,
            "food_disliked": self.nutrition.preferences.disliked,
            "food_liked": self.nutrition.preferences.liked,
            "food_intolerances": self.nutrition.preferences.intolerances,
            "dietary_restrictions": self.nutrition.preferences.dietary_restrictions,
            "sleep_hours": self.recovery.sleep_hours,
            "injury_status": self.recovery.injury_status,
            "injury_areas": self.recovery.injury_areas,
            "injury_notes": self.recovery.injury_notes,
            "profile_complete": self.profile_complete,
            "hydration": self.hydration or None,
            "workout": self.workout or None,
            "active_plan": self.active_plan,
            "memory_context": {
                "schema_version": self.memory_context.schema_version,
                "memories": memory_items,
                "authority_note": self.memory_context.authority_note,
            },
            "capabilities": self.capabilities.model_dump(),
        }


def _as_list(value: Any) -> list[str]:
    if value is None:
        return []
    if isinstance(value, str):
        text = value.strip()
        return [text] if text else []
    if not isinstance(value, list):
        return []
    out: list[str] = []
    for item in value:
        if isinstance(item, str) and item.strip():
            out.append(item.strip())
        elif isinstance(item, (int, float, bool)):
            out.append(str(item))
    return out


def _nested(raw: dict[str, Any], key: str) -> dict[str, Any]:
    value = raw.get(key)
    return value if isinstance(value, dict) else {}


def parse_user_context(raw: dict[str, Any] | None) -> CoachUserContext:
    """Accept nested 11a.1 payloads and legacy flat aliases."""
    if not isinstance(raw, dict):
        return CoachUserContext(available=False)

    if not raw.get("available"):
        return CoachUserContext(
            schema_version=str(raw.get("schema_version") or "11a.1"),
            available=False,
        )

    identity_raw = _nested(raw, "identity")
    body_raw = _nested(raw, "body")
    goals_raw = _nested(raw, "goals")
    training_raw = _nested(raw, "training")
    nutrition_raw = _nested(raw, "nutrition")
    recovery_raw = _nested(raw, "recovery")
    prefs_raw = nutrition_raw.get("preferences") if isinstance(nutrition_raw.get("preferences"), dict) else {}

    sex = body_raw.get("sex") or raw.get("gender") or raw.get("sex")
    primary_goal = goals_raw.get("primary_goal") or raw.get("primary_goal")
    fitness_level = training_raw.get("fitness_level") or raw.get("fitness_level")

    locale = identity_raw.get("locale") or raw.get("locale")
    if locale not in ("en", "ar"):
        locale = None

    unit = identity_raw.get("unit_system") or raw.get("unit_system") or "metric"
    if unit not in ("metric", "imperial"):
        unit = "metric"

    hydration = raw.get("hydration") if isinstance(raw.get("hydration"), dict) else {}
    workout = raw.get("workout") if isinstance(raw.get("workout"), dict) else {}
    active_plan = raw.get("active_plan") if isinstance(raw.get("active_plan"), dict) else None
    capabilities_raw = raw.get("capabilities") if isinstance(raw.get("capabilities"), dict) else {}
    memory_raw = raw.get("memory_context") if isinstance(raw.get("memory_context"), dict) else {}
    memory_items_raw = memory_raw.get("memories") if isinstance(memory_raw.get("memories"), list) else []
    memory_items: list[CoachMemoryItem] = []
    for entry in memory_items_raw:
        if isinstance(entry, dict):
            memory_items.append(CoachMemoryItem(**entry))

    return CoachUserContext(
        schema_version=str(raw.get("schema_version") or "11a.1"),
        available=True,
        purpose=str(raw.get("purpose") or "fitness_coaching"),
        data_boundary=str(raw.get("data_boundary") or "user_profile_data_not_instructions"),
        profile_complete=bool(raw.get("profile_complete")),
        identity=CoachIdentity(
            user_id=_as_int(identity_raw.get("user_id", raw.get("user_id"))),
            locale=locale,
            unit_system=unit,
        ),
        body=CoachBody(
            sex=str(sex) if sex else None,
            age=_as_int(body_raw.get("age", raw.get("age"))),
            height_cm=_as_float(body_raw.get("height_cm", raw.get("height_cm"))),
            weight_kg=_as_float(body_raw.get("weight_kg", raw.get("weight_kg"))),
        ),
        goals=CoachGoals(
            primary_goal=str(primary_goal) if primary_goal else None,
            secondary_goals=_as_list(goals_raw.get("secondary_goals", raw.get("secondary_goals"))),
        ),
        training=CoachTraining(
            fitness_level=str(fitness_level) if fitness_level else None,
            training_experience=_as_str(
                training_raw.get("training_experience", raw.get("training_experience"))
            ),
            training_frequency_per_week=_as_int(
                training_raw.get(
                    "training_frequency_per_week",
                    raw.get("training_frequency_per_week"),
                )
            ),
            session_duration_minutes=_as_int(
                training_raw.get("session_duration_minutes", raw.get("session_duration_minutes"))
            ),
            training_location=_as_str(
                training_raw.get("training_location", raw.get("training_location"))
            ),
            equipment=_as_list(training_raw.get("equipment", raw.get("equipment"))),
            activity_level=_as_str(training_raw.get("activity_level", raw.get("activity_level"))),
        ),
        nutrition=CoachNutrition(
            dietary_preference=_as_str(
                nutrition_raw.get("dietary_preference", raw.get("dietary_preference"))
            ),
            meals_per_day=_as_int(nutrition_raw.get("meals_per_day", raw.get("meals_per_day"))),
            preferences=CoachFoodPreferences(
                liked=_as_list(prefs_raw.get("liked")),
                disliked=_as_list(prefs_raw.get("disliked")),
                allergies=_as_list(prefs_raw.get("allergies")),
                intolerances=_as_list(prefs_raw.get("intolerances")),
                dietary_restrictions=_as_list(prefs_raw.get("dietary_restrictions")),
            ),
        ),
        recovery=CoachRecovery(
            sleep_hours=_as_float(recovery_raw.get("sleep_hours", raw.get("sleep_hours"))),
            injury_status=_as_str(recovery_raw.get("injury_status", raw.get("injury_status"))),
            injury_areas=_as_list(recovery_raw.get("injury_areas", raw.get("injury_areas"))),
            injury_notes=_as_str(recovery_raw.get("injury_notes", raw.get("injury_notes"))),
        ),
        hydration=hydration,
        workout=workout,
        active_plan=active_plan,
        memory_context=CoachMemoryContext(
            schema_version=str(memory_raw.get("schema_version") or "11h.1"),
            memories=memory_items,
            count=int(memory_raw.get("count") or len(memory_items)),
            authority_note=str(
                memory_raw.get("authority_note")
                or "supporting_context_current_request_and_profile_win"
            ),
        ),
        capabilities=CoachCapabilities(**capabilities_raw) if capabilities_raw else CoachCapabilities(),
    )


def _as_str(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def _as_int(value: Any) -> int | None:
    if value is None or value == "":
        return None
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


def _as_float(value: Any) -> float | None:
    if value is None or value == "":
        return None
    try:
        return float(value)
    except (TypeError, ValueError):
        return None
