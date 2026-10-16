# Architecture — Phases 1–10

## System

```
Flutter (user app + admin UI)
   │ Sanctum Bearer
   ▼
Laravel API :8000
   ├─ User routes (auth:sanctum)
   ├─ Feature gates (feature:coach|physio|motion)
   ├─ Admin routes (auth:sanctum + role:admin)
   ├─ MySQL / MariaDB
   └─ FastAPI AI services :8001–8003
```

## Phase 10 hardening

- Auth interceptor does **not** clear session on failed login/register 401
- GoRouter treats form `AuthError` with `sessionUser` as still authenticated
- Normalized Dio error keys: 401/403/404/409/422/429/5xx/timeout/network
- Public `GET /api/features` + middleware gates for AI modules
- Home scrolls; AI entries respect feature flags; Admin only for admins
- Unknown routes: GoRouter `errorBuilder` fallback

## Admin Dashboard (Phase 9)

### Authorization

- Middleware alias `role:admin` (`EnsureUserHasRole`)
- Last remaining admin cannot be demoted/deactivated
- Settings allowlist only

### Flutter admin routes

`/admin`, `/admin/users`, `/admin/training`, `/admin/products`, `/admin/orders`, `/admin/community`, `/admin/settings`

Server-side authorization remains mandatory.

## Prior phases

Phases 1–8 (auth, training, store, community, coach, physio, motion) remain the same architecture; Phase 10 only stabilizes integration and graduation readiness.
