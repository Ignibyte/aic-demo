<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\CodeBlock;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use App\Site\Entity\CoverageRow;
use App\Site\Entity\CoverageRows;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\PanelGrid\PanelGrid;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
use RuntimeException;

use function count;
use function str_replace;

/**
 * The Coverage page's catalog (#38): every surface `bin/aic code:coverage` lists, read from `config/coverage.php`, and
 * the page's parts made from it. The file returns the rows as data in code, a `CoverageRows` holding one `CoverageRow`
 * per surface in the command's order; AIC leaves the file out of what the command reads for uses (AIC #272, #273), so
 * naming a surface there is no use of it. Nothing here runs the command: the page's browser test holds the rows to it.
 */
final readonly class CoverageCatalog
{
    /**
     * The page's path, under the style guide.
     */
    public const string PATH = '/theme/coverage';

    /**
     * What the page is, in the notice the page puts above everything else on it.
     */
    public const string INTRO = 'Every surface of AIC and Tessera that bin/aic code:coverage lists, one row each: '
        . 'the page where Lotline uses it and the browser test that holds it, or why Lotline does not use it.';

    /**
     * The command that checks the rows: it lists the surfaces Lotline does not use.
     */
    private const string COMMAND = 'bin/aic code:coverage --missing';

    /**
     * @throws RuntimeException When `config/coverage.php` does not return a `CoverageRows`.
     *
     * @return list<CoverageRow> Every row, in `code:coverage`'s order.
     */
    public function rows(): array
    {
        $coverageRows = require __DIR__ . '/../../../../config/coverage.php';

        if (!$coverageRows instanceof CoverageRows) {
            throw new RuntimeException('config/coverage.php must return a ' . CoverageRows::class . '.');
        }

        return $coverageRows->rows;
    }

    /**
     * @throws RuntimeException         When `config/coverage.php` does not return a `CoverageRows`.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<Component> The page's panels: the command, then one for each group.
     */
    public function content(): array
    {
        $keyedComponent = new KeyedComponent('code', new CodeBlock(self::COMMAND));
        $panel = new Panel('coverage-command', 'Check it', 'Lists the surfaces Lotline does not use', true, [$keyedComponent]);
        $panels = [new KeyedComponent('command', $panel)];

        foreach (self::groups($this->rows()) as $group => $rows) {
            $panels[] = self::panel($group, $rows);
        }

        return [new PanelGrid($panels)];
    }

    /**
     * @throws InvalidArgumentException When the key is malformed.
     *
     * @return list<KeyedComponent> The style guide's link to the page.
     */
    public function links(): array
    {
        return [new KeyedComponent('coverage-link', new Link('Every AIC and Tessera surface, shown or reasoned', self::PATH))];
    }

    /**
     * @param list<CoverageRow> $rows Every row, in order.
     *
     * @return array<string, list<CoverageRow>> The rows by group, the groups in the order their first rows come.
     */
    private static function groups(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row->group][] = $row;
        }

        return $groups;
    }

    /**
     * @param string            $group The group, as `code:coverage` names it.
     * @param list<CoverageRow> $rows  Its rows.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return KeyedComponent The group's panel: its table, keyed by the group.
     */
    private static function panel(string $group, array $rows): KeyedComponent
    {
        $used = 0;
        $tableRows = [];

        foreach ($rows as $row) {
            if ($row->used()) {
                $used += 1;
            }

            $tableRows[] = new KeyedComponent(self::key($row->name), new TableRow(self::cells($row)));
        }

        $summary = $used . ' of ' . count($rows) . ' used';
        $dataTable = new DataTable('coverage-' . $group, '', 'No surface.', '', self::columns(), $tableRows);

        return new KeyedComponent($group, new Panel('coverage-' . $group, self::title($group), $summary, true, [
            new KeyedComponent('table', $dataTable),
        ]));
    }

    /**
     * @return list<TableColumn> The tables' heads.
     */
    private static function columns(): array
    {
        return [
            new TableColumn('Surface', '', 'none', '', false),
            new TableColumn('State', '', 'none', '', false),
            new TableColumn('Live example', '', 'none', '', false),
            new TableColumn('Browser test', '', 'none', '', false),
            new TableColumn('Reason', '', 'none', '', false),
        ];
    }

    /**
     * @param CoverageRow $coverageRow The row.
     *
     * @return list<TableCell> Its cells: the surface, its state, its page and its test, or its reason.
     */
    private static function cells(CoverageRow $coverageRow): array
    {
        $state = $coverageRow->used()
            ? new TableCell('Used', '', false, false, BadgeTone::Success)
            : new TableCell('Reasoned', '', false, false, BadgeTone::Neutral);

        return [
            new TableCell($coverageRow->name, '', true, false, null),
            $state,
            new TableCell($coverageRow->example, $coverageRow->example, true, false, null),
            new TableCell($coverageRow->test, '', true, false, null),
            new TableCell($coverageRow->reason, '', false, false, null),
        ];
    }

    /**
     * @param string $group The group, as `code:coverage` names it.
     *
     * @return string Its panel's heading.
     */
    private static function title(string $group): string
    {
        return match ($group) {
            'contracts' => 'Contracts: what an app implements or asks for',
            'responses' => 'Responses: the sealed answers of the five kinds',
            'manifest' => 'Manifest: what a module declares',
            'schema' => 'Schema: what a migration describes',
            'query' => 'Queries: what a repository asks of an entity query',
            'atoms' => "Atoms: AIC's own components",
            'tessera' => 'Tessera: what the runtime reads from a page, what it raises, and its members',
            default => $group,
        };
    }

    /**
     * @param string $name A surface's name, such as `AIC\Controller\Contracts\Page` or `@get`.
     *
     * @return string Its row's key in Tessera's grammar, such as `Controller.Contracts.Page` or `at.get`.
     */
    private static function key(string $name): string
    {
        return str_replace('@', 'at.', str_replace('\\', '.', str_replace('AIC\\', '', $name)));
    }
}
