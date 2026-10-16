# OCTA FIT Motion Analyzer (Phase 8)

Educational computer-vision fitness feature — **not medical-grade**.

```powershell
cd E:\OCTA_FIT\ai\motion_analyzer
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
$env:MOTION_PROVIDER="mock"
uvicorn app.main:app --host 127.0.0.1 --port 8003
```

- Health: http://127.0.0.1:8003/health
- Analyze: `POST /analyze` (multipart: `exercise`, `video`)

```env
MOTION_PROVIDER=mock
MAX_UPLOAD_MB=20
```

Supported exercises: `squat`, `push_up`, `biceps_curl`, `shoulder_press`, `lunge`

For real pose processing (optional):

```powershell
pip install opencv-python-headless mediapipe
$env:MOTION_PROVIDER="mediapipe"
```

If MediaPipe/OpenCV are unavailable, the mediapipe provider falls back safely.

Tests:

```powershell
pytest -q
```
