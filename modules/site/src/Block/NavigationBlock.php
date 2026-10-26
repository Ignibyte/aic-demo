<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use App\Theme\Components\Icon\Heroicons;
use App\Theme\Components\Navigation\Navigation;
use App\Theme\Components\NavLink\NavLink;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function str_starts_with;

/**
 * Lotline's sidebar, in `sidebar` on every page: the site's name, then a link to each area with its icon, the current
 * one marked from the page's request. Areas join the list as their pages land.
 */
final readonly class NavigationBlock implements Block
{
    /**
     * The site's name, as config/app.php names it.
     */
    private const string NAME = 'Lotline';

    /**
     * @return Placement `sidebar`, weight 0, every page.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('sidebar', 0, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, whose path picks the current link.
     *
     * @throws InvalidArgumentException When a link's key is malformed.
     *
     * @return Component The sidebar.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        $path = $serverRequest->getUri()->getPath();
        $navLink = new NavLink('/', 'Dashboard', (new Heroicons())->home(), self::isCurrent('/', $path));

        return new Navigation(self::NAME, [new KeyedComponent('dashboard', $navLink)]);
    }

    /**
     * @param string $href A link's path.
     * @param string $path The page's path.
     *
     * @return bool Whether the link is the page or a parent of it; `/` is current only on itself.
     */
    private static function isCurrent(string $href, string $path): bool
    {
        return $path === $href || ($href !== '/' && str_starts_with($path, $href . '/'));
    }
}
