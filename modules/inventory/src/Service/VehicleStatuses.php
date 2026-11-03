<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\Entity\VehicleLocation;
use App\Inventory\Entity\VehicleStatus;
use App\Theme\Components\SelectField\SelectOption;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\StatusBadge\StatusBadge;
use UnexpectedValueException;

/**
 * The three statuses in order, a stored value read back as its status (anything else is refused rather than read as
 * some status), and each status's badge colour and badge; and the lot's locations (#29) in order, a stored value read
 * back as its location.
 */
final readonly class VehicleStatuses
{
    /**
     * @return list<VehicleStatus> Every status, in the order the filter offers them.
     */
    public function all(): array
    {
        return [VehicleStatus::InStock, VehicleStatus::OnHold, VehicleStatus::Sold];
    }

    /**
     * @param string $value A status as `vehicles.status` stores it.
     *
     * @throws UnexpectedValueException When it is no status's value.
     *
     * @return VehicleStatus The status.
     */
    public function stored(string $value): VehicleStatus
    {
        foreach ($this->all() as $vehicleStatus) {
            if ($vehicleStatus->value === $value) {
                return $vehicleStatus;
            }
        }

        throw new UnexpectedValueException('No vehicle status is stored as "' . $value . '".');
    }

    /**
     * @return list<VehicleLocation> Every lot location, in the order the action bar offers them.
     */
    public function locations(): array
    {
        return [VehicleLocation::FrontRow, VehicleLocation::Showroom, VehicleLocation::BackLot, VehicleLocation::ServiceBay];
    }

    /**
     * @param string $value A location as `vehicles.location` stores it, or as a bulk move names it.
     *
     * @return VehicleLocation|null The location; null for none, or for a value that is no location's.
     */
    public function location(string $value): ?VehicleLocation
    {
        foreach ($this->locations() as $vehicleLocation) {
            if ($vehicleLocation->value === $value) {
                return $vehicleLocation;
            }
        }

        return null;
    }

    /**
     * @param VehicleStatus $vehicleStatus A status.
     *
     * @return BadgeTone Its badge's colour.
     */
    public function tone(VehicleStatus $vehicleStatus): BadgeTone
    {
        return match ($vehicleStatus) {
            VehicleStatus::InStock => BadgeTone::Success,
            VehicleStatus::OnHold => BadgeTone::Warning,
            VehicleStatus::Sold => BadgeTone::Neutral,
        };
    }

    /**
     * @param VehicleStatus $vehicleStatus A status.
     * @param string        $id            The badge's id.
     *
     * @return StatusBadge Its badge: its label in its colour.
     */
    public function badge(VehicleStatus $vehicleStatus, string $id): StatusBadge
    {
        return new StatusBadge($vehicleStatus->label(), $this->tone($vehicleStatus), $id);
    }

    /**
     * @param bool $confirming Whether a delete is being confirmed, so it is the one chosen.
     *
     * @return list<SelectOption> The inventory's action bar's choices (#29): each status but sold, each lot location in
     *                            order, and delete.
     */
    public function bulkChoices(bool $confirming): array
    {
        $choices = [
            new SelectOption('status:in_stock', 'Set to In stock', false),
            new SelectOption('status:on_hold', 'Set to On hold', false),
        ];

        foreach ($this->locations() as $vehicleLocation) {
            $choices[] = new SelectOption('location:' . $vehicleLocation->value, 'Move to ' . $vehicleLocation->label(), false);
        }

        $choices[] = new SelectOption('delete', 'Delete', $confirming);

        return $choices;
    }
}
