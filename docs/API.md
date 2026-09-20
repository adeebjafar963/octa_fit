# API architecture (planned)

Base URL (local): `http://127.0.0.1:8000/api/v1`

Auth: `Authorization: Bearer {token}`  
Locale hint: `Accept-Language: ar|en` (and/or user profile locale)

Envelope:

```json
{ "success": true, "message": "...", "data": {} }
```

---

## Auth

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| POST | `/auth/register` | Public | Create user + profile |
| POST | `/auth/login` | Public | Return token + user |
| POST | `/auth/logout` | User | Revoke token |
| GET | `/auth/me` | User | Current user |
| PUT | `/auth/profile` | User | Update profile |
| PUT | `/auth/password` | User | Change password |

---

## Training

| Method | Path | Auth |
|--------|------|------|
| GET | `/training/categories` | User |
| GET | `/training/categories/{id}/exercises` | User |
| GET | `/exercises` | User | Query: `type=bodybuilding\|mma`, `category_id` |
| GET | `/exercises/{id}` | User | Includes videos |

---

## Store

| Method | Path | Auth |
|--------|------|------|
| GET | `/store/categories` | User |
| GET | `/store/products` | User | Paginated |
| GET | `/store/products/{id}` | User |
| POST | `/store/orders` | User | Demo checkout (COD) |
| GET | `/store/orders` | User | History |
| GET | `/store/orders/{id}` | User | Details |

Request example (checkout):

```json
{
  "payment_method": "cod_demo",
  "items": [
    { "product_id": 1, "quantity": 2 }
  ],
  "notes": "Demo order"
}
```

---

## Community

| Method | Path | Auth |
|--------|------|------|
| GET | `/community/posts` | User | Paginated |
| POST | `/community/posts` | User | text + optional image |
| GET | `/community/posts/{id}` | User |
| DELETE | `/community/posts/{id}` | Owner |
| POST | `/community/posts/{id}/like` | User | Toggle or like |
| DELETE | `/community/posts/{id}/like` | User |
| GET | `/community/posts/{id}/comments` | User |
| POST | `/community/posts/{id}/comments` | User |
| GET | `/community/users/{id}` | User | Public profile snippet |

---

## AI (Laravel gateways)

| Method | Path | Auth | Proxies to |
|--------|------|------|------------|
| POST | `/ai/coach/ask` | User | Coach FastAPI |
| GET | `/ai/coach/conversations` | User | Laravel DB |
| GET | `/ai/coach/conversations/{id}` | User | Laravel DB |
| POST | `/ai/physio/assess` | User | Physio FastAPI |
| GET | `/ai/physio/history` | User | Laravel DB |
| GET | `/ai/motion/exercises` | User | Laravel / Motion service |
| POST | `/ai/motion/analyze` | User | Motion FastAPI (multipart or frame payload) |

Coach request sketch:

```json
{
  "message": "Give me a beginner hypertrophy week plan",
  "locale": "en",
  "conversation_id": null
}
```

Physio request sketch:

```json
{
  "locale": "ar",
  "pain_location": "knee",
  "pain_intensity": 7,
  "duration": "3 days",
  "mechanism": "twisted during squat",
  "limitations": "pain on stairs",
  "swelling": true,
  "numbness": false,
  "weakness": false,
  "other_symptoms": "",
  "red_flag_symptoms": []
}
```

---

## Settings / meta

| Method | Path | Auth |
|--------|------|------|
| GET | `/settings/public` | Public/User | App name, about blobs, feature flags |
| GET | `/health` | Public | API up |

---

## Admin

Admin HTML under `/admin/*` (session). Optional JSON under `/api/v1/admin/*` with admin role for later — Blade-first for simplicity.

---

## Error examples

422 validation:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."]
  },
  "data": null
}
```

401:

```json
{
  "success": false,
  "message": "Unauthenticated",
  "data": null
}
```

AI downstream down → Laravel returns **503** with friendly message (no internal exception dump).
