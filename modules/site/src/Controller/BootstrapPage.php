<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\TrackedAssets;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use App\Site\Component\BootstrapIntro;
use App\Site\Component\BootstrapLayout;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Compose the first Lotline page from data components in its document layout.
 */
final readonly class BootstrapPage implements Page
{
    /**
     * @param AppConfig $appConfig The application name and pinned browser runtime.
     */
    public function __construct(
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @throws InvalidArgumentException When the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $bootstrapIntro = new BootstrapIntro(
            $this->appConfig->name,
            'Ridgeway Motors',
            'A dealership operations app is taking shape. Inventory, customers and sales will come together here. '
                . 'This first foundation page is ready; staff sign-in and daily operations are coming in later updates.',
            '/health',
        );
        $regions = new Regions([new Region('content', [$bootstrapIntro])]);
        $trackedAssets = new TrackedAssets('/assets/aic.css', $this->appConfig->tessera->scriptPath());

        return new PageDocument(new BootstrapLayout($this->appConfig->name, $trackedAssets, $regions, $regions->region('content')));
    }
}
