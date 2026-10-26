<?php

declare(strict_types=1);

namespace App\Theme\Components\Notice;

/**
 * A notice's colour, as its daisyUI class.
 */
enum NoticeTone: string
{
    /** For information. */
    case Info = 'alert-info';

    /** Something worked. */
    case Success = 'alert-success';

    /** Something was refused or needs care. */
    case Warning = 'alert-warning';
}
