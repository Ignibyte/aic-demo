<?php

declare(strict_types=1);

namespace App\Theme\Components\Pagination;

use AIC\Component\Contracts\Component;

/**
 * A list's pages, from pagination.phtml: a daisyUI join of page buttons, the current one marked, answered in place by
 * Tessera. Build it only when there is more than one page.
 */
final readonly class Pagination implements Component
{
    /**
     * @param string         $id     Its id.
     * @param string         $target What a page link updates: a region (`content`) or a component's anchor (`@NAME`).
     * @param list<PageLink> $links  One link per page, in order.
     */
    public function __construct(
        public string $id,
        public string $target,
        public array $links,
    ) {
    }
}
