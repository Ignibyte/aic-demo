<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Customers\Service\SeedCustomers;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleLot;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Service\SeedVehicles;
use App\Inventory\Service\Vins;
use App\Sales\Entity\SeedSale;

use function count;
use function explode;
use function intdiv;
use function substr;

/**
 * The seed's invented sales, the same on every call: 36 vehicles of its own, three sold a month from November 2025 to
 * October 2026, each a few days after it came onto the lot; and the 8 vehicles the inventory seed marks sold, each
 * sold 10 days after it came. They go to the seeded customers and the sales staff in turn, at their list price less a
 * small discount. Every date is fixed, counted from the month's own days, so no PHP date class is needed: plain
 * arithmetic is what elephc is known to compile, as `SeedVehicles` notes; no AIC ticket carries the date classes. A
 * sale's day is its vehicle's day on the lot and its days there, counted over the months' lengths (#14), since the
 * entity store writes a sale's values and not a sum Postgres works out.
 *
 * @psalm-type SeedModel = array{make: string, model: string, trims: string, body: string, price: int}
 */
final readonly class SeedSales
{
    /**
     * How many vehicles of its own the seed sells.
     */
    private const int COUNT = 36;

    /**
     * The VIN place of its first vehicle: the inventory seed's take 0 to 59.
     */
    private const int FIRST_PLACE = 60;

    /**
     * The models its vehicles are, each one's trims and its price new in dollars; none is on the inventory seed's lot.
     */
    private const array MODELS = [
        ['make' => 'Ford', 'model' => 'Explorer', 'trims' => 'Base, XLT, Limited', 'body' => 'SUV', 'price' => 39_000],
        ['make' => 'Toyota', 'model' => 'Highlander', 'trims' => 'LE, XLE, Platinum', 'body' => 'SUV', 'price' => 41_000],
        ['make' => 'Honda', 'model' => 'Accord', 'trims' => 'LX, Sport, Touring', 'body' => 'Sedan', 'price' => 29_000],
        ['make' => 'Chevrolet', 'model' => 'Tahoe', 'trims' => 'LS, LT, Premier', 'body' => 'SUV', 'price' => 58_000],
        ['make' => 'Nissan', 'model' => 'Sentra', 'trims' => 'S, SV, SR', 'body' => 'Sedan', 'price' => 21_000],
        ['make' => 'Hyundai', 'model' => 'Kona', 'trims' => 'SE, SEL, N Line', 'body' => 'SUV', 'price' => 25_000],
        ['make' => 'Kia', 'model' => 'Soul', 'trims' => 'LX, S, GT-Line', 'body' => 'Hatchback', 'price' => 21_000],
        ['make' => 'Subaru', 'model' => 'Ascent', 'trims' => 'Base, Premium, Touring', 'body' => 'SUV', 'price' => 36_000],
        ['make' => 'Mazda', 'model' => 'CX-30', 'trims' => 'S, Select, Turbo', 'body' => 'SUV', 'price' => 26_000],
        ['make' => 'Ram', 'model' => '1500', 'trims' => 'Tradesman, Big Horn, Laramie', 'body' => 'Truck', 'price' => 42_000],
        ['make' => 'GMC', 'model' => 'Sierra 1500', 'trims' => 'Pro, SLE, Denali', 'body' => 'Truck', 'price' => 45_000],
        ['make' => 'Volkswagen', 'model' => 'Atlas', 'trims' => 'SE, SEL, Peak Edition', 'body' => 'SUV', 'price' => 38_000],
    ];

    /**
     * Its paints, in the order it hands them out.
     */
    private const array PAINTS = ['Pearl White', 'Graphite', 'Navy', 'Crimson', 'Forest Green', 'Sand'];

    /**
     * The days of its month on which a month's three vehicles came onto the lot.
     */
    private const array ARRIVALS = [1, 7, 13];

    /**
     * @param Vins          $vins          Writes each VIN.
     * @param SeedVehicles  $seedVehicles  The inventory seed's vehicles.
     * @param SeedCustomers $seedCustomers The customers seed's customers.
     * @param MonthLengths  $monthLengths  How long a month is.
     */
    public function __construct(
        private Vins $vins = new Vins(),
        private SeedVehicles $seedVehicles = new SeedVehicles(),
        private SeedCustomers $seedCustomers = new SeedCustomers(),
        private MonthLengths $monthLengths = new MonthLengths(),
    ) {
    }

    /**
     * @return list<Vehicle> The 36 sold vehicles of its own.
     */
    public function vehicles(): array
    {
        $vehicles = [];

        for ($index = 0; $index < self::COUNT; $index += 1) {
            $vehicles[] = $this->vehicle($index);
        }

        return $vehicles;
    }

    /**
     * @param list<string> $sellers The sales staff's logins; none gives no sales.
     *
     * @return list<SeedSale> The 44 sales: its own vehicles' and then the inventory seed's sold ones.
     */
    public function sales(array $sellers): array
    {
        $emails = $this->emails();

        if ($sellers === [] || $emails === []) {
            return [];
        }

        $sales = [];

        foreach ($this->vehicles() as $index => $vehicle) {
            $sales[] = $this->sale($vehicle, 2 + $index * 5 % 6, count($sales), $emails, $sellers);
        }

        foreach ($this->soldOnTheLot() as $vehicle) {
            $sales[] = $this->sale($vehicle, 10, count($sales), $emails, $sellers);
        }

        return $sales;
    }

    /**
     * @return list<Vehicle> The inventory seed's vehicles that it marks sold, which the seed sells too.
     */
    public function soldOnTheLot(): array
    {
        $sold = [];

        foreach ($this->seedVehicles->all() as $vehicle) {
            if ($vehicle->status !== VehicleStatus::Sold) {
                continue;
            }

            $sold[] = $vehicle;
        }

        return $sold;
    }

    /**
     * @return list<string> The seeded customers' emails, in the seed's order.
     */
    private function emails(): array
    {
        $emails = [];

        foreach ($this->seedCustomers->all() as $customerDetails) {
            $emails[] = $customerDetails->email;
        }

        return $emails;
    }

    /**
     * @param Vehicle      $vehicle The vehicle sold.
     * @param int          $days    The days from its arrival to the sale.
     * @param int          $place   The sale's place in the seed.
     * @param list<string> $emails  The customers' emails, at least one.
     * @param list<string> $sellers The sales staff's logins, at least one.
     *
     * @return SeedSale Its sale, to the customer and the seller its place picks, at its list price less 0 to $1,000.
     */
    private function sale(Vehicle $vehicle, int $days, int $place, array $emails, array $sellers): SeedSale
    {
        $customer = $emails[$place * 7 % count($emails)] ?? '';
        $seller = $sellers[$place % count($sellers)] ?? '';

        $price = $vehicle->price->list - 250 * ($place % 5);

        return new SeedSale($vehicle->vin, $customer, $seller, $price, $this->later($vehicle->lot->addedOn, $days));
    }

    /**
     * @param int<0, max> $index The vehicle's place, 0 to 35.
     *
     * @return Vehicle The vehicle there: its model by the place, a model year from 2019 to 2025, sold, and on the lot
     *                 from its month's arrival day, its month counted from November 2025.
     */
    private function vehicle(int $index): Vehicle
    {
        $seedModel = self::MODELS[$index % 12];
        $modelYear = 2_019 + $index * 5 % 7;
        $trimPlace = $index * 2 % 3;
        $listPrice = intdiv($seedModel['price'] * (100 - 6 * (2_026 - $modelYear)), 10_000) * 100 - 1;

        return new Vehicle(
            $this->vins->vin($index % 12, 0, $trimPlace, self::FIRST_PLACE + $index, $modelYear),
            $modelYear,
            $seedModel['make'],
            $seedModel['model'],
            explode(', ', $seedModel['trims'])[$trimPlace],
            $seedModel['body'],
            (2_026 - $modelYear) * 12_100 + $index * 1_877 % 6_000,
            self::PAINTS[$index % 6],
            new VehiclePrice($listPrice, intdiv($listPrice * 88, 10_000) * 100),
            VehicleStatus::Sold,
            new VehicleLot($this->arrival($index)),
        );
    }

    /**
     * @param int<0, max> $index The vehicle's place, 0 to 35.
     *
     * @return string The day it came onto the lot, `YYYY-MM-DD`: its month the place's third, from November 2025, and
     *                its day that month's first, second or third arrival day.
     */
    private function arrival(int $index): string
    {
        $month = intdiv($index, 3);
        $number = ($month + 10) % 12 + 1;
        $day = self::ARRIVALS[$index % 3];

        return ($month < 2 ? '2025' : '2026') . '-' . ($number < 10 ? '0' : '') . $number . '-' . ($day < 10 ? '0' : '') . $day;
    }

    /**
     * @param string $day  A day, `YYYY-MM-DD`.
     * @param int    $days How many days after it, from 0.
     *
     * @return string The day that many days later, `YYYY-MM-DD`, across the ends of months and years.
     */
    private function later(string $day, int $days): string
    {
        $year = (int) substr($day, 0, 4);
        $month = (int) substr($day, 5, 2);
        $date = (int) substr($day, 8, 2) + $days;

        while ($date > $this->monthLengths->days($year, $month)) {
            $date -= $this->monthLengths->days($year, $month);
            $year += intdiv($month, 12);
            $month = $month % 12 + 1;
        }

        return $year . '-' . ($month < 10 ? '0' : '') . $month . '-' . ($date < 10 ? '0' : '') . $date;
    }
}
