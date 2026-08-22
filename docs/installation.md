# Installation guide

## Requirements

- PHP 8.3 or later.
- The PHP JSON extension.
- Composer 2.

No PHP framework, PSR-7 implementation, session library, template engine, or frontend build tool is required.

## Install with Composer

```bash
composer require php-forge/inertia:^0.1
```

The package exposes the `PHPForge\Inertia\` namespace through PSR-4 autoloading.

## Integration prerequisite

The package intentionally provides protocol data rather than an HTTP integration. Before using it in an application,
implement a small adapter that can:

1. Build a `RequestContext` from the active HTTP request.
2. Build a `PageInput` from application data.
3. Translate the returned protocol result into the application's response type.
4. Render the root HTML document for `InitialPageResult`.

Continue with the [configuration and adapter reference](configuration.md).
