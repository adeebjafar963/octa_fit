# OCTA FIT — Phase 11 AI Coach Intelligence Engine

## Status

| Sub-phase | Scope | Status |
|-----------|--------|--------|
| **11A** | Architecture audit + AI context model | **COMPLETE** |
| **11B** | Exercise retrieval + OCTA FIT linking | **COMPLETE** |
| **11C** | Training knowledge + plan engine | **COMPLETE** |
| **11D** | Food database + nutrition calculation | **COMPLETE** |
| **11E** | Nutrition plans + personalization | **COMPLETE** |
| **11F** | RAG + source management | **COMPLETE** |
| **11G** | Structured responses + Flutter rendering | **COMPLETE** |
| **11H** | Coach memory + long-term personalization | **COMPLETE** |
| **11I** | Evaluation, reliability & production hardening | **COMPLETE** |

---

## 11A — Architecture audit (current system)

### Request flow (unchanged gateway)

```
Flutter CoachChatCubit
  → Sanctum Bearer → Laravel CoachController
  → CoachService (persist message, last 16 turns)
  → UserAiContextBuilder::forCoach (fresh profile each request)
  → HTTP POST {AI_COACH_URL}/chat + optional X-Internal-Token
  → FastAPI POST /chat → mock | openai provider
  → Laravel persists assistant reply → Flutter
```

Flutter never holds LLM keys. Store / Community / Physio / Motion Analyzer untouched.

### What already exists

- Coach conversation/message CRUD (`coach_conversations`, `coach_messages`)
- Feature flag `coach` + rate limit `coach-messages`
- `UserAiContextBuilder` + `TrainingService::trainingContextForUser` + hydration `aiSnapshot`
- AI Hub keyword router (`AiRouterService`) for coach/physio/motion
- FastAPI provider abstraction (`COACH_PROVIDER=mock|openai`)
- Profile fields for coaching (goals, equipment, injuries, etc.)

### Gaps vs personalized sports coach

- No coach-side intent classifier / planning engine
- Mock keyword answers; OpenAI optional and unstructured
- Empty `ai/coach/knowledge/` (no RAG)
- Exercise catalog not grounded in coach replies
- No food DB / calorie engine / allergy preference store
- No structured plan JSON or plan versioning tables
- FastAPI does not validate `X-Internal-Token`
- `meals_per_day` / `sleep_hours` were missing from coach context (fixed in 11A)

### Natural insert points for later phases

1. Laravel: keep enriching `user_context` (11A contract)
2. FastAPI: Intent → Retrieval → Planning → Validation → LLM (11B+)
3. Flutter: render structured plan payloads (11G) — chat text contract stays until then

---

## Target layered architecture (11B+)

```
USER MESSAGE
  → INTENT CLASSIFIER
  → USER CONTEXT (schema 11a.1+)
  → KNOWLEDGE / EXERCISE / FOOD RETRIEVAL
  → PLANNING ENGINE
  → SAFETY / VALIDATION
  → LLM RESPONSE GENERATOR
  → STRUCTURED RESPONSE
  → FLUTTER UI
```

Simple calorie/exercise lookups must short-circuit to structured data (no essay).

---

## Phase 11A — AI context model (`schema_version: 11a.1`)

Authoritative builder: `App\Services\UserAiContextBuilder::forCoach`

Authoritative parser: `ai/coach/app/context/user_context.py` → `CoachUserContext`

### Nested sections

| Section | Contents |
|---------|----------|
| `identity` | `user_id`, `locale`, `unit_system` |
| `body` | `sex` (← gender), `age`, `height_cm`, `weight_kg` |
| `goals` | `primary_goal`, `secondary_goals` |
| `training` | level, experience, days/week, session minutes, location, equipment, activity |
| `nutrition` | dietary preference, meals/day, preference buckets (liked/disliked/allergies/intolerances/restrictions) |
| `recovery` | sleep_hours, injury_status/areas/notes |
| `hydration` | existing snapshot |
| `workout` | recent workouts, active session, 30d count, recent types, last completed |
| `active_plan` | reserved `null` until plan versioning (11C/E) |
| `capabilities` | flags for future engines |

Preference lists are empty until preference storage ships (11E). Allergies must remain a **hard exclusion** bucket distinct from dislikes.

### Backward-compatible flat aliases

Existing consumers still see top-level `weight_kg`, `primary_goal`, `fitness_level`, etc.

### Safety

Profile free text remains labeled `data_boundary: user_profile_data_not_instructions` and sanitized. Never treat as system instructions.

