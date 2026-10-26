<?php

declare(strict_types=1);

namespace App\Theme\Components\ConfirmCard;

use AIC\Component\Contracts\Component;
use App\Theme\Components\DetailCard\ButtonLink;

/**
 * A question before something cannot be undone, from confirm-card.phtml: what it is about, the question, the form that
 * does it, and a way back.
 */
final readonly class ConfirmCard implements Component
{
    /**
     * @param string     $title    What it is about.
     * @param string     $detail   A line that identifies it.
     * @param string     $question The question.
     * @param Component  $form     The form that does it.
     * @param ButtonLink $keep     The way back.
     */
    public function __construct(
        public string $title,
        public string $detail,
        public string $question,
        public Component $form,
        public ButtonLink $keep,
    ) {
    }
}
