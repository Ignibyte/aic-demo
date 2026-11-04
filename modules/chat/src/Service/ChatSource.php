<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Tessera\Domain\TesseraEvents;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatSide;
use App\Chat\Repository\ChatMessageRepository;
use App\Chat\Repository\ChatRepository;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Override;
use UnexpectedValueException;

use function array_slice;
use function json_encode;
use function usleep;

use const JSON_THROW_ON_ERROR;

/**
 * One connection of a chat's stream (#32), for either side, as Tessera events whose ids are the chat's seqs. A fresh
 * connection first sends the whole thread as the log's inner markup, its id the newest seq; a resumed one sends an
 * `append` for each message after the seq it names. Then the signals: the other side's typing, and for the visitor
 * the unread count. It then looks once a second: a new message is an `append`, a change of the signals a signal patch
 * with no id, nothing a keep-alive comment. After `LOOKS` looks it ends with `reconnect()`, and Tessera comes back with
 * the last seq it saw (AIC #202). A chat that has ended sends "This chat has ended." in place of the send form and
 * closes; a source with no chat to serve sends one closing event. The member's connection marks what it sends seen.
 */
final class ChatSource implements EventSource
{
    /**
     * How many looks one connection makes before it ends: about twenty seconds.
     */
    private const int LOOKS = 20;

    /**
     * The pause before each look, in microseconds.
     */
    private const int PAUSE = 1_000_000;

    /**
     * The events found but not sent yet, in order.
     *
     * @var list<ServerEvent>
     */
    private array $due = [];

    /**
     * Whether the connection's first events have been found.
     */
    private bool $started = false;

    /**
     * Whether the connection has found the chat ended and queued its last event.
     */
    private bool $closed = false;

    /**
     * The looks made so far.
     */
    private int $looks = 0;

    /**
     * The signals last sent, as JSON; empty before the first.
     */
    private string $signals = '';

    /**
     * @param ChatRepository        $chatRepository        The chat, its unread count and its typing.
     * @param ChatMessageRepository $chatMessageRepository Its messages.
     * @param ChatParts             $chatParts             The thread and lines the events carry.
     * @param ChatSide              $chatSide              The side the connection serves.
     * @param int                   $chatId                The chat; 0 for none, which closes at once.
     * @param int                   $after                 The last seq the page has; -1 for a fresh connection.
     * @param TesseraEvents         $tesseraEvents         Makes the events.
     */
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ChatParts $chatParts,
        private readonly ChatSide $chatSide,
        private readonly int $chatId,
        private int $after,
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @throws QueryException           When the chat or its messages cannot be read or written.
     * @throws LogicException           When a key is malformed, or a type or a patch's target is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     * @throws JsonException            When the signals cannot be encoded.
     *
     * @return ServerEvent The next event: one found already, else what the next look finds, else a keep-alive; the
     *                     reconnect comment once the connection has made its looks; for no chat, a last comment.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if ($this->chatId < 1) {
            return $this->tesseraEvents->keepAlive(true);
        }

        $events = $this->due === []
            ? $this->found()
            : $this->due;
        $this->due = array_slice($events, 1);

