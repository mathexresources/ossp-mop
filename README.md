# OSSP MOP — Maintenance Operations Portal

PHP 8.3 / Nette Framework application running in Docker.

## Prerequisites

- Docker + Docker Compose
- `UID` / `GID` environment variables set to your host user (or defaults to 1000:1000)

## Quick Start

```bash
docker-compose up -d
```

Then apply any pending migrations in `docker/migrations/` via Adminer at <http://localhost:8081>.

## Services

| Service  | URL                          | Notes                        |
|----------|------------------------------|------------------------------|
| App      | <http://localhost:8080>      | Main web application         |
| Adminer  | <http://localhost:8081>      | DB admin (user: app / app)   |
| Mailpit  | <http://localhost:8025>      | Local email testing UI       |

## Default Credentials

| Role    | Email                 | Password   |
|---------|-----------------------|------------|
| Admin   | admin@example.com     | Admin123!  |
| Support | support@example.com   | Support1!  |
| Employee| employee@example.com  | Employee1! |

## Email / Mailpit

All outgoing emails in local development are caught by **Mailpit** — nothing is delivered to real addresses.

**Mailpit web UI:** <http://localhost:8025>

### How it works

- SMTP config in `config/local.neon` points to `mailpit:1025`
- Nette's MailExtension creates the SMTP mailer automatically via the `mail:` section
- `MailService` (`app/Model/Mail/MailService.php`) is the single entry point for all email sending
- Every send attempt (success or failure) is logged to the `email_log` table
- Admins can view the email log at `/admin/email-log`

### Email trigger points

| Event                    | Recipient(s)       | Type                |
|--------------------------|--------------------|---------------------|
| User registered          | User + all admins  | welcome / admin_new_pending |
| Account approved         | User               | approved            |
| Account rejected         | User               | rejected            |
| Ticket created           | All support users  | ticket_created      |
| Ticket assigned          | Assigned user      | ticket_assigned     |
| Ticket status changed    | Ticket creator     | ticket_status_changed |
| Damage point added       | Assigned user      | damage_point_added  |
| Service record added     | Ticket creator     | service_history_added |
| Password reset by admin  | User               | password_changed    |

### Adding a new email type

1. Add a method to `MailService`
2. Create a template in `app/Modules/Mail/templates/`
3. Call the method from the appropriate Facade

### Rebuilding after composer changes

The vendor directory lives in a Docker named volume. To install new packages:

```bash
docker exec -u root ossp-mop_app_1 composer require vendor/package
```

Or rebuild the image entirely:

```bash
docker-compose build app
docker-compose down -v   # removes vendor volume
docker-compose up -d
```
