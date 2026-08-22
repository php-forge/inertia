<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Exception;

use function sprintf;

/**
 * Defines exception message templates for invalid protocol input and prop resolution failures.
 *
 * Use {@see Message::getMessage()} to format a template with `sprintf()` arguments.
 */
enum Message: string
{
    /**
     * The absolute request URL is invalid.
     *
     * Format: "The absolute request URL must use HTTP or HTTPS and include a host."
     */
    case ABSOLUTE_REQUEST_URL_INVALID = 'The absolute request URL must use HTTP or HTTPS and include a host.';

    /**
     * The page component name is invalid.
     *
     * Format: "The component name must be non-empty and contain no control characters."
     */
    case COMPONENT_NAME_INVALID = 'The component name must be non-empty and contain no control characters.';

    /**
     * The deferred prop group is invalid.
     *
     * Format: "A deferred prop group must be non-empty and contain no control characters or commas."
     */
    case DEFERRED_PROP_GROUP_INVALID
        = 'A deferred prop group must be non-empty and contain no control characters or commas.';

    /**
     * An error field does not contain a supported message value.
     *
     * Format: "Each error field must contain a string or a list of strings."
     */
    case ERROR_FIELD_INVALID = 'Each error field must contain a string or a list of strings.';

    /**
     * A prop exceeds the supported JSON nesting depth.
     *
     * Format: "The prop \"%s\" exceeds the maximum JSON nesting depth."
     */
    case JSON_DEPTH_EXCEEDED = 'The prop "%s" exceeds the maximum JSON nesting depth.';

    /**
     * A prop contains a non-finite number.
     *
     * Format: "The prop \"%s\" contains a non-finite number."
     */
    case JSON_NON_FINITE_NUMBER = 'The prop "%s" contains a non-finite number.';

    /**
     * A prop contains invalid UTF-8.
     *
     * Format: "The prop \"%s\" contains invalid UTF-8."
     */
    case JSON_UTF8_INVALID = 'The prop "%s" contains invalid UTF-8.';

    /**
     * A prop contains a value that JSON cannot represent.
     *
     * Format: "The prop \"%s\" contains a value that cannot be represented as JSON."
     */
    case JSON_VALUE_INVALID = 'The prop "%s" contains a value that cannot be represented as JSON.';

    /**
     * An Inertia location visit URL is invalid.
     *
     * Format: "An Inertia location visit requires an absolute HTTP or HTTPS URL."
     */
    case LOCATION_URL_INVALID = 'An Inertia location visit requires an absolute HTTP or HTTPS URL.';

    /**
     * A merge match path is not a string.
     *
     * Format: "Merge match paths must be strings."
     */
    case MERGE_MATCH_PATH_INVALID = 'Merge match paths must be strings.';

    /**
     * A merge path is invalid.
     *
     * Format: "Merge paths must use non-empty dot-notation segments."
     */
    case MERGE_PATH_INVALID = 'Merge paths must use non-empty dot-notation segments.';

    /**
     * A once prop expiration delay is invalid.
     *
     * Format: "A once prop expiration delay must not be negative."
     */
    case ONCE_PROP_EXPIRATION_INVALID = 'A once prop expiration delay must not be negative.';

    /**
     * A once prop key is invalid.
     *
     * Format: "A once prop key must be non-empty and contain no control characters or commas."
     */
    case ONCE_PROP_KEY_INVALID = 'A once prop key must be non-empty and contain no control characters or commas.';

    /**
     * A page input key is invalid.
     *
     * Format: "All %s keys must be non-empty strings with valid dot-notation segments."
     */
    case PAGE_KEY_INVALID = 'All %s keys must be non-empty strings with valid dot-notation segments.';

    /**
     * A dot-notation prop path conflicts with a scalar segment.
     *
     * Format: "The prop path \"%s\" conflicts with the non-array segment \"%s\"."
     */
    case PROP_PATH_CONFLICT = 'The prop path "%s" conflicts with the non-array segment "%s".';

