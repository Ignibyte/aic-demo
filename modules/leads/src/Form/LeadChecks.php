<?php

declare(strict_types=1);

namespace App\Leads\Form;

use App\Leads\Entity\LeadDetails;
use App\Leads\Entity\LeadKind;

use function array_filter;
use function preg_match;
use function strlen;
use function strtolower;
use function trim;

/**
 * Checks a lead form as posted (#18): a name on one line, an email to reply to, a phone (a test drive's is needed),
 * a question (an inquiry's is needed) and, for a test drive, a day and a time in opening hours. Each refused field
 * gets its reason, shown beside it.
 */
final readonly class LeadChecks
{
    /**
     * The longest question or note.
     */
    private const int MESSAGE_BYTES = 2_000;

    /**
     * @param LeadHours $leadHours The opening hours a test drive is held to.
     */
    public function __construct(
        private LeadHours $leadHours = new LeadHours(),
    ) {
    }

    /**
     * @param LeadKind  $leadKind  Which form was sent.
     * @param LeadEntry $leadEntry What was typed.
     * @param string    $today     The database's today, `YYYY-MM-DD`.
     *
     * @return array<string, string> Why each refused field is refused, by its name; empty when every field passes.
     */
    public function errors(LeadKind $leadKind, LeadEntry $leadEntry, string $today): array
    {
        $testDrive = $leadKind === LeadKind::TestDrive;
        $errors = [
            'drive_at' => $testDrive ? $this->leadHours->timeError(trim($leadEntry->driveAt)) : '',
            'drive_on' => $testDrive ? $this->leadHours->dayError(trim($leadEntry->driveOn), $today) : '',
            'email' => $this->emailError($this->email($leadEntry)),
            'message' => $this->messageError(trim($leadEntry->message), !$testDrive),
            'name' => $this->nameError(trim($leadEntry->name)),
            'phone' => $this->phoneError(trim($leadEntry->phone), $testDrive),
        ];

        return array_filter($errors, static fn (string $error): bool => $error !== '');
    }

    /**
     * @param LeadKind  $leadKind  Which form was sent.
     * @param LeadEntry $leadEntry What was typed, every field passing.
     *
     * @return LeadDetails The lead, trimmed, its email lower case; a question keeps no day or time.
     */
    public function details(LeadKind $leadKind, LeadEntry $leadEntry): LeadDetails
    {
        $testDrive = $leadKind === LeadKind::TestDrive;

        return new LeadDetails(
            $leadKind,
            trim($leadEntry->name),
            $this->email($leadEntry),
            trim($leadEntry->phone),
            trim($leadEntry->message),
            $testDrive ? trim($leadEntry->driveOn) : '',
            $testDrive ? trim($leadEntry->driveAt) : '',
        );
    }

    /**
     * @param LeadEntry $leadEntry What was typed.
     *
     * @return string The email, trimmed and lower case, as customers store theirs.
     */
    private function email(LeadEntry $leadEntry): string
    {
        return strtolower(trim($leadEntry->email));
    }

    /**
     * @param string $name The name, trimmed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function nameError(string $name): string
    {
        if ($name === '') {
            return 'Give your name.';
        }

        return preg_match('/^[^\\x00-\\x1F\\x7F]{1,100}$/u', $name) === 1
            ? ''
            : 'Your name is one line of up to 100 characters.';
    }

    /**
     * @param string $email The email, trimmed and lower case.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function emailError(string $email): string
    {
        $written = strlen($email) <= 254 && preg_match('/^[^\\s<>@]+@[^\\s<>@.]+(\\.[^\\s<>@.]+)+$/', $email) === 1;

        return $written
            ? ''
            : 'Give an email we can reply to, such as you@example.com.';
    }

    /**
     * @param string $phone  The phone, trimmed.
     * @param bool   $needed Whether the form needs one (a test drive's does).
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function phoneError(string $phone, bool $needed): string
    {
        if ($phone === '') {
            return $needed
                ? 'Give a phone number, so we can confirm the drive.'
                : '';
        }

        return preg_match('/^[0-9+() .-]{7,30}$/', $phone) === 1
            ? ''
            : 'A phone number is 7 to 30 digits, spaces and + ( ) - . signs.';
    }

    /**
     * @param string $message The question or note, trimmed.
     * @param bool   $needed  Whether the form needs one (an inquiry's does).
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function messageError(string $message, bool $needed): string
    {
        if ($message === '') {
            return $needed
                ? 'Write your question.'
                : '';
        }

        return strlen($message) <= self::MESSAGE_BYTES
            ? ''
            : 'Keep it to 2,000 characters.';
    }
}
