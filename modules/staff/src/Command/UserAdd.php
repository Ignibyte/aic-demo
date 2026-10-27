<?php

declare(strict_types=1);

namespace App\Staff\Command;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Staff\Repository\StaffRepository;
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic user:add {login} {password}`: makes an admin who signs in with the login, named by it, or gives an
 * existing user the new password and nothing else. gate:23 and `aic-gate browser` make their own user with it, so it
 * takes exactly those two arguments: exit 2 with its usage otherwise.
 */
final readonly class UserAdd implements Command
{
    /**
     * @param StaffRepository $staffRepository The users.
     * @param PasswordHasher  $passwordHasher  Hashes the password.
     */
    public function __construct(
        private StaffRepository $staffRepository,
        private PasswordHasher $passwordHasher,
    ) {
    }

    /**
     * @param CommandInput $commandInput The login, then the password.
     *
     * @throws QueryException           When the user cannot be stored.
     * @throws InvalidArgumentException When the result's exit code is invalid.
     *
     * @return CommandResponse What was done, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $login = $commandInput->argument(0, '');
        $password = $commandInput->argument(1, '');

        if (count($commandInput->arguments()) !== 2 || $login === '' || $password === '') {
            return new CommandResult(['usage: bin/aic user:add {login} {password}'], 2);
        }

        $this->staffRepository->add($login, $this->passwordHasher->hash($password));

        return new CommandResult(['user ' . $login . ' saved.'], 0);
    }
}
