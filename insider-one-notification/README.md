# Insider One — Event-Driven Notification System

Laravel 11/12, PHP 8.3, MySQL 8.4 and Redis 7. This project implements an asynchronous notification API for SMS, Email and Push channels. `webhook.site` is used as the external provider simulator.

## Prerequisites

- Docker Engine with the Docker Compose v2 plugin.
- A webhook.site endpoint for the provider integration test.

> Run every Docker command from the directory that contains `docker-compose.yml` (the project root).

## 1. Configure the environment

The repository tracks `.env.example`, not `.env`. For a GitHub clone, create your local environment file:

```bash
cp .env.example .env
```

The `.env.example` includes development-only configuration and a provider simulation URL. Generate your own APP_KEY for any non-demo deployment. This project is an assessment demo, not a hardened public production service.

## 2. Build and start all services

From the project root, run:

```bash
docker compose up --build -d
```

Compose waits for healthy MySQL and Redis before starting Laravel. Laravel runs migrations before serving requests. Worker and scheduler start after the app container has started (without blocking on its temporary first-boot health status); they also depend on healthy MySQL and Redis. This prevents the first-build race where the app is briefly marked unhealthy while MySQL/migrations initialize.

Check the services:

```bash
docker compose ps
```

Expected services: `app`, `worker`, `scheduler`, `mysql`, `redis`. The `app`, `mysql`, and `redis` health/status columns should show healthy/running states after startup. `worker` and `scheduler` should show `Up`. Initial health checks may take a short time while migrations complete.

If the first build fails, inspect the actual build output. If the app exits, use:

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 mysql
docker compose logs --tail=100 worker
```

After changing Dockerfile or Compose configuration, rebuild and recreate:

```bash
docker compose up --build -d --force-recreate
```

## 3. Verify the API

Health endpoint:

```bash
curl -i http://localhost:8000/api/health
```

Expected: HTTP `200 OK` with a JSON response indicating the service is up.

In GitHub Codespaces, open the **Ports** tab. If port `8000` is not listed, add/forward port `8000`; then open its forwarded URL in a browser. For terminal tests inside Codespaces, `http://localhost:8000` is the preferred address.

## 4. Run automated tests

This project uses PHPUnit directly. Run:

```bash
docker compose exec app ./vendor/bin/phpunit
```

The command should exit successfully; inspect the actual test output.

The `composer test` script also invokes PHPUnit directly. `php artisan test` is not used because the optional Laravel Collision package is not installed in this project.

## 5. Send a notification request

Send a request with a unique idempotency key:

```bash
curl -i -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: engin-test-002" \
  -d '{"recipient":"+905551234567","channel":"sms","content":"Insider One webhook integration test","priority":"high"}'
```

The API should return a successful creation response (normally HTTP `201`) with the notification ID and `pending` status. The Redis queue worker then processes the job and posts the provider payload to webhook.site.

Check the configured webhook.site inbox for the outgoing `POST` request. The request body contains `to`, `channel`, `content`, `messageId`, and `correlationId`. The external provider simulator is expected to return HTTP `202` (accepted).

To check the final notification state, copy the notification UUID from the API response and call:

```bash
curl -i http://localhost:8000/api/notifications/NOTIFICATION_UUID
```

After the worker processes the request, the notification should be in `accepted` status. If it remains `pending` or becomes `failed`, inspect:

```bash
docker compose logs --tail=100 worker
docker compose logs --tail=100 app
```

> The `curl` request to the API creates a notification. Running PHPUnit does not send a real webhook request unless a test explicitly exercises the provider; the included tests use a fake queue and are isolated from the external webhook.

## API endpoints

Base URL: `http://localhost:8000/api`

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/notifications` | Create one notification |
| POST | `/notifications/batch` | Create 1–1000 notifications |
| GET | `/notifications/{uuid}` | Get notification status |
| GET | `/batches/{uuid}` | Get batch and its notifications |
| GET | `/notifications` | List/filter notifications |
| POST | `/notifications/{uuid}/cancel` | Cancel a pending notification |
| GET | `/metrics` | Queue depth and delivery status metrics |
| GET | `/health` | Application/database health check |

Single-notification requests accept an `Idempotency-Key` header or `idempotency_key` JSON property. Batch requests use a `notifications` array; individual items can include `idempotency_key`.

## Useful Docker commands

```bash
# Service status
docker compose ps

# Follow application logs
docker compose logs -f app

# Follow queue worker logs
docker compose logs -f worker

# Stop services (keeps database/Redis volumes)
docker compose down

# Stop services and DELETE database/Redis data
docker compose down -v
```

Do not use `docker compose down -v` unless you intentionally want to erase the persisted MySQL and Redis data.

## Architecture and implementation notes

- Notification creation is exposed through a REST API.
- Notifications are queued asynchronously in Redis and processed by a dedicated worker.
- Priority selects the `high`, `normal`, or `low` queue.
- Per-channel rate limiting is coordinated through Redis.
- The webhook provider accepts only HTTP `202` as a successful provider response.
- Delivery attempts, provider response, correlation IDs and status are recorded for querying.
- Scheduled notifications are handled by the scheduler command.
- Email and Push currently use the webhook simulator too; this project does not claim to deliver through real email or push providers.

## Assessment scope and production hardening

This is an assessment implementation, not a production-certified messaging platform. Before production use, add authentication/API keys and tenant isolation, provider adapters per channel, a transactional outbox or after-commit dispatch, robust distributed rate limiting, durable retry scheduling/dead-letter handling, richer metrics and alerting, PII encryption/retention controls, and load/concurrency tests.

## GitHub Codespaces — development setup (project files already in GitHub)

Reviewers cloning this repository **do not need to download or unzip anything**. From the directory containing `docker-compose.yml`:

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
docker compose exec app ./vendor/bin/phpunit
curl -i http://localhost:8000/api/health
```

For the provider integration test, use the notification request in section 5 and check the configured webhook.site inbox. No manual queue-worker startup should be needed: the Compose project includes `app`, `mysql`, `redis`, `worker`, and `scheduler`.

