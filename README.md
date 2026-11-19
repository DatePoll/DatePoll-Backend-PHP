# DatePoll backend

The PHP API uses Lumen 11.2.0. Direct production and development packages have exact versions in `composer.json`; `composer.lock` pins the full dependency tree. The backend uses Composer only. Node.js, npm, Husky, and lint-staged are no longer required.

## Runtime and installation

Use PHP 8.3.37 and Composer 2.10.3. Both Docker base images are pinned by digest in `.github/docker/Dockerfile` and in the separate `DatePoll-Dockerized/phpDockerfile`. The images include IMAP, PDO MySQL, PDO SQLite, mbstring, and the other required extensions. GitHub Actions builds the CI image directly from this backend repository.

From this backend repository root:

```sh
docker build -f .github/docker/Dockerfile -t datepoll-backend:local .github/docker
docker run --rm -v "$PWD:/backend:z" datepoll-backend:local composer install --no-interaction --prefer-dist
docker run --rm -v "$PWD:/backend:z" datepoll-backend:local composer check-platform-reqs
```

For a native PHP installation, run those Composer commands in `DatePoll-Backend-PHP`. Use `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader` for production. Do not bypass platform requirements. Supply the existing `.env` configuration and ensure `storage` is writable by the PHP and queue worker users.

## Upgrade notes

- Rebuild both the PHP web container and queue worker container. Restart workers after deploying the updated code and vendor directory, so they load the same framework version.
- Keep the existing database and `storage/app` volume. Local attachments still use `storage/app/files`; this upgrade does not move them. Test migrations on a database copy before deployment.
- Mail uses Symfony Mailer. Existing SMTP variables and `MAIL_DRIVER` continue to work; `MAIL_MAILER` takes precedence when present. `MAIL_ENCRYPTION=tls` requires STARTTLS, `ssl` uses implicit TLS, and `null` disables TLS. `MAIL_SCHEME` can explicitly select the SMTP scheme. Configured transports are SMTP, sendmail, log, and array.
- JWTs still use HS256 with the existing secret and claims. The upgraded JWT library requires an HS256 secret of at least 32 bytes. Existing tokens signed with a valid secret remain compatible; rotating an inadequate secret invalidates existing tokens.
- CORS uses Illuminate middleware with the existing CORS configuration. File storage uses Flysystem 3. Redis uses Predis 3. Database seeders now use class-based Eloquent factories.
- No production data migration is added by this framework upgrade.

## Checks

Run these commands in the backend directory with the pinned runtime:

```sh
composer validate --strict --no-check-all
composer check-platform-reqs
composer test
composer analyse
composer audit-dependencies
composer lint
```

`--no-check-all` disables Composer's advice against exact version constraints, which are intentional here. Schema and lockfile validation remain strict.

The default test database is SQLite in memory. Tests cover API boot, authentication, existing HS256 tokens, user management, CORS, mail rendering and delivery, attachment storage, factories, and mail address safety. Set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to run against a disposable MySQL database. Tests recreate the schema; never point them at a database containing real data. Set `TEST_REDIS=1` and the Redis connection variables to include the Redis queue delivery test.

Larastan 3 runs at level 5. `phpstan-baseline.neon` records 59 findings in existing application code, including inaccurate model annotations and return types. New findings fail analysis. The Laravel configuration-cache warning is disabled because Lumen does not support that configuration caching workflow.

`composer format` runs PHP CS Fixer. The formatter configuration is `.php-cs-fixer.dist.php`. The existing code has formatting debt. `composer lint` checks PHP syntax, as does CI; formatting remains a separate command.

## GitHub Actions

The repository-root `.github/workflows/backend.yml` replaces GitLab CI. It runs on branch pushes, `v*` tag pushes, pull requests, and manual runs. It builds the self-contained CI image from `.github/docker/Dockerfile`, installs the lockfile, checks platform requirements and advisories, runs tests against SQLite and MySQL 8.4.13 with Redis 7.4.11, and runs static analysis.

A successful `v*` tag creates a GitHub release with generated notes. No custom backend ZIP is packaged, uploaded as an artifact, attached to the release, or copied to the release server. Branch pushes verify the backend without publishing. Builds need no deployment secrets or sibling repository checkout. All actions and service images are pinned, checkout does not retain credentials, and only the tag release job receives repository write permission.

The production web/worker image remains in the separate `DatePoll-Dockerized` repository. Rebuild it after migrating the PHP Dockerfile changes. This repository's CI image uses the same pinned PHP and Composer versions and required extensions.

## Lumen 11 mail advisory exception

Lumen 11 depends on Illuminate 11 components. Their scheduled security support ended on March 12, 2026. Every Illuminate Mail 11 release is affected by [CVE-2026-48019](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq), which has no upstream Lumen-compatible patched release.

The application mitigates the reported path in two places:

- `App\Validation\Validator` backports Laravel 12.60's rejection of carriage returns and line feeds in email validation. User and forwarding-address endpoints apply the email rule to each address.
- `ADatePollMailable` rejects ASCII control characters in raw From, To, CC, BCC, and Reply-To addresses and display names before Symfony normalizes them. Every production mail path uses this base class, including existing queued jobs and incoming-mail forwarding. Unsafe messages fail before transport delivery. Unicode display names, plus addressing, and literal percent characters remain supported.

`MailAddressSafetyTest` tests these boundaries with a real array transport, including serialized queue jobs and fields added during broadcast construction. The Composer policy exception applies only to `PKSA-zwc5-qtrz-zm1n`, with its reason recorded in `composer.json`; other advisories still fail audits. This application mitigation does not restore upstream support. Future direct mail paths must use the same guard. Remove the exception when migrating to supported framework components.

To update dependencies, select compatible stable versions, update the exact constraints, run `composer update` in the pinned runtime, and commit both Composer files. Re-run all checks. Update the Docker tags and digests together when changing PHP or Composer.
