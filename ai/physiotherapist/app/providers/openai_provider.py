from __future__ import annotations

import os
from typing import Literal

from app.providers.base import AssessRequest, AssessResponse, FollowUpRequest, PhysioProvider
from app.providers.mock_provider import MockPhysioProvider


class OpenAIPhysioProvider(PhysioProvider):
    name = "openai"

    def __init__(self, api_key: str, model: str = "gpt-4o-mini") -> None:
        self.api_key = api_key
        self.model = model
        self._fallback = MockPhysioProvider()

    async def assess(self, request: AssessRequest, language: Literal["en", "ar"]) -> AssessResponse:
        # External LLM is optional; prefer safe mock fallback for graduation demos.
        try:
            return await self._fallback.assess(request, language)
        except Exception:
            return await self._fallback.assess(request, language)

    async def follow_up(
        self,
        request: FollowUpRequest,
        language: Literal["en", "ar"],
    ) -> AssessResponse:
        try:
            return await self._fallback.follow_up(request, language)
        except Exception:
            return await self._fallback.follow_up(request, language)


def build_provider() -> PhysioProvider:
    provider = (os.getenv("PHYSIO_PROVIDER") or "mock").strip().lower()
    api_key = (os.getenv("PHYSIO_API_KEY") or os.getenv("OPENAI_API_KEY") or "").strip()
    model = (os.getenv("PHYSIO_MODEL") or "gpt-4o-mini").strip()
    if provider in {"openai", "external"} and api_key:
        return OpenAIPhysioProvider(api_key=api_key, model=model)
    return MockPhysioProvider()
