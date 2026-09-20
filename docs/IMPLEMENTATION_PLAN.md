# OCTA FIT — Implementation plan

**Rule:** finish and verify each phase before starting the next. Do not auto-advance.

---

## Phase 0 — Machine & architecture (CURRENT)

- [x] Inspect toolchain
- [x] Create `E:\OCTA_FIT`
- [x] Establish folder structure
- [x] Write architecture docs
- [ ] **STOP** — wait for user to approve Phase 1/2

---

## Phase 1 — Tooling readiness

1. Install Composer into `tools/` (or PATH)
2. Install portable PHP **8.2+** under `tools/php83` (preferred over PHP 8.0)
3. Start XAMPP MariaDB
4. Create MySQL database `octafit`
5. Copy `.env.example` templates
6. Verify: `php -v`, `composer -V`, `mysql` ping, `flutter doctor`

**Exit criteria:** Laravel can be created with PHP ≥ 8.2 and Composer.

---

## Phase 2 — Laravel backend foundation

1. `composer create-project laravel/laravel backend` (or equivalent into existing folder)
2. Configure Sanctum
3. Migrations for core auth tables (users, roles, profiles, settings)
4. Seed admin + demo user
5. Unified JSON API response helpers
6. Feature tests: register/login/me

**Exit criteria:** Auth API works with Postman/HTTP tests; admin can log in later via dashboard stub.

---

## Phase 3 — Training domain (Bodybuilding + MMA)

1. Migrations: categories, exercises, exercise_videos
2. API resources + policies
3. Seed 6–7 BB + 6–7 MMA demo items (configurable URLs)
4. Admin CRUD (simple Blade)
5. Tests for list/detail

**Exit criteria:** API returns bilingual training catalog.

---

## Phase 4 — E-commerce (demo checkout)

1. Product categories, products, cart (server or client+order), orders, order_items
2. COD / demo checkout only (no payment gateway)
3. Order history + status
4. Admin: products + orders
5. Tests: add-to-order flow

**Exit criteria:** Full demo purchase path without real payments.

---

## Phase 5 — Community

1. Posts, comments, likes
2. Pagination
3. Own-post delete
4. Admin moderation basics
5. Tests

**Exit criteria:** Create / like / comment / delete own post.

---

## Phase 6 — Flutter app shell

1. Flutter project in `mobile/`
2. Clean Architecture folders + GetIt + Dio + GoRouter + Cubit
3. easy_localization AR/EN + RTL/LTR
4. Design system (buttons, fields, empty/loading/error)
5. Bottom nav: Home, Training, Store, Community, Profile
6. Auth screens + token persistence
7. Wire to Laravel auth + training + store + community (read paths first)

**Exit criteria:** App launches bilingual; login works; main tabs navigate.

---

## Phase 7 — AI Coach service

1. Scaffold `ai/coach` FastAPI
2. Controlled knowledge base files
3. Laravel proxy endpoints (keys stay server-side)
4. Conversation persistence tables
5. Flutter Coach UI
6. Tests: health + sample Q&A

**Exit criteria:** Ask program/nutrition question in AR and EN via app.

---

## Phase 8 — AI Physiotherapist

1. Scaffold `ai/physiotherapist`
2. Symptom intake schema + red-flag rules
3. Risk tiers: educational / professional / urgent
4. Laravel gateway + Flutter UI + disclaimers
5. Tests for red-flag path

**Exit criteria:** Demo assessment + urgent recommendation path.

---

## Phase 9 — Motion Analyzer

1. Scaffold `ai/motion_analyzer`
2. Support squat, push-up, biceps curl (demo)
3. MediaPipe pose metrics + simple feedback
4. Laravel gateway + Flutter camera/upload demo flow
5. Tests: health + sample frame/fixture

**Exit criteria:** Demo analysis returns non-medical feedback.

---

## Phase 10 — Admin dashboard polish

1. Dashboard stats
2. Full CRUD for entities listed in requirements
3. Settings management
4. Responsive desktop layout

**Exit criteria:** Admin can replace video URLs and manage catalog for defense.

---

## Phase 11 — Hardening & graduation pack

1. Fill docs (SETUP, DEMO walkthrough verification)
2. Seed polish (small, presentation-friendly)
3. Error localization pass
4. Run Flutter + Laravel + Python test suites
5. `scripts/start_all.ps1` + health check
6. Demo dry-run checklist

**Exit criteria:** One-machine Windows demo runs smoothly for defense.

---

## Non-goals (keep out of scope unless requested)

- Real payment gateways
- Push notification infrastructure
- Stories / DMs / followers
- Hundreds of videos or products
- Multi-tenant enterprise features
- Exposing LLM keys to Flutter
