<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Debug;

use Closure;
use InvalidArgumentException;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\Header;
use PHPForge\Inertia\Result\{FragmentRedirectResult, LocationResult, PageResult, RedirectResult, VersionConflictResult};
use Psr\EventDispatcher\EventDispatcherInterface;

use function is_string;

/**
 * Listens to protocol results and sanitizes their resolved values only at capture.
 *
 * Doubles as a single-listener {@see EventDispatcherInterface}, so a host without a PSR-14 dispatcher passes the
 * collector itself to {@see \PHPForge\Inertia\Protocol} instead of authoring one.
 */
final class InertiaCollector implements CollectorInterface, EventDispatcherInterface
{
    /**
     * @var list<string> Inertia protocol request headers preserved in the capture.
     */
    private const array REQUEST_HEADERS = [
        Header::INERTIA->value,
        Header::PARTIAL_COMPONENT->value,
        Header::PARTIAL_DATA->value,
        Header::PARTIAL_EXCEPT->value,
        Header::RESET->value,
        Header::ERROR_BAG->value,
        Header::EXCEPT_ONCE_PROPS->value,
        Header::INFINITE_SCROLL_MERGE_INTENT->value,
        Header::VERSION->value,
    ];
    /**
     * @var ProtocolResultCreated|null Protocol result observed in the active cycle, or `null` when none occurred.
     */
    private ProtocolResultCreated|null $event = null;
    /**
     * @var Closure(array<string, mixed>): array<string, mixed> Redaction policy applied to the whole payload.
     */
    private readonly Closure $sanitize;
    /**
     * @var Closure(string): string Redaction policy applied to page and redirect URLs.
     */
    private readonly Closure $sanitizeUrl;
    /**
     * @var bool Whether the collector is observing the active cycle.
     */
    private bool $started = false;

    /**
     * @param (Closure(array<string, mixed>): array<string, mixed>)|null $sanitize Sanitizes the complete diagnostic
     * payload, including headers and shared keys, or `null` to persist the captured values unchanged.
     * @param (Closure(string): string)|null $sanitizeUrl Sanitizes page and redirect URLs, or `null` to persist them
     * unchanged.
     */
    public function __construct(Closure|null $sanitize = null, Closure|null $sanitizeUrl = null)
    {
        $this->sanitize = $sanitize ?? self::keepPayload(...);
        $this->sanitizeUrl = $sanitizeUrl ?? self::keepUrl(...);
    }

    /**
     * Records the protocol result while the collector observes the active cycle.
     *
     * @param ProtocolResultCreated $event Protocol result dispatched by the Inertia protocol.
     */
    public function __invoke(ProtocolResultCreated $event): void
    {
        if ($this->started) {
            $this->event = $event;
        }
    }

    /**
     * Returns the sanitized diagnostics for the protocol result observed in the active cycle.
     *
     * @throws InvalidArgumentException If the observed result is not a supported Inertia protocol result.
     *
     * @return array<string, mixed>|null Sanitized diagnostics, or `null` when no protocol result was observed.
     */
    public function capture(): array|null
    {
        $event = $this->event;

        if ($event === null) {
            return null;
        }

        $headers = [];

        foreach (self::REQUEST_HEADERS as $name) {
            $value = $event->request->header($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        $result = $event->result;

        $page = $result instanceof PageResult ? $result->page()->toArray() : null;

        if (is_string($page['url'] ?? null)) {
            $page['url'] = ($this->sanitizeUrl)($page['url']);
        }

        [$type, $location] = match (true) {
            $result instanceof PageResult => ['page', null],
            $result instanceof VersionConflictResult => ['version-conflict', $result->url],
            $result instanceof LocationResult => ['location', $result->url],
            $result instanceof FragmentRedirectResult => ['fragment-redirect', $result->url],
            $result instanceof RedirectResult => ['redirect', $result->url],
            default => throw new InvalidArgumentException(
                Message::DIAGNOSTICS_RESULT_UNSUPPORTED->getMessage(),
            ),
        };

        return ($this->sanitize)(
            [
                'location' => $location === null ? null : ($this->sanitizeUrl)($location),
                'page' => $page,
                'requestHeaders' => $headers,
                'sharedKeys' => $result instanceof PageResult ? $result->page()->sharedProps() : [],
                'statusCode' => $result->statusCode(),
                'resultType' => $type,
            ],
        );
    }

    /**
     * Forwards a created protocol result to this collector.
     *
     * @param object $event Dispatched event; anything other than a {@see ProtocolResultCreated} is returned untouched.
     *
     * @return object The dispatched event.
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof ProtocolResultCreated) {
            $this($event);
        }

        return $event;
    }

    /**
     * Returns the stable ID associating the capture with the Inertia panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'inertia';
    }

    /**
     * Stops observing and discards the protocol result captured in the completed cycle.
     */
    public function shutdown(): void
    {
        $this->started = false;
        $this->event = null;
    }

    /**
     * Starts observing protocol results for a new cycle.
     */
    public function startup(): void
    {
        $this->started = true;
    }

    /**
     * Returns the diagnostic payload unchanged, the default when the host supplies no redaction policy.
     *
     * @param array<string, mixed> $payload Captured diagnostic payload.
     *
     * @return array<string, mixed> Captured diagnostic payload, unchanged.
     */
    private static function keepPayload(array $payload): array
    {
        return $payload;
    }

    /**
     * Returns the URL unchanged, the default when the host supplies no redaction policy.
     *
     * @param string $url Captured page or redirect URL.
     *
     * @return string Captured URL, unchanged.
     */
    private static function keepUrl(string $url): string
    {
        return $url;
    }
}
