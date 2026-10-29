<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * What a visitor asked for on a car's page (#18): a question about the car, or a test drive; its value is the `kind`
 * stored and the list's filter.
 */
enum LeadKind: string
{
    /**
     * "Ask about this car".
     */
    case Inquiry = 'inquiry';

    /**
     * "Book a test drive".
     */
    case TestDrive = 'test_drive';

    /**
     * @return string How staff pages and mails name it.
     */
    public function label(): string
    {
        // if/return rather than match: elephc cannot lower a match whose arms are enum cases yet (AIC #164).
        if ($this === self::Inquiry) {
            return 'Question';
        }

        return 'Test drive';
    }

    /**
     * @return string Its form's key, the start of its ids and the path it posts to below a car's page, and its anchor
     *                after `car-`: `inquiry` or `test-drive`.
     */
    public function slug(): string
    {
        if ($this === self::Inquiry) {
            return 'inquiry';
        }

        return 'test-drive';
    }

    /**
     * Called on the kind to fall back on (`LeadKind::Inquiry->stored(...)`), so no class is reached statically.
     *
     * @param string $value A kind as `leads.kind` stores it.
     *
     * @return self The kind stored as that value; this one for any other value.
     */
    public function stored(string $value): self
    {
        // if/return rather than a ternary or match over cases: elephc's build of the site fails on either (AIC #64, #164).
        if ($value === self::TestDrive->value) {
            return self::TestDrive;
        }

        if ($value === self::Inquiry->value) {
            return self::Inquiry;
        }

        return $this;
    }

    /**
     * @return self The other kind: a car's page shows both forms.
     */
    public function other(): self
    {
        if ($this === self::Inquiry) {
            return self::TestDrive;
        }

        return self::Inquiry;
    }
}
