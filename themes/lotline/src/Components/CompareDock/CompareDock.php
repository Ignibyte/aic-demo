<?php

declare(strict_types=1);

namespace App\Theme\Components\CompareDock;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The storefront's comparison dock (#33), from compare-dock.phtml: the compare bar fixed at the foot of every store
 * page, and the side drawer that shows the chosen cars side by side. Its root declares the selection in the document's
 * scope (`compare`, the chosen slugs; `compareFull`, a fourth tick refused; `compareOpen`, the drawer shown) and keeps
 * its id with `data-permanent`, so an enhanced visit keeps the live node and the selection with it. The bar and the
 * drawer sit in one root, so a click on either is never outside it and only a click elsewhere closes the drawer. With
 * JavaScript off the bar is a plain GET form to `/compare`, which the ticks join through their `form` attribute.
 */
final readonly class CompareDock implements Component
{
    /**
     * @param KeyedComponent $drawer The `CompareDrawer`, keyed `drawer` and anchored `compare-drawer`, which the
     *                               Compare link targets.
     */
    public function __construct(
        public KeyedComponent $drawer,
    ) {
    }
}
