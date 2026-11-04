<?php

declare(strict_types=1);

namespace App\Theme\Components\CaseWatch;

/**
 * The Tessera event a style guide resilience case watches (#34), by its name.
 */
enum WatchedEvent: string
{
    /** An answer was 419: raised on the document; Tessera's reload prompt follows. */
    case Expired = 'tessera:expired';

    /** A write may have been saved: raised on the form; Tessera's alert follows. */
    case Indeterminate = 'tessera:indeterminate';

    /** A request, a poll or a directive failed: raised on its element, kept out of the console here. */
    case Error = 'tessera:error';
}
