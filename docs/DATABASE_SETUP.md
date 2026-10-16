# Database setup (Phase 1)

## Engine

- **MariaDB 10.4** (XAMPP) or MySQL 8+
- Database name: `octafit`
- Charset: `utf8mb4` / `utf8mb4_unicode_ci`
- User (local demo): `root` with empty password (XAMPP default)

## Create database

```powershell
& "D:\php_xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS octafit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Laravel `.env` (backend)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=octafit
DB_USERNAME=root
DB_PASSWORD=
```

## Migrate & seed

```powershell
cd E:\OCTA_FIT\backend
E:\OCTA_FIT\tools\php83\php.exe artisan migrate --seed
```

## Tables created in Phase 1

| Table | Purpose |
|-------|---------|
| `users` | Auth identity (+ `role_id`, `is_active`) |
| `roles` | `admin`, `user` |
| `profiles` | Optional fitness/locale profile |
| `settings` | Key/value app settings |
| `personal_access_tokens` | Sanctum |
| `password_reset_tokens` | Laravel default |
| `sessions` / `cache` / `jobs` | Laravel infrastructure |

## Verify connection

```powershell
E:\OCTA_FIT\tools\php83\php.exe artisan db:show
& "D:\php_xampp\mysql\bin\mysql.exe" -u root octafit -e "SHOW TABLES;"
```

## Reset (dev only)

```powershell
E:\OCTA_FIT\tools\php83\php.exe artisan migrate:fresh --seed
```

**Do not** run `migrate:fresh` on any shared/production data.
