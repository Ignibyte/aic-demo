<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use JsonSerializable;
use Override;

/**
 * One vehicle in the feed for listing partners, as JSON: its slug and page, what it is, its mileage, its price as a
 * decimal string and its photos' paths. It holds no VIN and no cost.
 */
final readonly class FeedVehicle implements JsonSerializable
{
    /**
     * @param string       $slug           Its slug.
     * @param string       $path           Its public page's path.
     * @param int          $year           Its model year.
     * @param string       $make           Its make.
     * @param string       $model          Its model.
     * @param string       $trim           Its trim.
     * @param string       $bodyStyle      Its body style.
     * @param int          $mileage        Its odometer, in miles.
     * @param string       $exteriorColour Its paint.
     * @param string       $price          Its price, a decimal string such as `28995.00`.
     * @param list<string> $photos         Its photos' paths, the main one first.
     */
    public function __construct(
        private string $slug,
        private string $path,
        private int $year,
        private string $make,
        private string $model,
        private string $trim,
        private string $bodyStyle,
        private int $mileage,
        private string $exteriorColour,
        private string $price,
        private array $photos,
    ) {
    }

    /**
     * @return array<string, string|int|list<string>> Its fields by name.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'body_style' => $this->bodyStyle,
            'exterior_colour' => $this->exteriorColour,
            'make' => $this->make,
            'mileage' => $this->mileage,
            'model' => $this->model,
            'path' => $this->path,
            'photos' => $this->photos,
            'price' => $this->price,
            'slug' => $this->slug,
            'trim' => $this->trim,
            'year' => $this->year,
        ];
    }
}
