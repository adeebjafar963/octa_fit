import logging
import os
import time

from fastapi import Depends, FastAPI, HTTPException
from fastapi.responses import JSONResponse

from app.nlu.schema import InterpretRequest, InterpretResponse
from app.nlu.semantic_interpreter import interpret_message, interpret_with_openai
from app.providers import ChatRequest, ChatResponse, build_provider, detect_language
from app.security import require_internal_token

LOG_LEVEL = (os.getenv("LOG_LEVEL") or "info").upper()
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s %(levelname)s [octa-fit-coach] %(message)s",
)
logger = logging.getLogger("octa_fit.coach")

app = FastAPI(title="OCTA FIT AI Coach", version="0.2.1")
provider = build_provider()


@app.get("/health")
def health() -> JSONResponse:
    return JSONResponse(
        content={
            "status": "healthy",
            "service": "octa-fit-coach",
            "provider": getattr(provider, "name", "unknown"),
        }
    )


@app.post("/interpret", response_model=InterpretResponse, dependencies=[Depends(require_internal_token)])
async def interpret(payload: InterpretRequest) -> InterpretResponse:
    message = payload.message.strip()
    if not message:
        raise HTTPException(status_code=422, detail="Message is required")

    language = detect_language(message, payload.language)
    started = time.perf_counter()
    try:
        llm_result = await interpret_with_openai(payload)
        result = llm_result if llm_result is not None else interpret_message(payload)
    except Exception:
        logger.exception("interpret_failed")
        result = interpret_message(payload)

    if result.language != language:
        result = result.model_copy(update={"language": language})

    latency_ms = int((time.perf_counter() - started) * 1000)
    logger.info(
        "coach.semantic_intent intent=%s domain=%s sub_intent=%s confidence=%s latency_ms=%s",
        result.intent,
        result.domain,
        result.sub_intent,
        result.confidence,
        latency_ms,
    )
    return result


@app.post("/chat", response_model=ChatResponse, dependencies=[Depends(require_internal_token)])
async def chat(payload: ChatRequest) -> ChatResponse:
    message = payload.message.strip()
    if not message:
        raise HTTPException(status_code=422, detail="Message is required")

    language = detect_language(message, payload.language)
    started = time.perf_counter()
    try:
        response = await provider.generate(payload, language)
    except Exception:
        logger.exception(
            "provider_failed provider=%s language=%s",
            getattr(provider, "name", "unknown"),
            language,
        )
        raise HTTPException(status_code=502, detail="Coach provider unavailable") from None

    latency_ms = int((time.perf_counter() - started) * 1000)
    logger.info(
        "chat_ok provider=%s language=%s latency_ms=%s has_plan=%s has_nutrition=%s",
        getattr(provider, "name", "unknown"),
        language,
        latency_ms,
        bool(payload.training_plan),
        bool(payload.nutrition_plan or payload.nutrition),
    )
    return response
