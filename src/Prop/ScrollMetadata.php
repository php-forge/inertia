<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function preg_match;

/**
 * Carries pagination state for an infinite-scroll prop.
 */
final readonly class ScrollMetadata
{
    /**
     * @param string $pageName The name of the page for this scroll prop.
     * @param string|int|null $previousPage The previous page cursor, or `null` if there is no previous page.
     * @param string|int|null $nextPage The next page cursor, or `null` if there is no next page.
     * @param string|int|null $currentPage The current page cursor, or `null` if there is no current page.
     *
     * @throws InvalidPropException When `$pageName` is empty or contains control characters.
     */
    public function __construct(
        public string $pageName,
        public string|int|null $previousPage,
        public string|int|null $nextPage,
        public string|int|null $currentPage,
    ) {
        if ($pageName === '' || preg_match('/[\x00-\x1F\x7F]/', $pageName) === 1) {
            throw new InvalidPropException(
                Message::SCROLL_PAGE_NAME_INVALID->getMessage(),
            );
        }
    }

    /**
     * Returns the pagination state as an array for the Inertia protocol.
     *
     * @param bool $reset Whether to include a reset flag in the returned array.
     *
     * @return array{
     *   pageName: string,
     *   previousPage: int|string|null,
     *   nextPage: int|string|null,
     *   currentPage: int|string|null,
     *   reset: bool
     * } Pagination state map including page name, adjacent cursors, and reset flag.
     */
    public function toArray(bool $reset = false): array
    {
        return [
            'pageName' => $this->pageName,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
            'reset' => $reset,
        ];
    }
}
