<?php

declare(strict_types=1);

namespace App\Leads\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Where each lead stands on the board (#30): `leads.stage`, a `LeadStage` value. AIC adds a column to a table that has
 * rows only as nullable, and its alteration writes the add before the retype, so the retype's `USING` fills every
 * stored lead from its status in the same step: an open lead is New, a closed one Lost.
 */
final readonly class AddLeadStage implements Migration
{
    /**
     * @return string Its version, after the leads table's.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_03_000002_add_lead_stage';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('leads')
            ->addColumn('stage', $schema->types()->string(20))
            ->nullable()
            ->changeType('stage', $schema->types()->string(20), "CASE status WHEN 'closed' THEN 'lost' ELSE 'new' END")
            ->index(['stage']);
    }
}
