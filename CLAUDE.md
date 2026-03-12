# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OpenEMR is an open-source electronic health records and medical practice management application. It includes REST/FHIR APIs, patient portal, billing, scheduling, and clinical documentation.

## Project Structure

```
/src/              - Modern PSR-4 code (OpenEMR\ namespace)
/library/          - Legacy procedural PHP code
/interface/        - Web UI controllers and templates
/templates/        - Smarty/Twig templates
/tests/            - Test suite (unit, e2e, api, services)
/sql/              - Database schema and migrations
/public/           - Static assets
/docker/           - Docker configurations
/modules/          - Custom and third-party modules
```

## Technology Stack

- **PHP:** 8.2+ required
- **Backend:** Laminas MVC, Symfony components
- **Templates:** Twig 3.x (modern), Smarty 4.5 (legacy)
- **Frontend:** Angular 1.8, jQuery 3.7, Bootstrap 4.6
- **Build:** Gulp 4, SASS
- **Database:** MySQL via Doctrine DBAL 4.x (ADODB surface API for legacy code)
- **Testing:** PHPUnit 11, Jest 29
- **Static Analysis:** PHPStan level 10, Rector, custom rules in `tests/PHPStan/Rules/`

## Local Development

See `CONTRIBUTING.md` for full setup instructions. Quick start:

```bash
cd docker/development-easy
docker compose up --detach --wait
```

- **App URL:** http://localhost:8300/ or https://localhost:9300/
- **Login:** `admin` / `pass`
- **phpMyAdmin:** http://localhost:8310/

## Testing

Tests run inside Docker via devtools. Run from `docker/development-easy/`:

```bash
# Run all tests
docker compose exec openemr /root/devtools clean-sweep-tests

# Individual test suites
docker compose exec openemr /root/devtools unit-test
docker compose exec openemr /root/devtools api-test
docker compose exec openemr /root/devtools e2e-test
docker compose exec openemr /root/devtools services-test

# Additional test suites
docker compose exec openemr /root/devtools fixtures-test
docker compose exec openemr /root/devtools validators-test
docker compose exec openemr /root/devtools controllers-test
docker compose exec openemr /root/devtools common-test

# View PHP error log
docker compose exec openemr /root/devtools php-log
```

