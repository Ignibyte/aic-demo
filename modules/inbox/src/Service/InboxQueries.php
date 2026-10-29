<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function preg_match;
use function strlen;

/**
 * Reads what an inbox request asks for (#19): the list's `page`, and the route's notification id. A value that is
 * missing, a list (`page[]=…`) or not a number reads as the default.
 */
final readonly class InboxQueries
{
    /**
     * The most digits a page number is read with.
     */
    private const int PAGE_DIGITS = 9;

    /**
     * A page after every page there is, for a number too long to read.
     */
    private const int PAST_EVERY_PAGE = 999_999_999;

    /**
     * @param StaffRequests $staffRequests Reads the query and the route.
     */
    public function __construct(
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     *
     * @return int The page asked for; 1 for none or a malformed one. A number too long to read is past every page, so
     *             the list, which shows the nearest page, shows its last.
     */
    public function page(ServerRequestInterface $serverRequest): int
    {
        $page = $this->staffRequests->query($serverRequest, 'page');

        if (preg_match('/^[1-9][0-9]*$/', $page) !== 1) {
            return 1;
        }

        return strlen($page) > self::PAGE_DIGITS
            ? self::PAST_EVERY_PAGE
            : (int) $page;
    }

    /**
     * @param ServerRequestInterface $serverRequest A routed request with an `{id:int}`.
     *
     * @return int The id; 0 when it is no id.
     */
    public function notificationId(ServerRequestInterface $serverRequest): int
    {
        $id = $this->staffRequests->attribute($serverRequest, 'id');

        return $id !== '' && strlen($id) <= 18 && ctype_digit($id)
            ? (int) $id
            : 0;
    }
}
