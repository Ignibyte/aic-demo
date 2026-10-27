<?php

declare(strict_types=1);

namespace App\Site\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Application\DatabaseStatus;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

/** Check the configured database from the operator terminal, through AIC's `DatabaseStatus` (#14). */
final readonly class DatabasePing implements Command
{
    /** @param DatabaseStatus $databaseStatus What the database says about itself. */
    public function __construct(private DatabaseStatus $databaseStatus)
    {
    }

    /**
     * @param CommandInput $commandInput Optional setting to read, defaulting to server_version.
     *
     * @throws InvalidArgumentException When the command result is invalid.
     *
     * @return CommandResponse Success or the actual database failure.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $setting = $commandInput->argument(0, 'server_version');

        try {
            $value = $this->databaseStatus->setting($setting);
        } catch (QueryException $queryException) {
            return new CommandResult(['Database unavailable: ' . $queryException->getMessage()], 1);
        }

        return new CommandResult(['Database reachable: ' . $value], 0);
    }
}
