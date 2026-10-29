<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * What a visitor sent about a car (#18), every value checked: the kind, who they are and what they asked. A question
 * leaves the day and time empty; a test drive's phone is always given.
 */
final readonly class LeadDetails
{
    /**
     * @param LeadKind $leadKind A question or a test drive.
     * @param string   $name     Their name.
     * @param string   $email    Their email, lower case.
     * @param string   $phone    Their phone; empty when a question gives none.
     * @param string   $message  What they asked; may be empty for a test drive.
     * @param string   $driveOn  The day asked for, `YYYY-MM-DD`; empty for a question.
     * @param string   $driveAt  The time asked for, `HH:MM`; empty for a question.
     */
    public function __construct(
        public LeadKind $leadKind,
        public string $name,
        public string $email,
        public string $phone,
        public string $message,
        public string $driveOn,
        public string $driveAt,
    ) {
    }
}
