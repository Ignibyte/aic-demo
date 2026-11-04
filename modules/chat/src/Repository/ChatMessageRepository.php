<?php

declare(strict_types=1);

namespace App\Chat\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Chat\Entity\ChatMessage;
use App\Chat\Entity\ChatMessageTable;
use App\Chat\Entity\ChatSide;
use App\Chat\Entity\ChatTable;
use App\Chat\Entity\ChatWrite;
use InvalidArgumentException;
use UnexpectedValueException;

use function substr;

/**
 * A chat's messages (#32): the append, which numbers each message within its chat under a lock on the chat's row; the
 * whole thread; and the messages after a seq, which the streams send.
 */
final readonly class ChatMessageRepository
{
    /**
     * @param EntityStore      $entityStore      The entity store.
     * @param ChatTable        $chatTable        The chats' type.
     * @param ChatMessageTable $chatMessageTable The messages' type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ChatTable $chatTable = new ChatTable(),
        private ChatMessageTable $chatMessageTable = new ChatMessageTable(),
    ) {
    }

    /**
     * Adds a message as the chat's next seq: it locks the chat's row first, so two sends at once take two seqs in
     * turn; it raises `last_seq`, marks the message seen by its sender and clears the sender's typing. A chat that is
     * gone or has ended takes nothing. Called only inside `ChatRepository::transaction()`.
     *
     * @param int       $chatId    The chat's id.
     * @param ChatSide  $chatSide  Who writes it.
     * @param int       $staffId   The member who writes it; 0 for the visitor.
     * @param string    $body      Its text, checked.
     * @param ChatWrite $chatWrite Holds the new message's seq; it stays 0 when nothing was added.
     *
     * @throws QueryException           When the tables cannot be read or written.
     * @throws EntityNotFoundException  When the chat went between the lock and the write.
     * @throws InvalidArgumentException When a type or a value is malformed.
     */
    public function appendLocked(
        int $chatId,
        ChatSide $chatSide,
        int $staffId,
        string $body,
        ChatWrite $chatWrite,
    ): void {
        $row = $this->entityStore->query($this->chatTable->type())->where('id', $chatId)->forUpdate()->first();

        if (!$row instanceof Row || $row->string('status') !== 'open') {
            return;
        }

        $seq = $row->int('last_seq') + 1;
        $this->entityStore->insert($this->chatMessageTable->type(), [
            'body' => $body,
            'chat_id' => $chatId,
            'seq' => $seq,
            'side' => $chatSide->value,
            'staff_id' => $staffId === 0 ? null : $staffId,
        ]);
        $this->entityStore->update($this->chatTable->type(), $chatId, [
            $chatSide->seenColumn() => $seq,
            $chatSide->typingColumn() => null,
            'last_seq' => $seq,
        ]);
        $chatWrite->hold($seq);
    }

    /**
     * @param int    $chatId      The chat's id.
     * @param string $visitorName The visitor's name, which their messages carry.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return list<ChatMessage> Every message, oldest first.
     */
    public function thread(int $chatId, string $visitorName): array
    {
        return $this->after($chatId, 0, $visitorName);
    }

    /**
     * @param int    $chatId      The chat's id.
     * @param int    $seq         The last seq a page has.
     * @param string $visitorName The visitor's name, which their messages carry.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return list<ChatMessage> The messages after that seq, oldest first.
     */
    public function after(int $chatId, int $seq, string $visitorName): array
    {
        $rows = $this->entityStore->query($this->chatMessageTable->type())
            ->with('staff_id', 'u', true)
            ->where('chat_id', $chatId)
            ->where('seq', $seq + 1, Comparison::GreaterOrEqual)
            ->orderBy('seq')
            ->rows();
        $messages = [];

        foreach ($rows as $row) {
            $messages[] = self::message($row, $visitorName);
        }

        return $messages;
    }

    /**
     * @param Row    $row         A message's row, joined to its member as `u`.
     * @param string $visitorName The visitor's name.
     *
     * @throws QueryException           When a column is missing or of another type.
     * @throws UnexpectedValueException When the stored side is no side.
     *
     * @return ChatMessage The message.
     */
    private static function message(Row $row, string $visitorName): ChatMessage
    {
        $chatSide = ChatSide::Visitor->stored($row->string('side'));

        $author = $visitorName;

        if ($chatSide === ChatSide::Staff) {
            $author = $row->intOrNull('staff_id') === null
                ? 'Staff'
                : $row->string('u_name');
        }

        return new ChatMessage($row->int('seq'), $chatSide, $author, $row->string('body'), self::minute($row->string('created_at')));
    }

    /**
     * @param string $stamp A timestamp as Postgres writes it.
     *
     * @return string It to the minute, `YYYY-MM-DD HH:MM UTC`, a form gate:23 reads as a time.
     */
    private static function minute(string $stamp): string
    {
        return substr($stamp, 0, 16) . ' UTC';
    }
}
