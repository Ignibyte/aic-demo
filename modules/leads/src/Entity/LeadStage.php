<?php

declare(strict_types=1);

namespace App\Leads\Entity;

use function in_array;

/**
 * Where a lead stands on the board (#30), stored in `leads.stage`: New when it comes in, then Contacted and
 * Appointment while it is worked, and Won or Lost once it is done with. The first three are open, the last two closed,
 * which `leads.status` keeps in step for everything that reads it.
 */
enum LeadStage: string
{
    /** Just in from a car's page. */
    case New = 'new';

    /** The salesperson has answered. */
    case Contacted = 'contacted';

    /** A visit or a drive is booked. */
    case Appointment = 'appointment';

    /** It became a sale. */
    case Won = 'won';

    /** It came to nothing, or was closed from its page. */
    case Lost = 'lost';

    /**
     * What an anchor of a column starts with; the stage's value follows.
     */
    public const string ANCHOR = 'leads-';

    /**
     * @return string The stage as staff read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Appointment => 'Appointment',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * @return string Its column's anchor on the board, `leads-<value>`.
     */
    public function anchor(): string
    {
        return self::ANCHOR . $this->value;
    }

    /**
     * @return bool Whether a lead at this stage is still being worked.
     */
    public function open(): bool
    {
        return in_array($this, [self::New, self::Contacted, self::Appointment], true);
    }

    /**
     * @return string The `leads.status` a lead at this stage has: `open` or `closed`.
     */
    public function status(): string
    {
        if ($this->open()) {
            return 'open';
        }

        return 'closed';
    }

    /**
     * Called on any stage (`LeadStage::New->board()`), so no class is reached statically.
     *
     * @return list<self> Every stage in the board's order, its columns from left to right.
     */
    public function board(): array
    {
        return [self::New, self::Contacted, self::Appointment, self::Won, self::Lost];
    }

    /**
     * Called on any stage (`LeadStage::New->stored(...)`), so no class is reached statically.
     *
     * @param string $value A stage as `leads.stage` stores it, or as a move posts it.
     *
     * @return self|null The stage stored as that value; null for a value that is none.
     */
    public function stored(string $value): ?self
    {
        foreach ($this->board() as $leadStage) {
            if ($leadStage->value === $value) {
                return $leadStage;
            }
        }

        return null;
    }
}
