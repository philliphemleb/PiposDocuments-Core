# PiposDocuments — Core API

Symfony 8 backend for PiposDocuments, running on FrankenPHP + PostgreSQL 17 via Docker.

### License

PiposDocuments is hosted in a public GitHub repository so that the
source code can be viewed, studied, and modified.

However, **commercial use is not allowed** for other people or companies.
This includes:

- Running `PiposDocuments` as a hosted service (SaaS).
- Embedding it into a commercial product or website.
- Using it in a commercial business offering, with or without a fee.

If you want to use `PiposDocuments` in a commercial context,
you must contact the project maintainer and obtain a separate commercial license.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) with Compose v2.10+
- Make

## Getting started

```bash
make build
make up
make setup
```

The app is available at **https://localhost** — accept the self-signed TLS certificate on first visit.

On first start Docker installs Symfony, runs migrations, and waits for the database automatically. `make setup` creates and migrates the test database (run once).

## Daily workflow

| Command | Description |
|---------|-------------|
| `make up` | Start all containers (detached) |
| `make down` | Stop all containers |
| `make logs` | Tail live logs |
| `make sh` | Shell into the PHP container |
| `make bash` | Bash into the PHP container |
| `make migrate` | Run pending Doctrine migrations |
| `make test` | Run the full PHPUnit test suite |
| `make setup` | Create and migrate the test database (run once after first `make up`) |
| `make composer c='...'` | Run any Composer command inside the container |
| `make sf c='...'` | Run any Symfony console command inside the container |
| `make cc` | Clear the Symfony cache |

## Databases

| Database | Purpose |
|----------|---------|
| `app` | Main development database |
| `app_test` | Test suite — reset automatically before each test class |

Both databases are created on first container start. Credentials for local dev are in `compose.yaml` (defaults) and can be overridden via `.env.local`.

## Xdebug

Xdebug is pre-installed in the dev image. The mode defaults to `develop` (shows improved var_dump output). To enable step debugging, set `XDEBUG_MODE=debug` in your `.env.local`:

```dotenv
XDEBUG_MODE=debug
```

Then restart the containers with `make down && make up`. Your IDE (PhpStorm, VS Code) needs to listen on port 9003. See [docs/xdebug.md](docs/xdebug.md) for full IDE setup.

## Environment and credentials

The `.env` file is committed and contains safe local defaults. Never put real credentials there.

For local overrides, create `.env.local` (git-ignored):

```dotenv
# .env.local — never committed
POSTGRES_PASSWORD=something_else
DATABASE_URL="postgresql://app:something_else@127.0.0.1:5432/app?serverVersion=17&charset=utf8"
```

For staging/production, **do not use `.env` files on the server**. Instead set real environment variables on the host (via your CI/CD platform, systemd unit, or Docker run flags). Real environment variables always win over `.env` files. See the [production deployment docs](docs/production.md) for details.
