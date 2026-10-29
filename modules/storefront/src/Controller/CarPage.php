<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Inventory\Entity\ListedVehicle;
use App\Storefront\Service\CarFinder;
use App\Storefront\Service\CarProfiles;
use App\Storefront\Service\StorePages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /cars/{slug}: one car for sale, its photos, details and price; 404 in the public look for a car that is sold, on
 * hold or unknown, or for a slug that is not the car's own.
 */
final readonly class CarPage implements Page
{
    /**
     * @param StorePages  $storePages  The public pages' document.
     * @param CarFinder   $carFinder   Finds the car the slug names.
     * @param CarProfiles $carProfiles The car's page.
     */
    public function __construct(
        private StorePages $storePages,
        private CarFinder $carFinder,
        private CarProfiles $carProfiles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the slug.
     *
     * @throws LogicException   When an entity type is malformed, or a key or a region does not fit the theme.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return PageResponse The car's page, or the 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $listedVehicle = $this->carFinder->car($serverRequest);

        if (!$listedVehicle instanceof ListedVehicle) {
            return $this->storePages->missing();
        }

        $carProfile = $this->carProfiles->profile($listedVehicle);

        return $this->storePages->page($listedVehicle->title(), [new KeyedComponent('car', $carProfile)], 200);
    }
}
