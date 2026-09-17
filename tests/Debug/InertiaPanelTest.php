<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Debug;

use InvalidArgumentException;
use PHPForge\Debug\{ColumnStyle, PanelView};
use PHPForge\Debug\Presenter\{
    BadgeInline,
    Block,
    DisclosureBlock,
    EmptyStateBlock,
    HeadingBlock,
    Inline,
    OverviewBlock,
    ParagraphBlock,
    SummaryMetric,
    TableBlock,
    TextInline,
    ToolbarMetric,
    ValueInline
};
use PHPForge\Inertia\Debug\InertiaPanel;
use PHPForge\Inertia\Tests\Provider\InertiaPanelProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see InertiaPanel} page overviews, navigation empty states, and diagnostics validation.
 *
 * {@see InertiaPanelProvider} for test case data providers.
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
                    self::describe($view),
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

        self::assertSame(
            [],
            $view->toolbarMetrics(),
            'Missing pages must not fabricate component metrics.'
        );
        self::assertInstanceOf(
            EmptyStateBlock::class,
            self::blockAt($view, 0),
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
            'X-Inertia-Version' => '1',
        ];

        $view = (new InertiaPanel())->present($data);

        self::assertSame(
            '<Site>',
            self::toolbarValue($view->toolbarMetrics(), 0),
            'The frontend, not the provider, must escape text.',
        );
        self::assertSame(
            'Inertia visit',
            self::summaryValue($view->summaryMetrics(), 1),
            'XHR negotiation must be described.',
        );
        self::assertCount(
            6,
            self::overview(self::blockAt($view, 0))->fields,
            'Negotiation headers must be included once.',
        );

        $table = self::table(self::blockAt($view, 2));

        self::assertTrue(
            $table->collapsible,
            'Dense props must use the existing collapse behavior.',
        );
        self::assertSame(
            [
                1 => ColumnStyle::IDENTIFIER,
                2 => ColumnStyle::PILL,
                3 => ColumnStyle::IDENTIFIER,
                4 => ColumnStyle::PAYLOAD,
            ],
            $table->styles,
            'Prop columns must keep their semantic styles.',
        );

        $cell = $table->rows[0][4] ?? self::fail('Props must use the standard table.');

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
            self::summaryValue((new InertiaPanel())->present($data)->summaryMetrics(), 1),
            'Partial-except must identify partial visits.',
        );

        $data['statusCode'] = 409;
        $data['location'] = '/reload';

        $view = (new InertiaPanel())->present($data);

        self::assertSame(
            'Version conflict interrupted this visit',
            self::emptyState(self::blockAt($view, 0))->title,
            'Conflicts need their own explanation.',
        );
    }

    /**
     * @param PanelView $view View to read.
     * @param int $index Position of the block in display order.
     *
     * @return Block Block declared at the requested position.
     */
    private static function blockAt(PanelView $view, int $index): Block
    {
        return $view->blocks()[$index] ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * Describes the view as the plain structure stored in the reviewed fixtures.
     *
     * @param PanelView $view View to describe.
     *
     * @return array<string, mixed> Complete description in display order.
     */
    private static function describe(PanelView $view): array
    {
        $summary = [];

        foreach ($view->summaryMetrics() as $metric) {
            $summary[] = ['label' => $metric->label, 'value' => self::describeInline($metric->value)];
        }

        $blocks = [];

        foreach ($view->blocks() as $block) {
            $blocks[] = self::describeBlock($block);
        }

        $toolbar = [];

        foreach ($view->toolbarMetrics() as $metric) {
            $toolbar[] = [
                'label' => $metric->label,
                'value' => ['kind' => 'text', 'value' => $metric->value, 'style' => 'plain'],
            ];
        }

        return ['summary' => $summary, 'blocks' => $blocks, 'toolbar' => $toolbar];
    }

    /**
     * @param Block $block Block to describe.
     *
     * @return array<string, mixed> Block description keyed by its declared fields.
     */
    private static function describeBlock(Block $block): array
    {
        return match (true) {
            $block instanceof DisclosureBlock => [
                'kind' => 'disclosure',
                'title' => $block->title,
                'content' => $block->content,
            ],
            $block instanceof EmptyStateBlock => [
                'kind' => 'emptyState',
                'title' => $block->title,
                'paragraphs' => self::describeBlocks($block->paragraphs),
            ],
            $block instanceof HeadingBlock => [
                'kind' => 'heading',
                'title' => $block->title,
                'section' => $block->section,
            ],
            $block instanceof OverviewBlock => [
                'kind' => 'overview',
                'fields' => self::describeFields($block),
                'compact' => $block->compact,
            ],
            $block instanceof ParagraphBlock => [
                'kind' => 'paragraph',
                'content' => self::describeInlines($block->content),
                'tone' => $block->tone?->value,
            ],
            $block instanceof TableBlock => [
                'kind' => 'table',
                'headers' => $block->headers,
                'rows' => self::describeRows($block),
                'styles' => self::describeStyles($block),
                'collapsible' => $block->collapsible,
                'filterable' => $block->filterable,
            ],
            default => self::fail('The description must use a block the panel declares.'),
        };
    }

    /**
     * @param list<ParagraphBlock> $blocks Blocks to describe.
     *
     * @return list<array<string, mixed>> Block descriptions in display order.
     */
    private static function describeBlocks(array $blocks): array
    {
        $described = [];

        foreach ($blocks as $block) {
            $described[] = self::describeBlock($block);
        }

        return $described;
    }

    /**
     * @param OverviewBlock $block Overview whose fields are described.
     *
     * @return list<array<string, mixed>> Field descriptions in display order.
     */
    private static function describeFields(OverviewBlock $block): array
    {
        $described = [];

        foreach ($block->fields as $field) {
            $described[] = ['label' => $field->label, 'value' => self::describeInline($field->value)];
        }

        return $described;
    }

    /**
     * @param Inline $inline Inline value to describe.
     *
     * @return array<string, mixed> Inline description keyed by its declared fields.
     */
    private static function describeInline(Inline $inline): array
    {
        return match (true) {
            $inline instanceof BadgeInline => [
                'kind' => 'badge',
                'label' => $inline->label,
                'tone' => $inline->tone->value,
            ],
            $inline instanceof TextInline => [
                'kind' => 'text',
                'value' => $inline->value,
                'style' => $inline->style->value,
            ],
            $inline instanceof ValueInline => [
                'kind' => 'value',
                'value' => $inline->value,
                'typeOnly' => $inline->typeOnly,
            ],
            default => self::fail('The description must use an inline value the panel declares.'),
        };
    }

    /**
     * @param list<Inline> $inlines Inline values to describe.
     *
     * @return list<array<string, mixed>> Inline descriptions in display order.
     */
    private static function describeInlines(array $inlines): array
    {
        $described = [];

        foreach ($inlines as $inline) {
            $described[] = self::describeInline($inline);
        }

        return $described;
    }

    /**
     * @param TableBlock $block Table whose rows are described.
     *
     * @return list<list<array<string, mixed>>> Row descriptions in display order.
     */
    private static function describeRows(TableBlock $block): array
    {
        $described = [];

        foreach ($block->rows as $row) {
            $described[] = self::describeInlines($row);
        }

        return $described;
    }

    /**
     * @param TableBlock $block Table whose column styles are described.
     *
     * @return array<int, string> Style names keyed by column index.
     */
    private static function describeStyles(TableBlock $block): array
    {
        $described = [];

        foreach ($block->styles as $column => $style) {
            $described[$column] = $style->value;
        }

        return $described;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return EmptyStateBlock Narrowed empty state.
     */
    private static function emptyState(Block $block): EmptyStateBlock
    {
        return match (true) {
            $block instanceof EmptyStateBlock => $block,
            default => self::fail('An unresolved page must have an explicit empty state.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return OverviewBlock Narrowed overview.
     */
    private static function overview(Block $block): OverviewBlock
    {
        return match (true) {
            $block instanceof OverviewBlock => $block,
            default => self::fail('Page metadata must use a shared overview.'),
        };
    }

    /**
     * @param Inline $inline Inline value to read.
     *
     * @return mixed Captured diagnostic value.
     */
    private static function rawValue(Inline $inline): mixed
    {
        return match (true) {
            $inline instanceof ValueInline => $inline->value,
            default => self::fail('Captured values must remain unformatted.'),
        };
    }

    /**
     * @param list<SummaryMetric> $metrics Summary metrics in display order.
     * @param int $index Position of the metric in display order.
     *
     * @return string Plain-text metric value.
     */
    private static function summaryValue(array $metrics, int $index): string
    {
        $metric = $metrics[$index] ?? self::fail('The declared presentation structure must be complete.');

        return match (true) {
            $metric->value instanceof TextInline => $metric->value->value,
            default => self::fail('Metrics must describe plain text.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return TableBlock Narrowed table.
     */
    private static function table(Block $block): TableBlock
    {
        return match (true) {
            $block instanceof TableBlock => $block,
            default => self::fail('Props must use the standard table.'),
        };
    }

    /**
     * @param list<ToolbarMetric> $metrics Toolbar metrics in display order.
     * @param int $index Position of the metric in display order.
     *
     * @return string Plain-text metric value.
     */
    private static function toolbarValue(array $metrics, int $index): string
    {
        $metric = $metrics[$index] ?? self::fail('The declared presentation structure must be complete.');

        return $metric->value;
    }
}
