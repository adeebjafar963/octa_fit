# Setup (Windows) — Phase 10

## Prerequisites

| Tool | Path / notes |
|------|----------------|
| Flutter 3.44+ | `flutter` on PATH |
| PHP **8.3.14** | `E:\OCTA_FIT\tools\php83\php.exe` (do **not** use XAMPP PHP 8.0 for Laravel) |
| Composer | `E:\OCTA_FIT\tools\composer.phar` |
| MariaDB | XAMPP — start **MySQL** from Control Panel |
| Python 3.11+ | `python` on PATH |

```powershell
$php = "E:\OCTA_FIT\tools\php83\php.exe"
```

---

## 1) Database

Prefer a dedicated DB for experiments. Do not wipe a shared production-like DB.

```powershell
# Example: create/reset only a local test DB name you choose
& "D:\php_xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS octafit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd E:\OCTA_FIT\backend
copy .env.example .env   # if needed; never commit real secrets
& $php artisan key:generate
& $php artisan migrate --seed
```

### Demo accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@octafit.test` | `Admin123!` |
| User | `demo@octafit.test` | `Demo123!` |

---

## 2) Laravel API

```powershell
cd E:\OCTA_FIT\backend
& $php artisan serve --host=127.0.0.1 --port=8000
```

- Health: http://127.0.0.1:8000/api/health
- Features (auth): http://127.0.0.1:8000/api/features
- Docs: [API.md](API.md)

```powershell
& $php artisan test
```

### Important env (backend `.env`)

- `APP_URL`, `DB_*`, `SANCTUM_*` / CORS as configured
- AI base URLs for Coach / Physio / Motion (see `config/services.php` or `.env.example`)
- Never put provider API keys in Flutter

---

## 3) Flutter

```powershell
cd E:\OCTA_FIT\mobile
flutter pub get
flutter analyze
flutter test
flutter run -d windows
```

**Windows Desktop** is the recommended local demo target. The Flutter app lives in `mobile/` (not the repo root). First-time Windows setup creates the `mobile/windows/` runner via Flutter tooling.

Android emulator API host (only if using Android — not required for Windows):

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

On Windows desktop the default API base is already `http://127.0.0.1:8000`.

Tokens: `flutter_secure_storage`, restored on splash via `/api/me`.

Feature flags: loaded after login from `/api/features`; Home hides disabled AI modules.

---

## 4) AI services (mock by default)

| Service | Port | Folder |
|---------|------|--------|
| Coach | 8001 | `ai/coach` |
| Physiotherapist | 8002 | `ai/physiotherapist` |
| Motion Analyzer | 8003 | `ai/motion_analyzer` |

See each service README. Mock providers are the graduation default.

```powershell
# Typical pattern per service:
cd E:\OCTA_FIT\ai\coach
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8001
# pytest
```

---

## 5) Build

```powershell
cd E:\OCTA_FIT\mobile
flutter build apk --debug   # if Android SDK available
# or
flutter build windows       # if Windows desktop enabled
```

Document environment limitations if a target toolchain is missing.

---

## Modules checklist

Auth · Profile · Home · Training · Store · Community · Coach · Physio · Motion · Admin · Settings · Localization (en/ar) · Feature toggles
