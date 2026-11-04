<?php

declare(strict_types=1);

namespace App\Chat\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use Override;

/**
 * The `chats` table (#32): one row per chat a visitor starts on a car's page. A chat goes with its car, as a lead
 * does; the member it went to, once removed, leaves it with none. The seqs and seen marks start at 0, written by the
 * insert, since AIC's tables take no defaults.
 */
final readonly class CreateChatsTable implements Migration
{
    /**
     * @return string Its version, after the vehicles' and the users' tables.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_04_000001_create_chats';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $this->keys($this->marks($this->columns($schema->create('chats'))));
    }

    /**
     * @param TableBuilder $tableBuilder The new table.
     *
     * @return TableBuilder It, with its car, member, visitor and status.
     */
    private function columns(TableBuilder $tableBuilder): TableBuilder
    {
        return $tableBuilder->identity('id')
            ->string('visitor_key', 32)
            ->bigInteger('vehicle_id')
            ->string('car_title', 120)
            ->string('car_path', 200)
            ->bigInteger('staff_id')
            ->nullable()
            ->string('visitor_name', 80)
            ->string('visitor_email', 254)
            ->nullable()
            ->string('status', 10);
    }

    /**
     * @param TableBuilder $tableBuilder The new table, with its car, member and visitor.
     *
     * @return TableBuilder It, with its seqs, seen marks and times.
     */
    private function marks(TableBuilder $tableBuilder): TableBuilder
    {
        return $tableBuilder->integer('last_seq')
            ->integer('visitor_seen')
            ->integer('staff_seen')
            ->timestampTz('visitor_typing_at')
            ->nullable()
            ->timestampTz('staff_typing_at')
            ->nullable()
            ->timestampTz('created_at')
            ->timestampTz('ended_at')
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table, with its columns.
     */
    private function keys(TableBuilder $tableBuilder): void
    {
        $tableBuilder->primary(['id'])
            ->index(['visitor_key'])
            ->index(['status'])
            ->index(['vehicle_id'])
            ->foreignKey('vehicle_id', 'vehicles', 'id', OnDelete::Cascade)
            ->foreignKey('staff_id', 'users', 'id', OnDelete::SetNull);
    }
}
