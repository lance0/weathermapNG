# Contributing to WeathermapNG

This page explains development setup, checks, and pull request rules for the
WeathermapNG LibreNMS plugin.

## Requirements

| Requirement | Needed for |
|-------------|------------|
| PHP 8.2+ with `ext-gd`, `ext-json`, `ext-mbstring` | All PHP work and the PHPUnit suite |
| Composer 2 | Dependency installation and metadata validation |
| Git | Source checkout and commits |
| Node.js | `just test-js` and the browser suites |
| Docker with Compose | `just dev-up`, `just test-integration`, and install tests |
| A LibreNMS instance | Manual UI, install, and upgrade validation |
| [`just`](https://just.systems/) (optional) | The standard task runner used by CI |

PHP 8.3 and 8.4 are supported; CI runs the suite on 8.2, 8.3, and 8.4, plus a
LibreNMS Docker image for exact-runtime parity. Host PHP with `composer install`
is the normal development path. Use the Docker image when you need to reproduce
CI's LibreNMS runtime, not as the only way to run tooling.

## Development setup

Install `just` once if you want the same entrypoints CI uses:

```bash
cargo install --locked just   # or: brew install just
```

Clone and install dependencies:

```bash
git clone https://github.com/lance0/weathermapNG.git
cd weathermapNG
just setup
```

`just setup` installs Composer to `~/.local/bin` when it is missing, then runs
`composer install`. Without `just`, run `composer install` directly.

Run the standard local gate before opening a pull request:

```bash
just check
```

| Command | Runs |
|---------|------|
| `just setup` | Composer install (installs Composer if absent) |
| `just test` | `vendor/bin/phpunit --no-coverage` |
| `just test-one MapServiceTest` | `vendor/bin/phpunit tests/MapServiceTest.php` |
| `just test-js` | Every pure Node suite in `tests/*-test.cjs` |
| `just lint` | `php -l` over `src`, `config`, `database`, `routes`, `bin` |
| `just check-metadata` | `composer validate` and VERSION/composer.json/CHANGELOG alignment |
| `just check-docs` | Required docs exist; install guidance stays aligned |
| `just check-security` | Scans PHP sources for hardcoded secrets |
| `just check` | `lint`, `check-metadata`, `check-docs`, `check-security`, `test` |

The PHPUnit suite has 455 tests and 1760 assertions. Add tests for new behavior.
A focused run takes one class:

```bash
just test-one MapServiceTest
```

To run the suite inside the LibreNMS image, matching CI's Docker job:

```bash
docker run --rm --entrypoint php \
  -v "$PWD":/opt/librenms/html/plugins/WeathermapNG \
  -w /opt/librenms/html/plugins/WeathermapNG \
  librenms/librenms:latest \
  vendor/bin/phpunit --no-coverage
```

## Integration and browser testing

The integration suites talk to a live LibreNMS stack and a database. Start the
development stack, wait for `http://localhost:8000` to respond, then run them:

```bash
just dev-up
just test-integration
just dev-stop
```

`just test-integration` runs the `tests/integration_*.php` suites inside the
`librenms-dev` container. After editing source, `just dev-sync` copies tracked
plugin files into the container; it excludes `vendor/`, so reinstall dependencies
there if needed.

Visual checks render the index, editor, and embed views at 480x800, 768x1024,
and 1920x1080:

```bash
just visual http://localhost:8000
```

`just visual` writes PNGs to `output/screenshots` and needs a Chromium-family
binary on `PATH`. Screenshot comparison uses `playwright-core` from `NODE_PATH`;
CI installs Playwright beside the browser and sets `NODE_PATH` for the job. A
local equivalent:

```bash
npm install --no-save --prefix /tmp/wmng-browser playwright@1.62.1
/tmp/wmng-browser/node_modules/.bin/playwright install chromium
NODE_PATH=/tmp/wmng-browser/node_modules just visual-compare http://localhost:8000
```

`just visual-compare` compares against `tests/screenshots/*.png`. Regenerate
baselines only for intended visual changes, review the PNGs, then commit them:

```bash
NODE_PATH=/tmp/wmng-browser/node_modules just visual-baseline http://localhost:8000
```

The plain Node suites under `just test-js` do not need Playwright.

## Code and UI standards

- Follow PSR-12.
- Add parameter and return types where they make behavior clearer.
- Keep controllers thin; put reusable behavior in `src/Services`.
- Use FormRequest classes for write-endpoint validation.
- Follow existing LibreNMS conventions and Bootstrap 4 / Font Awesome patterns.
- Prefer focused changes over broad rewrites.

UI changes must preserve:

- Keyboard access and visible focus states.
- Accessible names on icon-only controls.
- Readable layouts at small and large viewports; check the editor, embed, and
  index views when layout changes.
- Existing LibreNMS theme and component behavior.

## Architecture and authorization

`src/WeathermapNGProvider.php` is the Composer-discovered Laravel provider. It
registers routes and views and publishes the `MenuEntry` and `Settings` hooks.
Routes live in `routes/web.php`; that file is the authoritative route list.

Read endpoints are open to any authenticated LibreNMS user. Every mutation
endpoint requires an administrator. The shared `AdminCheck` trait
(`src/AdminCheck.php`) checks `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`.
Use it in new write endpoints:

```php
use LibreNMS\Plugins\WeathermapNG\AdminCheck;

class MyController extends Controller
{
    use AdminCheck;

    public function store(Request $request): JsonResponse
    {
        $this->requireAdmin();
        // ...
    }
}
```

`requireAdmin()` aborts with 403 for non-admins. Read-only endpoints do not need
the guard.

## Pull requests

1. Branch from `main` with a short descriptive name.
2. Keep commits focused; use conventional prefixes where they fit:
   `fix:`, `feat:`, `docs:`, `test:`, `refactor:`, `ci:`.
3. Run `just check` and `just test-js`.
4. Update documentation for behavior changes (see below).
5. Open the pull request and make sure every required workflow is green.

Do not bump `VERSION` or `composer.json` in a feature or fix pull request.
Version sources change only during release preparation; see
[RELEASE.md](RELEASE.md).

CI runs these workflows:

| Workflow | Coverage |
|----------|----------|
| [CI](.github/workflows/ci.yml) | PHP lint, version metadata, PHPUnit on PHP 8.2/8.3/8.4, PHPUnit in the LibreNMS image, security scan, docs validation |
| [Embed Authentication](.github/workflows/embed-auth.yml) | LibreNMS HTTP/browser regression, embed auth, favorites, version restore, Node suites, screenshot comparison |
| [Installation Tests](.github/workflows/installation-tests.yml) | Installer scripts, Compose validation, install/upgrade lifecycle |
| [Release](.github/workflows/release.yml) | On `v*` tags: full suite, version metadata, changelog extraction, GitHub release and archives |

### Documentation rule

Update docs in the same pull request when behavior changes:

| Change | Update |
|--------|--------|
| Routes, auth tiers, request/response shapes | [docs/API.md](docs/API.md) |
| Embed parameters, kiosk mode, live updates | [docs/EMBED.md](docs/EMBED.md) |
| Install, upgrade, configuration, recovery | [docs/INSTALL.md](docs/INSTALL.md) |
| User-facing additions and fixes | [CHANGELOG.md](CHANGELOG.md) under `## [Unreleased]` |
| Planned work that ships | [ROADMAP.md](ROADMAP.md) |
| Release steps or gates | [RELEASE.md](RELEASE.md) |

`just check-docs` verifies required files and install-guidance literals.
`just test-js` runs `tests/docs-links-test.cjs`, which resolves repository
links and heading anchors, including those in `CONTRIBUTING.md` and `RELEASE.md`.

## Project structure

```text
WeathermapNG/
├── composer.json                 # Package metadata and Laravel provider discovery
├── VERSION                       # Release version source used by runtime/tests
├── quick-install.sh              # Supported installer
├── routes/web.php                # All plugin routes
├── src/
│   ├── WeathermapNGProvider.php  # Composer-discovered Laravel provider
│   ├── AdminCheck.php            # Shared admin-gate trait
│   ├── Console/Commands/         # Artisan commands
│   ├── Hooks/                    # LibreNMS plugin hooks
│   ├── Http/Controllers/         # Web, API, embed, and health controllers
│   ├── Http/Requests/            # Request validation
│   ├── Http/Middleware/          # Embed token authentication
│   ├── Models/                   # Eloquent models
│   ├── RRD/                      # RRD helpers
│   └── Services/                 # Core domain services
├── resources/
│   ├── views/                    # Blade views, including hooks and install screens
│   ├── js/                       # Editor, embed, index, SVG export, topology preview
│   └── css/                      # Stylesheets
├── database/
│   ├── setup.php                 # Supported table setup/upgrade entrypoint
│   ├── migrations/               # Schema history/reference
│   ├── seeders/                  # Template/demo seeders
│   └── seeds/                    # Map template seed data
├── config/                       # Plugin configuration and environment variants
├── bin/                          # Operational scripts (backup, poller)
├── tests/                        # PHPUnit, integration, Node, browser, and screenshots
│   ├── *-test.cjs                # Pure Node regression suites
│   ├── *-browser.cjs             # Playwright browser suites
│   └── screenshots/              # Committed visual baselines
└── .github/workflows/            # CI, embed/browser, install, and release validation
```

## Reporting issues

Include:

- WeathermapNG version and LibreNMS version.
- PHP version.
- Install method: native, Docker, or other.
- Relevant route output from
  `php artisan route:list | grep -iE 'weathermap|wmng'`.
- Logs, screenshots, or exact error text.
- Whether LibreNMS `validate.php` reports anything relevant.

## License

Contributions are licensed under the [MIT License](LICENSE).
