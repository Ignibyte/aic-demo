<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * The member of staff who looks at the chats (#32): their user id and whether they manage, which lets them see every
 * chat; a salesperson sees the chats that went to them.
 */
final readonly class ChatViewer
{
    /**
     * @param int  $userId  Their user id; 0 for no one signed in.
     * @param bool $manages Whether they are an admin or a sales manager.
     */
    public function __construct(
        public int $userId,
        public bool $manages,
    ) {
    }

    /**
     * @param Chat $chat A chat.
     *
     * @return bool Whether they may see it and write in it.
     */
    public function mayRead(Chat $chat): bool
    {
        return $this->userId > 0 && ($this->manages || $chat->staffId === $this->userId);
    }
}
