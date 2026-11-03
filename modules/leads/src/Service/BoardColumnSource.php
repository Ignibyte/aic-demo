<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\GuardFields;
use AIC\Database\Contracts\QueryException;
use App\Leads\Entity\BoardViewer;
use App\Leads\Entity\LeadStage;
use App\Leads\Form\BoardCards;
use App\Leads\Repository\LeadRepository;
use InvalidArgumentException;
use Override;

/**
 * One column of the leads board as a source (#30, AIC #192): it holds the repository, its stage, the viewer, their guard
 * fields and the lead that just moved, and reads only in `build()`, which the render pass calls when it renders the
 * column. A move's answer names two columns, so it reads those two and no other.
 */
final readonly class BoardColumnSource implements ComponentSource
{
    /**
     * The most cards a column shows; its count says how many are at its stage.
     */
    public const int SHOWN = 50;

    /**
     * @param LeadRepository $leadRepository The leads.
     * @param LeadStage      $leadStage      The column's stage.
     * @param BoardViewer    $boardViewer    Who looks.
     * @param GuardFields    $guardFields    Their form token and honeypot.
     * @param int            $moved          The lead that just moved, whose menu takes focus; 0 for none.
     * @param BoardCards     $boardCards     Builds the column.
     */
    public function __construct(
        private LeadRepository $leadRepository,
        private LeadStage $leadStage,
        private BoardViewer $boardViewer,
        private GuardFields $guardFields,
        private int $moved,
        private BoardCards $boardCards = new BoardCards(),
    ) {
    }

    /**
     * @throws QueryException           When the leads cannot be read.
     * @throws InvalidArgumentException When a type or a card's key is malformed.
     *
     * @return Component The column: its heading and count, and its newest cards.
     */
    #[Override]
    public function build(): Component
    {
        return $this->boardCards->column(
            $this->leadStage,
            $this->leadRepository->inStage($this->leadStage, self::SHOWN),
            $this->leadRepository->countStage($this->leadStage),
            $this->boardViewer,
            $this->guardFields,
            $this->moved,
        );
    }
}
