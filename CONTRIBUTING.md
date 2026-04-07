# Contributing to CardNavigator

## Prerequisites

- PHP 8.4+
- Composer
- Node.js 20+
- SQLite

## Development Setup

```bash
composer run setup
```

This installs dependencies, copies `.env.example` → `.env`, generates an `APP_KEY`, runs database migrations, and builds frontend assets. You will also need a Google Maps API key with the **Places API (New)** enabled — set `GOOGLE_MAPS_API_KEY` in your `.env`.

## Running Tests

```bash
php artisan test --compact
```

## Code Style

This project uses **Laravel Pint** with a custom ruleset (`pint.json`). Run before committing:

```bash
vendor/bin/pint
```

The GitHub CI workflow enforces formatting on every push and pull request. PRs with formatting violations will be automatically marked "Request Changes".

## Documentation Comments

Every new or modified **public method** must have a complete PHPDoc block:

```php
/**
 * One-sentence description of what the method does.
 *
 * @param  string  $foo  Description of the parameter.
 * @return array<string, mixed>  Description of the return value.
 */
public function myMethod(string $foo): array
```

Doc comments are required. PRs missing them will not be merged. They also power the auto-generated API reference published to GitHub Pages.

## Translation / UI Strings

ALL user-facing strings must use `__()`. Strings must be complete, translatable phrases. Use named placeholders for dynamic values:

```php
// Correct
__('Hello :name', ['name' => $name])

// Wrong — never concatenate or split across calls
'Hello ' . $name
__('Hello') . ' ' . $name
```

## Tests

All new functionality requires accompanying tests. Follow the project's structure:

```php
#[TestDox('it does the expected thing')]
public function test_it_does_the_expected_thing(): void
{
    // Arrange
    $input = ...;

    // Act
    $result = ...;

    // Assert
    $this->assertEquals(..., $result);
}
```

Tests cover happy paths, failure paths, and edge cases. See [tests/](tests/) for examples.

## CHANGELOG

Every PR must include an entry in the `[Unreleased]` section of [CHANGELOG.md](CHANGELOG.md), attributed with your GitHub handle:

```markdown
### Added
- Brief description of the change. ([@yourhandle](https://github.com/yourhandle))
```

## Pull Request Process

1. Fork the repository and create a feature branch.
2. Make your changes following the guidelines above.
3. Run `vendor/bin/pint` and `php artisan test` locally.
4. Open a PR against `main`. The CI workflow will run automatically.
5. Address any "Request Changes" feedback from the automated review.

## Security

See [SECURITY.md](.github/SECURITY.md) for the vulnerability disclosure policy.
