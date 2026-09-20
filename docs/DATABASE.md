# Database architecture

**Engine:** MySQL-compatible (MariaDB 10.4 via XAMPP is acceptable for development).  
**Database name (planned):** `octafit`  
**Conventions:** snake_case tables, `id` PK bigint, `timestamps`, soft deletes where content may be restored, FKs with indexes.

---

## Entity relationship (logical)

```
roles ──< users >── profiles
                │
                ├── community_posts ──< community_comments
                │                 └──< community_likes
                ├── orders ──< order_items >── products >── product_categories
                ├── ai_conversations ──< ai_messages
                ├── physio_assessments
                └── motion_analysis_results >── motion_exercises

training_categories ──< exercises ──< exercise_videos
settings (key/value app config)
ai_knowledge_articles (optional DB mirror of coach KB for admin editing)
physio_content (optional educational content for admin)
```

---

## Tables (minimum)

### Identity

| Table | Purpose |
|-------|---------|
| `roles` | `admin`, `user` |
| `users` | auth identity, email unique, password hash, role_id or pivot |
| `profiles` | avatar, age, gender, height, weight, training_goal, locale preference |
| `personal_access_tokens` | Sanctum |

### Training

| Table | Purpose |
|-------|---------|
| `training_categories` | Bodybuilding / MMA (+ subcategories if needed) |
| `exercises` | bilingual titles/descriptions, difficulty, target muscle / technique type, instructions, safety notes |
| `exercise_videos` | video_url, thumbnail_url, sort order, linked exercise |

Suggested exercise fields: `title_ar`, `title_en`, `description_ar`, `description_en`, `instructions_ar`, `instructions_en`, `safety_notes_ar`, `safety_notes_en`, `difficulty`, `target`, `is_demo`, `is_active`.

### Commerce

| Table | Purpose |
|-------|---------|
| `product_categories` | clothing, gloves, equipment, … |
| `products` | bilingual name/description, price, stock, image, is_active |
| `orders` | user_id, status, total, payment_method=`cod_demo`, notes |
| `order_items` | order_id, product_id, qty, unit_price |

Cart: prefer **client cart + create order on checkout** for simplicity (graduation). Optional `carts`/`cart_items` only if needed.

Order statuses (simple): `pending`, `confirmed`, `cancelled`, `completed`.

### Community

| Table | Purpose |
|-------|---------|
| `community_posts` | user_id, body, optional image path, soft deletes |
| `community_comments` | post_id, user_id, body |
| `community_likes` | unique(post_id, user_id) |

### AI persistence

| Table | Purpose |
|-------|---------|
| `ai_conversations` | user_id, type=`coach`, locale |
| `ai_messages` | conversation_id, role, content |
| `physio_assessments` | user inputs JSON, risk_level, response summary, red_flags |
| `motion_exercises` | squat / push_up / biceps_curl definitions |
| `motion_analysis_results` | user_id, exercise_id, metrics JSON, feedback JSON |

### Content / settings (admin-editable)

| Table | Purpose |
|-------|---------|
| `settings` | key, value (JSON/string), group |
| `ai_knowledge_articles` | bilingual coach KB entries (demo-marked) |
| `physio_content` | educational physio snippets |

---

## Seed strategy

Small, presentation-friendly:

- 1 admin + 1 demo user (documented passwords in SETUP — not production secrets)
- ~6–7 bodybuilding exercises/videos
- ~6–7 MMA techniques/videos
- ~6–10 products across few categories
- ~3–5 community posts with comments/likes
- Motion exercises: squat, push-up, biceps curl
- AI knowledge articles clearly labeled **demonstration/sample**

No hundreds of fake rows.

---

## Indexing notes

- FK columns indexed
- `users.email` unique
- `community_likes (post_id, user_id)` unique
- `products` / `exercises` filtered by `is_active`
- Orders listed by `user_id`, `created_at`

---

## Soft deletes

Recommended on: `users` (careful), `exercises`, `products`, `community_posts`, knowledge articles.  
Prefer hard delete or cascade for likes/comments when parent is removed, or soft-delete comments with posts.
