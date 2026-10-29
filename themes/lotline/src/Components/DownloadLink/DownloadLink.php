<?php

declare(strict_types=1);

namespace App\Theme\Components\DownloadLink;

use AIC\Component\Contracts\Component;

/**
 * A link to a file to save, from download-link.phtml, set as a button (#21). It carries `download`, so Tessera leaves
 * it to the browser instead of visiting it, and the file's own name comes from the answer's `Content-Disposition`.
 */
final readonly class DownloadLink implements Component
{
    /**
     * @param string $id    The link's id.
     * @param string $label Its text.
     * @param string $href  The file's path on this site.
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $href,
    ) {
    }
}
