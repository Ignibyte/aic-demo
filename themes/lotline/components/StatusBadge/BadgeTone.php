<?php

declare(strict_types=1);

namespace App\Theme\Components\StatusBadge;

/**
 * A badge's colour, as its daisyUI class.
 */
enum BadgeTone: string
{
    /** Available: in stock, completed. */
    case Success = 'badge-success';

    /** Held: on hold, pending. */
    case Warning = 'badge-warning';

    /** Done with: sold, archived. */
    case Neutral = 'badge-neutral';

    /** Undone: voided, refused. */
    case Error = 'badge-error';
}
