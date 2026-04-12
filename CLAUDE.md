# PiposDocuments — Core API

Symfony 8 backend for PiposDocuments. PHP 8.5, FrankenPHP, PostgreSQL 17, Doctrine ORM 3.

## Tickets

Tracked in Linear, prefix `PIP-`. Always check the relevant ticket before starting work.

## Running things

**Always use `make` targets** — never invoke `docker compose`, `bin/console`, or `composer` directly on the host.

| Task | Command |
|------|---------|
| Start environment | `make up` |
| Stop environment | `make down` |
| Run tests | `make test` |
| Static analysis | `make analyse` |
| Style check | `make lint` |
| Auto-fix style | `make lint-fix` |
| Run a Symfony command | `make sf c='cache:clear'` |
| Run a Composer command | `make composer c='req some/package'` |
| Run migrations | `make migrate` |
| First-time test DB setup | `make setup` |

`make help` lists all targets. Everything runs inside the `php` container.

## Layer Architecture (per domain)

Default architecture per domain. Present when needed, absent otherwise.

```
src/{Domain}/
    Client/             # External API clients
    Command/            # Symfony console commands
    Controller/         # HTTP controllers
        Input/          # Request DTOs with validation
    DTO/                # Data transfer objects
    Entity/             # Doctrine entities
    Enum/               # PHP 8.5 enums
    Exception/          # Domain exceptions
    Message/            # Symfony Messenger messages
    Model/              # Domain models
    Repository/         # Doctrine repositories
    Serializer/         # Serializers
    Service/            # Business services
    Story/              # Foundry Stories: reusable scenario builders for fixtures + tests
    Task/               # Schedules tasks
    ValueObject/        # Immutable value objects
    di.php              # Domain-specific DI configuration
```

A top-level `src/Story/` also exists for cross-domain orchestration Stories
that compose per-domain ones (e.g. `AppStory` with `#[AsFixture(name: 'main')]`).

## Code conventions

- **Strict types**: every PHP file starts with `declare(strict_types=1);`
- **Style**: `@Symfony` + `@Symfony:risky` (PHP-CS-Fixer with `.php-cs-fixer.dist.php`)
- **Static analysis**: PHPStan level max with extra strict rules in `phpstan.dist.neon`
- **Native types over PHPDoc**: PHP 8.5 native types everywhere; PHPDoc only for generics like `array<int, Foo>`
- After every code change, all three must pass: `make lint && make analyse && make test`
- Constructor property promotion with `readonly` for immutable data
- PHP 8.5 enums for fixed value sets
- Nullable types as `?T` (not `T|null`)
- Trailing commas in multi-line arrays and function parameters
- Multi-line function signatures for 2+ parameters
- Explicit boolean comparisons: `if (isset($var) === true)`
- **Date/Time handling**: Always use `Carbon/CarbonImmutable` instead of `DateTimeImmutable`
- **Entity IDs**: UUID v7 via Symfony UID component

## Composer / Symfony Flex

- Always require packages **inside the container**: `make composer c='req some/package'`
- This ensures Symfony Flex recipes patch `compose.yaml`, `.env`, and config correctly
- Never edit the `require` section of `composer.json` by hand
- Composer can OOM on large requires — set `COMPOSER_MEMORY_LIMIT=-1` if needed

## Database

- Two databases: `app` (dev) and `app_test` (tests)
- Test DB used automatically when `APP_ENV=test` via Doctrine's `dbname_suffix` in `config/packages/doctrine.yaml`
- Local password: `localdevpassword` (defined in `compose.yaml` with safe defaults)
- Never put real credentials in `.env`. Use `.env.local` for local overrides, real environment variables for staging/prod
- Always use `IF NOT EXISTS` and `IF EXISTS` in migrations
- `messenger_messages` is owned by Symfony Messenger's Doctrine transport, not by an entity. It is excluded from ORM diffs via `schema_filter` in `config/packages/doctrine.yaml` and managed by a hand-written migration

## Messenger

- `async` transport: Redis (`redis://redis:6379/messages`) — see the `redis` service in `compose.yaml`
- `failed` transport: Doctrine on Postgres (`messenger_messages` table) — durable, SQL-queryable dead letter
- Run a worker locally with `make sf c='messenger:consume async -vv'`
- Inspect failed messages: `make sf c='messenger:failed:show'`, retry: `messenger:failed:retry`, drop: `messenger:failed:remove`

## Tests

- Run via `make test`
- PHPUnit 13 with DAMA DoctrineTestBundle for database isolation
- Each test runs inside a transaction that is rolled back on completion (via `dama/doctrine-test-bundle`), so DB state never leaks between tests
- DDL statements inside a test (`ALTER TABLE`, `CREATE TABLE`, `TRUNCATE`, etc.) implicitly commit and break that isolation — avoid them, or opt the test out
- Messenger tests use `Zenstruck\Messenger\Test\InteractsWithMessenger` — both `async` and `failed` transports are routed to the in-memory `test://` transport in the test environment, so Redis and Postgres are never touched by message dispatches in tests
- First time: run `make setup` to create the test DB and migrate it

## Test structure

- `tests/Setup/{Context}`               # Helper files for specific context (PHPStan, ...)
- `tests/Unit/{Domain}/`                # Unit tests split by domain
- `tests/Integration/`                  # Possible Integration test that does not fit in a single domain
- `tests/Integration/{Domain}/`         # Integration tests split by domain
- `tests/Integration/Mock/`             # WireMock helpers (mappings, files, service wrapper, ...)

## PHPStan bootstrap files

PHPStan needs Symfony container + Doctrine entity manager metadata. These are wired via:

- `tests/Setup/PHPStan/console-application.php` — boots kernel, returns `Application` (for command analysis)
- `tests/Setup/PHPStan/object-manager.php` — boots kernel, returns Doctrine `EntityManager` (for DQL + entity analysis)
- `var/cache/dev/App_KernelDevDebugContainer.xml` — auto-generated by `bin/console cache:warmup`

If PHPStan complains the container XML is missing, run `make sf c='cache:warmup --env=dev'` first.

## Things to never do

- **Never bypass `make`** and call `docker compose` directly unless debugging Make itself
- **Never add PHPStan ignore rules preemptively** — only add them when a real warning fires. Use `reportUnmatched: false` per-rule only when you know the rule will become relevant later (e.g. `doctrine.finalEntity`)

## Future tooling to revisit

Tracked here so it doesn't get lost between sessions.

- **Architecture enforcement (Deptrac or PHPat)** — currently deferred. The layer architecture in "Layer Architecture (per domain)" above is enforced only by convention. Once `src/` reaches **2+ domains** with real cross-domain boundaries, add one of:
  - [Deptrac](https://github.com/qossmic/deptrac) — standalone analyzer, rules in `deptrac.yaml`, new CI step.
  - [PHPat](https://github.com/carlosas/phpat) — PHPStan extension, rules in PHP, runs inside existing `make analyse` with zero new CI overhead.
  PHPat is the cheaper path in; Deptrac is more mature. Decide when the need is real, not preemptively.
- **Doctrine schema validator in CI** — currently commented out in `.github/workflows/ci.yaml` waiting for the first entity (PIP-27+). Re-enable as soon as one entity exists.
- **HTTPS `/api` health check in CI** — same file, same trigger (first `/api` endpoint).
- **Mutation testing (Infection)** — valuable but slow; add as a weekly scheduled workflow, not per-PR, once the test suite is substantial enough to make the signal worthwhile.
