<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use PHPForge\Inertia\Exception\{InvalidPageInputException, Message};

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function trim;

/**
 * Carries validated application data and page options into an Inertia page operation.
 */
final readonly class PageInput
{
    /**
     * @param string $component The name of the front-end component to render.
     * @param array<string, mixed> $props Page-specific props, merged over shared props during resolution.
     * @param int|string $version The current page version, used to detect stale pages on the client.
     * @param array<string, mixed> $sharedProps Props shared across all pages, overridden by page-specific props.
     * @param array<array-key, mixed> $errors Validation error messages keyed by field name.
     * @param array<string, mixed> $flash Flash data passed alongside the page response.
     * @param bool $encryptHistory Whether to encrypt the page history for this request.
     * @param bool $clearHistory Whether to clear the page history for this request.
     * @param bool $preserveFragment Whether to preserve the URL fragment for this request.
     * @param bool $exposeSharedProps Whether to expose the shared props to the client for awareness, even if they are
     * not used by the page component.
     */
    public function __construct(
        public string $component,
        public array $props,
        public string|int $version,
        public array $sharedProps = [],
        public array $errors = [],
        public array $flash = [],
        public bool $encryptHistory = false,
        public bool $clearHistory = false,
        public bool $preserveFragment = false,
        public bool $exposeSharedProps = true,
    ) {
        if (trim($component) === '' || preg_match('/[\x00-\x1F\x7F]/', $component) === 1) {
            throw new InvalidPageInputException(
                Message::COMPONENT_NAME_INVALID->getMessage(),
            );
        }

        self::validateKeys($props, 'page props');
        self::validateKeys($sharedProps, 'shared props');
        self::validateKeys($flash, 'flash data');

        if (array_key_exists('errors', $props) || array_key_exists('errors', $sharedProps)) {
            throw new InvalidPageInputException(
                Message::RESERVED_ERRORS_PROP->getMessage(),
            );
        }

        foreach ($errors as $field => $messages) {
            self::validateKey($field, 'error fields');

            if (is_string($messages)) {
                continue;
            }

            if (!is_array($messages) || !array_is_list($messages)) {
                throw new InvalidPageInputException(
                    Message::ERROR_FIELD_INVALID->getMessage(),
                );
            }

            foreach ($messages as $message) {
                if (!is_string($message)) {
                    throw new InvalidPageInputException(
                        Message::VALIDATION_ERROR_MESSAGE_INVALID->getMessage(),
                    );
                }
            }
        }
    }

    /**
     * Validates a prop or shared-prop key.
     *
     * @param mixed $key The candidate key; must be a non-empty string without control characters or empty segments.
     * @param string $label Human-readable label used in the error message (for example, `'prop'` or `'shared prop'`).
     *
     * @throws InvalidPageInputException When `$key` fails validation.
     */
    private static function validateKey(mixed $key, string $label): void
    {
        if (
            !is_string($key)
            || $key === ''
            || preg_match('/[\x00-\x1F\x7F,]/', $key) === 1
            || in_array('', explode('.', $key), true)
        ) {
            throw new InvalidPageInputException(
                Message::PAGE_KEY_INVALID->getMessage($label),
            );
        }
    }

    /**
     * Validates every key in `$values` using {@see validateKey()}.
     *
     * @param array<array-key, mixed> $values The prop map whose keys to validate.
     */
    private static function validateKeys(array $values, string $label): void
    {
        foreach (array_keys($values) as $key) {
            self::validateKey($key, $label);
        }
    }
}
