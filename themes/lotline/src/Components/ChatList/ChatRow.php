<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatList;

/**
 * One row of the staff's chat list (#32): the chat's page, its car, the visitor, the member it went to, whether it is
 * open, when its newest message came and the visitor's messages no member has seen.
 */
final readonly class ChatRow
{
    /**
     * @param int    $id      The chat's id, which the row's ids end with.
     * @param string $path    The chat's page.
     * @param string $car     The car it is about.
     * @param string $visitor The visitor's name.
     * @param string $staff   The member it went to, or "Unassigned".
     * @param bool   $open    Whether it is open.
     * @param string $latest  When its newest message came, `YYYY-MM-DD HH:MM UTC`.
     * @param int    $unread  The visitor's messages no member has seen.
     */
    public function __construct(
        public int $id,
        public string $path,
        public string $car,
        public string $visitor,
        public string $staff,
        public bool $open,
        public string $latest,
        public int $unread,
    ) {
    }
}
