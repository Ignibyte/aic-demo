<?php

declare(strict_types=1);

namespace App\Reports\Form;

use AIC\Component\Domain\KeyedComponent;
use App\Reports\Entity\RangePreset;
use App\Reports\Entity\ReportRange;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\RangeLinks\RangeLink;
use App\Theme\Components\RangeLinks\RangeLinks;
use InvalidArgumentException;

/**
 * How a manager picks the reports' range (#21): the presets as the theme's `RangeLinks`, and two dates in the theme's
 * `FilterBar`, a plain GET form. Both answer the content region in place through Tessera, and work as they are with
 * JavaScript off. Also the warning a refused range shows above them.
 */
final readonly class RangeFilters
{
    /**
     * The reports page.
     */
    public const string PAGE = '/app/reports';

    /**
     * What the links and the form update.
     */
    private const string TARGET = 'content';

    /**
     * @param list<RangePreset> $presets     The presets, in order.
     * @param ReportRange       $reportRange The range shown.
     *
     * @return RangeLinks A link per preset, the one that is the range shown marked.
     */
    public function presets(array $presets, ReportRange $reportRange): RangeLinks
    {
        $links = [];

        foreach ($presets as $preset) {
            $range = $preset->reportRange;
            $chosen = $range->from === $reportRange->from && $range->until === $reportRange->until;
            $links[] = new RangeLink($preset->label, self::PAGE . '?' . $range->query(), $chosen);
        }

        return new RangeLinks('report-ranges', 'Preset ranges', self::TARGET, $links);
    }

    /**
     * @param RangeCheck $rangeCheck The range asked for, and what was wrong with it.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FilterBar The first and last day, as asked, each with its error.
     */
    public function filterBar(RangeCheck $rangeCheck): FilterBar
    {
        $from = new FormField('report-from', 'from', 'First day', 'date', $rangeCheck->from, $rangeCheck->errors['from'] ?? '', true, true);
        $until = new FormField('report-to', 'to', 'Last day', 'date', $rangeCheck->until, $rangeCheck->errors['to'] ?? '', true, true);

        return new FilterBar('report-range', self::PAGE, self::TARGET, [
            new KeyedComponent('from', $from),
            new KeyedComponent('to', $until),
        ], self::PAGE);
    }

    /**
     * @return Notice What a refused range says: the reports are this month's.
     */
    public function refusal(): Notice
    {
        $message = "That range cannot be reported on, so these are this month's reports.";

        return new Notice($message, NoticeTone::Warning, 'report-range-notice');
    }
}
