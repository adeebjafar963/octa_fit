# AI services architecture

## Principles

1. Flutter → Laravel → AI service  
2. No provider API keys in the mobile app  
3. Prefer **controlled knowledge base** answers for project-specific fitness content  
4. Clear non-medical / non-diagnostic disclaimers  
5. Small demo scope over “full product” AI  

---

## 1) AI Sports Coach — `ai/coach`

**Stack:** Python 3.11, FastAPI, uvicorn, pydantic  
**Knowledge:** markdown/JSON under `ai/coach/knowledge/` (and optional DB articles synced from admin)

### Topics covered (KB)

Bodybuilding basics, hypertrophy, strength, fat loss, training principles, recovery, nutrition basics (protein/carbs/fats), hydration, MMA basics, beginner plans, exercise safety.

### Behavior

- Answer in `ar` or `en` per request locale  
- KB-first retrieval (simple embeddings or keyword/section retrieval for graduation simplicity)  
- Optional LLM polish **only if** key configured; otherwise deterministic KB templates still work for demo  
- Refuse medical diagnosis; distinguish fitness info vs medical advice  

### Endpoints (service-local)

- `GET /health`
- `POST /ask` → `{ answer, sources[], locale, disclaimer }`

---

## 2) AI Physiotherapist — `ai/physiotherapist`

**Stack:** FastAPI + rule engine + educational knowledge snippets

### Input

Pain location, intensity, duration, mechanism, limitations, swelling, numbness, weakness, other symptoms.

### Classification

1. **General / low-risk educational guidance**  
2. **Needs professional evaluation**  
3. **Urgent medical attention**

### Red flags (non-exhaustive)

Sudden severe pain, major trauma, inability to use limb, severe swelling, deformity, loss of sensation, severe weakness, chest pain, breathing difficulty, loss of consciousness, etc.

### Output

- Risk level + explanation  
- Conservative suggestions **only** for non-urgent educational cases  
- Explicit “not a diagnosis” disclaimer  
- Never provide dangerous treatment instructions  

### Endpoints

- `GET /health`
- `POST /assess`

---

## 3) Motion Analyzer — `ai/motion_analyzer`

**Stack:** FastAPI, OpenCV, MediaPipe Pose, NumPy

### Supported demo exercises (start)

1. Squat  
2. Push-up  
3. Biceps curl  

### Metrics (examples)

Joint angles, rep count, ROM approximation, basic posture flags.

### Feedback examples

- Keep your back more neutral  
- Knee angle outside recommended demo range  
- Lower further  
- Keep elbow closer to body  
- Rep completed / uncertain analysis  

### Claims boundary

Computer-vision **demonstration** only — not medical or professional biomechanical diagnosis. If landmarks unreliable → say analysis is uncertain.

### Endpoints

- `GET /health`
- `GET /exercises`
- `POST /analyze` (video file or image sequence — decide in Phase 9 for simplest reliable demo)

---

## Laravel gateway responsibilities

- Authenticate user  
- Validate payload  
- Forward to correct service with internal timeout  
- Persist conversations / assessments / results  
- Map downstream failures to friendly API errors  
- Strip/never forward secrets  

---

## Environment (examples)

```env
# Laravel
AI_COACH_URL=http://127.0.0.1:8001
AI_PHYSIO_URL=http://127.0.0.1:8002
AI_MOTION_URL=http://127.0.0.1:8003
AI_INTERNAL_TOKEN=change-me

# Coach service
OPENAI_API_KEY=           # optional
COACH_MODE=kb_first
```

---

## Testing (AI)

- Health checks always green when service up  
- Coach: known KB question returns expected keywords  
- Physio: red-flag fixture → urgent  
- Motion: fixture pose/image → structured feedback without crash  
