<?php

declare(strict_types=1);

namespace App\Theme\Components\DetailCard;

/**
 * A link drawn as a small button: its text, where it goes, its id and its colour.
 */
final readonly class ButtonLink
{
    /**
     * @param string     $label      Its text.
     * @param string     $href       Where it goes.
     * @param string     $id         Its id; empty for none.
     * @param ButtonTone $buttonTone Its colour.
     */
    public function __construct(
        public string $label,
        public string $href,
        public string $id,
        public ButtonTone $buttonTone,
    ) {
    }
}
