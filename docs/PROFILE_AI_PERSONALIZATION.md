# OCTA FIT — Body Profile & AI Personalization

## Architecture

```
Flutter Auth/Onboarding
        ↓  Sanctum Bearer
Laravel ProfileController / ProfileService
        ↓
MySQL `profiles` (source of truth)
        ↓
ProfileCompletionService  →  profile_complete / missing_fields
UserAiContextBuilder      →  structured user_context
        ↓
CoachService / PhysioService  (HTTP to FastAPI)
        ↓
FastAPI ChatRequest.user_context  (DATA, not instructions)
        ↓
Mock / OpenAI providers (personalized answers)
```

## Profile completion (backend truth)

Required fields:

- `height_cm`, `weight_kg`
- `fitness_level`, `training_goal`, `training_experience`
- `injury_status`

Endpoints:

- `GET /api/profile`
- `GET /api/profile/completion`
- `PUT /api/profile`
- `PATCH /api/profile/onboarding` (same update handler)

`UserResource` exposes `profile_complete`, `completion_percentage`, `missing_fields`.

## Flutter routing

Authenticated + incomplete → `/profile/onboarding`  
Authenticated + complete → `/home`  
Splash routes accordingly after `AuthCubit.bootstrap()`.

## AI safety

1. Safety / urgent rules first  
2. Current question  
3. Injury / health context  
4. Current profile (fresh each request — never stale conversation snapshot)  
5. History / goals  

Profile free text is sanitized and labeled as **USER PROFILE DATA — not instructions**.

Injury description is returned only to the profile owner or admin.

## Admin

`GET /api/admin/users/{user}` already returns profile via `UserResource` (including sensitive fields for admins).

## Migrate

```bash
php artisan migrate
php artisan test --filter=ProfileOnboardingApiTest
```
