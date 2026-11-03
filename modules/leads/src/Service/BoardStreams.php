<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use App\Leads\Repository\LeadMoveRepository;
use App\Leads\Repository\LeadRepository;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function ctype_digit;
use function strlen;

/**
 * Where a connection of the leads board's stream starts (#30): after the `Last-Event-ID` Tessera sends when it
 * reconnects; else after the newest move, with every column sent first, since the page names no move (gate:23 compares
 * each site's page, and a move's id would differ between its passes). The viewer and their form token go with it, so
 * each card's menu is the one the page shows them.
 */
final readonly class BoardStreams
{
    /**
     * The longest id read: more digits than an int holds are no id.
     */
    private const int DIGITS = 18;

    /**
     * @param LeadRepository     $leadRepository     The leads.
     * @param LeadMoveRepository $leadMoveRepository The moves.
     * @param LeadAccess         $leadAccess         Who looks.
     * @param FormTokens         $formTokens         Issues their form token.
     */
    public function __construct(
        private LeadRepository $leadRepository,
        private LeadMoveRepository $leadMoveRepository,
        private LeadAccess $leadAccess = new LeadAccess(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The stream's request, with its session and `Last-Event-ID`.
     *
     * @throws QueryException           When the moves cannot be read.
     * @throws LogicException           When the request carries no session, or a type, a key or an anchor is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return EventSource One connection's events.
     */
    public function connection(ServerRequestInterface $serverRequest): EventSource
    {
        $boardViewer = $this->leadAccess->viewer($serverRequest);
        $token = $this->formTokens->issue($serverRequest)->value();
        $resumed = $serverRequest->getHeaderLine('Last-Event-ID');

        $fresh = $resumed === '' || strlen($resumed) > self::DIGITS || !ctype_digit($resumed);
        $after = $fresh
            ? $this->leadMoveRepository->newest()
            : (int) $resumed;

        return new BoardSource($this->leadRepository, $this->leadMoveRepository, $boardViewer, $token, $after, $fresh);
    }
}
