# Contributing to WeathermapNG

Thanks for helping improve WeathermapNG. The project is a LibreNMS v2 plugin, so changes should be tested both as a PHP package and inside a LibreNMS plugin install when possible.

## Requirements

- PHP 8.2 or newer
- Composer
- Git
- Docker, recommended for LibreNMS integration testing
- LibreNMS development or test instance for UI/install validation

## Development Setup

### Local Package Setup

```bash
git clone https://github.com/lance0/weathermapNG.git
cd weathermapNG
composer install
composer validate --no-check-publish
composer test
```

If your host does not have the right PHP extensions, use the same Docker image pattern as CI/local validation:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app weathermapng-php-composer validate --no-check-publish
docker run --rm --entrypoint sh -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app weathermapng-php-composer -lc 'vendor/bin/phpunit'
```

### LibreNMS Plugin Setup

For manual local integration testing, install the checkout under LibreNMS:

```bash
cd /opt/librenms/html/plugins
git clone /path/to/your/weathermapNG WeathermapNG
cd WeathermapNG
composer install

cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
composer require 'librenms/weathermapng:*' --with-dependencies
php artisan package:discover

cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php

cd /opt/librenms
php artisan optimize:clear
php artisan route:list | grep -iE 'weathermap|wmng'
./lnms plugin:enable WeathermapNG
```

The Composer path package registration is required. Without it, Laravel will not discover the service provider, routes, and views reliably.

## Project Structure

```text
WeathermapNG/
├── composer.json                 # Package metadata and Laravel provider discovery
├── VERSION                       # Release version source used by runtime/tests
├── quick-install.sh              # Supported installer
├── routes/web.php                # Plugin routes
├── src/
│   ├── WeathermapNGProvider.php  # Composer-discovered Laravel provider
│   ├── Hooks/                    # LibreNMS plugin hooks
│   ├── Http/Controllers/         # Web/API controllers
│   ├── Http/Requests/            # Request validation
│   ├── Models/                   # Eloquent models
│   ├── Policies/                 # Authorization policies
│   ├── RRD/                      # RRD helpers
│   └── Services/                 # Core domain/services
├── resources/
│   ├── views/                    # Blade views
│   ├── js/                       # Browser-side JavaScript
│   └── css/                      # Stylesheets
├── database/
│   ├── setup.php                 # Supported table setup/upgrade entrypoint
│   ├── migrations/               # Schema history/reference
│   └── seeds/                    # Template/demo seeders
├── config/                       # Plugin configuration
├── bin/                          # Optional operational scripts
├── tests/                        # PHPUnit and install/documentation tests
└── .github/workflows/            # CI, install validation, release validation
```

## Coding Standards

- Follow PSR-12 for PHP.
- Prefer typed parameters and return types where they make behavior clearer.
- Keep controller actions thin and put reusable behavior in services.
- Preserve existing LibreNMS conventions and Bootstrap 4 compatibility.
- Use FormRequest classes for request validation when adding write endpoints.
- Avoid broad rewrites when a focused change will solve the problem.

## UI Standards

- Keep the UI practical and operator-focused.
- Preserve keyboard access and visible focus states.
- Add accessible names to icon-only controls.
- Test editor, embed, and index views at more than one viewport size when touching layout.
- Prefer existing Font Awesome/Bootstrap patterns already used by LibreNMS.

## Testing

Run the package tests:

```bash
composer test
```

Useful focused checks:

```bash
./vendor/bin/phpunit tests/DocsPathsTest.php
./vendor/bin/phpunit tests/RoutesSmokeTest.php
./vendor/bin/phpunit tests/VersionMetadataTest.php
```

Before release-oriented changes, also validate:

```bash
composer validate --no-check-publish
git diff --check
```

## Documentation

Update docs when changing:

- Install, upgrade, or Composer discovery behavior
- Routes or API payloads
- Health/readiness/security boundaries
- Version metadata or release flow
- Editor, embed, or map-management workflows
- Supported PHP/LibreNMS requirements

## Pull Request Checklist

- [ ] Tests pass.
- [ ] Composer metadata validates.
- [ ] Install or route changes include docs and smoke-test updates.
- [ ] User-facing changes are reflected in README, INSTALL, API, or EMBED docs as appropriate.
- [ ] Versioned releases update `VERSION`, `composer.json`, and `CHANGELOG.md` together.
- [ ] UI changes account for keyboard access, focus states, and smaller viewports.

## Commit Messages

Use concise conventional-style commit messages where practical:

```text
fix: correct embed zoom controls
docs: refresh deployment guide
test: add version metadata guard
```

## Reporting Issues

Good bug reports include:

- WeathermapNG version
- LibreNMS version
- PHP version
- Install method: native, Docker, or other
- Relevant route output from `php artisan route:list | grep -iE 'weathermap|wmng'`
- Logs, screenshots, or exact error text
- Whether LibreNMS `validate.php` reports anything relevant

## License

By contributing to WeathermapNG, you agree that your contributions will be licensed under the MIT License.
