from __future__ import annotations

import os

from app.providers.base import MotionProvider
from app.providers.mediapipe_provider import MediaPipeMotionProvider
from app.providers.mock_provider import MockMotionProvider


def build_provider() -> MotionProvider:
    provider = (os.getenv("MOTION_PROVIDER") or "mock").strip().lower()
    if provider in {"mediapipe", "opencv", "real"}:
        return MediaPipeMotionProvider()
    return MockMotionProvider()
