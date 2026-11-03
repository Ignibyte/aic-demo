<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Form\Application\FormTokens;
use App\Leads\Entity\LeadStage;
use App\Leads\Repository\LeadRepository;
use App\Leads\Service\BoardColumnSource;
use App\Leads\Service\LeadAccess;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The leads board's five columns (#30), in board order: each keyed by its stage's value, anchored `leads-<stage>`, and
 * built from a `BoardColumnSource` that holds the viewer, their guard fields and the lead that just moved, so a Tessera
 * answer reads only the columns it renders.
 */
final readonly class BoardColumns
{
    /**
     * @param LeadAccess $leadAccess Who looks.
     * @param FormTokens $formTokens Gives the viewer's guard fields, which the cards' forms carry (AIC #246).
     */
    public function __construct(
        private LeadAccess $leadAccess = new LeadAccess(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param LeadRepository         $leadRepository The leads the columns read.
     * @param ServerRequestInterface $serverRequest  The request, with its session.
     * @param int                    $moved          The lead that just moved, whose card takes focus; 0 for none.
     *
     * @throws LogicException           When the request carries no session, or a key or an anchor is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return list<KeyedComponent> The five columns in board order, each a source.
     */
    public function columns(LeadRepository $leadRepository, ServerRequestInterface $serverRequest, int $moved): array
    {
        $boardViewer = $this->leadAccess->viewer($serverRequest);
        $guardFields = $this->formTokens->guard($serverRequest);
        $columns = [];

        foreach (LeadStage::New->board() as $leadStage) {
            $source = new BoardColumnSource($leadRepository, $leadStage, $boardViewer, $guardFields, $moved);
            $columns[] = new KeyedComponent($leadStage->value, new SourcedComponent($source), '', $leadStage->anchor());
        }

        return $columns;
    }
}
