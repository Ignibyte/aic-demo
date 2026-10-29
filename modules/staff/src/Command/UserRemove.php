<?php

declare(strict_types=1);

namespace App\Staff\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use App\Staff\Entity\StaffAccount;
use App\Staff\Repository\AccountRepository;
use App\Staff\Repository\StaffRepository;
use InvalidArgumentException;
use Override;
use RuntimeException;

use function count;
use function str_contains;

/**
 * `bin/aic user:remove {login}`: deletes the member of staff who signs in with that login, with their password links (by
 * the links' cascade) and then their kept notifications (#22). A member a sale, a hold or a lead names stays: the
 * database refuses the delete (SQLSTATE 23503), and the command says to deactivate them in the settings instead.
 * gate:23 runs it at the end of each pass, so each pass adds its own newcomer afresh and leaves none behind. Exit 1 for
 * no such member or one kept, 2 with its usage for anything but one argument.
 */
final readonly class UserRemove implements Command
{
    /**
     * The SQLSTATE of a delete a foreign key refuses.
     */
    private const string REFERENCED = 'SQLSTATE 23503';

    /**
     * @param AccountRepository $accountRepository Finds the member.
     * @param StaffRepository   $staffRepository   Deletes them.
     * @param Inbox             $inbox             Their kept notifications.
     */
    public function __construct(
        private AccountRepository $accountRepository,
        private StaffRepository $staffRepository,
        private Inbox $inbox,
    ) {
    }

    /**
     * @param CommandInput $commandInput The login.
     *
     * @throws RuntimeException         When the users cannot be read or written for any reason but a record naming
     *                                  the member, or a stored role is no role.
     * @throws InvalidArgumentException When the users' type is malformed, or the result's exit code is invalid.
     *
     * @return CommandResponse What was removed, or why nothing was.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $logins = $commandInput->arguments();
        $login = $logins === []
            ? ''
            : $commandInput->argument(0, '');

        if ($login === '' || count($logins) > 1) {
            return new CommandResult(['usage: bin/aic user:remove {login}'], 2);
        }

        $staffAccount = $this->accountRepository->byLogin($login);

        if (!$staffAccount instanceof StaffAccount) {
            return new CommandResult(['user:remove found no member of staff who signs in as ' . $login . '.'], 1);
        }

        if (!$this->removed($staffAccount)) {
            return new CommandResult(['user:remove kept ' . $login . ': records name them; deactivate them in the settings instead.'], 1);
        }

        $forgotten = $this->inbox->forget((string) $staffAccount->id);

        return new CommandResult(['user ' . $login . ' removed, with ' . $forgotten . ' kept notification(s).'], 0);
    }

    /**
     * @param StaffAccount $staffAccount The member.
     *
     * @throws RuntimeException         When the user cannot be deleted for any reason but a record naming them.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return bool Whether they were deleted; false when a record names them.
     */
    private function removed(StaffAccount $staffAccount): bool
    {
        try {
            $this->staffRepository->remove($staffAccount->id);
        } catch (QueryException $queryException) {
            if (!str_contains($queryException->getMessage(), self::REFERENCED)) {
                throw $queryException;
            }

            return false;
        }

        return true;
    }
}
