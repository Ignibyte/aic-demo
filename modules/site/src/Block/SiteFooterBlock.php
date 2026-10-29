<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use App\Staff\Service\StaffDealership;
use App\Theme\Components\SiteFooter\SiteFooter;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The footer line, in `footer` on every page: the site's name and the dealership's, from its profile (#23).
 */
final readonly class SiteFooterBlock implements Block
{
    /**
     * The site's name, as config/app.php names it.
     */
    private const string NAME = 'Lotline';

    /**
     * @param StaffDealership $staffDealership The dealership's name.
     */
    public function __construct(
        private StaffDealership $staffDealership,
    ) {
    }

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
     * @throws RuntimeException         When the dealership's name cannot be read.
     * @throws InvalidArgumentException When an entity type the name is read through is malformed.
     *
     * @return Component The footer.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new SiteFooter(self::NAME, $this->staffDealership->name());
    }
}
