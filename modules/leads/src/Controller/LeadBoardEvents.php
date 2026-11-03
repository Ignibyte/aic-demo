<?php

declare(strict_types=1);

namespace App\Leads\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use AIC\Database\Contracts\QueryException;
use App\Leads\Service\BoardStreams;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/leads/events: the leads board's stream (#30), Server-Sent Events in Tessera's protocol. A connection sends
 * each column a move after `Last-Event-ID` touched (every column when it has none), then looks once a second for about
 * twenty seconds, and ends so Tessera reconnects. Signed out, the protected prefix turns it away.
 */
final readonly class LeadBoardEvents implements Stream
{
    /**
     * @param BoardStreams $boardStreams Builds the connection's source.
     */
    public function __construct(
        private BoardStreams $boardStreams,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the moves cannot be read.
     * @throws LogicException           When the request carries no session, or a type, a key or an anchor is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return StreamResponse The event stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return new EventStream($this->boardStreams->connection($serverRequest));
    }
}
