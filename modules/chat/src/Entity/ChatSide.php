<?php

declare(strict_types=1);

namespace App\Chat\Entity;

use UnexpectedValueException;

/**
 * Who wrote a chat's message (#32): the visitor on the storefront, or a member of staff; its value is the `side`
 * stored.
 */
enum ChatSide: string
{
    /**
     * The storefront's visitor, known by the session alone.
     */
    case Visitor = 'visitor';

    /**
     * A member of staff.
     */
    case Staff = 'staff';

    /**
     * @param string $stored A stored `side`.
     *
     * @throws UnexpectedValueException When it is no side.
     *
     * @return self The side it names; called on any case, as leads' `LeadKind::stored()` is.
     */
    public function stored(string $stored): self
    {
        return match ($stored) {
            self::Visitor->value => self::Visitor,
            self::Staff->value => self::Staff,
            default => throw new UnexpectedValueException('A chat message is stored with the side "' . $stored . '".'),
        };
    }

    /**
     * @return self The side that reads what this one writes.
     */
    public function other(): self
    {
        return match ($this) {
            self::Visitor => self::Staff,
            self::Staff => self::Visitor,
        };
    }

    /**
     * @return string The `chats` column holding the last seq this side has seen.
     */
    public function seenColumn(): string
    {
        return match ($this) {
            self::Visitor => 'visitor_seen',
            self::Staff => 'staff_seen',
        };
    }

    /**
     * @return string The `chats` column holding when this side last typed.
     */
    public function typingColumn(): string
    {
        return match ($this) {
            self::Visitor => 'visitor_typing_at',
            self::Staff => 'staff_typing_at',
        };
    }

    /**
     * @return string The `chat_states` column saying whether this side typed in the last five seconds.
     */
    public function typingState(): string
    {
        return match ($this) {
            self::Visitor => 'visitor_typing',
            self::Staff => 'staff_typing',
        };
    }
}
