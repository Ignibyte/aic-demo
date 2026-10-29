<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use App\Inventory\Entity\ListedVehicle;

use function preg_match;
use function preg_replace;
use function strtolower;
use function substr;
use function trim;

/**
 * A car's public slug, `{year}-{make}-{model}-{key}`: lower case, each run of characters other than `a-z` and `0-9` one
 * hyphen, and the key inventory gives it, the first 6 hex digits of the SHA-256 of its VIN. The slug is the same on
 * every database, where a row id is not, and does not show the VIN. A slug is read back into its year and key.
 */
final readonly class CarSlugs
{
    /**
     * Where the cars' pages are: the list, and below it each car's.
     */
    public const string PAGES = '/cars';

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @return string Its slug, such as `2023-volkswagen-tiguan-9f381a`.
     */
    public function slug(ListedVehicle $listedVehicle): string
    {
        return $listedVehicle->year . '-' . $this->words($listedVehicle->make) . '-' . $this->words($listedVehicle->model)
            . '-' . $listedVehicle->key;
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @return string Its page's path, `/cars/{slug}`.
     */
    public function path(ListedVehicle $listedVehicle): string
    {
        return self::PAGES . '/' . $this->slug($listedVehicle);
    }

    /**
     * @param string $slug A slug from a request.
     *
     * @return bool Whether it has a slug's form: four digits, words, and six hex digits, joined by hyphens.
     */
    public function holds(string $slug): bool
    {
        return preg_match('/^[0-9]{4}(-[a-z0-9]+)+-[0-9a-f]{6}$/', $slug) === 1;
    }

    /**
     * @param string $slug A slug that `holds()`.
     *
     * @return int The model year it starts with.
     */
    public function year(string $slug): int
    {
        return (int) substr($slug, 0, 4);
    }

    /**
     * @param string $slug A slug that `holds()`.
     *
     * @return string The key it ends with.
     */
    public function key(string $slug): string
    {
        return substr($slug, -6);
    }

    /**
     * @param string $text A make or a model.
     *
     * @return string It in lower case, each run of other characters than letters and digits one hyphen, none at
     *                either end.
     */
    private function words(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }
}
