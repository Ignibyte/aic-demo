<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

/**
 * The bell's source (#25): a member's notifications are read only when the page renders the bell, so a Tessera answer
 * for one target of the content (an inventory filter, a form refused in place) reads none of them. The profiler showed
 * every such answer reading the bell's latest five and its unread count for a part it never sent.
 */
final readonly class BellPart implements ComponentSource
{
    /**
     * @param BellParts $bellParts Builds the bell.
     * @param string    $userId    The member's user id; empty for none.
     */
    public function __construct(
        private BellParts $bellParts,
        private string $userId,
    ) {
    }

    /**
     * @throws QueryException           When the notifications cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Component The bell.
     */
    #[Override]
    public function build(): Component
    {
        return $this->bellParts->bell($this->userId);
    }
}
