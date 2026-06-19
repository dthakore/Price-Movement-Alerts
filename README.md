# Price Movement Alerts

Standalone Laravel 9 app for monitoring Binance Futures price movements. Extracted from [CryptoPulse](../CryptoPulse) — includes alert configuration, notifications (email, Slack, FCM), funnel follow-up pipeline, and user authentication.

## Requirements

- PHP 8.3+ (MAMP recommended)
- MySQL 8 (MAMP port **8889**)
- Composer
- Node.js & npm (for frontend assets)

## Quick start

```bash
cd /Users/apple/Documents/public_html/PriceMovementAlerts

composer install
npm install && npm run build

cp .env.example .env   # if .env does not exist
php artisan key:generate

php artisan serve --port=8001
```

Open **http://127.0.0.1:8001/login** and sign in with an existing user from the database.

## Environment

Key variables in `.env`:

| Variable | Description |
|----------|-------------|
| `DB_DATABASE` | `price_movement_alerts` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `8889` (MAMP default) |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / `root` |
| `MAIL_*` | SMTP settings for alert emails |
| `FUTURE_ALERT_WEBHOOK` | Slack webhook URL for alert notifications |
| `FIREBASE_CREDENTIALS` | Path to Firebase JSON for FCM push (`storage/app/firebase-credentials.json`) |

## Database

**Database name:** `price_movement_alerts`

**Tables:**

- `users`, `personal_access_tokens` — auth
- `alert_configurations`, `alert_logs`, `alert_funnel` — core alerts
- `alert_configuration_users`, `alert_user_notification_logs` — subscriptions & email tracking
- `symbols`, `exchanges` — symbol list for auto-source alerts
- `cron_job_logs` — scheduler run history
- `user_fcm_tokens` — mobile push tokens

A full data dump from CryptoPulse is saved at `database/seed_data.sql`.

**Import manually (if needed):**

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h 127.0.0.1 -P 8889 -u root -proot \
  -e "CREATE DATABASE IF NOT EXISTS price_movement_alerts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h 127.0.0.1 -P 8889 -u root -proot price_movement_alerts \
  < database/seed_data.sql
```

## Scheduled commands

Run the scheduler locally:

```bash
php artisan schedule:work
```

Or add to system cron (every minute):

```cron
* * * * * cd /path/to/PriceMovementAlerts && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Schedule | Description |
|---------|----------|-------------|
| `alerts:check` | Every 5 min | Scans configured symbols for price movement |
| `funnel_alert:check` | Every 2 min | Follow-up checks on funnel symbols |

Run manually:

```bash
php artisan alerts:check
php artisan funnel_alert:check
```

## Queue workers (production)

For local dev, `QUEUE_CONNECTION=sync` is fine. In production, use Redis:

```env
QUEUE_CONNECTION=redis
```

Run workers for the alert queues:

```bash
php artisan queue:work --queue=price_alert
php artisan queue:work --queue=funnel_alert
```

## Web routes

| URL | Description |
|-----|-------------|
| `/login` | Sign in |
| `/logout` | Sign out |
| `/alerts/config` | Alert rule configuration |
| `/alerts/config/{id}/notifications` | Notification history |
| `/cron-logs` | Scheduler monitor (admin only) |

## API (Sanctum)

Authenticate with `POST /api/login` to receive a bearer token.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Get API token |
| POST | `/api/logout` | Revoke token |
| GET | `/api/alerts` | List alert rules |
| GET | `/api/alerts/{id}/notifications` | Notification history |
| GET | `/api/notifications/recent` | Recent notifications |
| POST | `/api/fcm-token/register` | Register FCM push token |
| DELETE | `/api/fcm-token/unregister` | Remove FCM token |

## Frontend development

```bash
npm run dev     # hot reload
npm run build   # production build (required before first run)
```

## Project structure

```
app/
  Console/Commands/     CheckPriceAlerts, CheckFunnelAlerts
  Http/Controllers/     AlertConfigurationController, Api/*
  Jobs/                 ProcessAlertConfigurationJob, FunnelAlertConfigurationJob
  Models/               AlertConfiguration, AlertLog, AlertFunnel, User, ...
  Notifications/        CoinPerformanceAlert (email)
  Services/             FcmNotificationService, GeneralService (Slack)
resources/views/alerts/   Config & notification UI
routes/web.php          Web + auth routes
routes/api.php          Mobile API routes
```

## Logs

| File | Content |
|------|---------|
| `storage/logs/laravel.log` | General app errors |
| `storage/logs/price_alert.log` | Price alert job output |
| `storage/logs/funnel_alert.log` | Funnel alert job output |

## Not included

This extract does **not** include:

- Bot trading / order management
- Auto-bot-on-alert
- Volume spike alerts
- V2 alerts (Go / PostgreSQL)
- WebSockets / real-time bot updates
- CoinDCX integration

## Parent project

Extracted from **CryptoPulse** at `/Users/apple/Documents/public_html/CryptoPulse`.
