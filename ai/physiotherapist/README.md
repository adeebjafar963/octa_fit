# OCTA FIT AI Physiotherapist (Phase 7)

Educational preliminary assessment — **not** a clinical diagnosis system.

```powershell
cd E:\OCTA_FIT\ai\physiotherapist
.\.venv\Scripts\Activate.ps1
$env:PHYSIO_PROVIDER="mock"
uvicorn app.main:app --host 127.0.0.1 --port 8002
```

- Health: http://127.0.0.1:8002/health
- Assess: `POST /assess`
- Follow-up: `POST /chat`

```env
PHYSIO_PROVIDER=mock
PHYSIO_API_KEY=
PHYSIO_MODEL=gpt-4o-mini
```

Tests:

```powershell
cd E:\OCTA_FIT\ai\physiotherapist
.\.venv\Scripts\Activate.ps1
pytest -q
```
