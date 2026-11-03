<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;
use AIC\Notification\Domain\InboxItem;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function number_format;

/**
 * The inbox's form, table and page links (#19): "Mark all read", a row per notification, newest first, its subject
 * linking to its opening, and a link per page that answers the content region in place.
 */
final readonly class InboxRows
{
    /**
     * The region a page link updates.
     */
    private const string TARGET = 'content';

    /**
     * @param BellParts $bellParts Words when a notification came, as the bell does.
     */
    public function __construct(
        private BellParts $bellParts,
    ) {
    }

    /**
     * @param list<InboxItem> $inboxItems The page's notifications, newest first.
     * @param int             $total      How many the member has.
     * @param int             $unread     How many of them are unread.
     * @param int             $page       The page shown.
     * @param int             $pages      How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The page's rows under the heads, or the empty line.
     */
    public function table(array $inboxItems, int $total, int $unread, int $page, int $pages): DataTable
    {
        $rows = [];

        foreach ($inboxItems as $inboxItem) {
            $rows[] = new KeyedComponent('notification-' . $inboxItem->id, $this->row($inboxItem));
        }

        return new DataTable('inbox-table', $this->summary($total, $unread, $page, $pages), 'No notifications yet.', self::TARGET, [
            new TableColumn('Received', '', 'none', '', false),
            new TableColumn('Notification', '', 'none', '', false),
            new TableColumn('Status', '', 'none', '', false),
        ], $rows);
    }

    /**
     * @param GuardFields $guardFields The form's CSRF token and honeypot.
     *
     * @return Form "Mark all read", posted to `/app/inbox/read-all`.
     */
    public function readAllForm(GuardFields $guardFields): Form
    {
        $button = new Button('Mark all read', 'submit', 'inbox-read-all');

        return new Form('inbox-read-all-form', BellParts::INBOX . '/read-all', [$button], $guardFields);
    }

    /**
     * @param int $page  The page shown.
     * @param int $pages How many pages there are.
     *
     * @return Pagination A link per page.
     */
    public function pagination(int $page, int $pages): Pagination
    {
        $pageLinks = [];

        for ($number = 1; $number <= $pages; $number += 1) {
            $href = $number === 1
                ? BellParts::INBOX
                : BellParts::INBOX . '?page=' . $number;
            $pageLinks[] = new PageLink((string) $number, $href, $number === $page);
        }

        return new Pagination('inbox-pages', self::TARGET, $pageLinks);
    }

    /**
     * @param InboxItem $inboxItem A notification.
     *
     * @return TableRow Its row: when it came, its subject linked to its opening, and Unread or Read.
     */
    private function row(InboxItem $inboxItem): TableRow
    {
        $status = $inboxItem->read
            ? new TableCell('Read', '', false, false, BadgeTone::Neutral)
            : new TableCell('Unread', '', false, false, BadgeTone::Success);

        return new TableRow([
            new TableCell($this->bellParts->sent($inboxItem), '', false, false, null),
            new TableCell($inboxItem->subject, BellParts::INBOX . '/' . $inboxItem->id, false, false, null),
            $status,
        ]);
    }

    /**
     * @param int $total  How many notifications the member has.
     * @param int $unread How many are unread.
     * @param int $page   The page shown.
     * @param int $pages  How many pages there are.
     *
     * @return string What the table shows: the count, how many are unread, and which page when there is more than one.
     */
    private function summary(int $total, int $unread, int $page, int $pages): string
    {
        $shown = number_format($total) . ($total === 1 ? ' notification, ' : ' notifications, ') . number_format($unread)
            . ' unread, newest first';

        return $pages > 1
            ? $shown . ' (page ' . $page . ' of ' . $pages . ')'
            : $shown;
    }
}
