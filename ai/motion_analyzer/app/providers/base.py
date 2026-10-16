from __future__ import annotations

from abc import ABC, abstractmethod
from pathlib import Path

from app.models.schemas import AnalyzeResponse


class MotionProvider(ABC):
    name: str = "base"

    @abstractmethod
    async def analyze(self, exercise: str, video_path: Path) -> AnalyzeResponse:
        raise NotImplementedError
