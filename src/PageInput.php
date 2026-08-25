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
final class PageInput
{
    /**
     * Whether to clear the page history for this request.
     */
    private bool $clearHistory = false;

    /**
     * Whether to encrypt the page history for this request.
     */
    private bool $encryptHistory = false;

    /**
     * @var array<array-key, mixed> Validation error messages keyed by field name.
     */
    private array $errors = [];

    /**
     * Whether to expose shared props to the client for awareness.
     */
    private bool $exposeSharedProps = true;

    /**
     * @var array<string, mixed> Flash data passed alongside the page response.
     */
    private array $flash = [];

    /**
     * Whether to preserve the URL fragment for this request.
     */
    private bool $preserveFragment = false;

    /**
     * @var array<string, mixed> Props shared across all pages, overridden by page-specific props.
     */
    private array $sharedProps = [];

    /**
     * @param string $component The name of the front-end component to render.
     * @param array<string, mixed> $props Page-specific props, merged over shared props during resolution.
     * @param string|int $version The current page version, used to detect stale pages on the client.
     */
    public function __construct(
        public readonly string $component,
        public readonly array $props,
        public readonly string|int $version,
    ) {
        if (trim($component) === '' || preg_match('/[\x00-\x1F\x7F]/', $component) === 1) {
            throw new InvalidPageInputException(
                Message::COMPONENT_NAME_INVALID->getMessage(),
            );
        }

        self::validateProps($props, 'page props');
    }

    /**
     * Returns whether the page history should be cleared for this request.
     *
     * @return bool Whether the page history should be cleared for this request.
     */
    public function clearHistory(): bool
    {
        return $this->clearHistory;
    }

    /**
     * Returns whether the page history should be encrypted for this request.
     *
     * @return bool Whether the page history should be encrypted for this request.
     */
    public function encryptHistory(): bool
    {
        return $this->encryptHistory;
    }

    /**
     * Returns the validation error messages keyed by field name.
     *
     * @return array<array-key, mixed> Validation error messages keyed by field name.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Returns whether shared props should be exposed to the client for awareness.
     *
     * @return bool Whether shared props should be exposed to the client for awareness.
     */
    public function exposesSharedProps(): bool
    {
        return $this->exposeSharedProps;
    }

    /**
     * Returns the flash data passed alongside the page response.
     *
     * @return array<string, mixed> Flash data passed alongside the page response.
     */
    public function flash(): array
    {
        return $this->flash;
    }

    /**
     * Returns whether the URL fragment should be preserved for this request.
     *
     * @return bool Whether the URL fragment should be preserved for this request.
     */
    public function preserveFragment(): bool
    {
        return $this->preserveFragment;
    }

    /**
     * Returns the props shared across all pages.
     *
     * @return array<string, mixed> Props shared across all pages, overridden by page-specific props.
     */
    public function sharedProps(): array
    {
        return $this->sharedProps;
    }

    /**
     * Returns a new input with the clear-history option replaced.
     *
     * @param bool $enabled Whether to clear the page history for this request.
     *
     * @return PageInput A new input with the clear-history option replaced.
     */
    public function withClearHistory(bool $enabled = true): PageInput
    {
        $clone = clone $this;
        $clone->clearHistory = $enabled;

        return $clone;
    }

    /**
     * Returns a new input with the encrypt-history option replaced.
     *
     * @param bool $enabled Whether to encrypt the page history for this request.
     *
     * @return PageInput A new input with the encrypt-history option replaced.
     */
    public function withEncryptHistory(bool $enabled = true): PageInput
    {
        $clone = clone $this;
        $clone->encryptHistory = $enabled;

        return $clone;
    }

    /**
     * Returns a new input with the validation errors replaced.
     *
     * @param array<array-key, mixed> $errors Validation error messages keyed by field name.
     *
     * @return PageInput A new input with the validation errors replaced.
     */
    public function withErrors(array $errors): PageInput
    {
        self::validateErrors($errors);

        $clone = clone $this;
        $clone->errors = $errors;

        return $clone;
    }

    /**
     * Returns a new input with the flash data replaced.
     *
     * @param array<string, mixed> $flash Flash data passed alongside the page response.
     *
     * @return PageInput A new input with the flash data replaced.
     */
    public function withFlash(array $flash): PageInput
    {
        self::validateKeys($flash, 'flash data');

        $clone = clone $this;
        $clone->flash = $flash;

        return $clone;
    }

    /**
     * Returns a new input with the preserve-fragment option replaced.
     *
     * @param bool $enabled Whether to preserve the URL fragment for this request.
     *
     * @return PageInput A new input with the preserve-fragment option replaced.
     */
    public function withPreserveFragment(bool $enabled = true): PageInput
    {
        $clone = clone $this;
        $clone->preserveFragment = $enabled;

        return $clone;
    }

    /**
     * Returns a new input with the shared props replaced.
     *
     * @param array<string, mixed> $sharedProps Props shared across all pages, overridden by page-specific props.
     *
     * @return PageInput A new input with the shared props replaced.
     */
    public function withSharedProps(array $sharedProps): PageInput
    {
        self::validateProps($sharedProps, 'shared props');

        $clone = clone $this;
        $clone->sharedProps = $sharedProps;

        return $clone;
    }

    /**
     * Returns a new input with the shared-prop exposure option replaced.
     *
     * @param bool $enabled Whether to expose shared props to the client for awareness.
     *
     * @return PageInput A new input with the shared-prop exposure option replaced.
     */
    public function withSharedPropsExposure(bool $enabled = true): PageInput
    {
        $clone = clone $this;
        $clone->exposeSharedProps = $enabled;

        return $clone;
    }

    /**
     * Validates validation error messages keyed by field name.
     *
     * @param array<array-key, mixed> $errors Validation error messages keyed by field name.
     */
    private static function validateErrors(array $errors): void
    {
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

    /**
     * Validates prop keys and rejects the reserved `errors` prop.
     *
     * @param array<array-key, mixed> $props The prop map whose keys to validate.
     * @param string $label Human-readable label used in validation errors.
     */
    private static function validateProps(array $props, string $label): void
    {
        self::validateKeys($props, $label);

        if (array_key_exists('errors', $props)) {
            throw new InvalidPageInputException(
                Message::RESERVED_ERRORS_PROP->getMessage(),
            );
        }
    }
}
