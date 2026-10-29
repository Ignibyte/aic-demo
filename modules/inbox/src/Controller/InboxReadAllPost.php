<?php

declare(strict_types=1);

namespace App\Inbox\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inbox\Service\InboxMoves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * POST /app/inbox/read-all: marks every notification of the signed-in member read (#19) and answers 303 to the inbox
 * with a notice; another member's stay as they were.
 */
final readonly class InboxReadAllPost implements Page
{
    /**
     * @param InboxMoves $inboxMoves Marks them.
     */
    public function __construct(
        private InboxMoves $inboxMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException           When the member cannot be read or the notifications written.
     * @throws LogicException           When the request carries no session, or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 to the inbox.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->inboxMoves->readAll($serverRequest);
    }
}
