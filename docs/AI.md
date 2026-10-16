# AI services architecture

## Principles

1. Flutter → Laravel → AI service  
2. No provider API keys in the mobile app  
3. Prefer **mock / controlled answers** for graduation demos  
4. Clear non-medical / non-diagnostic disclaimers  
5. Small demo scope over “full product” AI  

---

## 1) AI Sports Coach — `ai/coach` (Phase 6 COMPLETE · Phase 11 in progress)

**Stack:** Python 3.11, FastAPI, uvicorn, pydantic  
**Port:** `127.0.0.1:8001`

### Phase 11

See [`docs/AI_COACH_PHASE11.md`](AI_COACH_PHASE11.md).

- **11A complete:** versioned `user_context` schema (`11a.1`) + FastAPI `CoachUserContext` parser.
- **11B complete:** Laravel `CoachExerciseRetrievalService` grounds coach turns to real `exercises` rows; FastAPI receives `exercise_candidates` and may only return those IDs; Flutter parses `grounded_exercises` for `/training/exercises/:id`.
- **11C complete:** deterministic `TrainingPlanEngine` (`11c.1`) builds grounded multi-day plans (hypertrophy/strength/fat-loss training/MMA/home); nutrition not in training plans.
- **11D complete:** `foods` table + USDA-aligned fixtures; deterministic search/normalize/calculate (`11d.1`); coach nutrition lookup mode; FastAPI `ChatRequest.nutrition` pass-through; Flutter parses `nutrition` map (UI in 11G).
- **11E complete:** preference persistence; Mifflin–St Jeor calorie + macro targets; meal-plan engine with allergy hard exclusions; versioned `nutrition_plans` (`11e.1`); FastAPI `nutrition_plan`; Flutter parses `nutritionPlan`.
- **11G complete:** Coach chat structured cards (training/nutrition/meals/food/evidence), Open Exercise navigation, regenerate/replace prompts, EN/AR + RTL-aware layout, `url_launcher` for real source URLs only.
- Phase 11H Coach memory (`11h.1`) and Phase 11I evaluation/reliability hardening are complete. AI Coach architecture 11A–11I is closed for feature work.

### Endpoints

- `GET /health`
- `POST /chat` → `{ success, message, provider, language }`

### Providers

```env
COACH_PROVIDER=mock
COACH_API_KEY=
COACH_MODEL=gpt-4o-mini
```

- `mock` — deterministic fitness answers (default; no API key)
- `openai` — optional external LLM; falls back to mock on failure

### Safety

Not a doctor. No diagnosis, prescriptions, steroids, or unsafe dosing. Medical/injury questions get a safe redirect.

### Laravel gateway

`CoachService` authenticates users, stores conversations/messages, forwards recent context (last ~16 messages) to FastAPI, and persists assistant replies.

`UserAiContextBuilder::forCoach` emits Phase 11A nested context (+ flat aliases).
---

## 2) AI Physiotherapist — `ai/physiotherapist` (Phase 7 COMPLETE)

**Port:** `127.0.0.1:8002`

The AI Physiotherapist is an **educational/project-level system** and is **not clinically validated** or intended to diagnose medical conditions.

### Endpoints

- `GET /health`
- `POST /assess` — structured symptom intake → risk heuristic + guidance
- `POST /chat` — follow-up with assessment snapshot + recent conversation

### Providers

```env
PHYSIO_PROVIDER=mock
PHYSIO_API_KEY=
PHYSIO_MODEL=gpt-4o-mini
```

- `mock` — deterministic scenario responses (soreness, tightness, discomfort, red flags)
- Optional external provider falls back to mock; never fabricates a confident diagnosis on failure

### Risk heuristic (project-level, not clinical triage)

| Level | Meaning |
|-------|---------|
| low | General self-care + monitoring |
| moderate | Professional care if persistent/worsening |
| high | Prompt professional assessment |
| urgent | Seek urgent/emergency care (`requires_urgent_care=true`) |

Language stays uncertain (“may be consistent with…”). No definitive diagnoses. No medication dosing.

### Laravel gateway

`PhysioService` stores assessments/messages, calls FastAPI, and persists structured `result_payload` + risk flags.

---

## 3) Motion Analyzer — `ai/motion_analyzer` (Phase 8 COMPLETE)

**Port:** `127.0.0.1:8003`

Educational computer-vision fitness feature — **not medical-grade** and not intended to diagnose injuries or medical conditions.

### Endpoints

- `GET /health`
- `POST /analyze` — multipart `exercise` + `video`

### Providers

```env
MOTION_PROVIDER=mock
MAX_UPLOAD_MB=20
```

- `mock` — deterministic sample results per exercise (default)
- `mediapipe` — OpenCV + MediaPipe pose when installed; fails gracefully otherwise

### Supported exercises

`squat`, `push_up`, `biceps_curl`, `shoulder_press`, `lunge`

### Processing modules

- `utils/geometry.py` — `calculate_angle(a, b, c)`
- `exercises/config.py` — centralized thresholds
- `exercises/repetition.py` — UP/DOWN state machine
- `exercises/form.py` — cautious educational observations

### Laravel gateway

`MotionService` validates uploads and exercises, forwards multipart to FastAPI, and returns structured analysis without persisting raw video.

