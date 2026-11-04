<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * What became of a visitor's chat form (#32), for the store's page that answers it: its status (200 taken, 422
 * refused, 409 the chat has ended), the fields as sent, the reason and the field it is about, and the seq of the
 * message it added.
 */
final readonly class ChatReply
{
    /**
     * Why a form sent to a chat that has ended, or to none, is refused.
     */
    public const string ENDED = 'This chat has ended.';

    /**
     * @param int    $status  200, 422 or 409.
     * @param string $name    The name as sent; empty for a send.
     * @param string $email   The email as sent; empty for a send.
     * @param string $message The message as sent; empty once it was taken.
     * @param string $reason  Why it was refused; empty when it was taken.
     * @param string $field   The field the reason is about (`name`, `email` or `message`); empty for none.
     * @param int    $sent    The seq of the message it added; 0 when it added none.
     */
    public function __construct(
        public int $status,
        public string $name,
        public string $email,
        public string $message,
        public string $reason,
        public string $field,
        public int $sent,
    ) {
    }

    /**
     * @return bool Whether it was taken.
     */
    public function isTaken(): bool
    {
        return $this->status === 200;
    }
}
