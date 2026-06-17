# Client Goals + Progress Prediction — Build Spec (Path A, No-AI)

> **Status: PLANNED — not yet implemented.** This is a design/build spec for a future feature. Nothing here is in the codebase yet. Revisit before building; verify table/model paths still match current conventions.

**Version:** 1.0  ·  **Date:** 2026-06-17
**Scope:** Backend (`gym-management-api`, Laravel 12) + Frontend (`gym-management-system`, React 19)
**Approach:** Deterministic prediction (math on existing progress data) + rules-based recommendation matching from the gym's real offerings. No LLM.

---

## 1. What we're building

Three connected capabilities:

1. **Goals** — a client sets a measurable body goal (e.g. "shrink waist to 80cm", "raise muscle mass", "build abs").
2. **Goal linkage on progress** — every new progress record automatically updates how close the client is to their goal.
3. **Prediction + recommendations** — from the client's trend and the gym's actual classes / PT packages / equipment, the system computes whether they're on track and what to do next.

The "AI/prediction" is **real math on the client's own measurements** plus **rules-based matching** — predictable, free, no external dependency, and built entirely with the patterns already in the codebase. An LLM layer can be added later without changing the rest of the system (recommendations are stored, so the source can be swapped).

---

## 2. How it connects to what already exists

`tb_customer_progress` already stores rich measurements per record: `weight`, `height`, `body_fat_percentage`, `bmi`, `chest`, `waist`, `hips`, `left_arm`, `right_arm`, `left_thigh`, `right_thigh`, `left_calf`, `right_calf`, `skeletal_muscle_mass`, `body_fat_mass`, `visceral_fat_level`, `basal_metabolic_rate`, plus `recorded_date`.

A **goal targets one of these columns** as its measurable anchor (the `target_metric`). Each progress entry reads that column to update goal progress. No new measurement fields are required — we reuse what's there.

Offerings we match against, already in the codebase:
- `PtPackage` (has `category_id`, `package_name`, `description`, `features` array) + `PtCategory`
- `ClassSchedule` (has `class_name`, `class_type`, `description`)
- Equipment — **new** lightweight table (the gym has none today)

---

## 3. Data model

### 3.1 New table — `tb_customer_goals`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `account_id` | unsignedBigInteger | follows existing pattern |
| `customer_id` | FK → `tb_customers` | cascade on delete |
| `created_by` | unsignedBigInteger nullable | user who set it |
| `goal_type` | enum | `muscle_gain`, `fat_loss`, `body_recomposition`, `endurance`, `strength`, `general_fitness` |
| `target_area` | enum | `abs`, `arms`, `chest`, `back`, `legs`, `glutes`, `full_body` |
| `target_metric` | string | column name in `tb_customer_progress` used to measure (e.g. `waist`, `body_fat_percentage`, `skeletal_muscle_mass`) |
| `direction` | enum | `decrease` or `increase` — whether success means the metric goes down or up |
| `starting_value` | decimal(8,2) nullable | metric value when goal was set |
| `target_value` | decimal(8,2) | the number they want to reach |
| `target_date` | date nullable | deadline |
| `status` | enum | `active`, `achieved`, `paused`, `abandoned` |
| `notes` | text nullable | |
| timestamps + softDeletes | | matches `CustomerProgress` |

Index: `['customer_id', 'status']`.

### 3.2 New table — `tb_goal_progress` (link table)

Connects each progress record to the goal it advanced, with a snapshot.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `goal_id` | FK → `tb_customer_goals` | cascade |
| `customer_progress_id` | FK → `tb_customer_progress` | cascade |
| `value_at_record` | decimal(8,2) | the `target_metric` value pulled from that progress record |
| `percent_to_goal` | decimal(5,2) | 0–100, computed (see §5.1) |
| `recorded_date` | date | copied from progress for easy charting |
| timestamps | | |

Index: `['goal_id', 'recorded_date']`.

### 3.3 New table — `tb_goal_recommendations`

Stores computed recommendation output so it isn't recomputed on every page load.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `goal_id` | FK → `tb_customer_goals` | cascade |
| `customer_progress_id` | FK → `tb_customer_progress` nullable | which entry triggered it |
| `summary` | text | short human-readable status line |
| `on_track` | boolean | from prediction |
| `projected_completion_date` | date nullable | from trend extrapolation |
| `recommendation_json` | json | structured: exercises, equipment, classes, pt_packages (see §6.3) |
| `source` | string | `rules` now; `llm` later — lets us swap engines |
| `generated_at` | datetime | |
| timestamps | | |

### 3.4 New table — `tb_gym_equipment` (lightweight catalog)

The gym fills this once so recommendations reference real equipment.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `account_id` | unsignedBigInteger | account-scoped |
| `name` | string | e.g. "Cable machine", "Ab roller" |
| `target_areas` | json | array of areas it trains, e.g. `["abs","back"]` |
| `is_active` | boolean default true | |
| timestamps + softDeletes | | |

### 3.5 Tagging existing offerings (additive migrations)