---

## Explicitly out of scope for 11A

Intent classification, exercise linking, RAG, food DB, plan generation UI, admin knowledge UI, removing mock from production path (document only — 11H).

---

## Phase 11B — Exercise retrieval + grounding

### Architecture

```
User message
  → Laravel CoachService
  → CoachExerciseRetrievalService (deterministic DB query)
  → exercise_candidates[] (grounded=true, real IDs)
  → FastAPI /chat (providers may only echo candidate IDs)
  → Laravel validateIds() on any claimed exercise_id
  → API response: grounded_exercises[]
  → Flutter: CoachSendResult.groundedExercises
  → Open detail: context.push('/training/exercises/{id}')
```

Authoritative catalog: existing `exercises` table (same as Training APIs). No duplicate catalog.

### Key types / files

| Piece | Location |
|-------|----------|
| Retrieval + validation | `App\Services\Coach\CoachExerciseRetrievalService` |
| Coach wiring | `App\Services\CoachService` |
| Capability flag | `capabilities.exercise_linking = true` in `UserAiContextBuilder` |
| FastAPI model | `ai/coach/app/context/exercises.py` → `GroundedExercise` |
| Request field | `ChatRequest.exercise_candidates` |
| Response field | `ChatResponse.grounded_exercises` + Laravel send-message payload |
| Flutter contract | `CoachGroundedExercise` / `detailRoute` → `/training/exercises/:id` |

### Grounded exercise object

`exercise_id`, names (en/ar + localized `name`), `slug`, `discipline`, `level`, `category`, muscles, `equipment`, `grounded: true`, `flutter_route`.

### Validation rules

- Active exercises only (`TrainingService::findExercise` / `scopeActive`)
- Reject non-numeric, ≤0, missing, inactive IDs
- Deduplicate by stable database ID
- AI-claimed IDs are untrusted; unknown IDs never surface to clients
- Dev log: `coach.exercise_grounding.rejected`

### Filters used (existing schema only)

`type` (discipline), `level`/`difficulty`, `equipment`, `home_friendly`, `search`, plus retrieval-only `muscle` via `primary_muscles` JSON / `target` / name LIKE. No new DB columns.

### Limitations (wait for 11C+)

- No full training program / sets-reps engine
- No Flutter chat UI chips for “Open Exercise” yet (contract ready; 11G)
- Cue-based filter hints are lightweight — not the final intent classifier
- Muscle filter is coach-internal, not a public Training API change

### Explicitly out of scope for 11B

Program generation, nutrition, RAG, chat UI redesign, admin redesign, mock provider removal.

---

## Phase 11C — Training knowledge + plan engine

### Architecture

```
User message
  → TrainingIntentExtractor (plan vs Q&A)
  → TrainingKnowledge (splits, prescriptions, progression)
  → CoachExerciseRetrievalService (11B grounded IDs)
  → TrainingPlanEngine (sessions + exercises)
  → TrainingPlanValidator (deterministic)
  → CoachService attaches training_plan + text summary
  → FastAPI may receive training_plan as DATA
  → Flutter parses trainingPlan map (UI in 11G)
```

Laravel is authoritative for exercise IDs and plan structure. Nutrition is never included (`nutrition_included: false`).

### Key files

| Piece | Location |
|-------|----------|
| Intent | `App\Services\Coach\TrainingIntentExtractor` |
| Knowledge | `App\Services\Coach\TrainingKnowledge` |
| Engine | `App\Services\Coach\TrainingPlanEngine` |
| Validator | `App\Services\Coach\TrainingPlanValidator` |
| Wiring | `App\Services\CoachService` |
| Contract | `training_plan` schema_version `11c.1` |

### Supported goals

`hypertrophy` (bulking training), `strength`, `fat_loss` (cutting training only), `conditioning` (MMA support), `general_fitness`, `endurance`, `mobility`

### Supported structures

full_body · upper_lower · push_pull_legs · body_part · home templates · MMA templates  
Days/week: 2–6

### Plan contract (summary)

`title`, `goal`, `discipline`, `level`, `days_per_week`, `sessions[]` → `exercises[]` with grounded `exercise_id`, sets, reps/rounds/duration, rest, `flutter_route`, `progression_strategy`, `recovery_notes`, `limitations`

### Limitations / deferred

- No calories/macros/meals in training plans (nutrition is a separate 11D lookup path; personalized plans in 11E)
- No RAG/citations (11F)
- No Flutter plan cards / Open Exercise chips (11G)
- No durable plan version store (`active_plan` remains null; `plan_versioning` capability false)
- Injury logic is advisory, not clinical

