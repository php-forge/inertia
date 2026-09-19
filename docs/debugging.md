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
`PHPForge\Inertia\Debug\InertiaPanel`; the host never reimplements collection or presentation. What differs between
frameworks is only how the dispatcher reaches the `PHPForge\Inertia\Protocol` service.

### Yii3, nothing to add

`yii3/debug` registers the collector and the panel in its packaged parameters as soon as this package is installed,
routes `ProtocolResultCreated` to that collector from its packaged `events-web` group, and builds the collector with
the host `CapturePolicy` redaction callbacks. The container autowires `Psr\EventDispatcher\EventDispatcherInterface`
into `Protocol`, so the application adds nothing. Disable both packaged entries with `enabled => false` when the
capture is unwanted.

### Yii2, nothing to add

Yii2 has no framework-native PSR-14 dispatcher, and its DI container does not autowire optional constructor arguments.
`Protocol` emits exactly one event type, so `InertiaCollector` is its own single-listener dispatcher.
`yii2-extensions/debug` registers the collector and the panel once this package is installed, builds the collector
with the module capture policy, and hands it to the `inertia` component through
`yii\inertia\Manager::$eventDispatcher` before the request runs. A component that configures `eventDispatcher` or
`protocol` itself keeps what it configures; disable the packaged entries with `enabled => false` when the capture is
unwanted.

If the application already owns a real PSR-14 dispatcher, register the collector as a listener on it and inject that
dispatcher instead; never replace a populated dispatcher with an empty one.

`yii2-extensions/debug` no longer ships an Inertia collector or panel, so the `inertia` ID is free: the module wraps
the portable objects in its generic adapters and groups them under Extensions. Pass `$policy->redact(...)` and
`$policy->redactUrl(...)` to `InertiaCollector` to apply the host redaction rules; the default keeps captured values
unchanged. Retain the existing module bootstrap, routing, access rules, and asset configuration. The public
`yii\inertia\Manager::$protocol` property accepts the instrumented core service.

Inject the dispatcher into the **actual** Protocol service, not into a duplicate diagnostic-only one. Omitting it is
safe: the protocol behaves normally and the panel simply stays empty.

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
