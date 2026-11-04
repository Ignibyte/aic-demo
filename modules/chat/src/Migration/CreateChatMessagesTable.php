<?php

declare(strict_types=1);

namespace App\Chat\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `chat_messages` table (#32), each message numbered within its chat by `seq`, which the streams send as their
 * event ids; and the two views the chats are read through for what the database's clock decides: `chat_states`,
 * whether each side typed in the last five seconds, and `chat_recent`, the visitor's messages in the last minute and
 * whether the chat started in the last hour, which the limits count; and `chat_list`, the staff's list with each
 * chat's member, newest message and unread count, in one read.
 */
final readonly class CreateChatMessagesTable implements Migration
{
    /**
     * @return string Its version, after the chats' table.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_04_000002_create_chat_messages';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When a view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('chat_messages')
            ->identity('id')
            ->bigInteger('chat_id')
            ->integer('seq')
            ->string('side', 10)
            ->bigInteger('staff_id')
            ->nullable()
            ->text('body')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->unique(['chat_id', 'seq'])
            ->foreignKey('chat_id', 'chats', 'id', OnDelete::Cascade)
            ->foreignKey('staff_id', 'users', 'id', OnDelete::SetNull);
        $this->views($schema);
    }

    /**
     * @param Schema $schema The schema to describe the views on.
     *
     * @throws InvalidArgumentException When a view's name or SELECT is malformed.
     */
    private function views(Schema $schema): void
    {
        $schema->view(
            'chat_states',
            "SELECT id, COALESCE(visitor_typing_at > now() - interval '5 seconds', false) AS visitor_typing,"
            . " COALESCE(staff_typing_at > now() - interval '5 seconds', false) AS staff_typing FROM chats",
        );
        $schema->view(
            'chat_recent',
            'SELECT c.id, c.visitor_key, (SELECT count(*)::integer FROM chat_messages m WHERE m.chat_id = c.id'
            . " AND m.side = 'visitor' AND m.created_at > now() - interval '60 seconds') AS recent_sends,"
            . " c.created_at > now() - interval '1 hour' AS started_lately FROM chats c",
        );
        $schema->view(
            'chat_list',
            "SELECT c.id, c.staff_id, c.status, c.car_title, c.visitor_name, COALESCE(u.name, '') AS staff_name,"
            . ' c.last_seq, c.staff_seen, (SELECT max(m.created_at) FROM chat_messages m WHERE m.chat_id = c.id) AS latest,'
            . ' (SELECT count(*)::integer FROM chat_messages m WHERE m.chat_id = c.id'
            . " AND m.side = 'visitor' AND m.seq > c.staff_seen) AS unread FROM chats c LEFT JOIN users u ON u.id = c.staff_id",
        );
    }
}
