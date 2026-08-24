<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

/**
 * Represents an Inertia redirect whose URL fragment must be handled by the client.
 */
final readonly class FragmentRedirectResult implements ProtocolResult
{
    /**
     * @param string $url Absolute URL including the fragment, sent to the client via `X-Inertia-Redirect`.
     */
    public function __construct(public string $url) {}

    /**
     * Returns the Inertia fragment-redirect headers.
     *
     * @return array<string, string> HTTP response headers to send with the fragment-redirect response.
     */
    public function headers(): array
    {
        return [
            'X-Inertia-Redirect' => $this->url,
            'Vary' => 'X-Inertia',
        ];
    }

    /**
     * Returns the HTTP status code (`409`) used by the Inertia fragment-redirect protocol.
     *
     * @return int HTTP status code for the fragment-redirect response.
     */
    public function statusCode(): int
    {
        return 409;
    }
}
