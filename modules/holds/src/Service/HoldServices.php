<?php

declare(strict_types=1);

namespace App\Holds\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use App\Holds\Form\HoldChecks;
use App\Holds\Form\HoldFields;
use App\Holds\Form\HoldForm;
use App\Holds\Repository\HoldChoiceRepository;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The holds module's factory (#20): the form's flows, which the form page and its post share, so the generated handler
 * parts make one call apiece rather than each writing out the graph (gate:14). It offers nothing the console side
 * asks for: it needs the page documents, which only the web side has.
 */
final readonly class HoldServices
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param PageDocuments $pageDocuments The theme's documents.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @return HoldSaves The form and the placement, framed as the panel `hold`.
     */
    public function holdSaves(): HoldSaves
    {
        $holdChoiceRepository = new HoldChoiceRepository($this->entityStore);
        $holdForm = new HoldForm(new StaffPanels(new StaffPages($this->pageDocuments), 'hold'), new HoldFields($holdChoiceRepository));
        $holdRepository = new HoldRepository($this->entityStore, new VehicleStatusRepository($this->entityStore));

        return new HoldSaves($holdRepository, $holdChoiceRepository, new HoldChecks($holdChoiceRepository), $holdForm);
    }
}
