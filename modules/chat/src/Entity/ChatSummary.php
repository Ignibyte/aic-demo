<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * One row of the staff's chat list (#32), read from the `chat_list` view: the chat, who it went to, whether it is
 * open, when its newest message came and the visitor's messages after the last a member has seen.
 */
final readonly class ChatSummary
{
    /**
     * @param int    $id          The chat's id.
     * @param string $carTitle    The car it is about.
     * @param string $visitorName The visitor's name.
     * @param string $staffName   The member it went to; empty for none.
     * @param bool   $open        Whether it is open.
     * @param string $latest      When its newest message came, to the minute: `YYYY-MM-DD HH:MM UTC`.
     * @param int    $unread      The visitor's messages after `staff_seen`.
     * @param int    $lastSeq     The seq of its newest message.
     * @param int    $staffSeen   The last seq a member has seen.
     */
    public function __construct(
        public int $id,
        public string $carTitle,
        public string $visitorName,
        public string $staffName,
        public bool $open,
        public string $latest,
        public int $unread,
        public int $lastSeq,
        public int $staffSeen,
    ) {
    }

    /**
     * @return string Its page on the staff's side.
     */
    public function path(): string
    {
        return '/app/chats/' . $this->id;
    }

    /**
     * @return string What the list's poll compares: its id, newest seq, seen mark and status.
     */
    public function version(): string
    {
        return $this->id . ':' . $this->lastSeq . ':' . $this->staffSeen . ':' . ($this->open ? 'o' : 'e');
    }
}
