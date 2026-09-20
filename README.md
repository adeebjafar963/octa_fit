# OCTA FIT

Graduation-project mobile fitness platform: **Bodybuilding**, **MMA**, **training videos**, **sports store**, **AI Coach**, **AI Physiotherapist**, **Motion Analyzer**, **community**, and a **Laravel admin dashboard**.

**Canonical workspace:** `E:\OCTA_FIT`

**Principle:** polished + simple + complete + demonstrable — not an enterprise platform.

---

## Status (Phase 1)

Architecture and documentation established. Feature implementation has **not** started yet. Await explicit Phase 2 instruction before scaffolding apps or writing business code.

---

## Repository layout

```
E:\OCTA_FIT
├── mobile/                 # Flutter app (Clean Architecture + Cubit)
├── backend/                # Laravel REST API + admin dashboard
├── ai/
│   ├── coach/              # FastAPI AI Sports Coach
│   ├── physiotherapist/    # FastAPI AI Physiotherapist
│   └── motion_analyzer/    # FastAPI + MediaPipe Motion Analyzer
├── docs/                   # Architecture & setup docs
├── scripts/                # Windows PowerShell helpers
├── tools/                  # Local helper binaries (Composer PHAR, etc.)
└── README.md
```

---

## Planned stack

| Layer | Technology |
|-------|------------|
| Mobile | Flutter, Cubit, Dio, GetIt, GoRouter, easy_localization |
| API | Laravel + Sanctum + MySQL/MariaDB |
| Admin | Laravel Blade (simple responsive dashboard) |
| AI Coach | Python FastAPI + controlled knowledge base |
| AI Physio | Python FastAPI + rules/knowledge |
| Motion | Python FastAPI + OpenCV + MediaPipe Pose |
| Auth | Sanctum tokens (user + admin roles) |

---

## Documentation

| Document | Purpose |
|----------|---------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design & layers |
| [docs/DATABASE.md](docs/DATABASE.md) | Schema & relationships |
| [docs/API.md](docs/API.md) | REST API outline |
| [docs/SETUP.md](docs/SETUP.md) | Windows setup (after Phase 2+) |
| [docs/AI.md](docs/AI.md) | AI services design |
| [docs/TESTING.md](docs/TESTING.md) | Test strategy |
| [docs/GRADUATION_DEMO.md](docs/GRADUATION_DEMO.md) | Defense demo script |
| [docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md) | Phased development plan |
| [docs/MACHINE_INSPECTION.md](docs/MACHINE_INSPECTION.md) | Detected toolchain (Phase 1) |

---

## Languages

- Arabic (RTL) and English (LTR)
- Switchable from Settings
- Flutter: `easy_localization` — no hardcoded UI strings in widgets

---

## Security notes

- No API keys in Flutter
- Secrets only in server `.env` files
- Commit `.env.example` only
- Passwords hashed via Laravel

---

## Next step

Wait for **Phase 2** instruction (typically: install missing tools → scaffold Laravel backend → database migrations foundation).