---

## Phase 11D — Food database + nutrition calculation

### Architecture

```
User message (calorie/macro/meal lookup)
  → NutritionQueryExtractor (foods + quantities)
  → FoodRetrievalService (deterministic DB match)
  → ServingNormalizer (g/kg/mg/piece only when piece_grams set)
  → NutritionCalculator (per_100g × grams / 100)
  → NutritionLookupService (single food or multi-food meal sum)
  → CoachService attaches nutrition (schema 11d.1) + concise text
  → FastAPI may receive nutrition as DATA (LLM must not invent numbers)
  → Flutter parses CoachSendResult.nutrition (UI cards in 11G)
```

Laravel owns authoritative food rows and all numerical calculations. Training plans are skipped when a nutrition lookup succeeds.

### Food database

| Piece | Location |
|-------|----------|
| Migration | `database/migrations/2026_09_29_110000_create_foods_table.php` |
| Model | `App\Models\Food` (`foods` table) |
| Fixture seeder | `Database\Seeders\NutritionFoodSeeder` (idempotent `updateOrCreate` on `source` + `external_id`) |
| Retrieval | `App\Services\Coach\Nutrition\FoodRetrievalService` |
| Units | `ServingNormalizer` |
| Math | `NutritionCalculator` |
| Query + lookup | `NutritionQueryExtractor`, `NutritionLookupService` |
| Capability | `capabilities.nutrition_engine = true` |

### Provenance / source

- Primary conceptual source: **USDA FoodData Central**
- Phase 11D ships a **small verified fixture set** (`source = usda_fdc_fixture`) with FDC-aligned external IDs and nutrient values per 100 g
- Unique constraint `(source, external_id)` prevents duplicate imports
- Full live FDC API/download ingestion is deferred (env key only if/when added; never in Flutter)

### Match statuses

`exact_match` · `strong_match` · `ambiguous` (`food_match_uncertain`) · `not_found` (`food_not_found`)

Ambiguous results return candidates; the system does **not** silently pick a branded product for a generic query.

### Calculation formula

For nutrients stored per 100 g:

`result = source_per_100g × grams / 100`

Optional Atwater cross-check (`4P+4C+9F`) is informational only; **source calories are retained**.

Piece/count units require `piece_grams` on the food row. Volume (`ml`/`l`) is rejected without density (`volume_without_density_unsupported`). Raw vs cooked / preparation remain distinct rows.

### Contract (`schema_version: 11d.1`)

`NutritionCalculation`: `mode` (`lookup` | `meal`), `ok`, `foods[]`, `totals`, `unresolved[]`, `source_metadata`, `calculation_notes`, `match_status`

Each food entry: `food_id`, `source`, `external_id`, `name`, `brand`, `preparation`, `quantity`, `unit`, `normalized_grams`, macros (+ fiber/sugar/sodium when present, else null).

### Explicitly out of scope for 11D

Personalized calorie targets / meal plans, allergy hard-exclusion engine, preference engine, RAG, Flutter nutrition UI, grocery lists, admin nutrition dashboard.

---

## Phase 11E — Personalized nutrition plans

### Architecture

```
User message
  → NutritionLookupService (11D) — if calorie Q&A, stop here
  → NutritionPreferenceService.applyMessageHints (persist allergies/dislikes)
  → NutritionIntentExtractor (plan / regen / replace / goal)
  → CalorieTargetCalculator (Mifflin–St Jeor + documented adjustments)
  → MacroTargetCalculator (protein g/kg, fat %, carbs remainder)
  → FoodConstraintService (allergy HARD / dislike SOFT)
  → NutritionPlanEngine (templates → Phase 11D foods → scale → totals)
  → NutritionPlanValidator
  → NutritionPlanRepository (versioned nutrition_plans rows)
  → CoachService attaches nutrition_plan (schema 11e.1)
  → FastAPI ChatRequest.nutrition_plan (DATA only)
  → Flutter CoachSendResult.nutritionPlan (UI in 11G)
```

Lookup questions never enter meal-plan generation. Training plans are skipped when a nutrition plan is produced.

### Key files

