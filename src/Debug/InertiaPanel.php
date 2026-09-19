<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Debug;

use InvalidArgumentException;
use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\Header;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Composes the Inertia panel from decoded, already-sanitized capture data.
 */
final class InertiaPanel extends Panel
{
    /**
     * Host-interpreted icon identifier for the Inertia panel.
     */
    protected const string ICON = 'inertia';
    /**
     * Stable identifier associating the panel with the Inertia capture.
     */
    protected const string ID = 'inertia';
    /**
     * Panel title used in the debugger navigation.
     */
    protected const string TITLE = 'Inertia';

    /**
     * Composes the panel view from the decoded Inertia capture.
     *
     * @param array<string, mixed> $data Decoded, already-sanitized Inertia diagnostics.
     *
     * @throws InvalidArgumentException if the capture declares an unsupported result type or malformed values.
     *
     * @return PanelView Summary, overview, props table, and raw payload disclosure.
     */
    public function present(array $data): PanelView
    {
        $status = $data['statusCode'] ?? null;
        $headers = $data['requestHeaders'] ?? null;
        $shared = $data['sharedKeys'] ?? null;
        $page = $data['page'] ?? null;
        $location = $data['location'] ?? null;
        $type = $data['resultType'] ?? null;

        if (
            $type !== null
            && !in_array($type, ['page', 'version-conflict', 'location', 'redirect', 'fragment-redirect'], true)
        ) {
            throw new InvalidArgumentException(
                Message::DIAGNOSTICS_RESULT_TYPE_INVALID->getMessage(),
            );
        }

        if (
            !is_int($status)
            || !is_array($headers)
            || !is_array($shared)
            || ($location !== null && !is_string($location))
        ) {
            throw new InvalidArgumentException(
                Message::DIAGNOSTICS_INVALID->getMessage(),
            );
        }

        // Historical captures may describe a non-array page; it remains an explicit empty state.
        $page = is_array($page) ? $page : null;

        $component = self::string($page['component'] ?? null);

        $props = is_array($page['props'] ?? null) ? $page['props'] : [];

        $visit = self::visit($status, $headers, $type);

        $view = PanelView::create()
            ->summary('', $component !== '' ? $component : '—')
            ->summary('', $visit, emphasized: false);

        if ($component !== '') {
            $view = $view->toolbar('Inertia component', $component);
        }

        if ($page === null) {
            return self::empty($view, $status, $location, $type);
        }

        $view = $view->summary(count($props) === 1 ? ' prop' : ' props', count($props));

        $version = is_scalar($page['version'] ?? null) ? (string) $page['version'] : '';

        $url = self::string($page['url'] ?? null);

        $fields = [
            'Component' => $component !== '' ? $component : '—',
            'URL' => $url !== '' ? $url : '—',
            'Version' => $version !== '' ? $version : '—',
            'Visit' => $visit,
            'Status' => $status,
        ];

        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value) && $name !== Header::INERTIA->value) {
                $fields[$name] = PanelView::text($value);
            }
        }

        $view = $view->overview($fields);

        $rows = [];

        foreach ($props as $name => $value) {
            $isShared = in_array((string) $name, $shared, true);

            $rows[] = [
                count($rows) + 1,
                PanelView::strong((string) $name),
                PanelView::badge($isShared ? 'shared' : 'page', $isShared ? Tone::INFO : Tone::MUTED),
                PanelView::value($value, typeOnly: true),
                PanelView::value($value),
            ];
        }

        $view = $view->heading('Props');

        $view = $rows === [] ? $view->paragraph('The page rendered without props.')
            : $view->table(
                ['#', 'Prop', 'Origin', 'Type', 'Value'],
                $rows,
                collapsible: true,
                styles: [
                    1 => ColumnStyle::IDENTIFIER,
                    2 => ColumnStyle::PILL,
                    3 => ColumnStyle::IDENTIFIER,
                    4 => ColumnStyle::PAYLOAD,
                ],
            );

        return $view->disclosure(
            'Raw payload',
            json_encode(
                $page,
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * Builds the empty state explaining why the request resolved without an Inertia page.
     *
     * @param PanelView $view View carrying the summary already composed for the request.
     * @param int $status HTTP status code of the captured response.
     * @param string|null $location Navigation target of the captured result, or `null` when none applies.
     * @param string|null $type Protocol result type, or `null` when the capture predates result typing.
     *
     * @return PanelView View completed with the matching empty state.
     */
    private static function empty(PanelView $view, int $status, string|null $location, string|null $type): PanelView
    {
        if (in_array($type, ['location', 'redirect', 'fragment-redirect'], true)) {
            return $view->emptyState(
                match ($type) {
                    'location' => 'External location visit',
                    'fragment-redirect' => 'Fragment redirect',
                    default => 'Redirect',
                },
                'The protocol returned a navigation result without resolving a page.',
                [
                    'Navigation target: ',
                    PanelView::code($location ?? '—'),
                ],
            );
        }

        if ($status === 409) {
            return $view->emptyState(
                'Version conflict interrupted this visit',
                [
                    'The client asset version sent in ', PanelView::code(Header::VERSION->value),
                    ' no longer matches the server version, so Inertia answered ', PanelView::code('409'),
                    ' and asked the client to reload the full page.',
                ],
                [
                    'Reload target: ',
                    PanelView::code($location ?? '—'),
                ],
            );
        }

        return $view->emptyState(
            'No Inertia page in this request',
            [
                'This response was not produced by ', PanelView::code('Inertia::render()'),
                ', so there is no page object to inspect.',
            ],
            'Both full page loads and Inertia XHR visits populate this view; plain JSON endpoints, redirects, and asset requests do not.',
        );
    }

    /**
     * Returns the captured value when it is a string, discarding any other type.
     *
     * @param mixed $value Captured value.
     *
     * @return string Captured string, or an empty string for any other type.
     */
    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Classifies the captured request as the visit kind shown in the summary and overview.
     *
     * @param int $status HTTP status code of the captured response.
     * @param array<array-key, mixed> $headers Captured Inertia request headers.
     * @param string|null $type Protocol result type, or `null` when the capture predates result typing.
     *
     * @return string Human-readable visit kind.
     */
    private static function visit(int $status, array $headers, string|null $type): string
    {
        return match (true) {
            $type === 'location' => 'External location',
            $type === 'redirect' => 'Redirect',
            $type === 'fragment-redirect' => 'Fragment redirect',
            $status === 409 => 'Version conflict',
            isset($headers[Header::INERTIA->value])
            && (
                isset($headers[Header::PARTIAL_DATA->value])
                || isset($headers[Header::PARTIAL_EXCEPT->value])
            ) => 'Partial reload',
            isset($headers[Header::INERTIA->value]) => 'Inertia visit',
            default => 'Full page load',
        };
    }
}
