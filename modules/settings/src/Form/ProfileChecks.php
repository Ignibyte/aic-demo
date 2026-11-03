<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Form\Application\PostedFields;
use App\Finance\Service\Payments;
use App\Settings\Entity\DealershipProfile;
use App\Settings\Entity\OpeningHours;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function mb_strlen;
use function preg_match;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * The profile form (#22): what a post sent, its checks one per field, and the profile a valid entry describes. A name
 * of at most 100 characters; an address of at most 200; a phone as customers' is, or none; an opening and a closing
 * time `HH:MM`, the closing at least an hour after the opening so a test drive fits; at least one open day; an
 * aged-stock threshold of 1 to 365 days; and a default APR from 0 to 30 percent with up to two decimals, blank for the
 * standard 6.90% (#26), so a post from a form that predates the field still saves. Times compare as minutes since
 * midnight and the APR as basis points, in integers (elephc refuses `<` between two strings, as leads' hours note).
 */
final readonly class ProfileChecks
{
    /**
     * A plausible phone, as customers' checks have it: 7 to 30 digits, spaces and `+ ( ) - .`, at least 7 of them digits.
     */
    private const string PHONE = '/^(?=(?:[^0-9]*[0-9]){7})[0-9+().\\- ]{7,30}$/';

    /**
     * A time of day, `HH:MM`.
     */
    private const string TIME = '/^([01]\\d|2[0-3]):[0-5]\\d$/';

    /**
     * An APR as typed: whole percent, then up to two decimals.
     */
    private const string APR = '/^\\d{1,2}(\\.\\d{1,2})?$/';

    /**
     * The highest APR the profile takes, in basis points: 30%.
     */
    private const int MAX_APR = 3_000;

    /**
     * @param PostedFields $postedFields Reads the posted fields.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted profile form.
     *
     * @return ProfileEntry What it sent: each field as sent (missing or a list reads empty), the open days gathered
     *                      from the boxes `day_1` (Monday) to `day_7` (Sunday) that were ticked.
     */
    public function posted(ServerRequestInterface $serverRequest): ProfileEntry
    {
        $values = [];

        foreach (DealershipProfile::FIELDS as $field) {
            $values[$field] = $this->postedFields->value($serverRequest, $field);
        }

        $openDays = '';

        for ($isoDay = 1; $isoDay <= 7; $isoDay += 1) {
            if ($this->postedFields->value($serverRequest, 'day_' . $isoDay) !== '1') {
                continue;
            }

            $openDays .= $isoDay;
        }

        $values['open_days'] = $openDays;

        return new ProfileEntry($values);
    }

    /**
     * @param ProfileEntry $profileEntry What was posted.
     *
     * @return array<string, string> Why each refused field is refused, by name (`days` for the open days); empty when
     *                               the entry is valid.
     */
    public function errors(ProfileEntry $profileEntry): array
    {
        $opensAt = trim($profileEntry->value('opens_at'));
        $errors = [
            'address' => $this->addressError(trim($profileEntry->value('address'))),
            'aged_stock_days' => $this->daysError(trim($profileEntry->value('aged_stock_days'))),
            'closes_at' => $this->closesError($opensAt, trim($profileEntry->value('closes_at'))),
            'days' => $this->openDaysError($profileEntry->value('open_days')),
            'default_apr' => $this->aprError(trim($profileEntry->value('default_apr'))),
            'name' => $this->nameError(trim($profileEntry->value('name'))),
            'opens_at' => $this->opensError($opensAt),
            'phone' => $this->phoneError(trim($profileEntry->value('phone'))),
        ];

        return array_filter($errors, static fn (string $error): bool => $error !== '');
    }

    /**
     * @param ProfileEntry $profileEntry An entry `errors()` found valid.
     *
     * @return DealershipProfile The profile it describes, its texts trimmed.
     */
    public function profile(ProfileEntry $profileEntry): DealershipProfile
    {
        $openingHours = new OpeningHours(
            trim($profileEntry->value('opens_at')),
            trim($profileEntry->value('closes_at')),
            $profileEntry->value('open_days'),
        );

        return new DealershipProfile(
            trim($profileEntry->value('name')),
            trim($profileEntry->value('address')),
            trim($profileEntry->value('phone')),
            $openingHours,
            (int) trim($profileEntry->value('aged_stock_days')),
            $this->basisPoints(trim($profileEntry->value('default_apr'))) ?? Payments::STANDARD_APR,
        );
    }

    /**
     * @param string $name A posted name, trimmed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function nameError(string $name): string
    {
        if ($name === '') {
            return "Enter the dealership's name.";
        }

        return mb_strlen($name) > 100
            ? 'The name is at most 100 characters.'
            : '';
    }

    /**
     * @param string $address A posted address, trimmed.
     *
     * @return string Why it is refused; empty when it is short enough.
     */
    private function addressError(string $address): string
    {
        return mb_strlen($address) > 200
            ? 'The address is at most 200 characters.'
            : '';
    }

    /**
     * @param string $openDays The ticked days, ISO weekday digits.
     *
     * @return string Why they are refused; empty when at least one is ticked.
     */
    private function openDaysError(string $openDays): string
    {
        return $openDays === ''
            ? 'Choose at least one day the dealership is open.'
            : '';
    }

    /**
     * @param string $opensAt The posted opening time, trimmed.
     *
     * @return string Why it is refused; empty when it is a time of day.
     */
    private function opensError(string $opensAt): string
    {
        return preg_match(self::TIME, $opensAt) === 1
            ? ''
            : 'Give the opening time as HH:MM, such as 09:00.';
    }

    /**
     * @param string $phone A posted phone, trimmed.
     *
     * @return string Why it is refused; empty when it is empty or plausible.
     */
    private function phoneError(string $phone): string
    {
        return $phone === '' || preg_match(self::PHONE, $phone) === 1
            ? ''
            : 'A phone is 7 to 30 digits, spaces and + ( ) - . characters, with at least 7 digits.';
    }

    /**
     * @param string $opensAt  The posted opening time, trimmed.
     * @param string $closesAt The posted closing time, trimmed.
     *
     * @return string Why the closing time is refused; empty when it is a time at least an hour after a valid opening.
     */
    private function closesError(string $opensAt, string $closesAt): string
    {
        if (preg_match(self::TIME, $closesAt) !== 1) {
            return 'Give the closing time as HH:MM, such as 18:00.';
        }

        if (preg_match(self::TIME, $opensAt) !== 1) {
            return '';
        }

        return $this->minutes($closesAt) - $this->minutes($opensAt) >= 60
            ? ''
            : 'Close at least an hour after opening, so a test drive fits.';
    }

    /**
     * @param string $time A time of day, `HH:MM`.
     *
     * @return int The minutes since midnight.
     */
    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    /**
     * @param string $days The posted threshold, trimmed.
     *
     * @return string Why it is refused; empty when it is a whole number of days from 1 to 365.
     */
    private function daysError(string $days): string
    {
        return preg_match('/^\\d{1,3}$/', $days) === 1 && (int) $days >= 1 && (int) $days <= 365
            ? ''
            : 'Give a whole number of days from 1 to 365.';
    }

    /**
     * @param string $apr The posted APR, trimmed.
     *
     * @return string Why it is refused; empty when it is blank or a percentage from 0 to 30 with up to two decimals.
     */
    private function aprError(string $apr): string
    {
        if ($apr === '') {
            return '';
        }

        $basisPoints = $this->basisPoints($apr);

        return $basisPoints !== null && $basisPoints <= self::MAX_APR
            ? ''
            : 'Give the APR as a percentage from 0 to 30, such as 6.9, or leave it blank for 6.90.';
    }

    /**
     * @param string $apr An APR as typed, trimmed.
     *
     * @return int|null It in basis points (`6.9` is 690); null when it is blank or not a percentage with up to two
     *                  decimals.
     */
    private function basisPoints(string $apr): ?int
    {
        if (preg_match(self::APR, $apr) !== 1) {
            return null;
        }

        $dot = strpos($apr, '.');

        if ($dot === false) {
            return (int) $apr * 100;
        }

        $decimals = substr($apr, $dot + 1);
        $hundredths = strlen($decimals) === 1
            ? (int) $decimals * 10
            : (int) $decimals;

        return (int) substr($apr, 0, $dot) * 100 + $hundredths;
    }
}