| Piece | Location |
|-------|----------|
| Config (formulas, tolerances) | `config/nutrition_plan.php` |
| Preferences model/table | `NutritionPreference` / `nutrition_preferences` |
| Plan versions | `NutritionPlan` / `nutrition_plans` (`plan_uuid` + `version`) |
| Intent | `NutritionIntentExtractor` |
| Energy | `CalorieTargetCalculator` |
| Macros | `MacroTargetCalculator` |
| Constraints | `FoodConstraintService` |
| Engine | `NutritionPlanEngine` |
| Validator | `NutritionPlanValidator` |
| Persistence | `NutritionPreferenceService`, `NutritionPlanRepository` |
| Food tags | `foods.allergen_tags` (fixture-seeded) |
| Capability | `nutrition_plan_engine`, `plan_versioning` |

### Calorie formula (documented)

Mifflin–St Jeor BMR × activity multiplier from `config/nutrition_plan.php`, then goal adjustment:

- maintenance: +0%
- muscle_gain (bulking): +10%
- fat_loss (cutting): −15%

Clamped to floor 1400 / ceiling 4500 kcal. Explicit user calorie targets override estimation. Assumptions always returned. **Not medical advice.**

### Macro formula

- protein_g = weight_kg × factor(goal)
- fat_kcal = calorie_target × fat_fraction(goal); fat_g = fat_kcal / 9
- carb_g = remaining kcal / 4

### Allergies vs dislikes

| Constraint | Behavior |
|------------|----------|
| Allergy / intolerance | HARD exclusion — plan invalid if present |
| Dislike / excluded_foods | Soft exclusion — replace with alternate grounded food |
| Dietary pattern | `omnivore` / `vegetarian` supported; unsupported patterns return limitation |

Matching uses `allergen_tags` + name/description keywords. **Incomplete allergen DB** and **no cross-contamination guarantee** are documented limitations on every plan.

### Contract (`schema_version: 11e.1`)

`goal`, `calorie_target`, `macro_targets`, `meals_per_day`, `duration_days`, `days[]` → `meals[]` → grounded `foods[]` with 11D quantities/macros, `daily_totals` / `meal_totals`, `assumptions`, `preferences_applied`, `exclusions_applied`, `limitations`, `validation_status`, `plan_id`, `version`.

### Regeneration

Preference updates (“allergic to…”, “don’t like…”, “replace X”) merge into `nutrition_preferences` and create a **new plan version** preserving goal/calories/meals when possible.

### Explicitly out of scope for 11E

RAG/embeddings (11F), Flutter meal cards / nutrition dashboard (11G), grocery cost optimization, full clinical allergy database, admin nutrition UI.

---

## Phase 11F — RAG + evidence knowledge engine

### Architecture

```
User message
  → deterministic engines first (11B–11E) as before
  → KnowledgeIntentDetector (shouldRetrieve?)
  → KnowledgeRetrievalService (lexical + topic + evidence-weight)
  → knowledge_context schema 11f.1
  → FastAPI ChatRequest.knowledge_context (DATA)
  → OpenAI/mock synthesize explanation WITHOUT inventing citations
  → Laravel may append Retrieved sources footer + deterministic targets
  → Flutter parses knowledgeContext (citation UI in 11G)
```

RAG never replaces: exercise IDs, food values, calorie/macro engines, allergy HARD exclusions, training/nutrition plan validators.

### Database

| Table | Role |
|-------|------|
| `knowledge_sources` | Real org/name/URL/evidence_level/reliability |
| `knowledge_documents` | Topic + language + content_hash + identifier |
| `knowledge_chunks` | Ordered chunks + content_hash + token_estimate |

Seeder: `KnowledgeSeeder` (idempotent via `KnowledgeIngestionService` + `KnowledgeChunker`).

### Initial real sources (small corpus)

Examples include ISSN protein & nutrient-timing position stands, ACSM quantity/quality of exercise, SCJ loading recommendations, WHO healthy diet, CDC hydration guidance, sleep/recovery educational synthesis, NSCA-aligned combat S&C themes, home/calisthenics principles. Missing URLs remain `null` (never invented). Bodies are educational paraphrases, not copyrighted full-text dumps.

### Retrieval

- Backend: `lexical_topic_v1` (vector-ready abstraction; no required external embedding API)
- Active-only; result limit; deterministic score ordering; duplicate content_hash skipped
- Evidence-level weighting; light personalization boost that does not override evidence quality
- Empty match → limitations, no fabricated citations

### Contract `schema_version: 11f.1`

`query`, `topics`, `item_count`, `items[]` (chunk_id, source, organization, url, publication_year, evidence_level, content, relevance_score, …), `limitations`, `retrieval_backend`, optional `personalized_targets` (11E engines).

### Admin

