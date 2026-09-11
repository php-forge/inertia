<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Debug;

use InvalidArgumentException;
use PHPForge\Debug\{ColumnStyle, PanelView};
use PHPForge\Inertia\Debug\InertiaPanel;
use PHPForge\Inertia\Tests\Provider\InertiaPanelProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see InertiaPanel} page overviews, navigation empty states, and diagnostics validation.
 *
 * {@see InertiaPanelProvider} for test case data providers.
 *
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type EmptyStateBlock from PanelView
 * @phpstan-import-type Inline from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type Pair from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
final class InertiaPanelTest extends TestCase
{
    public function testCompleteDescriptionsMatchReviewedFixtures(): void
    {
        $paths = glob(__DIR__ . '/fixtures/*.input.json');

        self::assertNotFalse(
            $paths,
            'The capture fixture directory must be readable.',
        );
        self::assertNotEmpty(
            $paths,
            'The complete-description fixtures must be present.',
        );

        foreach ($paths as $path) {
            $input = file_get_contents($path);

            self::assertNotFalse(
                $input,
                'The stored capture fixture must be readable.',
            );

            /** @var array<string, mixed> $data */
            $data = json_decode($input, true, flags: JSON_THROW_ON_ERROR);

            $view = (new InertiaPanel())->present($data);

            self::assertStringEqualsFile(
                str_replace('.input.json', '.view.json', $path),
                json_encode(
                    $view,
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ) . "\n",
                'The complete semantic description must preserve content, order, and styles.',
            );
        }
    }

    public function testMetadataAndMissingCapture(): void
    {
        $panel = new InertiaPanel();

        self::assertSame(
            'inertia',
            $panel->id(),
            'The persisted panel ID must stay stable.'
        );
        self::assertSame(
            'inertia',
            $panel->icon(),
            'The panel must reuse the existing icon.'
        );
        self::assertSame(
            'Inertia',
            $panel->name(),
            'The title must stay stable.'
        );

        $view = $panel->present(InertiaPanelProvider::capture());

        self::assertFalse(
            $view->isActive(),
            'Unrelated requests must not activate the panel.'
        );
        self::assertSame(
            [],
            $view->toolbarMetrics(),
            'Missing pages must not fabricate component metrics.'
        );
        self::assertSame(
            'emptyState',
            self::blockAt($view, 0)['kind'],
            'Missing pages must have an explicit empty state.',
        );
    }

    public function testResolvedPageDescribesPropsWithoutFormattingThem(): void
    {
        $data = InertiaPanelProvider::capture();

        $data['page'] = [
            'component' => '<Site>',
            'url' => '/',
            'version' => false,
            'props' => [
                'auth' => ['id' => 1],
                'title' => '<script>',
            ],
        ];
        $data['sharedKeys'] = ['auth'];
        $data['requestHeaders'] = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1'
        ];

        $view = (new InertiaPanel())->present($data);

        self::assertTrue(
            $view->isActive(),
            'A captured page must activate the panel.',
        );
        self::assertSame(
            '<Site>',
            self::metricValue($view->toolbarMetrics(), 0),
            'The frontend, not the provider, must escape text.',
        );
        self::assertSame(
            'Inertia visit',
            self::metricValue($view->summaryMetrics(), 1),
            'XHR negotiation must be described.',
        );
        self::assertCount(
            6,
            self::overview(self::blockAt($view, 0))['fields'],
            'Negotiation headers must be included once.',
        );

        $table = self::table(self::blockAt($view, 2));

        self::assertTrue(
            $table['collapsible'],
            'Dense props must use the existing collapse behavior.',
        );
        self::assertSame(
            [
                1 => ColumnStyle::IDENTIFIER,
                2 => ColumnStyle::PILL,
                3 => ColumnStyle::IDENTIFIER,
                4 => ColumnStyle::PAYLOAD,
            ],
            $table['styles'],
            'Prop columns must keep their semantic styles.',
        );

        $cell = $table['rows'][0][4] ?? self::fail('Props must use the standard table.');

        self::assertSame(
            ['id' => 1],
            self::rawValue($cell),
            'The renderer must receive the original diagnostic value.',
        );
    }

    /**
     * @param array<string, mixed> $capture
     */
    #[DataProviderExternal(InertiaPanelProvider::class, 'malformedCaptures')]
    public function testThrowInvalidArgumentExceptionForMalformedCapture(array $capture, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            $message,
        );

        (new InertiaPanel())->present($capture);
    }

    public function testVersionConflictAndPartialVisits(): void
    {
        $data = InertiaPanelProvider::capture();

        $data['requestHeaders'] = [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Except' => 'auth',
        ];

        self::assertSame(
            'Partial reload',
            self::metricValue((new InertiaPanel())->present($data)->summaryMetrics(), 1),
            'Partial-except must identify partial visits.',
        );

        $data['statusCode'] = 409;
        $data['location'] = '/reload';

        $view = (new InertiaPanel())->present($data);

        self::assertTrue(
            $view->isActive(),
            'Conflicted Inertia visits must remain visible.',
        );
        self::assertSame(
            'Version conflict interrupted this visit',
            self::emptyState(self::blockAt($view, 0))['title'],
            'Conflicts need their own explanation.',
        );
    }

    /**
     * @return Block
     */
    private static function blockAt(PanelView $view, int $index): array
    {
        return $view->blocks()[$index] ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param Block $block
     *
     * @return EmptyStateBlock
     */
    private static function emptyState(array $block): array
    {
        return match ($block['kind']) {
            'emptyState' => $block,
            default => self::fail('An unresolved page must have an explicit empty state.'),
        };
    }

    /**
     * @param list<Pair> $metrics
     */
    private static function metricValue(array $metrics, int $index): string
    {
        $metric = $metrics[$index] ?? self::fail('The declared presentation structure must be complete.');

        return match ($metric['value']['kind']) {
            'text' => $metric['value']['value'],
            default => self::fail('Metrics must describe plain text.'),
        };
    }

    /**
     * @param Block $block
     *
     * @return OverviewBlock
     */
    private static function overview(array $block): array
    {
        return match ($block['kind']) {
            'overview' => $block,
            default => self::fail('Page metadata must use a shared overview.'),
        };
    }

    /**
     * @param Inline $inline
     */
    private static function rawValue(array $inline): mixed
    {
        return match ($inline['kind']) {
            'value' => $inline['value'],
            default => self::fail('Captured values must remain unformatted.'),
        };
    }

    /**
     * @param Block $block
     *
     * @return TableBlock
     */
    private static function table(array $block): array
    {
        return match ($block['kind']) {
            'table' => $block,
            default => self::fail('Props must use the standard table.'),
        };
    }
}
