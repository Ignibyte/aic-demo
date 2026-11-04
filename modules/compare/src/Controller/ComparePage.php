<?php

declare(strict_types=1);

namespace App\Compare\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Chat\Service\ChatDesk;
use App\Compare\Service\CarComparisons;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /compare: up to three cars for sale side by side, named by their slugs (`?car=<slug>&car=<slug>`), for anyone;
 * the drawer's fragment for Tessera, a page of its own asked plainly, and 422 for four or more.
 */
final readonly class ComparePage implements Page
{
    /**
     * @param CarComparisons $carComparisons The comparison.
     * @param ChatDesk       $chatDesk       The chat's dock, which every store page carries (#32).
     */
    public function __construct(
        private CarComparisons $carComparisons,
        private ChatDesk $chatDesk,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its query and its session.
     *
     * @throws LogicException   When an entity type, a key or a region is malformed, or the request has no session.
     * @throws RuntimeException When the cars, their photos, the profile or the chat cannot be read.
     *
     * @return PageResponse The comparison, or the 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->carComparisons->page($serverRequest, $this->chatDesk->pageDock($serverRequest));
    }
}
