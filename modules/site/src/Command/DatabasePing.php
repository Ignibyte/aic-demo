<?php

declare(strict_types=1);

namespace App\Site\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

/** Check the configured database from the operator terminal. */
final readonly class DatabasePing implements Command
{
    /** @param Database $database The application database. */
    public function __construct(private Database $database)
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
            $value = $this->database->fetchOne('SELECT current_setting(?) AS value', [$setting])->string('value');
        } catch (QueryException $queryException) {
            return new CommandResult(['Database unavailable: ' . $queryException->getMessage()], 1);
        }

        return new CommandResult(['Database reachable: ' . $value], 0);
    }
}
