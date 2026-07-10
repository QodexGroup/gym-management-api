# Local Development on Docker — Migration Plan (XAMPP → Docker)

This document is the implementation plan and the runbook for replacing the XAMPP
local-dev environment for the **GymHubPH API** with Docker, using the **same
production `Dockerfile`** the server/Railway uses, plus a local MySQL 8 container.
It also covers importing your existing local data (the `triforceph` database).

---

## 1. Why and what changes

Today, local dev runs on **XAMPP** (Apache + PHP + MySQL) while the server runs
the containerized image built from `Dockerfile`. That mismatch means "works on my
machine" bugs: different PHP versions, extensions, php.ini, and MySQL settings.

After this change, local dev runs the **identical application image** as the
server. The only extra piece is a MySQL 8 container to replace XAMPP's MySQL.

**What we add (all local-only, the server is untouched):**

| File | Purpose |
|---|---|
| `docker-compose.yml` | Orchestrates two services: `app` (built from the prod `Dockerfile`) and `mysql` (MySQL 8). |
| `.env.docker` / `.env.docker.example` | Runtime env for the app container (DB points at the `mysql` service). |
| `.docker/mysql/init/` | Drop-in folder; MySQL auto-imports `*.sql` here on first boot. |
| `scripts/export-xampp-db.bat` | Dumps the XAMPP `triforceph` DB into the init folder. |

**What we do NOT change:** `Dockerfile`, `.docker/startup.sh`, `railway.toml`,
Nginx/PHP-FPM configs, or any application code. Railway keeps deploying exactly as
before — it builds `Dockerfile` and ignores `docker-compose.yml`.

---

## 2. How it works (architecture)

```
                 docker compose up
                        │
        ┌───────────────┴────────────────┐
        ▼                                 ▼
  ┌───────────┐                    ┌──────────────┐
  │   app     │  DB_HOST=mysql     │    mysql     │
  │ (prod img)│ ─────────────────► │  MySQL 8     │
  │ nginx +   │   port 3306        │  triforceph  │
  │ php-fpm + │                    │  (auto-import │
  │ queue +   │                    │   on 1st boot)│
  │ scheduler │                    └──────────────┘
  └─────┬─────┘                            │
        │ :8080                     volume: gymhub_mysql_data
        ▼
   http://localhost:8000
```

- The **app** image is built from `Dockerfile` unchanged. Its `startup.sh`
  clears/caches config, starts PHP-FPM + Nginx on **8080**, then in the background
  waits for the DB, runs `php artisan migrate --force`, and starts the queue
  worker and scheduler. All of that happens automatically inside the container.
- The **mysql** service replaces XAMPP MySQL. On the very first startup (empty
  data volume) it executes every `*.sql` in `.docker/mysql/init/`, which is how
  your exported `triforceph` data is loaded.
- Data persists in the named volume `gymhub_mysql_data` across restarts.

**Startup ordering is safe:** `app` uses `depends_on: mysql (service_healthy)`,
and MySQL only reports healthy *after* the init import finishes. The migrate step
in `startup.sh` then sees a populated schema and is effectively a no-op (existing
migrations already recorded in the imported `migrations` table); only migrations
newer than your dump get applied.

---

## 3. Prerequisites

1. **Docker Desktop** installed and running on Windows (WSL2 backend recommended).
2. Your existing **XAMPP** with the `triforceph` database (for the one-time export).
3. Port **8000** and **3306** free on the host (see the port note below).

---

## 4. Step-by-step: first-time setup

### Step 1 — Create the local env file
From the repo root (`D:\startup\gym-management-api`):

```bat
copy .env.docker.example .env.docker
```

The defaults already match the old XAMPP DB (`triforceph`, user `root`, empty
password). Fill in `FIREBASE_CREDENTIALS` if you need to exercise authenticated
endpoints locally (login/signup go through Firebase — there are no local
passwords). R2 can stay blank; `FILESYSTEM_DISK=local` is used locally.

### Step 2 — Export your existing XAMPP data
Make sure XAMPP MySQL is running, then:

```bat
scripts\export-xampp-db.bat
```

This writes `.docker\mysql\init\triforceph.sql`. If your XAMPP lives somewhere
other than `C:\xampp`, edit the `MYSQLDUMP` path at the top of the script.

> Prefer to start empty and let migrations build the schema instead? Just skip
> this step — with no dump present, `startup.sh` runs `migrate --force` and you
> get a fresh schema (plus the seeders baked into the migrations).

### Step 3 — Stop XAMPP MySQL (avoid the 3306 clash)
The compose file maps host `3306 → container 3306`. If XAMPP MySQL is still
listening on 3306, either stop it from the XAMPP Control Panel, **or** change the
host port in `docker-compose.yml` to `"3307:3306"` and connect your DB GUI to 3307.

### Step 4 — Build and start
```bat
docker compose up -d --build
```

First build pulls the PHP base image and installs Composer deps (a few minutes).
Subsequent starts are fast.

### Step 5 — Verify
```bat
docker compose ps
curl http://localhost:8000/up
```

`/up` should return HTTP 200. Then confirm the data imported:

```bat
docker compose exec mysql mysql -uroot triforceph -e "SHOW TABLES; SELECT COUNT(*) FROM tb_customers;"
```

Follow logs if needed:

```bat
docker compose logs -f app
docker compose logs -f mysql
```

Point the React frontend's API base URL at `http://localhost:8000`.