    /**
     * A prop cannot be resolved.
     *
     * Format: "Failed to resolve the Inertia prop \"%s\": %s"
     */
    case PROP_RESOLUTION_FAILED = 'Failed to resolve the Inertia prop "%s": %s';

    /**
     * A prop contains too many nested resolver callbacks.
     *
     * Format: "The prop \"%s\" contains too many nested resolvers."
     */
    case PROP_RESOLVER_DEPTH_EXCEEDED = 'The prop "%s" contains too many nested resolvers.';

    /**
     * A prop contains too many nested wrappers.
     *
     * Format: "A prop contains too many nested wrappers."
     */
    case PROP_WRAPPER_DEPTH_EXCEEDED = 'A prop contains too many nested wrappers.';

    /**
     * A redirect status code is unsupported.
     *
     * Format: "The redirect status code must be 301, 302, 303, 307, or 308."
     */
    case REDIRECT_STATUS_INVALID = 'The redirect status code must be 301, 302, 303, 307, or 308.';

    /**
     * A redirect URL is invalid.
     *
     * Format: "A redirect URL must be absolute or rooted at the current origin."
     */
    case REDIRECT_URL_INVALID = 'A redirect URL must be absolute or rooted at the current origin.';

    /**
     * A request header value contains a line break.
     *
     * Format: "Request header values must not contain line breaks."
     */
    case REQUEST_HEADER_LINE_BREAK_INVALID = 'Request header values must not contain line breaks.';

    /**
     * Request headers do not form a string-to-string map.
     *
     * Format: "Request headers must be a string-to-string map."
     */
    case REQUEST_HEADERS_INVALID = 'Request headers must be a string-to-string map.';

    /**
     * The request method is invalid.
     *
     * Format: "The request method must be a valid HTTP method token."
     */
    case REQUEST_METHOD_INVALID = 'The request method must be a valid HTTP method token.';

    /**
     * The absolute request URL cannot provide an origin.
     *
     * Format: "The request absolute URL cannot provide an origin."
     */
    case REQUEST_ORIGIN_INVALID = 'The request absolute URL cannot provide an origin.';

    /**
     * The rooted relative request URL is invalid.
     *
     * Format: "The request URL must be a rooted relative URL without credentials or a fragment."
     */
    case REQUEST_URL_INVALID = 'The request URL must be a rooted relative URL without credentials or a fragment.';

    /**
     * The reserved errors prop was supplied as a regular prop.
     *
     * Format: 'The reserved "errors" prop must be supplied through the errors argument.'
     */
    case RESERVED_ERRORS_PROP = 'The reserved "errors" prop must be supplied through the errors argument.';

    /**
     * A scroll metadata provider returns an unsupported value.
     *
     * Format: "The scroll metadata provider for \"%s\" must return ScrollMetadata."
     */
    case SCROLL_METADATA_INVALID = 'The scroll metadata provider for "%s" must return ScrollMetadata.';

    /**
     * A scroll page name is invalid.
     *
     * Format: "The scroll page name must be non-empty and contain no control characters."
     */
    case SCROLL_PAGE_NAME_INVALID = 'The scroll page name must be non-empty and contain no control characters.';

    /**
     * A scroll wrapper path is invalid.
     *
     * Format: "The scroll wrapper must use non-empty dot-notation segments."
     */
    case SCROLL_WRAPPER_INVALID = 'The scroll wrapper must use non-empty dot-notation segments.';

    /**
     * A validation error message is not a string.
     *
     * Format: "Each validation error message must be a string."
     */
    case VALIDATION_ERROR_MESSAGE_INVALID = 'Each validation error message must be a string.';

    /**
     * Formats the message template with the supplied arguments.
     *
     * @param int|string ...$argument Values inserted into the template.
     *
     * @return string Formatted exception message.
     */
    public function getMessage(int|string ...$argument): string
    {
        return sprintf($this->value, ...$argument);
    }
}
