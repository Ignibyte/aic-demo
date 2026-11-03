<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use AIC\Notification\Contracts\Notification;
use App\Staff\Entity\StaffAccount;

use function implode;

/**
 * The audit of a bulk edit (#29), which each active sales manager finds in their inbox: who did what, each vehicle by
 * title and VIN, and the list it links to.
 */
final readonly class BulkAudit
{
    /**
     * The audit's subject.
     */
    private string $subject;

    /**
     * The audit's text.
     */
    private string $body;

    /**
     * @param string        $member   Who did it, by name.
     * @param string        $done     What they did (`Set 3 vehicles to On hold.`).
     * @param list<Vehicle> $vehicles The vehicles it was done to.
     */
    public function __construct(string $member, string $done, array $vehicles)
    {
        $lines = [$member . ' made a bulk edit in the inventory list. ' . $done, ''];

        foreach ($vehicles as $vehicle) {
            $lines[] = $vehicle->title() . ', VIN ' . $vehicle->vin;
        }

        $lines[] = '';
        $lines[] = 'Open the list: ' . Vehicle::PAGES;
        $this->subject = 'Bulk edit: ' . $done;
        $this->body = implode("\n", $lines);
    }

    /**
     * @param StaffAccount $staffAccount An active sales manager.
     *
     * @return Notification The audit for them, linking to the inventory list.
     */
    public function notification(StaffAccount $staffAccount): Notification
    {
        return new Notification((string) $staffAccount->id, $staffAccount->email, $this->subject, $this->body, Vehicle::PAGES);
    }
}
