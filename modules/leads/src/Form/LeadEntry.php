<?php

declare(strict_types=1);

namespace App\Leads\Form;

/**
 * What a visitor typed into one of a car's lead forms (#18), each value as posted and not yet checked; a field the
 * form does not have is empty.
 */
final readonly class LeadEntry
{
    /**
     * @param string $name    Their name.
     * @param string $email   Their email.
     * @param string $phone   Their phone.
     * @param string $message What they asked.
     * @param string $driveOn The day asked for a test drive.
     * @param string $driveAt The time asked for a test drive.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $message,
        public string $driveOn,
        public string $driveAt,
    ) {
    }
}
