<?php

declare(strict_types=1);

namespace App\Site\Service;

use App\Site\Form\ThemeQuery;
use App\Theme\Components\StatusBadge\BadgeTone;

use function ltrim;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function usort;

/**
 * The style guide's sample Ridgeway Motors stock: eight vehicles, the ones a search and a status keep, in the order a
 * sort asks for, and each status's label and badge colour.
 *
 * @phpstan-type SampleVehicle array{
 *     vin: string, year: string, make: string, model: string, miles: int, price: int, status: string
 * }
 *
 * @psalm-type SampleVehicle = array{
 *     vin: string, year: string, make: string, model: string, miles: int, price: int, status: string
 * }
 */
final readonly class ThemeSampleStock
{
    /**
     * Each status's label, by value.
     */
    public const array STATUSES = ['in_stock' => 'In stock', 'on_hold' => 'On hold', 'sold' => 'Sold'];

    /**
     * The sample stock, in the order it came onto the lot.
     */
    private const array VEHICLES = [
        [
            'make' => 'Ford',
            'miles' => 18_420,
            'model' => 'F-150 XLT',
            'price' => 41_900,
            'status' => 'in_stock',
            'vin' => '1FTFW1E50PFA10233',
            'year' => '2023',
        ],
        [
            'make' => 'Honda',
            'miles' => 26_110,
            'model' => 'Accord EX',
            'price' => 27_450,
            'status' => 'on_hold',
            'vin' => '1HGCV1F34NA051212',
            'year' => '2022',
        ],
        [
            'make' => 'Tesla',
            'miles' => 33_780,
            'model' => 'Model 3',
            'price' => 29_990,
            'status' => 'sold',
            'vin' => '5YJ3E1EA7MF901544',
            'year' => '2021',
        ],
        [
            'make' => 'Toyota',
            'miles' => 61_200,
            'model' => 'Prius',
            'price' => 18_950,
            'status' => 'in_stock',
            'vin' => 'JTDKARFU8K3071188',
            'year' => '2019',
        ],
        [
            'make' => 'Jeep',
            'miles' => 40_150,
            'model' => 'Grand Cherokee',
            'price' => 33_200,
            'status' => 'in_stock',
            'vin' => '1C4RJFBG2MC601877',
            'year' => '2021',
        ],
        [
            'make' => 'Volkswagen',
            'miles' => 72_880,
            'model' => 'Jetta S',
            'price' => 13_400,
            'status' => 'sold',
            'vin' => '3VW2B7AJ9JM216402',
            'year' => '2018',
        ],
        [
            'make' => 'Hyundai',
            'miles' => 48_900,
            'model' => 'Tucson SE',
            'price' => 19_750,
            'status' => 'on_hold',
            'vin' => 'KM8J3CA46LU183319',
            'year' => '2020',
        ],
        [
            'make' => 'Chevrolet',
            'miles' => 52_300,
            'model' => 'Malibu LT',
            'price' => 17_600,
            'status' => 'in_stock',
            'vin' => '1G1ZD5ST1LF033751',
            'year' => '2020',
        ],
    ];

    /**
     * @param ThemeQuery $themeQuery What the list was asked for.
     *
     * @return list<SampleVehicle> The matching vehicles, sorted.
     */
    public function vehicles(ThemeQuery $themeQuery): array
    {
        return $this->sorted($this->matching($themeQuery), $themeQuery->sort);
    }

    /**
     * @param string $status A status's value.
     *
     * @return BadgeTone Its badge's colour.
     */
    public function tone(string $status): BadgeTone
    {
        return match ($status) {
            'in_stock' => BadgeTone::Success,
            'on_hold' => BadgeTone::Warning,
            default => BadgeTone::Neutral,
        };
    }

    /**
     * @param ThemeQuery $themeQuery What the list was asked for.
     *
     * @return list<SampleVehicle> The vehicles its search and status keep.
     */
    private function matching(ThemeQuery $themeQuery): array
    {
        $search = strtolower($themeQuery->search);
        $status = $themeQuery->status;
        $matching = [];

        foreach (self::VEHICLES as $vehicle) {
            if (!$this->keeps($vehicle, $status, $search)) {
                continue;
            }

            $matching[] = $vehicle;
        }

        return $matching;
    }

    /**
     * @param list<SampleVehicle> $vehicles The vehicles.
     * @param string              $sort     `year`, `-year`, `price`, `-price` or anything else for their own order.
     *
     * @return list<SampleVehicle> In that order.
     */
    private function sorted(array $vehicles, string $sort): array
    {
        $field = ltrim($sort, '-');

        return $field === 'year' || $field === 'price'
            ? $this->sortedBy($vehicles, $field, str_starts_with($sort, '-') ? -1 : 1)
            : $vehicles;
    }

    /**
     * @param list<SampleVehicle> $vehicles The vehicles.
     * @param string              $field    `year` or `price`.
     * @param int                 $sign     1 for ascending, -1 for descending.
     *
     * @return list<SampleVehicle> Sorted by that field.
     */
    private function sortedBy(array $vehicles, string $field, int $sign): array
    {
        usort(
            $vehicles,
            /**
             * @param SampleVehicle $one   A vehicle.
             * @param SampleVehicle $other Another.
             */
            static fn (array $one, array $other): int => $sign * ($field === 'year'
                ? $one['year'] <=> $other['year']
                : $one['price'] <=> $other['price']),
        );

        return $vehicles;
    }

    /**
     * @param SampleVehicle $vehicle A vehicle.
     * @param string        $status  The status asked for; empty for every one.
     * @param string        $search  The search text, lower case; empty for none.
     *
     * @return bool Whether it has that status and its VIN, make or model holds the text.
     */
    private function keeps(array $vehicle, string $status, string $search): bool
    {
        $text = strtolower($vehicle['vin'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']);

        return ($status === '' || $vehicle['status'] === $status) && ($search === '' || str_contains($text, $search));
    }
}