Read-only: `GET /api/admin/knowledge/sources|documents|chunks`

### Explicitly out of scope for 11F

Flutter citation UI (11G), live web browsing, medical diagnosis, fake papers, mandatory cloud vector DB.

---

## Phase 11G — Meal cards + full AI Coach UI

### Goal

Render Phase 11B–11F structured contracts in Flutter Coach chat without changing backend engines.

### Status: COMPLETE

### Architecture

```
CoachSendResult
  → CoachChatCubit stores CoachMessageExtras by assistant message id
  → CoachChatScreen message bubble + structured cards
  → Open Exercise → /training/exercises/{id}
  → Regenerate / Replace → conversational send prompts (Laravel authoritative)
  → Evidence section → expandable sources (url_launcher when URL present)
```

### UI components

| Widget | Role |
|--------|------|
| `CoachTrainingPlanCard` | 11C sessions + exercise tiles + Open Exercise |
| `CoachNutritionPlanCard` / `CoachMealCard` / `CoachFoodRow` | 11E days/meals/foods + macro metrics |
| `CoachNutritionLookupCard` | 11D lookup totals |
| `CoachEvidenceSection` | 11F sources (no fake URLs / no chunk IDs) |
| `CoachGroundedExercisesStrip` | 11B related exercises |

Historical messages remain text-only (API does not persist structured extras on reload). New turns in-session show cards.

### Validation

- `flutter test test/coach_cubit_test.dart` — pass
- `flutter analyze --no-fatal-infos` — pass (infos only, pre-existing)
- EN/AR coach_* keys — 72/72 parity
- Backend contracts unchanged (Flutter-only presentation)

### Explicitly out of scope for 11G

Phase 11H advanced memory/eval, redesigning non-Coach screens, inventing offline demo plans.

---

## Phase 11H — Coach memory + long-term personalization

### Status: COMPLETE

### Goal

Controlled, privacy-conscious structured memory for Coach personalization. Memory is **not** conversation history.

### Authority hierarchy

1. Safety constraints  
2. Explicit current user request  
3. Deterministic profile / database state  
4. Active nutrition preferences (11E — allergies authoritative)  
5. Confirmed long-term memory (`coach_memories`)  
6. Historical conversation context  
7. Inferred preferences (rarely used; not confirmed by default)

### Data model

Table `coach_memories` (unique `user_id + category + key`):

- categories: `profile_preference`, `training_preference`, `nutrition_preference`, `equipment`, `goal`, `schedule`, `food_preference`, `coaching_preference`
- `source`: `explicit` | `inferred`
- `explicit`, `active`, `confidence`, `expires_at`, `metadata`
- limits: 40 active total, 8 per category, 12 context items

### Services

| Class | Role |
|-------|------|
| `CoachMemoryService` | CRUD, limits, expiry, `buildContext` → schema **11h.1** |
| `CoachMemoryExtractor` | Conservative extraction from **user** messages only |
| `UserAiContextBuilder` | Injects `memory_context` + `capabilities.coach_memory` |
| `TrainingIntentExtractor` | Uses memory as supporting hints; current request / profile win |

### Extraction rules

Creates memory for: durable food dislikes (via 11E), equipment, schedule, training frequency/duration, goals, coaching style.  
Does **not** create memory for: questions, uncertain language (`might`/`maybe`), temporary replacements (`Replace tuna in this meal`), hypotheticals.

Allergies / dislikes: written through `NutritionPreferenceService` (11E). Memory may hold soft references only.

### Context contract `11h.1`

```json
{
  "schema_version": "11h.1",
  "memories": [
    {
      "category": "equipment",
      "key": "available_equipment",
      "value": {"items": ["dumbbells"], "mode": "dumbbell"},
      "source": "explicit",
      "confidence": 1.0,
      "last_confirmed_at": "...",
      "active": true
    }
  ],
  "count": 1,
  "authority_note": "supporting_context_current_request_and_profile_win"
}
```

Relevance filtering: nutrition queries get food/nutrition memories; training queries get equipment/schedule/training; general gets high-value stables only.

### API (Sanctum + `feature:coach`)

| Method | Path |
|--------|------|
| GET | `/api/coach/memories` |
| POST | `/api/coach/memories/{memory}/deactivate` |
| DELETE | `/api/coach/memories/{memory}` |
| DELETE | `/api/coach/memories` (clear all) |

Flutter never writes arbitrary memory rows — only list/deactivate/clear. Extraction runs server-side on `sendMessage`.

### FastAPI / providers

