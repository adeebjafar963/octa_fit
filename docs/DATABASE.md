# Database — Phases 1–4

## Phase 1–2 tables

`users`, `roles`, `profiles`, `settings`, Sanctum tokens, Laravel infra tables.

## Phase 3 training tables

### `training_categories`

| Column | Notes |
|--------|-------|
| name_en / name_ar | Bilingual titles |
| slug | Unique (`bodybuilding`, `mma`) |
| type | `bodybuilding` \| `mma` |
| description_en / description_ar | Optional |
| is_active, sort_order | Visibility + ordering |

### `exercises`

| Column | Notes |
|--------|-------|
| training_category_id | FK → categories |
| name_*/description_*/instructions_*/safety_notes_* | Bilingual content |
| difficulty | `beginner` \| `intermediate` \| `advanced` |
| thumbnail_url, target | Optional |
| is_active, is_demo, sort_order | Demo flag for graduation content |

### `exercise_videos`

| Column | Notes |
|--------|-------|
| exercise_id | FK → exercises |
| title_en / title_ar | Bilingual |
| video_url | Configurable; seeded as placeholders |
| is_placeholder | `true` for demo URLs |
| duration_seconds, thumbnail_url, is_active, sort_order | Optional metadata |

## Relationships (training)

`TrainingCategory` 1—* `Exercise` 1—* `ExerciseVideo`

## Seed volume (training)

- 2 categories
- 7 bodybuilding exercises + 7 videos
- 7 MMA techniques + 7 videos

## Phase 4 store tables

### `product_categories`

| Column | Notes |
|--------|-------|
| name_en / name_ar | Bilingual |
| slug | Unique |
| description_en / description_ar | Nullable |
| image_url | Nullable placeholder |
| is_active | Indexed |
| sort_order | Ordering |

### `products`

| Column | Notes |
|--------|-------|
| category_id | FK → product_categories |
| name_en / name_ar / slug | Unique slug |
| description_* | Nullable bilingual |
| price | Decimal ≥ 0 |
| compare_at_price | Nullable |
| stock_quantity | Integer ≥ 0 |
| image_url, sku | Optional |
| is_active, is_demo, sort_order | Demo catalog flags |

No product variants in Phase 4.

### `carts`

| Column | Notes |
|--------|-------|
| user_id | Unique — one cart per user |

### `cart_items`

| Column | Notes |
|--------|-------|
| cart_id, product_id | Unique pair |
| quantity | > 0; must not exceed stock |
| unit_price | Snapshot of price when added/updated |

### `orders`

| Column | Notes |
|--------|-------|
| user_id | FK → users |
| order_number | Unique (`OCTA-YYYYMMDD-####`) |
| status | pending / confirmed / processing / completed / cancelled |
| payment_method | `cash_on_delivery` \| `demo` |
| subtotal, total | Server-calculated; Phase 4: `total = subtotal` |
| notes | Nullable |

### `order_items`

| Column | Notes |
|--------|-------|
| order_id | FK → orders |
| product_id | Nullable FK (product may later be removed) |
| product_name_en / product_name_ar | Name snapshots |
| quantity, unit_price, line_total | Price snapshots |

## Relationships (store)

```
ProductCategory 1—* Product
User 1—1 Cart 1—* CartItem *—1 Product
User 1—* Order 1—* OrderItem *—0..1 Product
```

## Seed volume (store)

- 4 categories: Supplements, Sportswear, Equipment, Accessories
- 15 demo products (idempotent `updateOrCreate` by slug)

## Phase 5 community tables

### `posts`

| Column | Notes |
|--------|-------|
| user_id | FK → users (cascade delete) |
| content | Required, max 1000 |
| image_url | Nullable (optional; no cloud upload in Phase 5) |
| is_published | Default true |
| indexes | user_id, created_at, is_published |

### `post_likes`

| Column | Notes |
|--------|-------|
| post_id, user_id | FKs with cascade |
| unique | `(post_id, user_id)` prevents duplicate likes |

### `comments`

| Column | Notes |
|--------|-------|
| post_id, user_id | FKs with cascade |
| content | Required, max 500 |
| indexes | post_id, user_id, created_at |

One-level comments only (no nested replies).

## Relationships (community)

```
User 1—* Post 1—* Comment
User 1—* PostLike *—1 Post
User 1—* Comment
```

Deleting a post cascades likes and comments via foreign keys.

## Seed volume (community)

- Extra demo users: Sara, Omar, Layla (+ existing demo user)
- ~10 posts, ~15 comments, ~20 likes

## Phase 6 coach tables

### `coach_conversations`

| Column | Notes |
|--------|-------|
| user_id | FK → users (cascade) |
| title | Nullable; auto-filled from first message |
| indexes | `(user_id, updated_at)` |

### `coach_messages`

| Column | Notes |
|--------|-------|
| conversation_id | FK → coach_conversations (cascade) |
| role | `user` \| `assistant` |
| content | Text |
| indexes | `(conversation_id, created_at)` |

No API keys are stored in the database.

## Phase 7 physio tables

### `physio_assessments`

| Column | Notes |
|--------|-------|
| user_id | FK → users (cascade) |
| body_area | e.g. knee, lower_back |
| symptoms | Text (max 2000 via API) |
| pain_level | Nullable 0–10 |
| duration / onset / trigger / activity / additional_information | Nullable |
| risk_level | Nullable `low\|moderate\|high\|urgent` |
| requires_urgent_care | Boolean |
| result_payload | JSON structured guidance |
| indexes | `(user_id, updated_at)`, `(user_id, risk_level)` |

### `physio_messages`

| Column | Notes |
|--------|-------|
| assessment_id | FK → physio_assessments (cascade) |
| role | `user` \| `assistant` |
| content | Text |
| indexes | `(assessment_id, created_at)` |

See [DATABASE_SETUP.md](DATABASE_SETUP.md) for migrate/seed commands.

## Phase 8 Motion Analyzer

No dedicated Motion Analyzer tables. Analysis is request/response only.

Raw video files are **not** stored in MySQL. Temporary files on the FastAPI host are deleted after processing.

## Phase 9 Admin

No new admin tables required. Admin APIs reuse existing entities and the `settings` table (allowlisted keys only).



