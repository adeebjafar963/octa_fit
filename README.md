# OCTA FIT

Graduation fitness platform — **Phase 10 complete** (integration, hardening, demo readiness).

**Root:** `E:\OCTA_FIT`

## What it is

OCTA FIT combines training content, a demo e-commerce store, a community feed, and three educational AI assistants (Coach, Physiotherapist guidance, Motion Analyzer), plus an admin dashboard. It is an academic graduation project — not a medical device or production payment system.

## Demo accounts

- User: `demo@octafit.test` / `Demo123!`
- Admin: `admin@octafit.test` / `Admin123!`

## Quick start

```powershell
cd E:\OCTA_FIT\backend
E:\OCTA_FIT\tools\php83\php.exe artisan migrate --seed
E:\OCTA_FIT\tools\php83\php.exe artisan serve --host=127.0.0.1 --port=8000

# AI services (mock defaults — see ai/*/README.md):
# Coach :8001  Physio :8002  Motion :8003

cd E:\OCTA_FIT\mobile
flutter pub get
flutter run -d windows
# Android emulator (optional): --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Windows Desktop is the supported local demo target (`mobile/windows`).

## Architecture (summary)

```
Flutter (Clean Architecture + Cubit + GoRouter + easy_localization)
   │ Sanctum Bearer (flutter_secure_storage)
   ▼
Laravel API :8000
   ├─ auth:sanctum user routes
   ├─ role:admin admin routes
   ├─ feature:* gates for AI modules
   └─ proxies → FastAPI Coach/Physio/Motion
```

## Phases

| Phase | Status |
|-------|--------|
| 1–9 Foundation → Admin | Done |
| **10 Final integration & graduation readiness** | **Done** |
| 11+ | Not started |

## Documentation

| Doc | Purpose |
|-----|---------|
| [docs/SETUP.md](docs/SETUP.md) | Environment setup |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design |
| [docs/API.md](docs/API.md) | API overview |
| [docs/GRADUATION_DEMO.md](docs/GRADUATION_DEMO.md) | Defense demo script |
| [docs/KNOWN_LIMITATIONS.md](docs/KNOWN_LIMITATIONS.md) | Honest scope limits |
| [docs/SAFETY.md](docs/SAFETY.md) | AI / medical disclaimers |

## Tests

```powershell
cd E:\OCTA_FIT\mobile
flutter analyze
flutter test

cd E:\OCTA_FIT\backend
E:\OCTA_FIT\tools\php83\php.exe artisan test

# Python (from each service folder with venv active):
# pytest
```

## Security notes

- Tokens never logged or shown in UI
- AI provider keys only on server / Python env
- Admin settings allowlist rejects secrets
- Feature toggles hide UI **and** gate Laravel routes (UI is not security)
