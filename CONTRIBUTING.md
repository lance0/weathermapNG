# Contributing to WeathermapNG

Thank you for your interest in contributing to WeathermapNG! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Project Structure](#project-structure)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Reporting Issues](#reporting-issues)

## Code of Conduct

This project follows a code of conduct to ensure a welcoming environment for all contributors. By participating, you agree to:

- Be respectful and inclusive
- Focus on constructive feedback
- Accept responsibility for mistakes
- Show empathy towards other contributors
- Help create a positive community

## Getting Started

### Prerequisites

- PHP 8.0 or higher
- Composer
- Git
- LibreNMS installation
- MySQL or PostgreSQL

### Quick Setup

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/yourusername/weathermapNG.git
   cd weathermapNG
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Set up your development environment (see Development Setup below)

## Development Setup

### Local Development Environment

1. **LibreNMS Setup**: Install LibreNMS in a local development environment
2. **Plugin Installation**:
   ```bash
   cd /opt/librenms/html/plugins
   git clone /path/to/your/local/weathermapNG
   cd weathermapNG
   composer install
   ```

3. **Database Setup**:
   ```bash
   cd /opt/librenms
   php artisan migrate
   ```

4. **Permissions**:
   ```bash
   chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
   chmod +x /opt/librenms/html/plugins/WeathermapNG/bin/*
   ```

### Testing Environment

For testing, you can use the included seeder to create demo data:

```bash
cd /opt/librenms
php artisan db:seed --class=LibreNMS\\Plugins\\WeathermapNG\\Database\\Seeders\\WeathermapNGSeeder
```

## Project Structure

```
WeathermapNG/
├── WeathermapNG.php          # Main plugin bootstrap
├── routes.php                # Route definitions
├── composer.json             # Dependencies
├── database/
│   ├── migrations/           # Database schema
│   └── seeders/              # Demo data
├── Http/Controllers/         # Web controllers
├── Models/                   # Eloquent models
├── Services/                 # Business logic
├── Policies/                 # Authorization
├── Resources/
│   ├── views/                # Blade templates
│   ├── js/                   # JavaScript assets
│   └── css/                  # Stylesheets
├── config/                   # Configuration files
├── bin/                      # Executable scripts
├── tests/                    # Test files
└── lib/                      # Legacy support (to be removed)
```

## Coding Standards

### PHP Standards

- Follow PSR-12 coding standards
- Use type hints and return types where possible
- Use meaningful variable and method names
- Add PHPDoc comments for public methods
- Keep methods focused on single responsibilities

### JavaScript Standards

- Use modern ES6+ syntax
- Follow consistent naming conventions
- Add comments for complex logic
- Handle errors gracefully

### CSS Standards

- Use consistent naming conventions (BEM preferred)
- Keep specificity low
- Use CSS custom properties for theming
- Ensure responsive design

### Commit Messages

Follow conventional commit format:

```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: New features
- `fix`: Bug fixes
- `docs`: Documentation
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Testing
- `chore`: Maintenance

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/phpunit tests/MapTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

- Place test files in `tests/` directory
- Name test files with `Test.php` suffix
- Use descriptive test method names
- Follow Arrange-Act-Assert pattern
- Test both success and failure scenarios

Example:

```php
/** @test */
public function it_creates_map_with_valid_data()
{
    // Arrange
    $data = ['name' => 'test', 'title' => 'Test Map'];

    // Act
    $map = Map::create($data);

    // Assert
    $this->assertEquals('test', $map->name);
    $this->assertDatabaseHas('wmng_maps', $data);
}
```

## Submitting Changes

### Pull Request Process

1. **Create Feature Branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make Changes**:
   - Write tests for new functionality
   - Ensure all tests pass
   - Update documentation if needed
   - Follow coding standards

3. **Commit Changes**:
   ```bash
   git add .
   git commit -m "feat: add new feature description"
   ```

4. **Push and Create PR**:
   ```bash
   git push origin feature/your-feature-name
   # Create pull request on GitHub
   ```

### PR Requirements

- [ ] Tests pass (`composer test`)
- [ ] Code follows standards (`composer lint`)
- [ ] Documentation updated
- [ ] Commit messages follow conventional format
- [ ] PR description explains changes clearly
- [ ] No merge conflicts

## Reporting Issues

### Bug Reports

When reporting bugs, please include:

1. **Environment**: LibreNMS version, PHP version, OS
2. **Steps to Reproduce**: Clear steps to reproduce the issue
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happens
5. **Screenshots/Logs**: If applicable
6. **Additional Context**: Any other relevant information

### Feature Requests

For feature requests, please include:

1. **Problem**: What problem are you trying to solve?
2. **Solution**: Describe your proposed solution
3. **Alternatives**: Any alternative solutions considered
4. **Use Cases**: How would this feature be used?

## Development Workflow

### Daily Development

1. Pull latest changes: `git pull origin main`
2. Create feature branch: `git checkout -b feature/name`
3. Make changes and test
4. Commit with conventional format
5. Push and create PR

### Code Review Process

1. **Automated Checks**: Tests and linting run automatically
2. **Peer Review**: At least one maintainer reviews code
3. **Discussion**: Address any feedback or concerns
4. **Approval**: PR approved and merged

## Getting Help

- **Documentation**: Check the README.md and docs/
- **Issues**: Search existing GitHub issues
- **Discussions**: Use GitHub Discussions for questions
- **Community**: Join LibreNMS community forums

## License

By contributing to WeathermapNG, you agree that your contributions will be licensed under the Unlicense.

Thank you for contributing to WeathermapNG! 🎉