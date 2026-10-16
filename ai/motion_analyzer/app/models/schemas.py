from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field

from app.exercises.config import SUPPORTED_EXERCISES


class AnalyzeResponse(BaseModel):
    success: bool = True
    exercise: str
    repetitions: int = Field(ge=0)
    duration_seconds: float = Field(ge=0)
    confidence: float = Field(ge=0, le=1)
    movement_quality: Literal["excellent", "good", "fair", "needs_work", "unknown"]
    observations: list[str] = Field(default_factory=list)
    tips: list[str] = Field(default_factory=list)
    metrics: dict[str, float] = Field(default_factory=dict)
    provider: str = "mock"
    frames_analyzed: int = 0
    message: str | None = None


__all__ = ["AnalyzeResponse", "SUPPORTED_EXERCISES"]
