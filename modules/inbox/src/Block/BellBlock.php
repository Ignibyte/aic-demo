<?php

declare(strict_types=1);

namespace App\Inbox\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use App\Inbox\Service\BellPart;
use App\Inbox\Service\BellParts;
use App\Inbox\Service\InboxOwners;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The notification bell in the top bar of every staff page (#19), before the account menu: the signed-in member's
 * unread count and latest five, kept live by the stream it names. They are read from its source, `BellPart`, when the
 * page renders the bell (#25), so a Tessera answer for another target reads none of them.
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
     * @throws LogicException When the request carries no session, or the key is malformed.
     *
     * @return Component The bell, keyed `bell`, built from its source when it is rendered.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        $bellPart = new BellPart($this->bellParts, $this->inboxOwners->userId($serverRequest));

        return new KeyedComponent('bell', new SourcedComponent($bellPart));
    }
}
