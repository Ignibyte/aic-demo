<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use App\Staff\Service\StaffSession;
use App\Theme\Components\Icon\Heroicons;
use App\Theme\Components\Icon\Icon;
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

        // Appended one by one: elephc types an array spread as a list of mixed, which it refuses to assign (no AIC ticket
        // carries that yet).
        foreach ($signedIn ? self::staffLinks($heroicons, $path) : [] as $staffLink) {
            $links[] = $staffLink;
        }

        $links[] = new KeyedComponent('theme', new NavLink('/theme', 'Theme', $heroicons->cog(), self::isCurrent('/theme', $path)));

        return new Navigation(self::NAME, $links);
    }

    /**
     * @param Heroicons $heroicons The icons.
     * @param string    $path      The page's path.
     *
     * @throws LogicException When a link's key is malformed.
     *
     * @return list<KeyedComponent> The staff areas' links, each current on its pages.
     */
    private static function staffLinks(Heroicons $heroicons, string $path): array
    {
        return [
            self::link('inventory', '/app/inventory', 'Inventory', $heroicons->truck(), $path),
            self::link('customers', '/app/customers', 'Customers', $heroicons->users(), $path),
            self::link('sales', '/app/sales', 'Sales', $heroicons->banknotes(), $path),
            self::link('leads', '/app/leads', 'Leads', $heroicons->inbox(), $path),
            self::link('holds', '/app/holds', 'Holds', $heroicons->clock(), $path),
        ];
    }

    /**
     * @param string $key   The link's key.
     * @param string $href  Its path.
     * @param string $label Its text.
     * @param Icon   $icon  Its icon.
     * @param string $path  The page's path.
     *
     * @throws LogicException When the key is malformed.
     *
     * @return KeyedComponent The link, current on its pages.
     */
    private static function link(string $key, string $href, string $label, Icon $icon, string $path): KeyedComponent
    {
        return new KeyedComponent($key, new NavLink($href, $label, $icon, self::isCurrent($href, $path)));
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
