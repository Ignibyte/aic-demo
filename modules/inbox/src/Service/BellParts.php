<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use AIC\Notification\Domain\InboxItem;
use App\Theme\Components\Bell\Bell;
use App\Theme\Components\BellBadge\BellBadge;
use App\Theme\Components\BellCount\BellCount;
use App\Theme\Components\BellItem\BellItem;
use App\Theme\Components\BellItems\BellItems;
use App\Theme\Components\Icon\Heroicons;
use InvalidArgumentException;

use function str_replace;
use function substr;

/**
 * The bell and its parts (#19), from a member's inbox: the whole bell a page shows, and the badge and the item the
 * bell's stream sends. The stream's targets are the anchors of the parts keyed here (Tessera 0.3.1, AIC #204): inside
 * the bell's template their paths are positional, which no module may spell.
 */
final readonly class BellParts
{
    /**
     * The path of every notification.
     */
    public const string INBOX = '/app/inbox';

    /**
     * The count holder's anchor, the target of the stream's count.
     */
    public const string COUNT = '@bell-count';

    /**
     * The list's anchor, the target of the stream's new items.
     */
    public const string ITEMS = '@bell-items';

    /**
     * How many notifications the menu shows.
     */
    public const int SHOWN = 5;

    /**
     * @param Inbox     $inbox     The members' notifications.
     * @param Heroicons $heroicons The bell icon.
     */
    public function __construct(
        private Inbox $inbox,
        private Heroicons $heroicons = new Heroicons(),
    ) {
    }

    /**
     * @param string $userId The member's user id; empty for none, which gives a bell with nothing in it and no stream.
     *
     * @throws QueryException           When the notifications cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Bell The bell: the unread count, the latest five, and the stream after the newest. `BellPart` builds it
     *              when the page renders it, under the key `bell`.
     */
    public function bell(string $userId): Bell
    {
        $inboxItems = $userId === ''
            ? []
            : $this->inbox->page($userId, 0, self::SHOWN);
        $unread = $userId === ''
            ? 0
            : $this->inbox->unread($userId);
        $items = [];

        foreach ($inboxItems as $inboxItem) {
            $items[] = new KeyedComponent('notification-' . $inboxItem->id, $this->item($inboxItem));
        }

        $stream = $this->streamPath($userId, $inboxItems);
        $count = new KeyedComponent('count', new BellCount($this->badge($unread)), '', 'bell-count');
        $list = new KeyedComponent('items', new BellItems($items, 'No notifications yet.'), '', 'bell-items');

        return new Bell($stream, $this->heroicons->bell(), $count, $list, self::INBOX);
    }

    /**
     * @param int $unread How many notifications are unread.
     *
     * @return BellBadge The count's badge.
     */
    public function badge(int $unread): BellBadge
    {
        return new BellBadge($unread, $unread . ' unread');
    }

    /**
     * @param InboxItem $inboxItem A notification.
     *
     * @return BellItem It in the menu, linking to its opening.
     */
    public function item(InboxItem $inboxItem): BellItem
    {
        $id = (string) $inboxItem->id;

        return new BellItem('bell-item-' . $id, $inboxItem->subject, $this->sent($inboxItem), self::INBOX . '/' . $id, $inboxItem->read);
    }

    /**
     * @param InboxItem $inboxItem A notification.
     *
     * @return string When it came, to the minute, in UTC (`2026-10-29 05:12 UTC`).
     */
    public function sent(InboxItem $inboxItem): string
    {
        return str_replace('T', ' ', substr($inboxItem->sentAt, 0, 16)) . ' UTC';
    }

    /**
     * @param string          $userId     The member's user id; empty for none.
     * @param list<InboxItem> $inboxItems Their latest notifications, newest first.
     *
     * @return string The bell's stream, after the newest notification the page shows; empty for no member.
     */
    private function streamPath(string $userId, array $inboxItems): string
    {
        if ($userId === '') {
            return '';
        }

        $newest = $inboxItems === []
            ? 0
            : $inboxItems[0]->id;

        return self::INBOX . '/events?after=' . $newest;
    }
}
