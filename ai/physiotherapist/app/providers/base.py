from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Literal

from pydantic import BaseModel, Field

RiskLevel = Literal["low", "moderate", "high", "urgent"]


class ChatMessage(BaseModel):
    role: Literal["user", "assistant"]
    content: str = Field(min_length=1, max_length=4000)


class AssessRequest(BaseModel):
    body_area: str = Field(min_length=1, max_length=80)
    symptoms: str = Field(min_length=1, max_length=2000)
    pain_level: int | None = Field(default=None, ge=0, le=10)
    duration: str | None = Field(default=None, max_length=200)
    onset: str | None = Field(default=None, max_length=200)
    trigger: str | None = Field(default=None, max_length=500)
    activity: str | None = Field(default=None, max_length=200)
    additional_information: str | None = Field(default=None, max_length=2000)
    language: Literal["en", "ar"] | None = None
    user_context: dict | None = None


class FollowUpRequest(BaseModel):
    message: str = Field(min_length=1, max_length=2000)
    assessment: AssessRequest
    conversation: list[ChatMessage] = Field(default_factory=list, max_length=40)
    language: Literal["en", "ar"] | None = None
    user_context: dict | None = None


class AssessResponse(BaseModel):
    success: bool = True
    risk_level: RiskLevel
    requires_urgent_care: bool
    summary: str
    possible_explanations: list[str] = Field(default_factory=list)
    self_care: list[str] = Field(default_factory=list)
    avoid: list[str] = Field(default_factory=list)
    professional_care: str
    red_flags: list[str] = Field(default_factory=list)
    provider: str = "mock"
    language: Literal["en", "ar"] = "en"


class PhysioProvider(ABC):
    name: str = "base"

    @abstractmethod
    async def assess(self, request: AssessRequest, language: Literal["en", "ar"]) -> AssessResponse:
        raise NotImplementedError

    @abstractmethod
    async def follow_up(
        self,
        request: FollowUpRequest,
        language: Literal["en", "ar"],
    ) -> AssessResponse:
        raise NotImplementedError
