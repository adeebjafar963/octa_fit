# OCTA FIT AI Coach

```powershell
cd E:\OCTA_FIT\ai\coach
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
$env:COACH_PROVIDER="mock"
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

## Endpoints

- `GET /health`
- `POST /chat`

## Provider

```env
COACH_PROVIDER=mock
COACH_API_KEY=
COACH_MODEL=gpt-4o-mini
```

`mock` works without any external API key (graduation demo mode).

Optional: `COACH_PROVIDER=openai` with `COACH_API_KEY` set.
