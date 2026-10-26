<?php

declare(strict_types=1);

namespace App\Theme\Components\DetailCard;

/**
 * A button link's colour, as its daisyUI class.
 */
enum ButtonTone: string
{
    /** The main action. */
    case Primary = 'btn-primary';

    /** A second action that moves the record on. */
    case Secondary = 'btn-secondary';

    /** Going back. */
    case Ghost = 'btn-ghost';

    /** Removing. */
    case Error = 'btn-error';
}
