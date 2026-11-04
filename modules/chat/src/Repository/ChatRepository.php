<?php

declare(strict_types=1);

namespace App\Chat\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatMessageTable;
use App\Chat\Entity\ChatSide;
use App\Chat\Entity\ChatTable;
use App\Chat\Entity\ChatWrite;
use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * The chats (#32): a start with its first message, one chat by id or by the visitor's key, the seen marks, the typing
 * times, the end, and what the limits count. The typing times and `ended_at` go through `ChatTable::typing()` and
 * `ending()`, whose UpdatedAt field has the database's clock set them, and the `chat_states` and `chat_recent` views
 * read them against it.
 */
final readonly class ChatRepository
{
    /**
     * @param EntityStore      $entityStore      The entity store.
     * @param ChatTable        $chatTable        The chats' types.
     * @param ChatMessageTable $chatMessageTable The messages' type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ChatTable $chatTable = new ChatTable(),
        private ChatMessageTable $chatMessageTable = new ChatMessageTable(),
    ) {
    }

    /**
     * @param Closure(): void $work What to write together.
     *
     * @throws Throwable When the work throws; the transaction is rolled back first.
     */
    public function transaction(Closure $work): void
    {
        $this->entityStore->transaction($work);
    }

    /**
     * Inserts a chat and its first message, the visitor's, as seq 1 and seen by the visitor. Called only inside
     * `transaction()`.
     *
     * @param array<string, string|int|null> $values    The chat's car, visitor and member.
     * @param string                         $body      The visitor's first message.
     * @param ChatWrite                      $chatWrite Holds the new chat's id.
     *
     * @throws QueryException           When the tables cannot be written.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function startLocked(array $values, string $body, ChatWrite $chatWrite): void
    {
        $values['status'] = 'open';
        $values['last_seq'] = 1;
        $values['visitor_seen'] = 1;
        $values['staff_seen'] = 0;
        $id = $this->entityStore->insert($this->chatTable->type(), $values);
        $this->entityStore->insert($this->chatMessageTable->type(), [
            'body' => $body,
            'chat_id' => $id,
            'seq' => 1,
            'side' => ChatSide::Visitor->value,
            'staff_id' => null,
        ]);
        $chatWrite->hold($id);
    }

    /**
     * @param int $id A chat's id.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return Chat|null The chat, with its member's name; null when there is none.
     */
    public function find(int $id): ?Chat
    {
        $row = $this->entityStore->query($this->chatTable->type())->with('staff_id', 'u', true)->where('id', $id)->first();

        return $row instanceof Row
            ? self::chat($row)
            : null;
    }

    /**
     * @param int    $id  The chat the visitor's envelope names.
     * @param string $key The visitor's key from it.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return Chat|null The chat when it is that visitor's, open or ended; null otherwise.
     */
    public function visitors(int $id, string $key): ?Chat
    {
        if ($id < 1 || $key === '') {
            return null;
        }

        $row = $this->entityStore->query($this->chatTable->type())
            ->with('staff_id', 'u', true)
            ->where('id', $id)
            ->where('visitor_key', $key)
            ->first();

        return $row instanceof Row
            ? self::chat($row)
            : null;
    }

    /**
     * @param int    $id  The chat the visitor's envelope names.
     * @param string $key The visitor's key from it.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return Chat|null The chat when it is open and that visitor's; null otherwise.
     */
    public function visitorsOpen(int $id, string $key): ?Chat
    {
        $chat = $this->visitors($id, $key);

        return $chat instanceof Chat && $chat->open
            ? $chat
            : null;
    }

    /**
     * Raises a side's seen mark to a seq; a lower seq leaves it as it is.
     *
     * @param int      $id       The chat's id.
     * @param ChatSide $chatSide Whose mark.
     * @param int      $seq      The seq they have now seen.
     *
     * @throws QueryException           When the chats cannot be written.
     * @throws InvalidArgumentException When a type is malformed.
     */
    public function see(int $id, ChatSide $chatSide, int $seq): void
    {
        $column = $chatSide->seenColumn();
        $this->entityStore->query($this->chatTable->type())
            ->where('id', $id)
            ->where($column, $seq, Comparison::LessOrEqual)
            ->changes()
            ->update([$column => $seq]);
    }

    /**
     * @param int      $id       The chat's id.
     * @param ChatSide $chatSide Who is typing.
     *
     * @throws QueryException           When the chats cannot be written.
     * @throws InvalidArgumentException When a type is malformed.
     */
    public function typing(int $id, ChatSide $chatSide): void
    {
        $this->entityStore->query($this->chatTable->typing($chatSide))
            ->where('id', $id)
            ->where('status', 'open')
            ->changes()
            ->update(['status' => 'open']);
    }

    /**
     * @param int      $id       The chat's id.
     * @param ChatSide $chatSide A side.
     *
     * @throws QueryException           When the view cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return bool Whether that side typed in the last five seconds.
     */
    public function isTyping(int $id, ChatSide $chatSide): bool
    {
        $row = $this->entityStore->query($this->chatTable->states())->where('id', $id)->first();

        return $row instanceof Row && $row->bool($chatSide->typingState());
    }

    /**
     * Ends an open chat; one that has ended already stays as it is.
     *
     * @param int $id The chat's id.
     *
     * @throws QueryException           When the chats cannot be written.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return bool Whether this ended it.
     */
    public function end(int $id): bool
    {
        $changed = $this->entityStore->query($this->chatTable->ending())
            ->where('id', $id)
            ->where('status', 'open')
            ->changes()
            ->update(['staff_typing_at' => null, 'status' => 'ended', 'visitor_typing_at' => null]);

        return $changed > 0;
    }

    /**
     * @param string $key A visitor's key.
     *
     * @throws QueryException           When the view cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return int The chats that key started in the last hour.
     */
    public function startsLately(string $key): int
    {
        return $this->entityStore->query($this->chatTable->recent())
            ->where('visitor_key', $key)
            ->where('started_lately', true)
            ->totals()
            ->count();
    }

    /**
     * @param int $id A chat's id.
     *
     * @throws QueryException           When the view cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return int The visitor's messages in it in the last 60 seconds.
     */
    public function recentSends(int $id): int
    {
        $row = $this->entityStore->query($this->chatTable->recent())->where('id', $id)->first();

        return $row instanceof Row
            ? $row->int('recent_sends')
            : 0;
    }

    /**
     * @param Chat $chat A chat.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return int The staff's messages after the last seq the visitor has seen.
     */
    public function visitorUnread(Chat $chat): int
    {
        return $this->unread($chat, ChatSide::Visitor);
    }

    /**
     * @param Chat     $chat     A chat.
     * @param ChatSide $chatSide The side that reads.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return int The other side's messages after the last seq this side has seen.
     */
    public function unread(Chat $chat, ChatSide $chatSide): int
    {
        return $this->entityStore->query($this->chatMessageTable->type())
            ->where('chat_id', $chat->id)
            ->where('side', $chatSide->other()->value)
            ->where('seq', $chat->seen($chatSide) + 1, Comparison::GreaterOrEqual)
            ->totals()
            ->count();
    }

    /**
     * @param Row $row A chat's row, joined to its member as `u`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Chat The chat.
     */
    private static function chat(Row $row): Chat
    {
        $staffId = $row->intOrNull('staff_id') ?? 0;

        return new Chat(
            $row->int('id'),
            $row->string('car_title'),
            $row->string('car_path'),
            $staffId,
            $staffId === 0 ? '' : $row->string('u_name'),
            $row->string('visitor_name'),
            $row->stringOrNull('visitor_email') ?? '',
            $row->string('status') === 'open',
            $row->int('last_seq'),
            $row->int('visitor_seen'),
            $row->int('staff_seen'),
        );
    }
}