---

## 5. Everyday commands

| Task | Command |
|---|---|
| Start | `docker compose up -d` |
| Stop (keep data) | `docker compose stop` |
| Rebuild after PHP code change | `docker compose up -d --build` |
| Tail app logs | `docker compose logs -f app` |
| Run artisan | `docker compose exec app php artisan <cmd>` |
| Open a shell | `docker compose exec app sh` |
| MySQL shell | `docker compose exec mysql mysql -uroot triforceph` |
| Fresh DB + re-import dump | `docker compose down -v && docker compose up -d --build` |

> **Code is copied into the image, not bind-mounted** (this is the deliberate
> "run the exact prod image" trade-off you chose). After editing PHP files, run
> `docker compose up -d --build` to rebuild. If you later want instant reloads,
> we can add a bind mount of the source over `/app` as an optional override.

---

## 6. Re-importing / refreshing data later

The init scripts run **only** when the MySQL data volume is empty. To reload a
newer dump from XAMPP (or from the server):

```bat
scripts\export-xampp-db.bat          REM refresh the dump (or drop your own .sql in the init folder)
docker compose down -v               REM WARNING: deletes the local mysql volume
docker compose up -d --build
```

To import a dump **without** wiping the volume:

```bat
docker compose exec -T mysql mysql -uroot triforceph < .docker\mysql\init\triforceph.sql
```

---

## 7. Verification checklist (definition of done)

- [ ] `docker compose ps` shows `app` and `mysql` both `running`/`healthy`.
- [ ] `curl http://localhost:8000/up` returns 200.
- [ ] `SHOW TABLES` in `triforceph` lists your tables and `migrations` is present.
- [ ] Row counts on key tables (e.g. `tb_customers`, `tb_customer_bills`) match
      what you saw in XAMPP/phpMyAdmin.
- [ ] `docker compose logs app` shows "Migrations completed successfully" (or no
      pending migrations) and the queue worker starting.
- [ ] The frontend, pointed at `http://localhost:8000`, loads data as before.

---

## 8. Rollback

This change is additive and reversible. To go back to XAMPP:

1. `docker compose down` (add `-v` to also drop the DB volume).
2. Restart XAMPP (Apache + MySQL) — your original `.env` (`DB_HOST=127.0.0.1`)
   is untouched, so the app runs on XAMPP exactly as before.

Because none of `Dockerfile`, `startup.sh`, application code, or `.env` were
modified, there is nothing to revert in the app itself.

---

## 9. Troubleshooting

**`app` keeps restarting / can't reach DB.**
Check `docker compose logs app`. The startup script retries the DB for ~60s. If
MySQL is still importing a large dump, give it time; `depends_on: service_healthy`
should prevent this, but a very large dump can exceed the health `start_period` —
increase `start_period` in the `mysql` healthcheck if so.

**Port 3306 already in use.**
XAMPP MySQL is still running. Stop it, or change the host port to `3307:3306`.

**Port 8000 already in use.**
Change the app mapping to e.g. `8001:8080` and use `http://localhost:8001`.

**The dump didn't import.**
Init scripts only run on a *fresh* volume. Run `docker compose down -v` then
`up` again, or import manually (Section 6). Confirm exactly one `*.sql` sits in
`.docker/mysql/init/`.

**Auth (login/signup) fails locally.**
Set `FIREBASE_CREDENTIALS` in `.env.docker` to the service-account JSON (single
line). Endpoints behind `firebase.auth` require a valid Firebase token.

**Character set / emoji issues.**
The MySQL service is started with `utf8mb4` / `utf8mb4_unicode_ci`. If your dump
declares a different collation, that's fine — the dump's own settings win on import.

---

## 10. Notes on secrets & git

- `.env.docker` and `.docker/mysql/init/*.sql` are git-ignored (they can contain
  credentials and real member data). Commit `.env.docker.example` instead.
- The `.dockerignore` also excludes the compose file, dumps, and `scripts/` from
  the production image build, so none of this local-dev tooling ships to Railway.

---

## 11. Shared local credentials (single Navicat connection)

Local dev uses the same credentials as the `travelsystem` / `mymeds` stacks so
**one** Navicat connection works for all of them:

| Field | Value |
|---|---|
| User | `user` |
| Password | `Abc_12345` |
| Root | also available, passwordless |

These are set in `docker-compose.yml` (`MYSQL_USER` / `MYSQL_PASSWORD`) and
`.env.docker` (`DB_USERNAME` / `DB_PASSWORD`).

**Important:** MySQL only creates `MYSQL_USER` on the **first** boot of an empty
data volume. If the `gymhub_mysql_data` volume already exists (e.g. you started
the stack before adding these credentials), the account won't exist yet. Create
it once against the running container (no data loss):

```bash
docker exec -it gymhub-mysql mysql -uroot -e "CREATE USER IF NOT EXISTS 'user'@'%' IDENTIFIED BY 'Abc_12345'; ALTER USER 'user'@'%' IDENTIFIED BY 'Abc_12345'; GRANT ALL PRIVILEGES ON *.* TO 'user'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;"
```

(Granting on `*.*` makes `user` a full local admin, so the single connection can
browse every database on that server — same convenience as passwordless root.)

Alternatively, wipe and recreate the volume so the compose credentials apply
automatically (this re-imports the dump in `.docker/mysql/init/`):

```bash
docker compose down -v && docker compose up -d --build
```
