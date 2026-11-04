<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatMessage;
use App\Settings\Service\DealershipProfiles;
use InvalidArgumentException;
use JsonException;

use function implode;

/**
 * What the chat tells people (#32), through AIC's Notifier, which queues one `notify` job per channel: the member a
 * started chat went to gets a notification kept in the database, which their bell shows; and the visitor who left an
 * email gets the transcript by mail once the chat has ended, signed with the dealership's name.
 */
final readonly class ChatNotices
{
    /**
     * @param Notifier           $notifier           Queues each delivery.
     * @param DealershipProfiles $dealershipProfiles The dealership's name, which signs the transcript.
     */
    public function __construct(
        private Notifier $notifier,
        private DealershipProfiles $dealershipProfiles,
    ) {
    }

    /**
     * @param Chat   $chat  The chat, as stored.
     * @param string $first The visitor's first message.
     *
     * @throws JsonException  When the delivery's payload cannot be encoded.
     * @throws QueryException When the delivery cannot be queued.
     */
    public function started(Chat $chat, string $first): void
    {
        if ($chat->staffId === 0) {
            return;
        }

        $body = $chat->visitorName . ' started a chat about the ' . $chat->carTitle . ":\n\n" . $first
            . "\n\nOpen the chat: " . $chat->path();
        // The database channel alone, which keeps the notification by user id and reads no email.
        $subject = 'New chat about the ' . $chat->carTitle;
        $notification = new Notification((string) $chat->staffId, '', $subject, $body, $chat->path());
        $this->notifier->send($notification, ['database']);
    }

    /**
     * @param Chat              $chat     The chat, ended, with the visitor's email.
     * @param list<ChatMessage> $messages Its messages, oldest first.
     *
     * @throws JsonException            When the delivery's payload cannot be encoded.
     * @throws QueryException           When the delivery cannot be queued, or the profile cannot be read.
     * @throws InvalidArgumentException When the profile's entity type is malformed.
     */
    public function transcript(Chat $chat, array $messages): void
    {
        $lines = ['Hello ' . $chat->visitorName . ',', '', 'Here is your chat about the ' . $chat->carTitle . '.', ''];

        foreach ($messages as $message) {
            $lines[] = $message->time . ' ' . $message->author . ': ' . $message->body;
        }

        $lines[] = '';
        $lines[] = $this->dealershipProfiles->profile()->name;
        $notification = new Notification('', $chat->visitorEmail, 'Your chat about the ' . $chat->carTitle, implode("\n", $lines));
        $this->notifier->send($notification, ['mail']);
    }
}
