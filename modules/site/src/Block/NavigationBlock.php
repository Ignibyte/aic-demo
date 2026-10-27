<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use App\Staff\Service\StaffSession;
use App\Theme\Components\Icon\Heroicons;
use App\Theme\Components\Navigation\Navigation;
use App\Theme\Components\NavLink\NavLink;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function str_starts_with;

/**
 * Lotline's sidebar, in `sidebar` on every page: the site's name, then a link to each area with its icon, the current
 * one marked from the page's request. Areas join the list as their pages land; the staff areas show only to signed-in
 * staff, for whom Dashboard goes to the dashboard at `/app` (#8), current there alone since every staff page sits under
 * it; signed out it goes to `/`.
 */
final readonly class NavigationBlock implements Block
{
    /**
     * The site's name, as config/app.php names it.
     */
    private const string NAME = 'Lotline';

    /**
     * @param StaffSession $staffSession Who is signed in.
     */
    public function __construct(
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @return Placement `sidebar`, weight 0, every page.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('sidebar', 0, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, whose path picks the current link and whose
     *                                              session says whether staff are signed in.
     *
     * @throws LogicException           When a link's key is malformed, or the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return Component The sidebar.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        $path = $serverRequest->getUri()->getPath();
        $heroicons = new Heroicons();
        $signedIn = $this->staffSession->member($serverRequest) !== null;
        $home = $signedIn
            ? '/app'
            : '/';
        $links = [new KeyedComponent('dashboard', new NavLink($home, 'Dashboard', $heroicons->home(), $path === $home))];

        if ($signedIn) {
            $navLink = new NavLink('/app/inventory', 'Inventory', $heroicons->truck(), self::isCurrent('/app/inventory', $path));
            $links[] = new KeyedComponent('inventory', $navLink);
            $customersLink = new NavLink('/app/customers', 'Customers', $heroicons->users(), self::isCurrent('/app/customers', $path));
            $links[] = new KeyedComponent('customers', $customersLink);
            $salesLink = new NavLink('/app/sales', 'Sales', $heroicons->banknotes(), self::isCurrent('/app/sales', $path));
            $links[] = new KeyedComponent('sales', $salesLink);
        }

        $links[] = new KeyedComponent('theme', new NavLink('/theme', 'Theme', $heroicons->cog(), self::isCurrent('/theme', $path)));

        return new Navigation(self::NAME, $links);
    }

    /**
     * @param string $href A link's path.
     * @param string $path The page's path.
     *
     * @return bool Whether the link is the page or a parent of it.
     */
    private static function isCurrent(string $href, string $path): bool
    {
        return $path === $href || str_starts_with($path, $href . '/');
    }
}
