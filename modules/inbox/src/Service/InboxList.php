<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Notification\Application\Inbox;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function intdiv;
use function max;
use function min;

/**
 * The inbox's panel (#19): the notice "Mark all read" leaves, its form while any notification is unread, the table of
 * one page of the member's notifications, newest first, and the page links when there is more than one page.
 */
final readonly class InboxList
{
    /**
     * How many notifications a page shows.
     */
    private const int PER_PAGE = 20;

    /**
     * @param Inbox        $inbox        The members' notifications.
     * @param InboxOwners  $inboxOwners  Whose inbox it is.
     * @param InboxRows    $inboxRows    The table and page links.
     * @param InboxQueries $inboxQueries Reads the page.
     * @param StaffNotices $staffNotices "Mark all read"'s notice.
     * @param FormTokens   $formTokens   Issues the form's token.
     */
    public function __construct(
        private Inbox $inbox,
        private InboxOwners $inboxOwners,
        private InboxRows $inboxRows,
        private InboxQueries $inboxQueries = new InboxQueries(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The inbox's request, with its session.
     *
     * @throws QueryException           When the member or their notifications cannot be read.
     * @throws LogicException           When the request carries no session, or a key or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return Panel The inbox's panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $userId = $this->inboxOwners->userId($serverRequest);
        $body = $this->notices($serverRequest);
        $total = $this->totalOf($userId);
        $unread = $this->unreadOf($userId);
        $pages = max(1, intdiv($total + self::PER_PAGE - 1, self::PER_PAGE));
        $page = min($this->inboxQueries->page($serverRequest), $pages);
        $inboxItems = $total === 0
            ? []
            : $this->inbox->page($userId, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        if ($unread > 0) {
            $body[] = new KeyedComponent('read-all', $this->inboxRows->readAllForm($this->formTokens->issue($serverRequest)->value()));
        }

        $body[] = new KeyedComponent('table', $this->inboxRows->table($inboxItems, $total, $unread, $page, $pages));

        if ($pages > 1) {
            $body[] = new KeyedComponent('pages', $this->inboxRows->pagination($page, $pages));
        }

        return new Panel('inbox', 'Notifications', 'What the app has told you, newest first', true, $body);
    }

    /**
     * @param ServerRequestInterface $serverRequest The inbox's request, with its session.
     *
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return list<KeyedComponent> The notice "Mark all read" left, if any.
     */
    private function notices(ServerRequestInterface $serverRequest): array
    {
        $notices = [];

        foreach ($this->staffNotices->take($serverRequest, 'inbox-notice') as $key => $notice) {
            $notices[] = new KeyedComponent($key, $notice);
        }

        return $notices;
    }

    /**
     * @param string $userId The member's user id; empty for none.
     *
     * @throws QueryException When their notifications cannot be read.
     *
     * @return int How many notifications they have.
     */
    private function totalOf(string $userId): int
    {
        return $userId === ''
            ? 0
            : $this->inbox->total($userId);
    }

    /**
     * @param string $userId The member's user id; empty for none.
     *
     * @throws QueryException When their notifications cannot be read.
     *
     * @return int How many of them are unread.
     */
    private function unreadOf(string $userId): int
    {
        return $userId === ''
            ? 0
            : $this->inbox->unread($userId);
    }
}
