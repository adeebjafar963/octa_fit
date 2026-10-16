# Graduation demo script — Phase 10

Ideal defense walkthrough (~20–30 minutes). Keep MariaDB + Laravel + AI mock services running before starting.

---

## Project goal (30 sec)

OCTA FIT is a bilingual fitness graduation app: training library, demo store, community, and three educational AI helpers, managed through an admin dashboard. AI is **assistive and educational**, not medical-grade diagnosis.

---

## Architecture (1 min)

- **Flutter** Clean Architecture, Cubit, GoRouter, easy_localization (EN LTR / AR RTL)
- **Laravel** Sanctum API, policies, admin role middleware, AI gateway
- **FastAPI** Coach :8001, Physio :8002, Motion :8003 (mock providers by default)
- **Security:** tokens in secure storage; keys only on server; feature toggles gated on API + UI

---

## Prep (2 min)

1. Start XAMPP MySQL  
2. Start Laravel `:8000`  
3. Start Coach / Physio / Motion (mock)  
4. Launch Flutter  
5. Switch language EN ↔ AR once  

---

## Demo sequence

| # | Step | What to show |
|---|------|----------------|
| 1 | Register or login | Demo user `demo@octafit.test` |
| 2 | Home | Training / Store / Community / AI / Admin (admin only) |
| 3 | Training | Bodybuilding → exercise details |
| 4 | Store | Product → cart → checkout demo order |
| 5 | Orders | Order list / detail |
| 6 | Community | Create post → like → comment |
| 7 | AI Coach | Fitness question (not medical dosing) |
| 8 | AI Physiotherapist | Low-risk sample assessment + disclaimer |
| 9 | Motion Analyzer | Supported exercise + mock video analysis |
| 10 | Profile | Edit fields → logout |
| 11 | Admin login | `admin@octafit.test` |
| 12 | Admin dashboard | Stats |
| 13 | Admin manage | Training item / product / order view |
| 14 | Moderate | Community post |
| 15 | Settings | Safe setting or feature toggle (no secrets) |

---

## Correct wording for AI

| Module | Say | Do **not** say |
|--------|-----|----------------|
| Coach | Fitness-oriented AI assistant | Medical doctor |
| Physio | Educational preliminary guidance with safety rules | AI doctor / diagnosis |
| Motion | Pose-estimation-based educational movement analysis | Medical-grade diagnosis |

---

## Fail-safes

- AI down → friendly error / 502–503 mapping; screenshots OK  
- Feature toggle off → entry hidden + API 403  
- Emulator networking → `10.0.2.2:8000`  

---

## Success criteria

Committee sees the full loop: **train → shop → community → AI assist → admin control**, with clear honesty about AI limits and academic scope.
