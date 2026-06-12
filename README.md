# ProgLog

Personal strength training tracker — workout splits, session logging, progressive overload stats, strength levels, and body measurements. Single user, self-hosted, everything in Docker.

## Run

```sh
# production: one container on http://localhost:8080
docker compose up --build -d

# development: hot reload, web on http://localhost:4200, api on http://localhost:3000
docker compose -f docker-compose.dev.yml up
```

The SQLite database lives in `./data/proglog.db` — back up by copying that file.

## Tests

```sh
docker compose -f docker-compose.dev.yml run --rm api npm test        # API unit (Jest)
docker compose -f docker-compose.dev.yml run --rm api npm run test:e2e
docker compose -f docker-compose.dev.yml run --rm web npm test        # Angular (Vitest)
```

## Phone

Open the app's URL in Safari (same LAN, or via Tailscale from anywhere) → Share → Add to Home Screen.
