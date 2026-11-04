<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Database\Contracts\QueryException;
use App\Chat\Repository\ChatStaffRepository;
use App\Leads\Repository\VehicleLeadRepository;
use InvalidArgumentException;

/**
 * Who a chat started on a car's page goes to (#32): the salesperson on the car's newest lead that has one, while that
 * member is active; else the active sales manager with the lowest id; else no one, and the managers, who see every
 * chat, take it from the list.
 */
final readonly class ChatAssignees
{
    /**
     * @param VehicleLeadRepository $vehicleLeadRepository The car's leads, through leads' export.
     * @param ChatStaffRepository   $chatStaffRepository   The sales manager.
     */
    public function __construct(
        private VehicleLeadRepository $vehicleLeadRepository,
        private ChatStaffRepository $chatStaffRepository,
    ) {
    }

    /**
     * @param int $vehicleId The car's row id.
     *
     * @throws QueryException           When the leads or the users cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return int The member's user id; 0 for no one.
     */
    public function assignee(int $vehicleId): int
    {
        $seller = $this->vehicleLeadRepository->sellerOf($vehicleId);

        return $seller > 0
            ? $seller
            : $this->chatStaffRepository->salesManager();
    }
}
