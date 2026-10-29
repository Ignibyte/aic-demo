<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Reports\Entity\ReportKinds;
use App\Reports\Form\RangeCheck;
use App\Reports\Form\RangeChecks;
use App\Reports\Form\RangeFilters;
use App\Reports\Repository\RangeRepository;
use App\Staff\Service\StaffPages;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The reports page's document (#21): the range asked for, checked, then its panels in the staff page frame. The range
 * comes first, wide (a refused range's warning, the line about the export the query names, the presets and the two
 * dates), then each report's panel over the range, in the page's order; a refused range answers 422.
 */
final readonly class ReportPanels
{
    /**
     * @param RangeRepository $rangeRepository The presets.
     * @param RangeChecks     $rangeChecks     Reads the range.
     * @param ExportDesk      $exportDesk      The export's line.
     * @param ReportCards     $reportCards     Each report's panel.
     * @param StaffPages      $staffPages      The staff pages' document.
     * @param RangeFilters    $rangeFilters    The presets' links, the dates' form and a refused range's warning.
     * @param ReportKinds     $reportKinds     The reports, in order.
     * @param FormTokens      $formTokens      The export forms' token.
     */
    public function __construct(
        private RangeRepository $rangeRepository,
        private RangeChecks $rangeChecks,
        private ExportDesk $exportDesk,
        private ReportCards $reportCards,
        private StaffPages $staffPages,
        private RangeFilters $rangeFilters = new RangeFilters(),
        private ReportKinds $reportKinds = new ReportKinds(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its query and session, from a manager.
     *
     * @throws RuntimeException When the figures, the presets or the export cannot be read.
     * @throws LogicException   When the request carries no session, or a key, a region or an entity type is malformed.
     *
     * @return PageDocument The reports; 422 for a refused range, with this month's.
     */
    public function page(ServerRequestInterface $serverRequest): PageDocument
    {
        $rangeCheck = $this->rangeChecks->query($serverRequest);
        $status = $rangeCheck->refused()
            ? 422
            : 200;

        return $this->staffPages->page('Reports', $this->panels($serverRequest, $rangeCheck), $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its query and session.
     * @param RangeCheck             $rangeCheck    The range asked for, checked.
     *
     * @throws RuntimeException When the figures, the presets or the export cannot be read.
     * @throws LogicException   When the request carries no session, or a key or an entity type is malformed.
     *
     * @return list<KeyedComponent> The panels, each keyed.
     */
    private function panels(ServerRequestInterface $serverRequest, RangeCheck $rangeCheck): array
    {
        $reportRange = $rangeCheck->reportRange;
        $body = [];

        if ($rangeCheck->refused()) {
            $body[] = new KeyedComponent('refused', $this->rangeFilters->refusal());
        }

        foreach ($this->exportDesk->status($serverRequest) as $keyedComponent) {
            $body[] = $keyedComponent;
        }

        $body[] = new KeyedComponent('presets', $this->rangeFilters->presets($this->rangeRepository->presets(), $reportRange));
        $body[] = new KeyedComponent('dates', $this->rangeFilters->filterBar($rangeCheck));
        $description = $reportRange->from . ' to ' . $reportRange->until;
        $panels = [new KeyedComponent('range', new Panel('report-range-panel', 'Range', $description, true, $body))];
        $token = $this->formTokens->issue($serverRequest)->value();

        foreach ($this->reportKinds->all() as $reportKind) {
            $panels[] = $this->reportCards->card($reportKind, $reportRange, $token);
        }

        return $panels;
    }
}
