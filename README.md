<!-- markdownlint-disable MD041 -->
<p align="center">
    <a href="https://github.com/php-forge/inertia" target="_blank">
      <img src="https://avatars.githubusercontent.com/u/103309199?s=400&u=ca3561c692f53ed7eb290d3bb226a2828741606f&v=4" width="30%" alt="PHP Forge">
    </a>
    <h1 align="center">Inertia</h1>
    <br>
</p>
<!-- markdownlint-enable MD041 -->

<p align="center">
    <a href="https://github.com/php-forge/inertia/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/inertia/build.yml?style=for-the-badge&label=PHPUnit&logo=github" alt="PHPUnit">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/php-forge/inertia/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fphp-forge%2Finertia%2Fmain" alt="Mutation Testing">
    </a>
    <a href="https://github.com/php-forge/inertia/actions/workflows/ecs.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/inertia/ecs.yml?style=for-the-badge&label=ECS&logo=github" alt="Easy Coding Standard">
    </a>
    <a href="https://github.com/php-forge/inertia/actions/workflows/dependency-check.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/inertia/dependency-check.yml?style=for-the-badge&label=Dependency%20Check&logo=github" alt="Dependency Check">
    </a>
</p>

<p align="center">
    <strong>A framework-agnostic PHP core for negotiating and producing current Inertia.js protocol data.</strong>
</p>

## Features

<picture>
    <source media="(min-width: 768px)" srcset="./docs/svgs/features.svg">
    <img src="./docs/svgs/features-mobile.svg" alt="Feature overview" style="width: 100%;">
</picture>

## Installation

```bash
composer require php-forge/inertia:^0.2
```

PHP 8.3 or later and the JSON extension are required.

## Quick start

```php
use PHPForge\Inertia\{PageInput, Protocol, RequestContext};
use PHPForge\Inertia\Prop\Prop;

$request = new RequestContext(
    method: 'GET',
    url: '/users?page=1',
    absoluteUrl: 'https://example.com/users?page=1',
    headers: [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'assets-v1',
    ],
);

$input = PageInput::create(
    component: 'Users/Index',
    props: [
        'users' => Prop::merge(fn (): array => loadUsers())->append('data', 'id'),
        'analytics' => Prop::defer(fn (): array => loadAnalytics(), group: 'analytics'),
        'filters' => Prop::optional(fn (): array => availableFilters()),
    ],
    version: 'assets-v1',
)
->withSharedProps(
    [
        'auth' => Prop::once(fn (): array => currentUser())->as('authenticated-user'),
    ],
)
->withErrors(validationErrors())
->withFlash(flashedData());

$result = Protocol::create()->page($request, $input);
```

`Protocol` does not return a framework response. An adapter inspects the result, applies `statusCode()` and `headers()`,
serializes `page()` for an Inertia visit, or embeds the page JSON in the root HTML document for an initial visit.

## Adapter boundary

The core owns protocol decisions and page shaping. A framework adapter remains responsible for:

- extracting the method, relative URL, absolute URL, and supported request headers;
- resolving shared props, validation errors, flash data, and the asset version;
- rendering the root HTML document and embedding the initial page JSON;
- translating neutral results into its HTTP response implementation;
- managing sessions, flash persistence or reflashing, redirects, routes, CSRF, and asset tooling;
- JSON encoding the final page with the framework application's selected flags.

See the [configuration reference](docs/configuration.md) for the complete result mapping.

## Documentation

- 📚 [Installation guide](docs/installation.md)
- ⚙️ [Configuration and adapter reference](docs/configuration.md)
- 💡 [Usage examples](docs/examples.md)
- 🧪 [Testing guide](docs/testing.md)
- 📖 [Inertia.js protocol documentation](https://inertiajs.com/docs/v3/core-concepts/the-protocol)

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![Latest Stable Version](https://img.shields.io/packagist/v/php-forge/inertia.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Stable)](https://packagist.org/packages/php-forge/inertia)
[![Total Downloads](https://img.shields.io/packagist/dt/php-forge/inertia.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/php-forge/inertia)

## Code quality

[![Codecov](https://img.shields.io/codecov/c/github/php-forge/inertia.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/gh/php-forge/inertia)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/php-forge/inertia/actions/workflows/static.yml)
[![Quality](https://img.shields.io/github/actions/workflow/status/php-forge/inertia/quality.yml?style=for-the-badge&label=Quality&logo=github)](https://github.com/php-forge/inertia/actions/workflows/quality.yml)
[![StyleCI](https://img.shields.io/badge/StyleCI-Passed-44CC11.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.styleci.io/repos/1342862957?branch=main)

## Social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)
