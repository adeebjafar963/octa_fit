# Machine inspection report (Phase 1)

**Date:** 2026-09-20  
**Host OS:** Windows 11 (build 26200)  
**Project root:** `E:\OCTA_FIT`

---

## Detected versions

| Tool | Status | Version / location |
|------|--------|--------------------|
| Flutter | Available | **3.44.9** stable (`C:\development\flutter`) |
| Dart | Available | **3.12.2** |
| Flutter doctor | Healthy | Android, Chrome, Windows desktop, VS 2022 OK |
| PHP | Available | **8.0.30** (`D:\php_xampp\php\php.exe`) |
| Composer (PATH) | **Missing** | Not on PATH |
| Composer (PHAR) | Available elsewhere | `C:\Users\adybj\Desktop\OCTA_FIT\tools\composer.phar` (v2.10.3) |
| Laravel installer | **Missing** | Needs Composer |
| Python | Available | **3.11.9** |
| pip | Available | **24.0** |
| FastAPI / MediaPipe / OpenCV | **Not installed** (system pip) | Install per-service venvs later |
| Git | Available | **2.51.0.windows.1** |
| Node.js | Available | **v22.22.0** (useful for Laravel Vite assets) |
| npm | Available | **11.17.0** |
| MySQL CLI (PATH) | **Missing** | — |
| MariaDB (XAMPP) | Available | **10.4.32** at `D:\php_xampp\mysql\bin\mysql.exe` |
| MySQL/MariaDB Windows service | Not detected as running | Start via XAMPP Control Panel before Phase 2 DB work |
| E: drive | Available | ~**244 GB** free of ~244 GB volume |

---

## PHP extensions (relevant)

Present: `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd` (via related modules as listed by `php -m`).

---

## Critical blockers before backend scaffolding

1. **Composer not on PATH** — copy/install Composer into `E:\OCTA_FIT\tools\composer.phar` and use `php tools\composer.phar`, or install Composer globally.
2. **PHP 8.0.30** — Laravel 11 requires **PHP 8.2+**; Laravel 10 requires **PHP 8.1+**.  
   **Recommendation:** install portable PHP **8.2+ or 8.3** under `E:\OCTA_FIT\tools\php83` (or upgrade XAMPP) before creating the Laravel app.  
   Do **not** target Laravel 9 solely to stay on PHP 8.0 (EOL).
3. **MariaDB must be started** (XAMPP) before migrations/seeders.
4. **AI Python packages** — create isolated `.venv` per AI service in later phases.

---

## Prior work note (non-canonical)

A partial/prior OCTA FIT tree exists at:

`C:\Users\adybj\Desktop\OCTA_FIT`

It includes README references to Mini RAG, portable PHP 8.3, scripts, and some AI scaffolding. **Canonical root for this engagement is `E:\OCTA_FIT`.** Later phases may selectively reuse ideas or assets from Desktop **only after explicit review** — never blindly duplicate features.

---

## Available vs missing summary

### Available now
- Flutter / Dart / Android toolchain
- Python 3.11 + pip
- Git
- Node/npm
- XAMPP PHP 8.0 + MariaDB binaries
- Disk space on E:

### Missing / action required
- Composer on PATH (or project-local PHAR)
- PHP **≥ 8.2** for modern Laravel
- Laravel project (not created yet)
- Running MariaDB service
- AI service dependencies (venv packages)
- Flutter/Laravel/AI application code (intentionally deferred)
