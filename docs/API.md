# API — Phases 1–4

Base URL: `http://127.0.0.1:8000/api`

## Auth (Phase 2)

| Method | Path | Auth |
|--------|------|------|
| POST | `/register` | Public |
| POST | `/login` | Public |
| GET | `/me` | Sanctum |
| POST | `/logout` | Sanctum |
| GET/PUT | `/profile` | Sanctum |
| PUT | `/profile/password` | Sanctum |

## Training (Phase 3)

All training endpoints require Sanctum auth. Only **active** content is returned.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/training/categories` | List categories (`?type=bodybuilding\|mma`) |
| GET | `/training/categories/{id\|slug}` | Category detail |
| GET | `/training/exercises` | Paginated exercises (`type`, `category`, `difficulty`, `search`, `page`) |
| GET | `/training/exercises/{id}` | Exercise + active videos |
| GET | `/training/bodybuilding` | Bodybuilding exercises page |
| GET | `/training/mma` | MMA techniques page |

Placeholder video URLs use the host `demo.octafit.test`. There are **no** public write endpoints for training in Phase 3.

## Store (Phase 4)

All store endpoints require Sanctum auth. Users may only access **their own** cart and orders.

**Real payment gateway integration is not implemented in Phase 4.** Checkout supports `cash_on_delivery` and `demo` only. Product/order data is demo data for the graduation project.

### Catalog

| Method | Path | Description |
|--------|------|-------------|
| GET | `/store/categories` | Active product categories |
| GET | `/store/products` | Paginated products (`category`, `search`, `page`, `per_page`) |
| GET | `/store/products/{id\|slug}` | Active product details |

Inactive products are not exposed (404).

### Cart

| Method | Path | Description |
|--------|------|-------------|
| GET | `/store/cart` | Current user cart + subtotal |
| POST | `/store/cart/items` | Add item `{ product_id, quantity }` |
| PUT | `/store/cart/items/{cartItem}` | Update quantity `{ quantity }` |
| DELETE | `/store/cart/items/{cartItem}` | Remove item |
| DELETE | `/store/cart` | Clear cart |

Rules:

- Quantity must be ≥ 1 and ≤ available stock
- Adding an existing product increases its quantity
- Inactive products cannot be added
- Server stores `unit_price` from the current product price

### Orders / checkout

| Method | Path | Description |
|--------|------|-------------|
| POST | `/store/orders` | Checkout `{ payment_method, notes? }` |
| GET | `/store/orders` | Authenticated user's order history (paginated) |
| GET | `/store/orders/{id}` | Own order details only |

Checkout (transactional):

1. Validate cart is non-empty
2. Re-check active products + stock
3. Recalculate prices server-side (`total = subtotal`)
4. Create order + order item snapshots
5. Decrease stock
6. Clear cart
7. Return order (`order_number` like `OCTA-20260920-0001`)

Client-sent `total` / `subtotal` / unit prices are **ignored**.

### Statuses & payment methods

- Statuses: `pending`, `confirmed`, `processing`, `completed`, `cancelled`
- Payment methods: `cash_on_delivery`, `demo`

## Community (Phase 5)

All community endpoints require Sanctum auth. This is an intentionally **lightweight** fitness feed — not a full social network.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/community/posts` | Paginated feed (newest first) |
| POST | `/community/posts` | Create post `{ content, image_url? }` |
| GET | `/community/posts/{id}` | Post details |
| PUT | `/community/posts/{id}` | Update own post |
| DELETE | `/community/posts/{id}` | Delete own post (cascades likes/comments) |
| POST | `/community/posts/{id}/like` | Like (idempotent) |
| DELETE | `/community/posts/{id}/like` | Unlike (idempotent) |
| GET | `/community/posts/{id}/comments` | Paginated comments |
| POST | `/community/posts/{id}/comments` | Add comment `{ content }` |
| DELETE | `/community/comments/{id}` | Delete own comment |

### Authorization

- `PostPolicy`: only owner may update/delete
- `CommentPolicy`: only owner may delete
- Cross-user mutations → **403**

### Content rules

- Post content: trimmed, required, max 1000
- Comment content: trimmed, required, max 500
- `user_id` is always taken from the authenticated user

### Feed payload fields

