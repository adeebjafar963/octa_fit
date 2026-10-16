# OCTA FIT AI Coach — Master Intelligence Evaluation Bank

## Purpose

This is an **evaluation dataset**, not an answer key and not a hardcoded Q&A map.

It measures:

message → semantic intent → entities/constraints → capability routing → deterministic engines → validated result

## Location

- Corpus: `backend/tests/Fixtures/Coach/master_eval_bank.json`
- Loader: `backend/tests/Support/Coach/CoachEvalBank.php`
- Evaluator: `backend/tests/Support/Coach/CoachEvalCaseEvaluator.php`
- PHPUnit: `backend/tests/Feature/CoachMasterEvaluationBankTest.php`

## Run

```bash
php artisan test --filter=CoachMasterEvaluationBankTest
```

Reports are written to `backend/storage/app/coach_eval_*_report.json`.

## Rules

- Do **not** add `if ($message === '...')` branches for bank phrases.
- Do **not** weaken expectations to match buggy behavior.
- Engines / food DB / exercise DB remain the source of truth for values and IDs.
