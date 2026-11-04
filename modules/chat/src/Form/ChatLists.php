<?php

declare(strict_types=1);

namespace App\Chat\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use App\Chat\Entity\ChatSummary;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPages;
use App\Theme\Components\ChatList\ChatList;
use App\Theme\Components\ChatList\ChatRow;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The staff's chat list page (#32): a close's notice when one waits, then the list, keyed `chats` and anchored
 * `chat-list`, sealed with its version so its poll answers 204 while nothing changed, in the `chats` panel.
 */
final readonly class ChatLists
{
    /**
     * @param StaffPages   $staffPages   The staff pages' document.
     * @param StaffNotices $staffNotices A close's notice.
     */
    public function __construct(
        private StaffPages $staffPages,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its session.
     * @param list<ChatSummary>      $summaries     The chats the member sees.
     * @param string                 $props         The list's sealed version.
     *
     * @throws LogicException When the request carries no session, or a key, the token or the status does not fit.
     *
     * @return PageDocument The list's page.
     */
    public function page(ServerRequestInterface $serverRequest, array $summaries, string $props): PageDocument
    {
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'chats-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('chats', new ChatList('/app/chats', self::rows($summaries)), $props, 'chat-list');
        $panel = new Panel('chats', 'Chats', "Visitors who opened a chat on a car's page, open chats first", true, $body);

        return $this->staffPages->page('Chats', [new KeyedComponent('chats-panel', $panel)], 200);
    }

    /**
     * @param list<ChatSummary> $summaries The chats the member sees.
     *
     * @return list<ChatRow> Their rows, "Unassigned" for a chat that went to no one.
     */
    private static function rows(array $summaries): array
    {
        $rows = [];

        foreach ($summaries as $summary) {
            $staff = $summary->staffName === ''
                ? 'Unassigned'
                : $summary->staffName;
            $rows[] = new ChatRow(
                $summary->id,
                $summary->path(),
                $summary->carTitle,
                $summary->visitorName,
                $staff,
                $summary->open,
                $summary->latest,
                $summary->unread,
            );
        }

        return $rows;
    }
}
