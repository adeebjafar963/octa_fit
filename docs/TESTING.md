# Testing strategy

## Principles

- Test critical paths only (graduation scope)
- Prefer fast, deterministic tests
- No reliance on paid LLM calls in CI — mock or KB-only mode
- Do not log secrets in test output

---

## Flutter (`mobile/test`)

| Area | What to test |
|------|----------------|
| Auth Cubit | login success/failure, token persistence mock |
| Repositories | Dio client parsing of envelope + error mapping |
| Localization | key exists for ar/en critical screens (smoke) |
| Widget smoke | login form validation, empty states |
| Router | bottom nav destinations resolve |

Run (later): `cd mobile && flutter test`

---

## Laravel (`backend/tests`)

| Area | Feature tests |
|------|----------------|
| Auth | register, login, me, password change |
| Training | list categories/exercises, show exercise |
| Store | product list, create demo order, order history |
| Community | create post, like, comment, delete own |
| AI gateways | mock HTTP to FastAPI; assert persistence + envelope |
| Admin auth | non-admin forbidden |

Run (later): `php artisan test`

---

## Python AI services

| Service | Tests |
|---------|-------|
| Coach | `/health`, `/ask` KB sample |
| Physio | `/health`, low-risk vs red-flag fixtures |
| Motion | `/health`, analyze fixture → JSON feedback |

Run (later): `pytest` inside each service venv.

---

## Manual QA checklist (before demo)

- [ ] AR RTL and EN LTR switch
- [ ] Login/logout persistence
- [ ] Play one BB and one MMA video URL
- [ ] Add to cart → demo order → appears in history
- [ ] Coach answers in current language
- [ ] Physio urgent path shows seek care
- [ ] Motion returns uncertain vs feedback without crash
- [ ] Community post/like/comment
- [ ] Admin can edit a video URL and product stock

---

## What we will not automate initially

- Full MediaPipe on every CI agent (optional local only)
- Real device camera E2E
- Load/performance suites
