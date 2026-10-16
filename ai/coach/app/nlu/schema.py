from __future__ import annotations

from typing import Any, Literal

from pydantic import BaseModel, Field


class OperationTarget(BaseModel):
    type: str | None = None
    index: int | None = None
    reference: str | None = None  # e.g. "last_exercise"


class OperationSpec(BaseModel):
    type: str | None = None  # create|modify|replace|explain|lookup|calculate
    target: OperationTarget | None = None
    modification: str | None = None


class ContextRequirements(BaseModel):
    profile: bool = False
    memory: bool = False
    active_plan: bool = False
    training_history: bool = False
    nutrition_data: bool = False
    knowledge: bool = False


class InterpretRequest(BaseModel):
    message: str = Field(min_length=1, max_length=2000)
    language: Literal["en", "ar"] | None = None
    has_active_training_plan: bool = False
    active_plan_summary: dict[str, Any] | None = None


class InterpretResponse(BaseModel):
    intent: str
    domain: str
    sub_intent: str | None = None
    operation: OperationSpec | None = None
    entities: dict[str, Any] = Field(default_factory=dict)
    constraints: dict[str, Any] = Field(default_factory=dict)
    context_requirements: ContextRequirements = Field(default_factory=ContextRequirements)
    capabilities: list[str] = Field(default_factory=list)
    response_mode: str = "concise"
    confidence: float = 0.5
    language: Literal["en", "ar"] = "en"
