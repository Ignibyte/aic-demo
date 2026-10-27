<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Entity\VehicleStatus;

use function explode;
use function intdiv;

/**
 * The 60 invented vehicles `inventory:seed` saves: five for each of twelve makes, each one of its make's three models
 * in one of their trims. Everything else follows from the vehicle's place by arithmetic, and the days on the lot are
 * counted back from a fixed date, so every run makes the same 60.
 *
 * @phpstan-type SeedModel array{body: string, make: string, model: string, price: int, trims: string}
 *
 * @psalm-type SeedModel = array{body: string, make: string, model: string, price: int, trims: string}
 */
final readonly class SeedVehicles
{
    /**
     * How many vehicles the seed makes.
     */
    private const int COUNT = 60;

    /**
     * How many makes there are; each has three models, in MODELS one after another.
     */
    private const int MAKES = 12;

    /**
     * Each make's three models: its body style, its price new in dollars and its three trims.
     */
    private const array MODELS = [
        ['body' => 'Truck', 'make' => 'Ford', 'model' => 'F-150', 'price' => 46_000, 'trims' => 'XL, XLT, Lariat'],
        ['body' => 'SUV', 'make' => 'Ford', 'model' => 'Escape', 'price' => 29_000, 'trims' => 'S, SE, Titanium'],
        ['body' => 'Coupe', 'make' => 'Ford', 'model' => 'Mustang', 'price' => 34_000, 'trims' => 'EcoBoost, Premium, GT'],
        ['body' => 'Truck', 'make' => 'Chevrolet', 'model' => 'Silverado 1500', 'price' => 44_000, 'trims' => 'WT, LT, RST'],
        ['body' => 'SUV', 'make' => 'Chevrolet', 'model' => 'Equinox', 'price' => 28_000, 'trims' => 'LS, LT, Premier'],
        ['body' => 'Sedan', 'make' => 'Chevrolet', 'model' => 'Malibu', 'price' => 24_000, 'trims' => 'LS, RS, LT'],
        ['body' => 'Sedan', 'make' => 'Toyota', 'model' => 'Camry', 'price' => 28_000, 'trims' => 'LE, SE, XLE'],
        ['body' => 'SUV', 'make' => 'Toyota', 'model' => 'RAV4', 'price' => 31_000, 'trims' => 'LE, XLE, Limited'],
        ['body' => 'Truck', 'make' => 'Toyota', 'model' => 'Tacoma', 'price' => 36_000, 'trims' => 'SR, SR5, TRD Sport'],
        ['body' => 'Sedan', 'make' => 'Honda', 'model' => 'Civic', 'price' => 25_000, 'trims' => 'LX, Sport, EX'],
        ['body' => 'SUV', 'make' => 'Honda', 'model' => 'CR-V', 'price' => 31_000, 'trims' => 'LX, EX, EX-L'],
        ['body' => 'Minivan', 'make' => 'Honda', 'model' => 'Odyssey', 'price' => 38_000, 'trims' => 'EX, EX-L, Touring'],
        ['body' => 'Sedan', 'make' => 'Nissan', 'model' => 'Altima', 'price' => 26_000, 'trims' => 'S, SV, SR'],
        ['body' => 'SUV', 'make' => 'Nissan', 'model' => 'Rogue', 'price' => 29_000, 'trims' => 'S, SV, SL'],
        ['body' => 'Truck', 'make' => 'Nissan', 'model' => 'Frontier', 'price' => 32_000, 'trims' => 'S, SV, PRO-4X'],
        ['body' => 'Sedan', 'make' => 'Hyundai', 'model' => 'Elantra', 'price' => 22_000, 'trims' => 'SE, SEL, Limited'],
        ['body' => 'SUV', 'make' => 'Hyundai', 'model' => 'Tucson', 'price' => 28_000, 'trims' => 'SE, SEL, Limited'],
        ['body' => 'SUV', 'make' => 'Hyundai', 'model' => 'Santa Fe', 'price' => 33_000, 'trims' => 'SE, SEL, Calligraphy'],
        ['body' => 'Sedan', 'make' => 'Kia', 'model' => 'Forte', 'price' => 20_000, 'trims' => 'LX, LXS, GT-Line'],
        ['body' => 'SUV', 'make' => 'Kia', 'model' => 'Sportage', 'price' => 28_000, 'trims' => 'LX, EX, SX'],
        ['body' => 'SUV', 'make' => 'Kia', 'model' => 'Telluride', 'price' => 38_000, 'trims' => 'LX, EX, SX'],
        ['body' => 'Wagon', 'make' => 'Subaru', 'model' => 'Outback', 'price' => 30_000, 'trims' => 'Base, Premium, Limited'],
        ['body' => 'SUV', 'make' => 'Subaru', 'model' => 'Forester', 'price' => 29_000, 'trims' => 'Base, Premium, Sport'],
        ['body' => 'SUV', 'make' => 'Subaru', 'model' => 'Crosstrek', 'price' => 26_000, 'trims' => 'Base, Premium, Sport'],
        ['body' => 'Hatchback', 'make' => 'Mazda', 'model' => 'Mazda3', 'price' => 24_000, 'trims' => 'S, Select, Premium'],
        ['body' => 'SUV', 'make' => 'Mazda', 'model' => 'CX-5', 'price' => 29_000, 'trims' => 'S, Select, Carbon Edition'],
        ['body' => 'Convertible', 'make' => 'Mazda', 'model' => 'MX-5 Miata', 'price' => 30_000, 'trims' => 'Sport, Club, Grand Touring'],
        ['body' => 'SUV', 'make' => 'Jeep', 'model' => 'Wrangler', 'price' => 36_000, 'trims' => 'Sport, Sahara, Rubicon'],
        ['body' => 'SUV', 'make' => 'Jeep', 'model' => 'Grand Cherokee', 'price' => 41_000, 'trims' => 'Laredo, Limited, Overland'],
        ['body' => 'Truck', 'make' => 'Jeep', 'model' => 'Gladiator', 'price' => 40_000, 'trims' => 'Sport, Mojave, Rubicon'],
        ['body' => 'Sedan', 'make' => 'Volkswagen', 'model' => 'Jetta', 'price' => 23_000, 'trims' => 'S, SE, SEL'],
        ['body' => 'SUV', 'make' => 'Volkswagen', 'model' => 'Tiguan', 'price' => 29_000, 'trims' => 'S, SE, SEL R-Line'],
        ['body' => 'Hatchback', 'make' => 'Volkswagen', 'model' => 'Golf GTI', 'price' => 32_000, 'trims' => 'S, SE, Autobahn'],
        ['body' => 'Sedan', 'make' => 'BMW', 'model' => '3 Series', 'price' => 45_000, 'trims' => '330i, 330i xDrive, M340i'],
        ['body' => 'SUV', 'make' => 'BMW', 'model' => 'X3', 'price' => 49_000, 'trims' => 'sDrive30i, xDrive30i, M40i'],
        ['body' => 'SUV', 'make' => 'BMW', 'model' => 'X5', 'price' => 66_000, 'trims' => 'sDrive40i, xDrive40i, M60i'],
    ];

    /**
     * The paints, in the order the seed hands them out.
     */
    private const array COLOURS = ['White', 'Black', 'Silver', 'Grey', 'Blue', 'Red', 'Green', 'Beige', 'Brown', 'Orange'];

    /**
     * The days in each month of 2026, January first.
     */
    private const array MONTH_DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    /**
     * The day the seed counts back from, 2026-10-20, as the day of its year.
     */
    private const int LAST_DAY = 293;

    /**
     * @param Vins $vins Writes each VIN.
     */
    public function __construct(
        private Vins $vins = new Vins(),
    ) {
    }

    /**
     * @return list<Vehicle> The 60 vehicles, the same on every call.
     */
    public function all(): array
    {
        $vehicles = [];

        for ($index = 0; $index < self::COUNT; $index += 1) {
            $vehicles[] = $this->vehicle($index);
        }

        return $vehicles;
    }

    /**
     * @param int<0, max> $index The vehicle's place, 0 to 59.
     *
     * @return Vehicle The vehicle there: its make by the place, one of the make's models and that model's trims by
     *                 the place too, its model year 2017 to 2025, and a price that falls with age.
     */
    private function vehicle(int $index): Vehicle
    {
        $make = $index % self::MAKES;
        $model = ($index % 5 + $make) % 3;
        $trim = ($index * 7 + $index % 4) % 3;
        $year = 2_025 - ($index * 7 + $make) % 9;
        $seedModel = self::MODELS[$make * 3 + $model];
        $price = $this->price($seedModel['price'], $year);

        return new Vehicle(
            $this->vins->vin($make, $model, $trim, $index, $year),
            $year,
            $seedModel['make'],
            $seedModel['model'],
            explode(', ', $seedModel['trims'])[$trim] ?? '',
            $seedModel['body'],
            (2_026 - $year) * 11_400 + ($index * 2_389) % 8_000,
            self::COLOURS[($index * 3 + $make) % 10],
            new VehiclePrice($price, intdiv($price * 86, 10_000) * 100),
            $this->status($index),
            $this->daysBefore($index * 2 + $index % 5),
        );
    }

    /**
     * @param int $new  The model's price new, in dollars.
     * @param int $year The vehicle's model year.
     *
     * @return int Its list price: 7% off the price new for each year of age, to the 500 below, less 5.
     */
    private function price(int $new, int $year): int
    {
        return intdiv($new * (100 - 7 * (2_026 - $year)), 50_000) * 500 - 5;
    }

    /**
     * @param int $index The vehicle's place.
     *
     * @return VehicleStatus On hold for every seventh from the fourth, sold for every sixth from the sixth, else in
     *                       stock.
     */
    private function status(int $index): VehicleStatus
    {
        // if/return rather than a ternary: elephc types a ternary whose arms are enum cases as their backing string,
        // and the native site's build refuses the string where a VehicleStatus is due (gate:23). It is a limit beside
        // the match one AIC #164 carries; no AIC ticket carries it yet.
        if ($index % 7 === 3) {
            return VehicleStatus::OnHold;
        }

        if ($index % 6 === 5) {
            return VehicleStatus::Sold;
        }

        return VehicleStatus::InStock;
    }

    /**
     * Counted by hand over 2026's months rather than with PHP's date classes, which the seed has no need of: every
     * date it makes falls in 2026, from 2026-06-20 to 2026-10-20, and the plain arithmetic is what elephc is known to
     * compile.
     *
     * @param int $days How long the vehicle has been on the lot, under 293 days.
     *
     * @return string The day it came, counted back from 2026-10-20, as `YYYY-MM-DD`.
     */
    private function daysBefore(int $days): string
    {
        $day = self::LAST_DAY - $days;
        $month = 1;

        foreach (self::MONTH_DAYS as $length) {
            if ($day <= $length) {
                break;
            }

            $day -= $length;
            $month += 1;
        }

        return '2026-' . ($month < 10 ? '0' : '') . $month . '-' . ($day < 10 ? '0' : '') . $day;
    }
}
