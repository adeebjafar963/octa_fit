# Setup (Windows)

> Phase 1 status: architecture only. Commands below are the **planned** setup once Phase 2+ scaffolding exists. Do not expect running apps yet.

## Prerequisites checklist

| Tool | Required | Notes on this machine |
|------|----------|------------------------|
| Flutter 3.44+ | Yes | Installed |
| PHP **8.2+** | Yes | Currently **8.0.30** — upgrade/portable PHP required |
| Composer | Yes | Install PHAR into `tools/` |
| MariaDB/MySQL | Yes | XAMPP at `D:\php_xampp` — start MySQL |
| Python 3.11 | Yes | Installed |
| Git | Yes | Installed |
| Android SDK / device | For mobile demo | Flutter doctor OK |

---

## Planned one-time setup (future phases)

```powershell
cd E:\OCTA_FIT

# 1) Start MariaDB from XAMPP Control Panel

# 2) Create DB
& "D:\php_xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS octafit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3) Backend
cd backend
# php ../tools/php83/php.exe ../tools/composer.phar install
# copy .env.example .env
# php artisan key:generate
# php artisan migrate --seed

# 4) AI venvs
# scripts\setup_ai.ps1

# 5) Flutter
cd ..\mobile
flutter pub get
```

---

## Planned run (future)

| Service | Command | URL |
|---------|---------|-----|
| Laravel | `php artisan serve` | http://127.0.0.1:8000 |
| Coach | `uvicorn app.main:app --port 8001` | http://127.0.0.1:8001 |
| Physio | `uvicorn app.main:app --port 8002` | http://127.0.0.1:8002 |
| Motion | `uvicorn app.main:app --port 8003` | http://127.0.0.1:8003 |
| Flutter | `flutter run` | device / emulator |

Android emulator API host: `http://10.0.2.2:8000`

---

## Demo accounts (to be seeded later)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@octafit.test | (set in seeder; change for any shared machine) |
| User | demo@octafit.test | (set in seeder) |

Document actual seeded passwords in this file **after** seeders exist.

---

## Troubleshooting (expected later)

- **Composer not found** → `php E:\OCTA_FIT\tools\composer.phar`
- **PHP too old** → use `tools\php83\php.exe`
- **DB connection refused** → start XAMPP MySQL
- **AI 503** → start corresponding uvicorn service
