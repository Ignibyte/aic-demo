<?php

declare(strict_types=1);

namespace App\Inbox\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Database\Contracts\QueryException;
use App\Inbox\Service\BellParts;
use App\Inbox\Service\InboxOwners;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The notification bell in the top bar of every staff page (#19), before the account menu: the signed-in member's
 * unread count and latest five, kept live by the stream it names.
 */
final readonly class BellBlock implements Block
{
    /**
     * @param InboxOwners $inboxOwners Whose bell it is.
     * @param BellParts   $bellParts   Builds it.
     */
    public function __construct(
        private InboxOwners $inboxOwners,
        private BellParts $bellParts,
    ) {
    }

    /**
     * @return Placement `header`, weight -10 (before the account menu), the pages under `/app`.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('header', -10, '/app');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     *
     * @throws QueryException           When the member or their notifications cannot be read.
     * @throws LogicException           When the request carries no session, or a key or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return Component The bell, keyed `bell`.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return $this->bellParts->bell($this->inboxOwners->userId($serverRequest));
    }
}
