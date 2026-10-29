<?php

declare(strict_types=1);

namespace App\Reports\Form;

use AIC\Database\Contracts\QueryException;
use App\Reports\Entity\ReportRange;
use App\Reports\Repository\RangeRepository;
use App\Staff\Service\StaffRequests;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function strcmp;

/**
 * Reads and checks the range a request asks for (#21), from its query or its posted fields, `from` and `to`: neither
 * gives this month; each must be a day the calendar holds, and the first not after the last, which compare as text
 * since both are `YYYY-MM-DD`. The page, the export post and the API share it.
 */
final readonly class RangeChecks
{
    /**
     * What a day that is not one says.
     */
    private const string NOT_A_DAY = 'Give a day as YYYY-MM-DD, from 2000-01-01 to the end of next year.';

    /**
     * @param RangeRepository $rangeRepository The calendar and this month.
     * @param StaffRequests   $staffRequests   Reads a query parameter as text.
     */
    public function __construct(
        private RangeRepository $rangeRepository,
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A GET request with `from` and `to` in its query.
     *
     * @throws QueryException           When the calendar cannot be read.
     * @throws InvalidArgumentException When a view's type is malformed.
     *
     * @return RangeCheck The range, or this month with the errors.
     */
    public function query(ServerRequestInterface $serverRequest): RangeCheck
    {
        return $this->check($this->staffRequests->query($serverRequest, 'from'), $this->staffRequests->query($serverRequest, 'to'));
    }

    /**
     * @param string $from  The first day asked for; empty for none.
     * @param string $until The last day asked for; empty for none.
     *
     * @throws QueryException           When the calendar cannot be read.
     * @throws InvalidArgumentException When a view's type is malformed.
     *
     * @return RangeCheck The range, or this month with the errors.
     */
    public function check(string $from, string $until): RangeCheck
    {
        $standard = $this->rangeRepository->standard();

        if ($from === '' && $until === '') {
            return new RangeCheck($standard, $standard->from, $standard->until, []);
        }

        $errors = $this->errors($from, $until);

        return $errors === []
            ? new RangeCheck(new ReportRange($from, $until), $from, $until, [])
            : new RangeCheck($standard, $from, $until, $errors);
    }

    /**
     * @param string $from  The first day asked for.
     * @param string $until The last day asked for.
     *
     * @throws QueryException           When the calendar cannot be read.
     * @throws InvalidArgumentException When a view's type is malformed.
     *
     * @return array<string, string> What is wrong with each, by its field's name; empty when the range is one.
     */
    private function errors(string $from, string $until): array
    {
        $errors = [];

        if (!$this->rangeRepository->isDay($from)) {
            $errors['from'] = self::NOT_A_DAY;
        }

        if (!$this->rangeRepository->isDay($until)) {
            $errors['to'] = self::NOT_A_DAY;
        }

        if ($errors === [] && strcmp($from, $until) > 0) {
            $errors['to'] = 'The last day comes before the first.';
        }

        return $errors;
    }
}
