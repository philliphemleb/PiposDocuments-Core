# Contributing to PiposDocuments

Welcome! This guide covers how to contribute to PiposDocuments.

## Quick Links

- [README.md](README.md) — Setup and running the project
- [CLAUDE.md](CLAUDE.md) — Architecture, coding conventions, and guidelines

## Development Setup

1. Clone the repository
2. Run `make build && make up`
3. Run `make setup` to create the test database (once)

## Running the Project

```bash
make up     # Start containers
make down   # Stop containers
make logs   # Tail live logs
```

## Code Quality

Before submitting a PR, run all three checks:

```bash
make lint       # PHP-CS-Fixer (dry-run)
make analyse    # PHPStan static analysis
make test       # PHPUnit test suite
```

Optional:

```bash
make rector     # Auto-refactoring (dry-run)
```

## Making Changes

1. Create a feature branch from `main`
2. Follow the architecture rules in [CLAUDE.md](CLAUDE.md)
3. Write tests for new business logic (see "When to write which test" in CLAUDE.md)
4. Run the code quality checks above
5. Submit a PR

## Pull Request Description

## Description

[Brief description of what this PR does]

## Checklist

- [ ] I have run `make lint && make analyse && make test`
- [ ] Tests pass
- [ ] I have added tests for new business logic (if applicable)
