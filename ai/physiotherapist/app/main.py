from fastapi import FastAPI, HTTPException
from fastapi.responses import JSONResponse

from app.providers import AssessRequest, AssessResponse, FollowUpRequest, build_provider, detect_language

app = FastAPI(title="OCTA FIT AI Physiotherapist", version="0.2.0")
provider = build_provider()


@app.get("/health")
def health() -> JSONResponse:
    return JSONResponse(
        content={
            "status": "healthy",
            "service": "octa-fit-physiotherapist",
            "provider": getattr(provider, "name", "unknown"),
        }
    )


@app.post("/assess", response_model=AssessResponse)
async def assess(payload: AssessRequest) -> AssessResponse:
    symptoms = payload.symptoms.strip()
    if not symptoms:
        raise HTTPException(status_code=422, detail="Symptoms are required")
    language = detect_language(symptoms, payload.language)
    return await provider.assess(payload, language)


@app.post("/chat", response_model=AssessResponse)
async def chat(payload: FollowUpRequest) -> AssessResponse:
    message = payload.message.strip()
    if not message:
        raise HTTPException(status_code=422, detail="Message is required")
    language = detect_language(message, payload.language or payload.assessment.language)
    return await provider.follow_up(payload, language)
