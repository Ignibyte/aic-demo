<?php

declare(strict_types=1);

namespace App\Theme\Components\BrochureLinks;

use AIC\Component\Contracts\Component;

/**
 * A car's brochure from its page (#44): one link that opens it in the browser, styled for print, and one that saves the
 * same file. Both are plain links, so they work with JavaScript off.
 */
final readonly class BrochureLinks implements Component
{
    /**
     * @param string $id           The stem of the links' ids: `{id}-view` and `{id}-download`.
     * @param string $viewHref     Where the brochure opens inline.
     * @param string $downloadHref Where the same brochure is sent as an attachment.
     */
    public function __construct(
        public string $id,
        public string $viewHref,
        public string $downloadHref,
    ) {
    }
}
