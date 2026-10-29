<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Controller\Application\PageDocuments;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The leads module's factory (#18): the staff panel a lead's pages are framed in, which `StaffPanels` needs its id
 * for, so the generated wiring cannot build it from types alone.
 */
final readonly class LeadServices
{
    /**
     * @param PageDocuments $pageDocuments The theme's documents.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @return StaffPanels A lead's pages' frame, its panel `lead`.
     */
    public function staffPanels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'lead');
    }
}