- `CoachMemoryContext` / `CoachMemoryItem` in `user_context.py`
- OpenAI: memory is supporting only; priority order in system prompt
- Mock: test-only `coach_memory=<keys>` echo

### Flutter UI

Settings → **Coach memory** (`/settings/coach-memory`): privacy blurb, categorized list, remove, clear all. EN/AR localized. OCTA FIT untranslated.

### Validation

- `CoachMemoryPhase11hTest` — 14 passed
- FastAPI coach tests — 20 passed
- Flutter `coach_memory_test` + `coach_cubit_test` — passed
- `flutter analyze --no-fatal-infos` — infos only

### Explicitly out of scope for 11H

Phase 11I end-to-end QA, vector/semantic memory, unrestricted conversation ingestion, medical/political/religious profiling, autonomous agent memory.

---

## Phase 11I — Evaluation, reliability & production hardening

### Status: COMPLETE

### Goal

Prove the 11A–11H Coach pipeline is reliable, safe, grounded, and production-hardened. **No new major product features.**

### Pipeline ownership of truth

```
Flutter Cubit → Repository → Datasource
  → Laravel CoachController (auth, throttle, safe 502)
  → CoachService
      → CoachMemoryExtractor (11H)
      → UserAiContextBuilder (11A + memory_context 11h.1)
      → NutritionLookup (11D) | NutritionPlanEngine (11E) | TrainingPlanEngine (11C)
      → Exercise retrieval/validate (11B)
      → KnowledgeRetrieval (11F)
      → FastAPI /chat (+ optional X-Internal-Token)
      → Provider (mock|openai) — interpretive text only
      → Laravel re-validates exercise IDs; engines own numbers/IDs
  → Flutter structured cards (11G)
```

### Authority hierarchy (verified)

1. Safety constraints  
2. Explicit current user request  
3. Profile / database state  
4. 11E nutrition preferences / allergies (HARD)  
5. Confirmed Coach memory  
6. RAG knowledge (explanatory only)  
7. Chat history  
8. LLM interpretation  

RAG must not override calories/macros. Memory must not override current request. LLM must not invent exercise/food IDs or clear allergies.

### Hardening delivered in 11I

| Area | Change |
|------|--------|
| Upstream failures | Catch `ConnectionException`; non-JSON / empty message → controlled 502 |
| Client errors | Controller never returns provider internals / secrets |
| Logging | Laravel `coach.upstream_*` + FastAPI `chat_ok` / `provider_failed` (IDs, latency; no tokens/content) |
| FastAPI auth | Enforce `X-Internal-Token` when `INTERNAL_TOKEN` is set |
| Equipment intent | Explicit `gym` in message wins over home discipline/profile/memory |
| Flutter parsing | Accept any `Map` for structured payloads; malformed tests |

### Rate limiting

`throttle:coach-messages` — **20 requests/minute** per authenticated user id (fallback IP). Documented and covered by PHPUnit.

### Evaluation suite

`backend/tests/Feature/CoachPhase11iEvaluationTest.php` covers:

- Provider connection / non-JSON / empty / HTTP 503 → 502  
- Auth + cross-user isolation  
- Rate limit 429  
- Allergy adversarial (“ignore my allergy”)  
- Prompt injection (no fake exercise IDs)  
- Arabic/mixed input  
- Memory conflict + temporary replace  
- Training days 2–6 grounded  
- Nutrition plan macros + real foods  
- Context contracts 11a.1 / 11h.1  

Also: `ai/coach/tests/test_security.py`, Flutter malformed structured parsing + widget mounts.

### Known limitations (IMPLEMENTED vs LIMITATION)

| Item | Status |
|------|--------|
| Structured cards in-session only (not reloaded from history) | LIMITATION (documented 11G) |
| Orphan user message if FastAPI fails after persist | LIMITATION (retry-safe; no secret leak) |
| Incomplete clinical allergen database | LIMITATION (tags + keywords) |
| Rule-based memory extraction (EN-heavy) | LIMITATION |
| Interactive Windows visual QA not fully automated | LIMITATION (widget + build coverage) |

### Future work (not Phase 11)

Phase 12+ product features, vector memory, voice, autonomous agents — out of scope for Coach 11A–11I.

### Validation results

- Laravel Phase11/Coach filter: **120 passed**
- FastAPI coach tests: **24 passed**
- Flutter coach + memory tests: **passed**
- `flutter analyze --no-fatal-infos`: infos only (pre-existing)
- `flutter build windows --debug`: success
- `flutter build windows --release`: success
