# Known limitations (Phase 10)

Honest graduation-project scope. Do not oversell.

## Product

- No real payment gateway (checkout is demo / COD-style)
- No push notifications, real-time chat, followers, or stories
- No production cloud deployment or full CI/CD in this phase
- Motion Analyzer: file/gallery selection — **no camera capture** unless already present in the build (Phase 10 did not add camera)
- Videos in training use demo/placeholder URLs where seeded

## AI

- Coach / Physio / Motion default to **mock** providers for demos and tests
- Coach is fitness assistance only; medical questions should be redirected
- Physiotherapist is educational triage with uncertainty language — **not** diagnosis or medication dosing
- Motion is educational form feedback — **not** clinical biomechanics
- Rate limits exist on expensive AI endpoints; demos should stay within normal usage

## Security / ops

- Feature toggles hide UI and gate Laravel routes; Flutter hiding alone is not security
- CORS / env must be configured per machine; `.env` is never committed
- Admin settings allowlist only — secrets cannot be stored via admin UI

## Platform

- Android APK / Windows build success depends on local SDK tooling
- Full manual E2E on every device is environment-dependent
