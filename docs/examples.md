# Usage examples

## Initial or Inertia page

The same call produces an initial-page result or an Inertia JSON-page result according to the request headers.

```php
use PHPForge\Inertia\{PageInput, Protocol, RequestContext};

$context = new RequestContext(
    method: $adapter->method(),
    url: $adapter->relativeUrl(),
    absoluteUrl: $adapter->absoluteUrl(),
    headers: $adapter->inertiaHeaders(),
);

$result = (new Protocol())->page(
    $context,
    new PageInput(
        component: 'Dashboard',
        props: ['metrics' => fn (): array => $metrics->summary()],
        version: $assets->version(),
        sharedProps: ['auth.user' => $currentUser],
        errors: $adapter->validationErrors(),
        flash: $adapter->pullFlashData(),
    ),
);
```

The `$adapter` object in this example belongs to the application integration. The core never receives it inside a prop
callback.

## Partial, optional, and deferred props

```php
use PHPForge\Inertia\Prop\Prop;

$props = [
    'users' => fn (): array => $repository->users(),
    'filters' => Prop::optional(fn (): array => $repository->filters()),
    'analytics' => Prop::defer(
        fn (): array => $analytics->report(),
        group: 'analytics',
        rescue: true,
    ),
    'csrf' => Prop::always($csrfToken),
];
```

The full page omits `filters` and `analytics`. A matching partial request resolves only the selected values. A rescued
analytics failure appears in `rescuedProps` without hiding the original throwable from the adapter.

## Merge and infinite scroll metadata

```php
use PHPForge\Inertia\Prop\{Prop, ScrollMetadata};

$props = [
    'messages' => Prop::merge(fn (): array => $messages)->prepend(),
    'users' => Prop::merge(fn (): array => $users)->append('data', 'id'),
    'settings' => Prop::merge(fn (): array => $settings)->deepMerge()->matchOn('items.id'),
    'posts' => Prop::scroll(
        fn (): array => $posts,
        fn (array $resolved): ScrollMetadata => new ScrollMetadata(
            pageName: 'page',
            previousPage: $resolved['previous_page'],
            nextPage: $resolved['next_page'],
            currentPage: $resolved['current_page'],
        ),
    ),
];
```

The scroll merge direction is derived from `X-Inertia-Infinite-Scroll-Merge-Intent`. `X-Inertia-Reset` suppresses merge
metadata for the requested prop while keeping scroll metadata with `reset: true`.

## Once props

```php
$props = [
    'plans' => Prop::once(fn (): array => $billing->plans())
        ->as('billing-plans')
        ->until(new DateInterval('PT1H')),
];
```

On later full Inertia visits, `X-Inertia-Except-Once-Props: billing-plans` skips the callback but preserves `onceProps`
metadata. Explicit partial reloads and `fresh()` force resolution.

## Neutral result translation

An adapter can use exhaustive type checks without requiring the core to know its response implementation.

```php
use PHPForge\Inertia\Result\{InertiaPageResult, InitialPageResult, PageResult};

$headers = $result->headers();

if ($result instanceof InertiaPageResult) {
    return $adapter->json($result->page(), $result->statusCode(), $headers);
}

if ($result instanceof InitialPageResult) {
    return $adapter->html(
        $adapter->renderRoot(['page' => $result->page()]),
        $result->statusCode(),
        $headers,
    );
}

if ($result instanceof PageResult) {
    throw new LogicException('Unsupported page result.');
}

return $adapter->empty($result->statusCode(), $headers);
```

## Redirects

```php
$external = $protocol->location($context, 'https://accounts.example.com/login');
$redirect = $protocol->redirect($context, '/users#profile');
```

The adapter applies the neutral result status and headers. It remains responsible for session reflashing when the
application's redirect lifecycle requires it.

## Next steps

- 📚 [Installation guide](installation.md)
- ⚙️ [Configuration and adapter reference](configuration.md)
- 🧪 [Testing guide](testing.md)
