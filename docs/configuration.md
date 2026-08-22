# Configuration and adapter reference

The core has no global configuration or mutable static state. All request-specific values are explicit.

## Request context

Create one `RequestContext` per request with:

- the HTTP method;
- the rooted relative URL, including its query string;
- the absolute request URL;
- the supported Inertia request headers.

Header names are case-insensitive. Unsupported headers are intentionally discarded. The context recognizes:

- `X-Inertia`
- `X-Inertia-Version`
- `X-Inertia-Partial-Component`
- `X-Inertia-Partial-Data`
- `X-Inertia-Partial-Except`
- `X-Inertia-Reset`
- `X-Inertia-Error-Bag`
- `X-Inertia-Infinite-Scroll-Merge-Intent`
- `X-Inertia-Except-Once-Props`
- `Purpose`

The context contains request metadata only. Prop callbacks receive no request object. Capture explicitly prepared values in a
zero-argument closure when a prop needs application context.

## Page input

`PageInput` requires a component, a page-prop map, and an asset version. It also accepts:

- shared props;
- validation errors;
- flash data;
- `encryptHistory`, `clearHistory`, and `preserveFragment` flags;
- `exposeSharedProps`, which controls the top-level `sharedProps` metadata list.

The `errors` key is reserved and must be supplied through the dedicated `errors` argument. Empty errors are serialized as
an object in the page JSON. If `X-Inertia-Error-Bag` is present, non-empty errors are nested under that bag.

Shared props are merged before page props, so a page prop with the same top-level key wins. Top-level dot-notation keys are
expanded before resolution.

## Prop values

Use `PHPForge\Inertia\Prop\Prop` to create protocol-aware values:

| Factory                                     | Behavior                                                                                |
| ------------------------------------------- | --------------------------------------------------------------------------------------- |
| `Prop::always($value)`                      | Includes the value during partial reloads regardless of the requested prop filters.     |
| `Prop::optional($callback)`                 | Omits the prop from full visits and resolves it only when selected by a partial reload. |
| `Prop::defer($callback, $group, $rescue)`   | Omits the prop from full visits and announces a deferred group.                         |
| `Prop::merge($value)`                       | Adds append, prepend, deep-merge, and match metadata.                                   |
| `Prop::once($callback)`                     | Adds client cache metadata and skips cached values on later full Inertia visits.        |
| `Prop::scroll($value, $metadata, $wrapper)` | Adds infinite-scroll merge and pagination metadata.                                     |

Prop callbacks are exact zero-argument closures. A scroll metadata closure is the only exception: it receives the resolved
scroll value and must return `ScrollMetadata`.

Modifiers return new immutable values. Supported compositions include:

```php
$reports = Prop::defer(fn (): array => loadReports(), 'reports')
    ->deepMerge()
    ->once()
    ->as('report-data')
    ->until(3600);

$users = Prop::merge(fn (): array => loadUsers())
    ->append('data', 'id')
    ->once();

$filters = Prop::optional(fn (): array => loadFilters())->once();
```

`until()` accepts seconds, `DateInterval`, or `DateTimeInterface`. Expiration metadata uses Unix milliseconds. Inject a
custom `Clock` into `Protocol` when deterministic time is required.

## Low-level public API

Every package class is a public contract. Advanced integrations may use `PHPForge\Inertia\Resolution\PropResolver`
directly when they need resolved page data without protocol result negotiation. `PropDefinition`, `ResolvedPageData`, and
`ResolvedProp` in the same namespace expose the corresponding resolution values.

`PHPForge\Inertia\Support\DotArray` provides the package's strict dot-notation expansion, while
`PHPForge\Inertia\Support\JsonValue` validates and normalizes prop values. These classes are public contracts; the
`Support` namespace only groups low-level utilities.

## Page results

Call `Protocol::page($request, $input)`. The adapter maps the returned value as follows:

| Result                  | Status | Adapter body responsibility                                         |
| ----------------------- | -----: | ------------------------------------------------------------------- |
| `InitialPageResult`     |    200 | Render the root HTML document and embed `page()` as JSON.           |
| `InertiaPageResult`     |    200 | Encode `page()` as the JSON response body.                          |
| `VersionConflictResult` |    409 | Return an empty body and let the client perform the location visit. |

Apply every entry returned by `headers()`. `VersionConflictResult` deliberately omits `X-Inertia`; it provides
`X-Inertia-Location`, the current `X-Inertia-Version`, and `Vary: X-Inertia`.

Version conflicts are checked before any prop callback executes. They apply only to Inertia GET requests that provide a
different version header.

## Redirect and location results

Use `Protocol::location()` for an absolute external location visit. Standard requests receive `RedirectResult`; Inertia
requests receive `LocationResult` with status 409 and `X-Inertia-Location`.

Use `Protocol::redirect()` for ordinary redirects. A 302 redirect after an Inertia PUT, PATCH, or DELETE request becomes 303. A non-prefetch Inertia redirect containing a URL fragment becomes `FragmentRedirectResult` with status 409 and
`X-Inertia-Redirect`.

## Failure handling

A callback or JSON normalization failure throws `PropResolutionException`. Its `propPath()` identifies the failing prop.

A deferred prop configured with `rescue: true` is omitted instead. The page lists it in `rescuedProps`, and the page result
exposes each original throwable through `rescuedFailures()`. The adapter may report those failures through its own logging
or exception-reporting facility.

## Adapter responsibilities

The core does not perform any of the following tasks:

- read a framework request or create a framework response;
- access sessions, service containers, routes, or views;
- persist, pull, or reflash flash data;
- discover validation errors or select an asset version;
- render the root template or encode a response body;
- manage CSRF, middleware ordering, server-side rendering, or asset bundlers.

These responsibilities remain explicit in the adapter so the protocol core stays deterministic and reusable.
