# GitHub Codespaces — setup

## For reviewers (files already committed to GitHub)

Open a Codespace on the repository. In the directory containing `docker-compose.yml`, run:

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
docker compose exec app ./vendor/bin/phpunit
curl -i http://localhost:8000/api/health
```

The five services are `app`, `mysql`, `redis`, `worker`, `scheduler`. To test delivery, use the POST example in `README.md` and check webhook.site. Reviewers do not need a ZIP or an `unzip` command.

## For the maintainer only (importing the delivery ZIP)

Upload `insider-one-notification-delivery.zip` into the new Codespace repository root and run:

```bash
unzip insider-one-notification-delivery.zip
cd insider-one-notification
docker compose up --build -d
docker compose ps
docker compose exec app ./vendor/bin/phpunit
curl -i http://localhost:8000/api/health
```

To publish, commit the extracted source files, not the ZIP or `.env`. Prefer putting the project contents at repository root for the simplest reviewer setup.
