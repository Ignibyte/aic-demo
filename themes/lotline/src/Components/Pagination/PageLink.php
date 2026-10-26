<?php

declare(strict_types=1);

namespace App\Theme\Components\Pagination;

/**
 * One page's link: its number, where it goes, and whether it is the page shown.
 */
final readonly class PageLink
{
    /**
     * @param string $label   The page's number.
     * @param string $href    The list at that page.
     * @param bool   $current Whether it is the page shown.
     */
    public function __construct(
        public string $label,
        public string $href,
        public bool $current,
    ) {
    }
}
