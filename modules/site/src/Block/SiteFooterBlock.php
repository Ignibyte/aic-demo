<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use App\Theme\Components\SiteFooter\SiteFooter;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The footer line, in `footer` on every page.
 */
final readonly class SiteFooterBlock implements Block
{
    /**
     * The site's name, as config/app.php names it.
     */
    private const string NAME = 'Lotline';

    /**
     * @return Placement `footer`, weight 0, every page.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('footer', 0, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request; the line is the same on every page.
     *
     * @return Component The footer.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new SiteFooter(self::NAME, 'Ridgeway Motors');
    }
}
