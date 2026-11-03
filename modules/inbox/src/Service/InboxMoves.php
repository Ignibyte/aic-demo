<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageFullVisit;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use AIC\Notification\Domain\InboxItem;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPanels;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function preg_match;

/**
 * Opening a notification and marking them all read (#19), each for the signed-in member alone: a notification that is
 * not theirs is not found, whatever its id, and nothing of another member's changes. An opening goes on as a full
 * visit (#21): what a notification links may be a file to save, such as a report's CSV export, which a Tessera visit
 * cannot use, so the browser loads it itself.
 */
final readonly class InboxMoves
{
    /**
     * @param StaffPanels  $staffPanels  The refusal's frame.
     * @param Inbox        $inbox        The members' notifications.
     * @param InboxOwners  $inboxOwners  Whose inbox it is.
     * @param InboxQueries $inboxQueries Reads the route's id.
     * @param StaffNotices $staffNotices "Mark all read"'s notice.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private Inbox $inbox,
        private InboxOwners $inboxOwners,
        private InboxQueries $inboxQueries = new InboxQueries(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The opening's request, with its session and the route's id.
     *
     * @throws QueryException           When the member or the notification cannot be read or written.
     * @throws LogicException           When the request carries no session, the users' type is malformed, or the status
     *                                  does not fit the theme.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse A full visit to what it is about (303 for a plain request), once it is marked read; 404 when
     *                      it is not the member's.
     */
    public function open(ServerRequestInterface $serverRequest): PageResponse
    {
        $userId = $this->inboxOwners->userId($serverRequest);
        $id = $this->inboxQueries->notificationId($serverRequest);
        $inboxItem = $userId === '' || $id === 0
            ? null
            : $this->inbox->one($userId, $id);

        if (!$inboxItem instanceof InboxItem || !$this->inbox->markRead($userId, $id)) {
            $title = 'Notification not found';
            $message = 'You have no notification with that number.';

            return $this->staffPanels->warning($title, $message, 'inbox-missing', 'Back to the inbox', BellParts::INBOX, 404);
        }

        return new PageFullVisit(self::sitePath($inboxItem->link) ? $inboxItem->link : BellParts::INBOX);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the notifications cannot be written.
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse 303 to the inbox, with the notice.
     */
    public function readAll(ServerRequestInterface $serverRequest): PageResponse
    {
        $userId = $this->inboxOwners->userId($serverRequest);

        if ($userId !== '') {
            $this->inbox->markAllRead($userId);
        }

        $this->staffNotices->keep($serverRequest, 'Marked every notification read.');

        return new PageRedirect(BellParts::INBOX);
    }

    /**
     * @param string $link A notification's link.
     *
     * @return bool Whether it is a path on this site, which an opening may go on to: `/` and then printable ASCII with no
     *              space or backslash, and not `//`. A browser reads `//…` and `/\…` as another host, and drops a tab
     *              or a newline before it reads the rest.
     */
    private static function sitePath(string $link): bool
    {
        return preg_match('#^/(?!/)[!-\\[\\]-~]*$#', $link) === 1;
    }
}
