<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function preg_match;

final readonly class ScrollMetadata
{
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
     * @return array{
     *   pageName: string,
     *   previousPage: int|string|null,
     *   nextPage: int|string|null,
     *   currentPage: int|string|null,
     *   reset: bool
     * }
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
