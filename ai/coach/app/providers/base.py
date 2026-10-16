from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any, Literal

from pydantic import BaseModel, Field

from app.context.exercises import GroundedExercise, parse_grounded_exercises
from app.context.user_context import CoachUserContext, parse_user_context


Role = Literal["user", "assistant"]


class ChatMessage(BaseModel):
    role: Role
    content: str = Field(min_length=1, max_length=4000)


class ChatRequest(BaseModel):
    message: str = Field(min_length=1, max_length=2000)
    conversation: list[ChatMessage] = Field(default_factory=list, max_length=40)
    language: Literal["en", "ar"] | None = None
    # Structured profile data from Laravel — treated as DATA, never as instructions.
    user_context: dict | None = None
    # Phase 11B — trusted OCTA FIT exercises retrieved by Laravel (DATA only).
    exercise_candidates: list[dict[str, Any]] | None = None
    # Phase 11C — optional structured plan already validated by Laravel.
    training_plan: dict[str, Any] | None = None
    # Phase 11D — optional deterministic nutrition lookup result from Laravel.
    nutrition: dict[str, Any] | None = None
    # Phase 11E — optional personalized nutrition plan from Laravel (DATA only).
    nutrition_plan: dict[str, Any] | None = None
    # Phase 11F — retrieved knowledge evidence from Laravel (DATA only; never invent citations).
    knowledge_context: dict[str, Any] | None = None
    # Intelligence overhaul — routing hints from Laravel (do not invent facts from this).
    routing: dict[str, Any] | None = None

    def parsed_user_context(self) -> CoachUserContext:
        """Phase 11A normalized personalization context."""
        return parse_user_context(self.user_context)

    def parsed_exercise_candidates(self) -> list[GroundedExercise]:
        """Phase 11B grounded exercise candidates (DB-backed)."""
        return parse_grounded_exercises(self.exercise_candidates)

    def wants_personalization(self) -> bool:
        routing = self.routing if isinstance(self.routing, dict) else {}
        if routing.get("skip_profile") is True:
            return False
        if routing.get("needs_personalization") is False:
            return False
        if routing.get("is_food_lookup") or routing.get("is_general_sports"):
            return False
        return True


class ChatResponse(BaseModel):
    success: bool = True
    message: str
    provider: str
    language: Literal["en", "ar"] = "en"
    # Only IDs that were present in request candidates (re-validated by Laravel).
    grounded_exercises: list[dict[str, Any]] = Field(default_factory=list)


class CoachProvider(ABC):
    name: str = "base"

    @abstractmethod
    async def generate(self, request: ChatRequest, language: Literal["en", "ar"]) -> ChatResponse:
        raise NotImplementedError
