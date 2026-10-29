<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Component\Domain\KeyedComponent;
use App\Leads\Entity\LeadKind;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use InvalidArgumentException;

/**
 * A lead form's fields (#18), each with what was typed and its error: name, email and phone in a grid, with a test
 * drive's day (`type="date"`) and time (`type="time"`), then the question or the test drive's note. Their ids start
 * with the kind's slug, so the two forms on one page never share one.
 */
final readonly class LeadFields
{
    /**
     * @param LeadKind              $leadKind  Which form.
     * @param LeadEntry             $leadEntry The values to show.
     * @param array<string, string> $errors    Why each refused field is refused, by name.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FormGrid Name, email and phone, and a test drive's day and time.
     */
    public function grid(LeadKind $leadKind, LeadEntry $leadEntry, array $errors): FormGrid
    {
        $slug = $leadKind->slug();
        $testDrive = $leadKind === LeadKind::TestDrive;
        $phone = $testDrive
            ? 'Phone'
            : 'Phone (optional)';
        $fields = [
            new FormField($slug . '-name', 'name', 'Your name', 'text', $leadEntry->name, $errors['name'] ?? '', true, false),
            new FormField($slug . '-email', 'email', 'Email', 'email', $leadEntry->email, $errors['email'] ?? '', true, false),
            new FormField($slug . '-phone', 'phone', $phone, 'tel', $leadEntry->phone, $errors['phone'] ?? '', $testDrive, false),
        ];

        if ($testDrive) {
            $dayError = $errors['drive_on'] ?? '';
            $timeError = $errors['drive_at'] ?? '';
            $fields[] = new FormField('test-drive-day', 'drive_on', 'Day', 'date', $leadEntry->driveOn, $dayError, true, false);
            $fields[] = new FormField('test-drive-time', 'drive_at', 'Time', 'time', $leadEntry->driveAt, $timeError, true, false);
        }

        return new FormGrid($this->keyed($fields));
    }

    /**
     * @param LeadKind  $leadKind  Which form.
     * @param LeadEntry $leadEntry The values to show.
     * @param string    $error     Why the message is refused; empty when it is not.
     *
     * @return FormField The question, or a test drive's optional note.
     */
    public function message(LeadKind $leadKind, LeadEntry $leadEntry, string $error): FormField
    {
        $inquiry = $leadKind === LeadKind::Inquiry;
        $label = $inquiry
            ? 'Your question'
            : 'Anything we should know (optional)';

        return new FormField($leadKind->slug() . '-message', 'message', $label, 'textarea', $leadEntry->message, $error, $inquiry, false);
    }

    /**
     * @param list<FormField> $fields Fields.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> Each keyed by its name.
     */
    private function keyed(array $fields): array
    {
        $keyed = [];

        foreach ($fields as $field) {
            $keyed[] = new KeyedComponent($field->name, $field);
        }

        return $keyed;
    }
}