        return $events[0];
    }

    /**
     * Waits for something to send: the first look's events at once; else, once a second, what a look finds, a
     * keep-alive when it finds nothing; the reconnect comment once the connection has made its looks.
     *
     * @throws QueryException           When the chat or its messages cannot be read or written.
     * @throws LogicException           When a key is malformed, or a type or a patch's target is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     * @throws JsonException            When the signals cannot be encoded.
     *
     * @return non-empty-list<ServerEvent> The events to send next, in order.
     */
    private function found(): array
    {
        if (!$this->started) {
            $this->started = true;
            $this->look();

            if ($this->due !== []) {
                return $this->due;
            }
        }

        if ($this->looks >= self::LOOKS) {
            return [$this->tesseraEvents->reconnect()];
        }

        usleep(self::PAUSE);
        $this->looks += 1;
        $this->look();

        return $this->due === []
            ? [$this->tesseraEvents->keepAlive(false)]
            : $this->due;
    }

    /**
     * Finds what changed since the last look: the messages after the cursor (the whole thread on a fresh connection's
     * first look), then the signals when they are not the ones last sent; or, for a chat that is gone or ended, the
     * ended notice as the last event.
     *
     * @throws QueryException           When the chat or its messages cannot be read or written.
     * @throws LogicException           When a key is malformed, or a type or a patch's target is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     * @throws JsonException            When the signals cannot be encoded.
     */
    private function look(): void
    {
        if ($this->closed) {
            return;
        }

        $chat = $this->chatRepository->find($this->chatId);

        if (!$chat instanceof Chat || !$chat->open) {
            $this->closed = true;
            $notice = new Notice('This chat has ended.', NoticeTone::Info, 'chat-ended');
            $this->due[] = $this->tesseraEvents->patchElements('@chat-send', $notice, 'inner', '', true);

            return;
        }

        $this->messages($chat);
        $signals = $this->signalsOf($chat);

        if ($signals === $this->signals) {
            return;
        }

        $this->signals = $signals;
        $scope = $this->chatSide === ChatSide::Visitor
            ? '@chat'
            : '@chat-conversation';
        $this->due[] = $this->tesseraEvents->patchSignals($scope, $signals, false, '', false);
    }

    /**
     * @param Chat $chat The chat, open.
     *
     * @throws QueryException           When the messages cannot be read, or the seen mark written.
     * @throws LogicException           When a key is malformed, or a type or a patch's target is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     */
    private function messages(Chat $chat): void
    {
        if ($this->after < 0) {
            $thread = $this->chatParts->thread($this->chatMessageRepository->thread($chat->id, $chat->visitorName), $this->chatSide);
            $this->due[] = $this->tesseraEvents->patchElements('@chat-messages', $thread, 'inner', (string) $chat->lastSeq, false);
            $this->after = $chat->lastSeq;
            $this->seen($chat->id);

            return;
        }

        if ($chat->lastSeq <= $this->after) {
            return;
        }

        foreach ($this->chatMessageRepository->after($chat->id, $this->after, $chat->visitorName) as $chatMessage) {
            $line = $this->chatParts->line($chatMessage, $this->chatSide);
            $this->due[] = $this->tesseraEvents->patchElements('@chat-messages', $line, 'append', (string) $chatMessage->seq, false);
            $this->after = $chatMessage->seq;
        }

        $this->seen($chat->id);
    }

    /**
     * Marks what a member's connection sent as seen; the visitor reads by opening the dock.
     *
     * @param int $chatId The chat.
     *
     * @throws QueryException           When the seen mark cannot be written.
     * @throws InvalidArgumentException When a type is malformed.
     */
    private function seen(int $chatId): void
    {
        if ($this->chatSide !== ChatSide::Staff) {
            return;
        }

        $this->chatRepository->see($chatId, ChatSide::Staff, $this->after);
    }

    /**
     * @param Chat $chat The chat, open.
     *
     * @throws QueryException           When the typing or the messages cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     * @throws JsonException            When the signals cannot be encoded.
     *
     * @return string The signals now, as JSON: whether the other side is typing, and the visitor's unread count.
     */
    private function signalsOf(Chat $chat): string
    {
        $typing = $this->chatRepository->isTyping($chat->id, $this->chatSide->other());

        if ($this->chatSide === ChatSide::Staff) {
            return json_encode(['chatTyping' => $typing], JSON_THROW_ON_ERROR);
        }

        $unread = $this->chatRepository->unread($chat, ChatSide::Visitor);

        return json_encode(['chatTyping' => $typing, 'chatUnread' => $unread], JSON_THROW_ON_ERROR);
    }
}
