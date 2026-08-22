<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use PHPForge\Inertia\Exception\{InvalidPageInputException, Message};

use function is_array;
use function is_string;

final readonly class PageInput
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $sharedProps
     * @param array<string, string|list<string>> $errors
     * @param array<string, mixed> $flash
     * @phpstan-param array<array-key, mixed> $errors
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
     * @param array<array-key, mixed> $values
     */
    private static function validateKeys(array $values, string $label): void
    {
        foreach (array_keys($values) as $key) {
            self::validateKey($key, $label);
        }
    }
}
