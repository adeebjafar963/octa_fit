from __future__ import annotations

import os
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import JSONResponse

from app.exercises.config import SUPPORTED_EXERCISES, is_supported_exercise
from app.models.schemas import AnalyzeResponse
from app.providers import build_provider

app = FastAPI(title="OCTA FIT Motion Analyzer", version="0.2.0")
provider = build_provider()

MAX_UPLOAD_MB = int(os.getenv("MAX_UPLOAD_MB") or "20")
ALLOWED_EXTENSIONS = {".mp4", ".mov", ".avi", ".webm", ".mkv"}
ALLOWED_CONTENT_TYPES = {
    "video/mp4",
    "video/quicktime",
    "video/x-msvideo",
    "video/webm",
    "video/x-matroska",
    "application/octet-stream",
}


@app.get("/health")
def health() -> JSONResponse:
    return JSONResponse(
        content={
            "status": "healthy",
            "service": "octa-fit-motion-analyzer",
            "provider": getattr(provider, "name", "unknown"),
            "supported_exercises": list(SUPPORTED_EXERCISES),
        }
    )


@app.post("/analyze", response_model=AnalyzeResponse)
async def analyze(
    exercise: str = Form(...),
    video: UploadFile = File(...),
) -> AnalyzeResponse:
    exercise_id = (exercise or "").strip().lower()
    if not is_supported_exercise(exercise_id):
        raise HTTPException(
            status_code=422,
            detail=f"Unsupported exercise. Allowed: {', '.join(SUPPORTED_EXERCISES)}",
        )

    filename = video.filename or "upload.bin"
    suffix = Path(filename).suffix.lower()
    content_type = (video.content_type or "").lower()

    if suffix and suffix not in ALLOWED_EXTENSIONS:
        raise HTTPException(status_code=422, detail="Unsupported video format.")
    if content_type and content_type not in ALLOWED_CONTENT_TYPES and suffix not in ALLOWED_EXTENSIONS:
        raise HTTPException(status_code=422, detail="Unsupported video content type.")

    raw = await video.read()
    if not raw:
        raise HTTPException(status_code=422, detail="Video file is empty.")

    max_bytes = MAX_UPLOAD_MB * 1024 * 1024
    if len(raw) > max_bytes:
        raise HTTPException(
            status_code=422,
            detail=f"Video exceeds maximum size of {MAX_UPLOAD_MB} MB.",
        )

    tmp_path: Path | None = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix or ".mp4") as tmp:
            tmp.write(raw)
            tmp_path = Path(tmp.name)

        return await provider.analyze(exercise_id, tmp_path)
    except HTTPException:
        raise
    except Exception:
        raise HTTPException(
            status_code=500,
            detail="Motion analysis failed unexpectedly.",
        ) from None
    finally:
        if tmp_path is not None:
            try:
                tmp_path.unlink(missing_ok=True)
            except OSError:
                pass
