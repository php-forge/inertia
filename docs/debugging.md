# Inertia diagnostics

## PSR-14, and nothing else

`Protocol` emits `PHPForge\Inertia\Event\ProtocolResultCreated` through an optional
`Psr\EventDispatcher\EventDispatcherInterface`. The application classes never import a debug contract or call a
collector, and installing the contracts does not activate a debugger.

Events beat logging here: resolved props, URLs and negotiation metadata must not reach the ordinary application log.

The event carries the validated request context and the returned protocol result. The collector retains the latest
result of the active request, without resolving props or serializing them at dispatch. Pages, version conflicts,
external locations, fragment redirects and ordinary redirects stay distinct, and the `resultType` field keeps a
navigation response with status `409` from being mislabeled as a version conflict.

The capture is the **protocol result**, not a later middleware-modified HTTP response; inspect the host's Request
panel for the final status. The Yii3 adapter performs a preliminary protocol page call before the final one; the last
result replaces the preliminary one, and the debugger repeats neither invocation.

## Development wiring

The package ships one collector and one panel, `PHPForge\Inertia\Debug\InertiaCollector` and
`PHPForge\Inertia\Debug\InertiaPanel`; the host never reimplements collection or presentation. In any framework: pass
`InertiaCollector` as the `eventDispatcher` of `Protocol::create()` or register it as a listener of
`ProtocolResultCreated` on your dispatcher, build it with the host redaction callbacks, and register the collector and
`InertiaPanel` with your debugger under the ID `inertia`. The debuggers name no provider package, so the wiring below
lives in the framework adapter or the application.

### Yii3, from `yii3/debug` 0.1.0 and `yii3/inertia` 0.1.0

`yii3/inertia` declares the three entries in its own configuration groups, so an application using it adds nothing:

```php
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Event\ProtocolResultCreated;

// params.php
'yii3/debug' => [
    'collectors' => ['inertia' => InertiaCollector::class],
    'panels' => ['inertia' => InertiaPanel::class],
],

// events-web.php
ProtocolResultCreated::class => [InertiaCollector::class],

// di-web.php, only when Debug Core is installed
InertiaCollector::class => static fn(CapturePolicy $policy): InertiaCollector => new InertiaCollector(
    $policy->redact(...),
    $policy->redactUrl(...),
),
```

`yii3/debug` resolves the collector from the container, the same instance the event dispatcher calls, and the
container autowires `Psr\EventDispatcher\EventDispatcherInterface` into `Protocol`. Without the debugger installed the
params entry is inert, the listener only buffers, and the container definition is skipped. Disable the capture with
`enabled => false` on both the collector and the panel entry.

### Yii2, from `yii2-extensions/debug` 0.2.0

`yii2-extensions/debug` 0.2.0 introduces both provider-owned panels and the `dispatchers` option; the 0.1.x
releases support neither, so the registration below requires 0.2.0 or later.

Yii2 has no framework-native PSR-14 dispatcher. `Protocol` emits exactly one event type, so `InertiaCollector` is its
own single-listener dispatcher, and the module's `dispatchers` option hands it to the `yii\inertia\Manager` component
through its `eventDispatcher` property. Inside the existing `YII_DEBUG` configuration guard:

```php
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};

$config['modules']['debug']['collectors']['inertia'] = static fn(CapturePolicy $policy): InertiaCollector
    => new InertiaCollector($policy->redact(...), $policy->redactUrl(...));
$config['modules']['debug']['panels']['inertia'] = InertiaPanel::class;
// Collector ID => the component ID the application already uses for `yii\inertia\Manager`.
$config['modules']['debug']['dispatchers']['inertia'] = 'inertia';
```

The closure receives the module `CapturePolicy`, so props and URLs are redacted with the rules the Request panel
applies; a bare `InertiaCollector::class` entry keeps the captured values unchanged. A component that configures
`eventDispatcher` or `protocol` itself keeps what it configures.

If the application already owns a real PSR-14 dispatcher, register the collector as a listener on it and inject that
dispatcher instead; never replace a populated dispatcher with an empty one. Inject the dispatcher into the **actual**
`Protocol` service, not into a duplicate diagnostic-only one. Omitting it is safe: the protocol behaves normally and
the panel simply stays empty.

## Lifecycle, privacy, and errors

Register the collector once and let the host drive it. `startup()` enables the listener without discarding current
observations if called twice; `shutdown()` disables it and clears references. Events outside that window are ignored.

A collector with no protocol result captures `null`, because it cannot invent an HTTP status or a page. Disabled
collection also captures `null`.

The two callbacks run at capture, never inside the protocol operation: the first sanitizes the **complete capture
array** (page props, request headers and shared-key metadata) and the second sanitizes page and navigation URLs. The
result must still satisfy the presenter schema; a policy failure or unencodable payload becomes an isolated host
capture failure.

Attach the event only to trusted listeners: it carries real application data, and capture-time redaction does not
protect it from an unrelated listener that logs it, so never point an unrestricted event dumper at it. Listener
exceptions propagate per PSR-14; they are never swallowed and never retried. The supplied listener only buffers the
event.

## Compatibility

Hosts previously shipped their own Inertia collector and panel; both were removed, so the `inertia` ID is free for the
provider-owned objects an application registers itself. An extension now declares its own ID, icon and title. A capture
written by the removed host collector is not decoded, but stays readable through the host's raw JSON fallback.

`eventDispatcher` is an optional trailing argument: calls without one behave normally and emit no events.
`ResolvedPageObserver` is an independent published feature with its own tests and documentation; it was never part of
this capture path and is unchanged. The branch aliases still describe an unreleased linked prototype, not a published
release.

## How this is verified

- The full PHPUnit suite runs through this package's own autoloader, without Debug Core installed.
- `python3 tools/check-provider-consumer.py` (in `php-forge/debug`) exports the package and its locked production
  dependencies into a temporary mirror, installs with Packagist and plugins disabled, then exercises real resolution,
  sanitization, cleanup and replay with no debugger or framework present.
- `DEBUG_UI_SEED_FIXTURES=0 npx playwright test e2e/provider-events.spec.js` (in Debug Core) checks persisted values
  after a changed request: navigation, props/JSON, non-conflict `409`, sanitization, accessibility, and both themes and
  viewport sizes.

Reference: [PSR-14](https://www.php-fig.org/psr/psr-14/).

---

[← Back to documentation](../README.md#documentation)
