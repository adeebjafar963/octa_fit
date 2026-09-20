# Architecture

## Goals

Deliver a **simple, bilingual, demonstrable** graduation fitness app with clear boundaries between mobile, API, admin, and AI services.

## High-level diagram

```
┌────────────────────┐
│  Flutter Mobile    │
│  (AR/EN, Cubit)    │
└─────────┬──────────┘
          │ HTTPS / JSON (Sanctum token)
          ▼
┌────────────────────┐       ┌─────────────────────┐
│  Laravel Backend   │──────▶│  MySQL / MariaDB    │
│  REST + Admin UI   │       └─────────────────────┘
└─────────┬──────────┘
          │ Internal HTTP (service keys / localhost)
    ┌─────┴──────┬────────────────┐
    ▼            ▼                ▼
┌─────────┐ ┌──────────────┐ ┌────────────────┐
│ AI Coach│ │ Physiotherapist│ │ Motion Analyzer│
│ FastAPI │ │ FastAPI        │ │ FastAPI+CV     │
└─────────┘ └──────────────┘ └────────────────┘
```

**Rule:** Flutter never calls AI services or LLM providers directly. Laravel is the secure gateway.

---

## Response contract (all public APIs)

```json
{
  "success": true,
  "message": "Human-readable localized or English server message",
  "data": {}
}
```

Errors use the same envelope with `success: false` and optional `errors` for validation (422).

HTTP statuses: 200, 201, 400, 401, 403, 404, 422, 500. No stack traces to clients.

---

## Flutter architecture

```
mobile/lib
├── main.dart
├── config/           # env, theme, constants
├── core/             # network, errors, DI, utils
├── localization/     # easy_localization assets wiring
├── routing/          # GoRouter
├── domain/           # entities, repository interfaces, use cases
├── data/             # DTOs, datasources, repository impls
└── presentation/     # screens, widgets, cubits
    ├── home/
    ├── training/
    ├── store/
    ├── community/
    ├── profile/
    ├── ai_coach/
    ├── physio/
    ├── motion/
    └── auth/
```

### Patterns

| Concern | Choice |
|---------|--------|
| State | Cubit / flutter_bloc |
| DI | GetIt |
| HTTP | Dio + interceptors (auth, locale, errors) |
| Navigation | GoRouter + bottom nav shell |
| i18n | easy_localization (`ar.json`, `en.json`) |
| Architecture | Clean layers; presentation does not import data sources |

### Navigation (mobile)

Bottom nav (5 items only):

1. Home  
2. Training  
3. Store  
4. Community  
5. Profile  

AI Coach / Physio / Motion Analyzer: entry points from Home (and Profile/settings if needed), not extra bottom tabs.

### UI principles

- One purpose per screen  
- Minimal cards/buttons  
- Shared design system tokens (colors, spacing, type)  
- Loading / empty / error / retry states everywhere  
- Light/Dark theme if practical without visual noise  

---

## Laravel architecture

```
backend/
├── app/
│   ├── Http/Controllers/Api/
│   ├── Http/Controllers/Admin/
│   ├── Http/Requests/
│   ├── Http/Resources/
│   ├── Models/
│   ├── Policies/
│   ├── Services/          # business logic
│   └── Support/ApiResponse.php
├── routes/api.php
├── routes/web.php         # admin dashboard
├── database/migrations|
├── database/seeders/
└── resources/views/admin/
```

### Auth & roles

- Laravel Sanctum personal access tokens for mobile
- Roles: `admin`, `user` (simple roles table or enum column — prefer `roles` + pivot if flexible)
- Admin dashboard session/Sanctum as appropriate; authorize with Policies

### Service layer examples

- `OrderService` (demo checkout)
- `AiCoachGateway`
- `PhysioGateway`
- `MotionGateway`
- `CommunityService`

---

## AI architecture

| Service | Path | Port (dev) | Responsibility |
|---------|------|------------|----------------|
| Coach | `ai/coach` | 8001 | KB-first fitness Q&A (AR/EN) |
| Physio | `ai/physiotherapist` | 8002 | Symptom triage + educational guidance |
| Motion | `ai/motion_analyzer` | 8003 | Pose demo metrics + form hints |

Each service exposes:

- `GET /health`
- Domain POST endpoints
- Structured JSON errors

Secrets: OpenAI/other provider keys only in AI or Laravel `.env` — never Flutter.

Knowledge bases are **repo-controlled** markdown/JSON. Users cannot upload arbitrary documents.

---

## Configuration

| App | Config |
|-----|--------|
| Flutter | `--dart-define` or `config` pointing to Laravel base URL only |
| Laravel | `.env` → DB, Sanctum, AI service URLs, internal AI token |
| AI services | `.env` → model keys, bind host/port |

Provide `.env.example` for every backend/AI service.

---

## Admin dashboard

Simple Blade + Bootstrap (or minimal CSS) desktop UI:

- Stats home
- CRUD: users, training, videos, products, categories, orders, community, AI/physio/motion content, settings

Not a SPA unless later justified.

---

## Deployment (graduation / local demo)

All services run on one Windows machine:

- MariaDB (XAMPP)
- `php artisan serve` → `:8000`
- uvicorn coach / physio / motion → `:8001–8003`
- Flutter debug / release APK for demo device or emulator (`10.0.2.2` for Android emulator)

See `SETUP.md` (filled in Phase 1–2) and `GRADUATION_DEMO.md`.
