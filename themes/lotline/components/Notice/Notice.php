<?php

declare(strict_types=1);

namespace App\Theme\Components\Notice;

use AIC\Component\Contracts\Component;

/**
 * A short message in a daisyUI alert, from notice.phtml. Form errors use the framework's error alert.
 */
final readonly class Notice implements Component
{
    /**
     * @param string     $message    The message.
     * @param NoticeTone $noticeTone Its colour.
     * @param string     $id         Its id.
     */
    public function __construct(
        public string $message,
        public NoticeTone $noticeTone,
        public string $id,
    ) {
    }
}
