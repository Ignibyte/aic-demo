<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Staff\Service\StaffDealership;
use App\Theme\Components\BootstrapIntro\BootstrapIntro;
use App\Theme\Components\TopBar\TopBar;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Lotline's foundation page at `/`, the Dashboard entry: the top bar's title in `header` and the introduction in
 * `content`, in the theme's drawer, naming the dealership from its profile (#23); the navigation and footer are
 * blocks.
 */
final readonly class BootstrapPage implements Page
{
    /**
     * @param AppConfig       $appConfig       The application name.
     * @param PageDocuments   $pageDocuments   The theme's document around the page.
     * @param StaffDealership $staffDealership The dealership's name.
     */
    public function __construct(
        private AppConfig $appConfig,
        private PageDocuments $pageDocuments,
        private StaffDealership $staffDealership,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @throws InvalidArgumentException When the page's regions do not fit the theme.
     * @throws RuntimeException         When the dealership's name cannot be read.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $bootstrapIntro = new BootstrapIntro(
            $this->appConfig->name,
            $this->staffDealership->name(),
            'A dealership operations app is taking shape. Inventory, customers and sales will come together here. '
                . 'Staff can sign in now; daily operations are coming in later updates.',
            '/health',
        );

        return $this->pageDocuments->page(
            new Regions([new Region('header', [new TopBar('Dashboard')]), new Region('content', [$bootstrapIntro])]),
            200,
            'Dashboard',
        );
    }
}
