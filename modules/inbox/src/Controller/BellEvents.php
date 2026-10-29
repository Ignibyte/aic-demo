<?php

declare(strict_types=1);

namespace App\Inbox\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use AIC\Database\Contracts\QueryException;
use App\Inbox\Service\BellStreams;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/inbox/events: the bell's stream (#19), Server-Sent Events in Tessera's protocol. A connection sends the
 * notifications after `Last-Event-ID` (else `after`), then the unread count, then what changes, for about twenty
 * seconds; it then ends so Tessera reconnects. Signed out, the protected prefix turns it away.
 */
final readonly class BellEvents implements Stream
{
    /**
     * @param BellStreams $bellStreams Builds the connection's source.
     */
    public function __construct(
        private BellStreams $bellStreams,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the member or their notifications cannot be read.
     * @throws LogicException           When the request carries no session, or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return StreamResponse The event stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return new EventStream($this->bellStreams->connection($serverRequest));
    }
}
