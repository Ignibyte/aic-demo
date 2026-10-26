<?php

declare(strict_types=1);

namespace App\Theme\Components\NavLink;

use AIC\Component\Contracts\Component;
use App\Theme\Components\Icon\Icon;

/**
 * One sidebar link, from nav-link.phtml: its icon and label, marked `aria-current="page"` when it is the current
 * entry, which daisyUI's menu shows as active.
 */
final readonly class NavLink implements Component
{
    /**
     * @param string $path    Its path.
     * @param string $label   Its text.
     * @param Icon   $icon    Its icon.
     * @param bool   $current Whether the request lies at or below its path, and no longer link's path holds it.
     */
    public function __construct(
        public string $path,
        public string $label,
        public Icon $icon,
        public bool $current,
    ) {
    }
}
