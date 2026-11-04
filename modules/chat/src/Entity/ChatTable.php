<?php

declare(strict_types=1);

namespace App\Chat\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Inventory\Entity\VehicleTable;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The chats' entity types (#32): `chats`, keyed by `id`, each referring to the car it is about and to the member of
 * staff it went to (null for none, or once that user is removed); and two read-only views over it that the database's
 * clock answers: `chat_states` (whether each side typed in the last five seconds) and `chat_recent` (the visitor's
 * messages in the last minute, and whether the chat started in the last hour); and `chat_list`, the staff's list.
 */
final readonly class ChatTable
{
    /**
     * @param VehicleTable $vehicleTable The vehicles' type, which `vehicle_id` refers to.
     * @param StaffTable   $staffTable   The users' type, which `staff_id` refers to.
     */
    public function __construct(
        private VehicleTable $vehicleTable = new VehicleTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * The typing times and `ended_at` are timestamps the database's clock sets, through `typing()` and `ending()`; here
     * they are read, and the typing times cleared.
     *
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The chats.
     */
    public function type(): EntityType
    {
        return new EntityType('chats', 'id', [
            new Field('visitor_key', FieldType::Text),
            new Field('vehicle_id', FieldType::Reference, false, $this->vehicleTable->type()),
            new Field('car_title', FieldType::Text),
            new Field('car_path', FieldType::Text),
            new Field('staff_id', FieldType::Reference, true, $this->staffTable->type()),
            new Field('visitor_name', FieldType::Text),
            new Field('visitor_email', FieldType::Text, true),
            new Field('status', FieldType::Text),
            new Field('last_seq', FieldType::Integer),
            new Field('visitor_seen', FieldType::Integer),
            new Field('staff_seen', FieldType::Integer),
            new Field('visitor_typing_at', FieldType::Text, true),
            new Field('staff_typing_at', FieldType::Text, true),
            new Field('created_at', FieldType::CreatedAt),
            new Field('ended_at', FieldType::Text, true),
        ]);
    }

    /**
     * @param ChatSide $chatSide Who is typing.
     *
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The chats with that side's typing time as an UpdatedAt field, so an update through it has the
     *                    database set the time to `now()`, as AIC sets `created_at`; its status is the field written.
     */
    public function typing(ChatSide $chatSide): EntityType
    {
        return new EntityType('chats', 'id', [
            new Field('status', FieldType::Text),
            new Field($chatSide->typingColumn(), FieldType::UpdatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The chats with `ended_at` as an UpdatedAt field, so the end has the database set it to `now()`;
     *                    the status and the typing times are the fields written.
     */
    public function ending(): EntityType
    {
        return new EntityType('chats', 'id', [
            new Field('status', FieldType::Text),
            new Field('visitor_typing_at', FieldType::Text, true),
            new Field('staff_typing_at', FieldType::Text, true),
            new Field('ended_at', FieldType::UpdatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The `chat_states` view: each chat's id and whether each side typed in the last five seconds.
     */
    public function states(): EntityType
    {
        return new EntityType('chat_states', 'id', [
            new Field('visitor_typing', FieldType::Boolean),
            new Field('staff_typing', FieldType::Boolean),
        ], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The `chat_recent` view: each chat's id, its visitor's key, its visitor messages in the last 60
     *                    seconds, and whether it started in the last hour.
     */
    public function recent(): EntityType
    {
        return new EntityType('chat_recent', 'id', [
            new Field('visitor_key', FieldType::Text),
            new Field('recent_sends', FieldType::Integer),
            new Field('started_lately', FieldType::Boolean),
        ], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The `chat_list` view: each chat's id, member, status, car, visitor, the member's name, its
     *                    seqs, when its newest message came and the visitor's messages after `staff_seen`.
     */
    public function listing(): EntityType
    {
        return new EntityType('chat_list', 'id', [
            new Field('staff_id', FieldType::Integer, true),
            new Field('status', FieldType::Text),
            new Field('car_title', FieldType::Text),
            new Field('visitor_name', FieldType::Text),
            new Field('staff_name', FieldType::Text),
            new Field('last_seq', FieldType::Integer),
            new Field('staff_seen', FieldType::Integer),
            new Field('latest', FieldType::Text, true),
            new Field('unread', FieldType::Integer),
        ], true);
    }
}
