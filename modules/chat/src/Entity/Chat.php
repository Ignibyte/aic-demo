<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * A chat as read (#32): the car it is about, the visitor, the member it went to, whether it is open, the seq of its
 * newest message and the last seq each side has seen.
 */
final readonly class Chat
{
    /**
     * @param int    $id           Its row's id.
     * @param string $carTitle     What the public calls the car.
     * @param string $carPath      The car's storefront page.
     * @param int    $staffId      The member it went to; 0 for none.
     * @param string $staffName    Their name; empty for none.
     * @param string $visitorName  The name the visitor gave, or "Visitor".
     * @param string $visitorEmail The email they gave; empty for none.
     * @param bool   $open         Whether it is open; false once either side ended it.
     * @param int    $lastSeq      The seq of its newest message.
     * @param int    $visitorSeen  The last seq the visitor has seen.
     * @param int    $staffSeen    The last seq a member has seen.
     */
    public function __construct(
        public int $id,
        public string $carTitle,
        public string $carPath,
        public int $staffId,
        public string $staffName,
        public string $visitorName,
        public string $visitorEmail,
        public bool $open,
        public int $lastSeq,
        public int $visitorSeen,
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
     * @param ChatSide $chatSide A side.
     *
     * @return int The last seq that side has seen.
     */
    public function seen(ChatSide $chatSide): int
    {
        return $chatSide === ChatSide::Visitor
            ? $this->visitorSeen
            : $this->staffSeen;
    }
}
