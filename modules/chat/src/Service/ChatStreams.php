<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatSide;
use App\Chat\Repository\ChatMessageRepository;
use App\Chat\Repository\ChatRepository;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function ctype_digit;
use function strlen;

/**
 * Where a connection of a chat's stream starts (#32), for each side: the chat the visitor's envelope names when its
 * key is theirs, or the chat the member's route names when they may see it; after the seq in `Last-Event-ID` when
 * Tessera reconnects, else fresh, so the connection sends the whole thread first and the page names no seq. A request
 * with no chat to serve gets a source that closes at once, since a Stream cannot answer 204.
 */
final readonly class ChatStreams
{
    /**
     * The longest seq read: more digits than that are no seq.
     */
    private const int DIGITS = 9;

    /**
     * @param ChatRepository        $chatRepository        The chats.
     * @param ChatMessageRepository $chatMessageRepository Their messages.
     * @param ChatParts             $chatParts             The thread and lines.
     * @param ChatVisitors          $chatVisitors          The visitor's envelope.
     * @param ChatAccess            $chatAccess            The member and the chat their route names.
     */
    public function __construct(
        private ChatRepository $chatRepository,
        private ChatMessageRepository $chatMessageRepository,
        private ChatParts $chatParts,
        private ChatVisitors $chatVisitors,
        private ChatAccess $chatAccess,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The visitor's stream request, with its session.
     *
     * @throws QueryException When the chat cannot be read.
     * @throws LogicException When the request carries no session, or a type is malformed.
     *
     * @return EventSource One connection's events.
     */
    public function visitor(ServerRequestInterface $serverRequest): EventSource
    {
        $chatVisitor = $this->chatVisitors->visitor($serverRequest);
        $chat = $this->chatRepository->visitors($chatVisitor->chatId, $chatVisitor->key);
        $chatId = $chat instanceof Chat
            ? $chat->id
            : 0;

        return $this->sourceFor(ChatSide::Visitor, $chatId, $serverRequest);
    }

    /**
     * @param ServerRequestInterface $serverRequest The member's stream request, routed with the chat's `id`.
     *
     * @throws QueryException           When the chat cannot be read.
     * @throws LogicException           When the request carries no session, or a type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return EventSource One connection's events.
     */
    public function staff(ServerRequestInterface $serverRequest): EventSource
    {
        $chat = $this->chatAccess->routed($serverRequest);
        $chatId = $chat instanceof Chat && $this->chatAccess->viewer($serverRequest)->mayRead($chat)
            ? $chat->id
            : 0;

        return $this->sourceFor(ChatSide::Staff, $chatId, $serverRequest);
    }

    /**
     * @param ChatSide               $chatSide      The side it serves.
     * @param int                    $chatId        The chat; 0 for none.
     * @param ServerRequestInterface $serverRequest The request, with its `Last-Event-ID`.
     *
     * @return EventSource The connection's source.
     */
    private function sourceFor(ChatSide $chatSide, int $chatId, ServerRequestInterface $serverRequest): EventSource
    {
        $last = $serverRequest->getHeaderLine('Last-Event-ID');
        $after = $last !== '' && strlen($last) <= self::DIGITS && ctype_digit($last)
            ? (int) $last
            : -1;

        return new ChatSource($this->chatRepository, $this->chatMessageRepository, $this->chatParts, $chatSide, $chatId, $after);
    }
}
