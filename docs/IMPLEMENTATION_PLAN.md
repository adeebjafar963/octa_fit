# OCTA FIT — Implementation plan

**Rule:** finish and verify each phase before starting the next. Do not auto-advance.

---

## Phase 0 — Machine & architecture

- [x] Inspect toolchain
- [x] Create `E:\OCTA_FIT`
- [x] Establish folder structure
- [x] Write architecture docs

---

## Phase 1 — Project foundation (COMPLETE)

- [x] Portable PHP 8.3 + Composer PHAR in `tools/`
- [x] MariaDB `octafit` + foundation migrations
- [x] Flutter app scaffold (Clean Architecture + Cubit/Dio/GetIt/GoRouter/easy_localization)
- [x] Splash / Home / Settings (language)
- [x] Laravel 13 + Sanctum + CORS + `GET /api/health`
- [x] AI Coach / Physio / Motion FastAPI health stubs
- [x] Docs + verification (analyze/tests/health)

---

## Phase 2 — Authentication (COMPLETE)

- [x] Register / login / me / logout
- [x] Profile get/update + password change
- [x] Roles foundation (`admin` / `user`) + middleware/policy
- [x] Flutter AuthCubit, secure token storage, interceptor, protected routes
- [x] AR/EN auth screens + session persistence
- [x] Laravel + Flutter tests + manual API verification

---

## Phase 3 — Training system (COMPLETE)

- [x] Categories / exercises / videos migrations + models
- [x] Seed 7 bodybuilding + 7 MMA demo items (placeholder videos)
- [x] Authenticated read APIs + filters/search
- [x] Flutter Training home, lists, details, video placeholder UI
- [x] Localization AR/EN + tests

**STOP** — wait for explicit Phase 4 instruction (Store).

---

## Phase 4 — E-commerce (demo checkout) (COMPLETE)

- [x] Product categories, products, carts, cart_items, orders, order_items
- [x] COD / demo checkout only (no payment gateway)
- [x] Order history + status + stock management
- [x] Flutter store screens (browse, cart, checkout, orders) AR/EN
- [x] Laravel + Flutter tests + manual API verification

**STOP** — wait for explicit Phase 5 instruction (Community).

---

## Phase 5 — Community (COMPLETE)

- [x] Posts / likes / comments migrations + models + policies
- [x] Feed pagination, create/edit/delete own posts
- [x] Like/unlike with unique constraint
- [x] Comments create + delete own
- [x] Flutter community screens AR/EN
- [x] Laravel + Flutter tests + manual API verification

**STOP** — wait for explicit Phase 6 instruction.

---

## Phase 6 — AI Sports Coach (COMPLETE)

- [x] FastAPI `/chat` + mock/optional provider
- [x] Laravel conversations/messages + gateway to Coach service
- [x] Ownership policies + rate limiting
- [x] Flutter conversation list + chat UI (AR/EN)
- [x] Tests (Laravel / Flutter / FastAPI) + manual API verification

**STOP** — wait for explicit Phase 7 instruction.

---

## Phase 7 — AI Physiotherapist (COMPLETE)

- [x] FastAPI `/assess` + `/chat` + mock provider + red-flag heuristic
- [x] Laravel `physio_assessments` / `physio_messages` + PhysioService gateway
- [x] Ownership policies + rate limiting
- [x] Flutter assessment form + result + follow-up (AR/EN)
- [x] Tests (Laravel / Flutter / FastAPI) + manual API verification

**STOP** — wait for explicit Phase 8 instruction. Do not start Motion Analyzer.

---

## Phase 8 — Motion Analyzer (COMPLETE)

- [x] FastAPI `/analyze` + mock/mediapipe providers + angle/rep modules
- [x] Laravel `MotionService` + multipart gateway (no video DB storage)
- [x] Flutter exercise select + video picker + results (AR/EN)
- [x] Tests (Laravel / Flutter / FastAPI) + manual API verification

**STOP** — wait for explicit Phase 9 instruction. Do not start Admin polish automatically.

---

## Phase 9 — Admin Dashboard (COMPLETE)

- [x] Admin API (`role:admin`) for dashboard, users, training, store, orders, community, settings
- [x] Last-admin protection + safe settings (no secrets)
- [x] Flutter admin screens + route guard (UI convenience; Laravel enforces authz)
- [x] Tests + manual API verification

**STOP** — wait for explicit Phase 10 instruction.

---

## Phase 10+ — Future (NOT STARTED)

Hardening / graduation demo pack — only when explicitly requested.

Do not auto-advance.

---

## Historical notes (earlier plan numbering)

Older drafts labeled Coach as “Phase 7” and Physio as “Phase 8”. The active project phases are:

- Phase 6 = AI Sports Coach  
- Phase 7 = AI Physiotherapist  
- Phase 8 = Motion Analyzer  
- Phase 9 = Admin Dashboard  
- Phase 10+ = Hardening / demo pack

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

## Phase 10 — Motion Analyzer notes (historical draft numbering)

Motion Analyzer was delivered as **Phase 8** in the active plan.

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