Add a nullable `target_areas` JSON column to:
- `tb_pt_packages` (or reuse the existing `features` array — but a dedicated `target_areas` column is cleaner for matching)
- `tb_class_schedules`

Each holds an array like `["abs","full_body"]`. Backfilled by the gym via the UI. Matching ignores untagged rows gracefully.

---

## 4. Constants

New file `app/Constant/CustomerGoalConstant.php` — every enum value above as a constant. No raw strings anywhere in services/repos.

```
GOAL_TYPE_MUSCLE_GAIN, GOAL_TYPE_FAT_LOSS, GOAL_TYPE_BODY_RECOMPOSITION,
GOAL_TYPE_ENDURANCE, GOAL_TYPE_STRENGTH, GOAL_TYPE_GENERAL_FITNESS

TARGET_AREA_ABS, TARGET_AREA_ARMS, TARGET_AREA_CHEST, TARGET_AREA_BACK,
TARGET_AREA_LEGS, TARGET_AREA_GLUTES, TARGET_AREA_FULL_BODY

DIRECTION_INCREASE, DIRECTION_DECREASE

STATUS_ACTIVE, STATUS_ACHIEVED, STATUS_PAUSED, STATUS_ABANDONED
```

Also add permission strings: `goals_view`, `goals_create`, `goals_update`, `goals_delete`.

---

## 5. The prediction math (deterministic, no AI)

All of this lives in a service and is pure arithmetic on `tb_goal_progress` rows.

### 5.1 Percent-to-goal (per record)

```
range      = target_value - starting_value          (signed)
progressed = value_at_record - starting_value        (signed)
percent    = clamp( (progressed / range) * 100, 0, 100 )
```

Works for both directions because `range` carries the sign. If `range == 0`, treat as already achieved (100).

### 5.2 Rate of change (trend)

Use the last N (default 3–5) `tb_goal_progress` rows for the goal, ordered by `recorded_date`:

```
rate_per_day = (latest_value - oldest_value) / days_between(oldest, latest)
```

Use a simple **least-squares slope** over the points if you want it smoother (recommended — robust to a single noisy reading). Slope of value vs. day index.

### 5.3 On-track / projected completion

```
remaining        = target_value - latest_value          (signed)
days_to_target   = remaining / rate_per_day              (if rate moves toward target)
projected_date   = today + days_to_target

on_track = projected_date <= target_date   (when a deadline exists)
```

Edge cases to handle explicitly:
- **rate is zero or moving away from target** → `on_track = false`, `projected_date = null`, status = "plateaued" / "regressing".
- **fewer than 2 progress points** → not enough data; return "need more data", no projection.
- **already at/past target** → flip goal `status` to `achieved`.

### 5.4 Trend state label

Derive a simple label for the UI from slope + direction:
`on_track`, `ahead`, `behind`, `plateaued`, `regressing`, `achieved`, `insufficient_data`.

---

## 6. Backend implementation (Controller → Service → Repository)

### 6.1 Files to create (follows the existing checklist)

**Migrations** — the 5 tables / column adds in §3.

