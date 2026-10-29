<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use function implode;
use function intdiv;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;

/**
 * When the dealership is open (#22): one opening and one closing time for every open day, `HH:MM`, and the open days as
 * ISO weekday digits (`123456`, Monday to Saturday). A test drive starts on an open day, from the opening to an hour
 * before the closing; leads hold its form to them and build its words from here, so the texts follow the profile.
 */
final readonly class OpeningHours
{
    /**
     * The days by ISO number less one.
     */
    private const array NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * @param string $opensAt  When the doors open, `HH:MM`.
     * @param string $closesAt When they close, `HH:MM`.
     * @param string $openDays The open days, ISO weekday digits in order (`123456`).
     */
    public function __construct(
        public string $opensAt,
        public string $closesAt,
        public string $openDays,
    ) {
    }

    /**
     * @param int $isoDay A weekday, 1 for Monday to 7 for Sunday.
     *
     * @return bool Whether the dealership opens that day.
     */
    public function opensOn(int $isoDay): bool
    {
        return $isoDay >= 1 && $isoDay <= 7 && str_contains($this->openDays, (string) $isoDay);
    }

    /**
     * @return string The latest start of a test drive, an hour before the closing, `HH:MM`.
     */
    public function lastStart(): string
    {
        $minutes = (int) substr($this->closesAt, 0, 2) * 60 + (int) substr($this->closesAt, 3, 2) - 60;

        return $minutes < 0
            ? '00:00'
            : sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * @param int $isoDay A weekday, 1 for Monday to 7 for Sunday.
     *
     * @return string Its name; empty for a number that is no weekday.
     */
    public function dayName(int $isoDay): string
    {
        foreach (self::NAMES as $index => $name) {
            if ($index + 1 === $isoDay) {
                return $name;
            }
        }

        return '';
    }

    /**
     * @return string The open days in words: a run of three or more as `Monday to Saturday`, else each named,
     *                `Monday, Wednesday and Friday`.
     */
    public function days(): string
    {
        return $this->phrase(' to ', ' and ');
    }

    /**
     * @return string The open days as a choice: `a day from Monday to Saturday` for a run, else
     *                `Monday, Wednesday or Friday`.
     */
    public function choice(): string
    {
        return $this->run()
            ? 'a day from ' . $this->phrase(' to ', ' or ')
            : $this->phrase(' to ', ' or ');
    }

    /**
     * @return string The hours in one line, `Open Monday to Saturday, 09:00 to 18:00`.
     */
    public function line(): string
    {
        return 'Open ' . $this->days() . ', ' . $this->opensAt . ' to ' . $this->closesAt;
    }

    /**
     * @param string $through What joins the first and last of a run.
     * @param string $last    What joins the last two of a list.
     *
     * @return string The open days, a run or a list.
     */
    private function phrase(string $through, string $last): string
    {
        $count = strlen($this->openDays);
        $first = $this->dayName((int) substr($this->openDays, 0, 1));
        $final = $this->dayName((int) substr($this->openDays, -1));

        if ($count === 0) {
            return 'no day';
        }

        if ($this->run()) {
            return $first . $through . $final;
        }

        if ($count === 1) {
            return $first;
        }

        $head = [];

        for ($index = 0; $index < $count - 1; $index += 1) {
            $head[] = $this->dayName((int) substr($this->openDays, $index, 1));
        }

        return implode(', ', $head) . $last . $final;
    }

    /**
     * @return bool Whether the open days are three or more in a row; they are stored in order, each once, as the form's
     *              boxes give them.
     */
    private function run(): bool
    {
        $count = strlen($this->openDays);

        return $count >= 3 && (int) substr($this->openDays, -1) - (int) substr($this->openDays, 0, 1) + 1 === $count;
    }
}
