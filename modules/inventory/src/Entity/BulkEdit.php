<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use function count;
use function number_format;

/**
 * A bulk edit of the inventory list as posted (#29), read: what it does and to what, the ticked VINs, the rows the page
 * showed, whether a delete was confirmed, the list it came from (which a plain post goes back to) and its query, whether
 * Tessera sent it, and the visitor's form token.
 */
final readonly class BulkEdit
{
    /**
     * The most vehicles one bulk edit takes: a page of the list.
     */
    public const int MOST = 15;

    /**
     * @param ?BulkAction           $bulkAction      What it does; null for a choice that is none of the bar's.
     * @param ?VehicleStatus        $vehicleStatus   The status a status change gives; null for another action.
     * @param ?VehicleLocation      $vehicleLocation The location a move gives; null for another action.
     * @param list<string>          $vins            The ticked VINs, each once, in the order they came.
     * @param list<string>          $page            The VINs of the rows the page showed, in order.
     * @param bool                  $confirmed       Whether a delete was confirmed.
     * @param string                $back            The list it came from, the list's path and its query.
     * @param array<string, string> $query           The list's query's single values, read from `back`.
     * @param bool                  $enhanced        Whether Tessera sent it, so only the touched rows and the totals come back.
     * @param string                $token           The visitor's form token, for the action bar its answer shows again.
     */
    public function __construct(
        public ?BulkAction $bulkAction,
        public ?VehicleStatus $vehicleStatus,
        public ?VehicleLocation $vehicleLocation,
        public array $vins,
        public array $page,
        public bool $confirmed,
        public string $back,
        public array $query,
        public bool $enhanced,
        public string $token,
    ) {
    }

    /**
     * @return bool Whether it deletes.
     */
    public function deletes(): bool
    {
        return $this->bulkAction === BulkAction::Delete;
    }

    /**
     * @return string Why it cannot be applied as posted; empty when it can.
     */
    public function problem(): string
    {
        if ($this->vins === []) {
            return 'Tick at least one vehicle.';
        }

        if (count($this->vins) > self::MOST) {
            return 'Tick at most ' . self::MOST . ' vehicles, one page of the list.';
        }

        if (!$this->bulkAction instanceof BulkAction) {
            return 'Choose what to do with the ticked vehicles.';
        }

        return '';
    }

    /**
     * @param int $count How many vehicles it changed.
     *
     * @return string What it did, as staff read it (`Set 3 vehicles to On hold.`).
     */
    public function done(int $count): string
    {
        $vehicles = number_format($count) . ($count === 1 ? ' vehicle' : ' vehicles');

        if ($this->vehicleStatus instanceof VehicleStatus) {
            return 'Set ' . $vehicles . ' to ' . $this->vehicleStatus->label() . '.';
        }

        if ($this->vehicleLocation instanceof VehicleLocation) {
            return 'Moved ' . $vehicles . ' to ' . $this->vehicleLocation->label() . '.';
        }

        return 'Deleted ' . $vehicles . '.';
    }
}