**Tip:** Install [openemr-cmd](https://github.com/openemr/openemr-devops/tree/master/utilities/openemr-cmd)
for shorter commands (e.g., `openemr-cmd ut` for unit tests) from any directory.

### Isolated tests (no Docker required)

Isolated tests run on the host without a database or Docker:

```bash
composer phpunit-isolated        # Run all isolated tests
```

### Twig template tests

Twig templates have two layers of testing (both isolated):

- **Compilation tests** verify every `.twig` file parses and references valid
  filters/functions/tests. These run automatically over all templates.
- **Render tests** render specific templates with known parameters and compare
  the full HTML output to expected fixture files in
  `tests/Tests/Isolated/Common/Twig/fixtures/render/`.

When modifying a Twig template that has render test coverage, update the
fixture files:

```bash
composer update-twig-fixtures    # Regenerate fixture files
```

Review the diff before committing. See the
[fixtures README](tests/Tests/Isolated/Common/Twig/fixtures/render/README.md)
for details on adding new test cases.

## Code Quality

These run on the host (requires local PHP/Node):

```bash
# Run all PHP quality checks (phpcs, phpstan, rector, etc.)
composer code-quality

# Individual checks (composer scripts handle memory limits)
composer phpstan              # Static analysis (level 10)
composer phpstan-baseline     # Regenerate PHPStan baseline
composer phpcs                # PHP code style check
composer phpcbf               # PHP code style auto-fix
composer rector-check         # Code modernization (dry-run)
composer rector-fix           # Code modernization (apply changes)
composer require-checker      # Detect undeclared dependencies
composer checks               # Validate composer.json and normalize
composer codespell            # Spell-check the codebase
composer conventional-commits:check  # Validate commit messages
composer php-syntax-check     # Run php -l on all PHP files

# JavaScript/CSS
npm run lint:js           # ESLint check
npm run lint:js-fix       # ESLint auto-fix
npm run stylelint         # CSS/SCSS lint
```

## Build Commands

```bash
npm run build        # Production build
npm run dev          # Development with file watching
npm run gulp-build   # Build only (no watch)
```

## Architecture

### Routing

- **REST API:** Routes defined in `_rest_routes.inc.php` at repo root. Maps HTTP methods + paths to controller classes in `src/RestControllers/`.
- **FHIR API:** Routes in `_rest_routes.inc.php` under `/fhir/` prefix, handled by `src/FHIR/R4/` resources and `src/RestControllers/FHIR/`.
- **Web UI:** Traditional PHP scripts in `interface/` — no centralized router. Each page is a PHP file that includes common headers.

### Kernel & Bootstrap

- `src/Core/Kernel.php` — Application kernel, manages service container and event dispatcher.
- `globals.php` — Legacy bootstrap loaded by most pages, sets up `$GLOBALS`, session, database connection.
- Module bootstrap files (`openemr.bootstrap.php`) register event listeners and services.

### Event System

Uses Symfony EventDispatcher. Modules and core code dispatch/listen to events defined in `src/Events/`. Key event categories:
- Patient events (create, update, delete)
- Appointment/calendar events
- Menu/sidebar filter events (for UI customization)
- API events
- Billing events

### ACL / Authorization

- `src/Common/Acl/AclMain.php` — Central ACL checking. Uses `acl_check()` function throughout legacy code.
- ACL sections defined in `library/acl.inc.php` and administered via Admin > ACL.
- API authentication uses OAuth2 (league/oauth2-server).

### FHIR Implementation

- `src/FHIR/R4/FHIRResource/` — FHIR R4 resource model classes (auto-generated from FHIR spec).
- `src/Services/FHIR/` — FHIR service classes that map OpenEMR data to FHIR resources.
- `src/RestControllers/FHIR/` — REST controllers for FHIR endpoints.

### Module System

Custom modules live in `interface/modules/custom_modules/`. Each module has:
- `moduleConfig.php` — Module metadata and configuration
- `openemr.bootstrap.php` — Bootstrap file that registers with EventDispatcher
- `ModuleManagerListener.php` — Handles module install/enable/disable lifecycle
- `table.sql` / `cleanup.sql` — Database setup and teardown

### Database Access

- **New code:** Use `QueryUtils` (`src/Common/Database/QueryUtils.php`) for queries.
- **Connection factory:** `DatabaseConnectionFactory` — never instantiate connections directly.
- **Migrations:** New schema changes use Doctrine Migrations (`sql/` directory).
- **Legacy:** Many files still use ADODB via `$GLOBALS['adodb']['db']`.

### Custom PHPStan Rules

Rules in `tests/PHPStan/Rules/` enforce project conventions:
- `ForbiddenGlobalsAccessRule` / `ForbiddenGlobalKeywordRule` — Prevent direct `$GLOBALS` usage (use `OEGlobalsBag`)
- `ForbiddenInstantiationsRule` — Block direct instantiation of certain classes (use factories)
- `ForbiddenClassesRule` — Block usage of deprecated/forbidden classes
- `ForbiddenCurlFunctionsRule` — Prevent raw curl (use Guzzle)
- `ForbiddenFunctionsRule` / `ForbiddenMethodsRule` — Block deprecated functions
- `ForbiddenGlobalNamespaceRule` — Enforce namespace usage in `src/`
- `OEGlobalsBagTypedGetterRule` — Enforce typed getters on OEGlobalsBag
- `NoCoversAnnotationRule` — PHPUnit test annotation conventions

## Coding Standards

- **Indentation:** 4 spaces
- **Line endings:** LF (Unix)
- **strict_types:** New files should use `declare(strict_types=1)` — adoption is growing
- **Namespaces:** PSR-4 with `OpenEMR\` prefix for `/src/`
- New code goes in `/src/`, legacy helpers in `/library/`
- **Database:** Use `QueryUtils` for queries. New schema changes use Doctrine
  Migrations. Do not instantiate database connections directly — use the
  centralized `DatabaseConnectionFactory`.
- **Global settings:** Use `OEGlobalsBag` (extends Symfony `ParameterBag`) instead
  of `$GLOBALS`. Prefer typed getters over `get()` + cast:
  - `getString($key)` instead of `(string) get($key)`
  - `getInt($key)` instead of `(int) get($key)`
  - `getBoolean($key)` instead of `(bool) get($key)`
  - `getKernel()` for the Kernel instance
  - Check the parent class for more: `getAlpha()`, `getAlnum()`, `getDigits()`, `getEnum()`

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>
```

**Types:** feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert

**Examples:**
- `feat(api): add PATCH support for patient resource`
- `fix(calendar): correct date parsing for recurring events`
- `chore(deps): bump monolog/monolog to 3.10.0`

**CI tip:** During development, add `Skip-Slow-Tests: true` as a commit trailer to skip the full test matrix:
```bash
git commit --trailer "Skip-Slow-Tests: true" -m "fix: work in progress"
```
Remove `Skip-Slow-Tests` trailers before merging.

## Service Layer Pattern

New services should extend `BaseService`:

```php
namespace OpenEMR\Services;

class ExampleService extends BaseService
{
    public const TABLE_NAME = "table_name";

    public function __construct()
    {
        parent::__construct(self::TABLE_NAME);
    }
}
```

## File Headers

When modifying PHP files, ensure proper docblock:

```php
/**
 * Brief description
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Your Name <your@email.com>
 * @copyright Copyright (c) YEAR Your Name or Organization
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
```

Preserve existing authors/copyrights when editing files.

## Common Gotchas

- Multiple template engines: check extension (.twig, .html, .php)
- Event system uses Symfony EventDispatcher
- **Pre-commit hooks:** Install with `prek install` (or `pre-commit install` if
  prek is unavailable). Run `prek run --all-files` before committing to catch
  issues early — the hooks run phpstan, rector, phpcs, codespell, and more.
- Custom PHPStan rules in `tests/PHPStan/Rules/` enforce project conventions
  (forbidden globals, forbidden direct instantiations, namespace rules, etc.)
- Commit messages are validated against Conventional Commits format in CI
- Theme CSS changes require rebuilding: `docker compose exec openemr /root/devtools build-themes`
- E2E tests can be watched live at http://localhost:7900 (password: `openemr123`)
- Default Docker PHP version is 8.4; test other versions by changing the image in docker-compose.yml (e.g., `openemr/openemr:flex-3.22-php-8.2`)

## Key Documentation

- `CONTRIBUTING.md` - Contributing guidelines
- `API_README.md` - REST API docs
- `FHIR_README.md` - FHIR implementation
- `tests/Tests/README.md` - Testing guide
- `ci/README.md` - CI system details
