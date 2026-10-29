<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Controller\Application\PageDocuments;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The inbox module's factory (#19): the staff panel a refused opening is framed in, which `StaffPanels` needs its id
 * for, so the generated wiring cannot build it from types alone.
 */
final readonly class InboxServices
{
    /**
     * @param PageDocuments $pageDocuments The theme's documents.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @return StaffPanels The inbox's refusals' frame, its panel `inbox`.
     */
    public function staffPanels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'inbox');
    }
}
