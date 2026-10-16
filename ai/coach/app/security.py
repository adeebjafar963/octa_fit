"""Internal token gate for Laravel → FastAPI Coach calls (Phase 11I)."""

from __future__ import annotations

import os

from fastapi import Header, HTTPException


def require_internal_token(
    x_internal_token: str | None = Header(default=None, alias="X-Internal-Token"),
) -> None:
    """
    When INTERNAL_TOKEN is configured, require a matching X-Internal-Token header.
    When unset/empty (local tests / open mock), allow the request.
    """
    expected = (os.getenv("INTERNAL_TOKEN") or "").strip()
    if expected == "":
        return
    provided = (x_internal_token or "").strip()
    if provided == "" or provided != expected:
        raise HTTPException(status_code=401, detail="Unauthorized")