`author`, `like_count`, `comment_count`, `is_liked`, `is_owner`, `created_at` — loaded with counts/`withExists` to avoid N+1.

## AI Sports Coach (Phase 6)

All coach endpoints require Sanctum auth. Users may only access **their own** conversations.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/coach/conversations` | List conversations |
| POST | `/coach/conversations` | Create `{ title? }` |
| GET | `/coach/conversations/{id}` | Conversation + messages |
| DELETE | `/coach/conversations/{id}` | Delete own conversation |
| POST | `/coach/conversations/{id}/messages` | Send message `{ message, language? }` (rate limited) |

Laravel forwards chat requests to FastAPI `POST {AI_COACH_URL}/chat` and persists both user and assistant messages.

Default FastAPI provider is **mock** (`COACH_PROVIDER=mock`) so demos work without an external API key.

## AI Physiotherapist (Phase 7)

All physio endpoints require Sanctum auth. Users may only access **their own** assessments.

The AI Physiotherapist is an educational/project-level system and is **not clinically validated** or intended to diagnose medical conditions.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/physio/assessments` | List assessments |
| POST | `/physio/assessments` | Create assessment + AI result (rate limited) |
| GET | `/physio/assessments/{id}` | Assessment + messages + structured result |
| DELETE | `/physio/assessments/{id}` | Delete own assessment |
| POST | `/physio/assessments/{id}/messages` | Follow-up `{ message, language? }` (rate limited) |

Create body (subset): `body_area`, `symptoms`, `pain_level?` (0–10), `duration?`, `onset?`, `trigger?`, `activity?`, `additional_information?`, `language?`.

Laravel calls FastAPI `POST {AI_PHYSIO_URL}/assess` (create) or `/chat` (follow-up). Response includes `risk_level`, `requires_urgent_care`, and guidance sections.

## Motion Analyzer (Phase 8)

All motion endpoints require Sanctum auth.

Educational fitness analysis only — **not medical-grade**.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/motion/exercises` | Supported exercise slugs |
| POST | `/motion/analyze` | Multipart `{ exercise, video }` (rate limited) |

Validation: supported exercise, required video, MIME/type, max 20 MB.

Laravel forwards to FastAPI `POST {AI_MOTION_URL}/analyze`. Videos are processed temporarily and are not stored in MySQL.

## Admin API (Phase 9)

All routes require `auth:sanctum` **and** `role:admin`.

Normal users receive **403**. Unauthenticated requests receive **401**.

Laravel authorization is the security boundary. Flutter admin visibility is UI convenience only.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/dashboard` | Aggregate stats |
| GET | `/admin/users` | Paginated users (+ search) |
| GET | `/admin/users/{id}` | User detail (no password) |
| PUT | `/admin/users/{id}/role` | Set `admin` \| `user` (last-admin protected) |
| PUT | `/admin/users/{id}/status` | Activate/deactivate |
| GET/POST/PUT/DELETE | `/admin/training/categories` | Training categories CRUD |
| GET/POST/PUT/DELETE | `/admin/training/exercises` | Exercises CRUD |
| POST/PUT/DELETE | `/admin/training/exercises/{id}/videos`, `/admin/training/videos/{id}` | Video metadata |
| GET/POST/PUT/DELETE | `/admin/store/categories`, `/admin/store/products` | Catalog CRUD |
| GET | `/admin/orders` | Orders list |
| GET | `/admin/orders/{id}` | Order detail (+ snapshots) |
| PUT | `/admin/orders/{id}/status` | Status update only (not totals) |
| GET/DELETE | `/admin/community/posts` | Moderation |
| GET/PUT | `/admin/settings` | Safe settings + feature toggles only |

Allowed settings keys: `app_name`, `default_locale`, `support_text`, `feature_coach_enabled`, `feature_physio_enabled`, `feature_motion_enabled`.

Secrets / API keys are rejected and never returned.

## Feature flags (Phase 10)

| Method | Path | Auth | Notes |
|--------|------|------|--------|
| GET | `/features` | sanctum | `{ coach_enabled, physio_enabled, motion_enabled }` |

When a feature flag is off, matching `/coach/*`, `/physio/*`, or `/motion/*` routes return **403**. Flutter hides Home entries and redirects direct routes; Laravel remains authoritative.