**Models** (`app/Models/Core/`):
- `CustomerGoal` (+ relations: `customer`, `goalProgress`, `recommendations`, `recordedByUser`)
- `GoalProgress`
- `GoalRecommendation`
- `GymEquipment` (`app/Models/Account/` — it's gym config, like PT/classes)

Use `HasCamelCaseAttributes` + `SoftDeletes` like `CustomerProgress`.

**Resources** (`app/Http/Resources/Core/`): one per model.

**Requests** (`app/Http/Requests/Core/`): `CustomerGoalRequest` (validates metric is a real progress column, target_value numeric, direction/area enums via constants).

**Repositories** (`app/Repositories/Core/`, extend `BaseRepository`):
- `CustomerGoalRepository` — goal CRUD + active-goal lookups for a customer.
- `GoalProgressRepository` — write snapshots, fetch last-N for trend.
- `GoalRecommendationRepository` — store/fetch latest recommendation.
- `GymEquipmentRepository` (`app/Repositories/Account/`).

**Services** (`app/Services/Core/`):
- `CustomerGoalService` — goal CRUD orchestration.
- `GoalPredictionService` — all the math in §5. Returns a typed `GoalAnalysis`.
- `GoalRecommendationService` — rules-based matching in §6.3. Returns typed `GoalRecommendationResult`.

**Typed data objects** (`app/Data/`):
- `GoalAnalysis` — `goal`, `latestValue`, `percentToGoal`, `trendState`, `ratePerDay`, `projectedCompletionDate`, `onTrack`, `historyPoints` (collection for charting).
- `GoalRecommendationResult` — `summary`, `exercises` (array), `equipment` (collection), `classes` (collection), `ptPackages` (collection).

Plain classes, no constructor, direct property assignment (per the codebase rule).

**Controllers** (`app/Http/Controllers/Core/`):
- `CustomerGoalController` — thin, `ApiResponse`, sub-resource of customer.

### 6.2 The key wiring — progress save updates the goal

Hook point: `CustomerProgressService::create()` (and `update()`). After the progress row is saved, within the same DB transaction:

1. Fetch the customer's `active` goal(s) via `CustomerGoalRepository`.
2. For each goal, read the goal's `target_metric` column off the new progress record.
3. Compute `percent_to_goal` (§5.1) and write a `tb_goal_progress` snapshot via `GoalProgressRepository`.
4. If target reached → flip goal `status` to `achieved`.
5. **Dispatch an async job** `GenerateGoalRecommendationJob` (database queue) — keeps the save fast.

> Keep this orchestration in the **service**, not the controller (it has conditionals + multiple repo calls + a side effect).

### 6.3 The recommendation engine (rules-based matching)

`GoalRecommendationService` does NOT call any external API. Steps:

1. Run `GoalPredictionService` → get `GoalAnalysis` (trend, on-track, projection).
2. Read the goal's `target_area`.
3. Query offerings whose `target_areas` JSON contains that area, account-scoped:
   - `GymEquipmentRepository->matchingArea($accountId, $area)`
   - `ClassScheduleRepository->matchingArea(...)`
   - `PtPackageRepository->matchingArea(...)` (whereJsonContains)
4. Optionally fall back to `full_body`-tagged items if matches are thin.
5. Build a `summary` string from the trend state via a small template map (e.g. plateaued → "Your {metric} hasn't moved in {n} weeks — consider adding {topClass}.").
6. Exercises: a static seed map of `target_area → exercise list` (config/array, gym-agnostic baseline). This is the only generic content; everything else is the gym's real inventory.
7. Persist the result as `tb_goal_recommendations` (`source = 'rules'`).

Validation guardrail (matters even without AI): only return class/PT IDs that belong to `account_id`.

### 6.4 Routes (`routes/api.php`, `firebase.auth` + `idempotency` on writes)

```
GET    /customers/{id}/goals                 list goals
POST   /customers/{id}/goals                 create
GET    /customers/{id}/goals/{goalId}        show + GoalAnalysis
PUT    /customers/{id}/goals/{goalId}        update
DELETE /customers/{id}/goals/{goalId}        delete
GET    /customers/{id}/goals/{goalId}/analysis        prediction only
GET    /customers/{id}/goals/{goalId}/recommendation  latest recommendation

# Gym config (account scope)
GET/POST/PUT/DELETE /gym-equipment
PUT  /class-schedules/{id}/target-areas
PUT  /pt-packages/{id}/target-areas
```

---

## 7. Frontend implementation (React, feature co-location)

### 7.1 New sub-feature `src/features/customers/goals/`

Mirrors the `progress/` and `scans/` sub-features.

- `GoalsTab.page.jsx` — receives `member` prop. Shows active goal(s): progress bar (start → current → target), trend badge (on-track / behind / plateaued), a small line chart of `historyPoints`, and the recommendation card.
- `GoalForm.jsx` — create/edit goal: `goal_type`, `target_area`, `target_metric` (dropdown of progress columns), `direction`, `target_value`, `target_date`.
- `GoalRecommendationCard.jsx` — summary line, exercise list, and clickable chips for recommended **classes** and **PT packages** that deep-link into existing booking flows.
- `goalTable.config.jsx`, `index.js` barrel.

Register `GoalsTab` in `CustomerProfile.page.jsx` tabs.

### 7.2 Hooks + service (shared)

- `src/shared/services/goalService.js` — CRUD + analysis + recommendation endpoints (backend message first on errors).
- `src/shared/hooks/useGoals.js` — query key factory (`goalKeys`), list/detail/analysis/recommendation queries, create/update/delete mutations.
  - Creates/updates use the **idempotency-key `useRef` pattern**.
  - Deletes use `useMutationWithToast` + `useConfirmAction`.

### 7.3 Tie-in on the Progress tab

In `ProgressForm.jsx`: after a successful save, show toast "Progress saved — updating goal…", then invalidate `goalKeys.detail(goalId)` / recommendation query. Because the recommendation is generated async, refetch once after a short delay (or poll the recommendation query a couple times).

### 7.4 Permissions + nav

- Gate the Goals tab and actions with `hasPermission('goals_view' | 'goals_create' | ...)`.
- Add gym-equipment + tagging screens under the existing account/config area; gate by admin.

---

## 8. Build order (suggested)

1. Migrations + constants + models (goals, goal_progress, recommendations, equipment, target_areas columns).
2. Goal CRUD: repository → service → resource → controller → routes. Ship the UI to set/view a goal.
3. `GoalPredictionService` (math) + `GoalAnalysis` + the analysis endpoint + progress-bar/chart UI.
4. Wire `CustomerProgressService` to write `tb_goal_progress` snapshots on save.
5. Equipment table + tagging UI for classes/PT.
6. `GoalRecommendationService` (rules matching) + job + recommendation card.
7. Permissions, polish, edge cases (insufficient data, plateau, achieved).

---

## 9. Future LLM upgrade path (not now)

Because recommendations are stored in `tb_goal_recommendations` with a `source` column, adding an LLM later means: feed the same `GoalAnalysis` + matched offerings into a model that writes a richer `summary`/`recommendation_json`, set `source = 'llm'`. No frontend or schema change required — it's a drop-in swap inside `GoalRecommendationService`.
