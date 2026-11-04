<?php

declare(strict_types=1);

namespace App\Chat\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The chat messages' entity type (#32): `chat_messages`, keyed by `id`, each referring to its chat, numbered within it
 * by `seq` from 1, and, for a member's message, to the member who wrote it (null once that user is removed).
 */
final readonly class ChatMessageTable
{
    /**
     * @param ChatTable  $chatTable  The chats' type, which `chat_id` refers to.
     * @param StaffTable $staffTable The users' type, which `staff_id` refers to.
     */
    public function __construct(
        private ChatTable $chatTable = new ChatTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The chat messages.
     */
    public function type(): EntityType
    {
        return new EntityType('chat_messages', 'id', [
            new Field('chat_id', FieldType::Reference, false, $this->chatTable->type()),
            new Field('seq', FieldType::Integer),
            new Field('side', FieldType::Text),
            new Field('staff_id', FieldType::Reference, true, $this->staffTable->type()),
            new Field('body', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
