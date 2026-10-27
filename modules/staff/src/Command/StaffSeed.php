<?php

declare(strict_types=1);

namespace App\Staff\Command;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffSeedConfig;
use App\Staff\Repository\StaffRepository;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic staff:seed`: saves the admin the environment names, and four invented Ridgeway Motors staff when it gives
 * them a password; run again, it updates them. It takes no arguments: exit 2 with its usage when given any, or when
 * the admin's email, name or password is missing.
 */
final readonly class StaffSeed implements Command
{
    /**
     * The invented sales managers, email => name.
     */
    private const array SALES_MANAGERS = ['dana.whitlock@ridgeway-motors.test' => 'Dana Whitlock'];

    /**
     * The invented salespeople, email => name.
     */
    private const array SALESPEOPLE = [
        'leo.fontaine@ridgeway-motors.test' => 'Leo Fontaine',
        'marcus.hale@ridgeway-motors.test' => 'Marcus Hale',
        'priya.natarajan@ridgeway-motors.test' => 'Priya Natarajan',
    ];

    /**
     * @param StaffRepository $staffRepository The users.
     * @param PasswordHasher  $passwordHasher  Hashes the passwords.
     * @param StaffSeedConfig $staffSeedConfig Who to save, from the environment.
     */
    public function __construct(
        private StaffRepository $staffRepository,
        private PasswordHasher $passwordHasher,
        private StaffSeedConfig $staffSeedConfig,
    ) {
    }

    /**
     * @param CommandInput $commandInput Its arguments, which must be none.
     *
     * @throws QueryException           When a user cannot be stored.
     * @throws InvalidArgumentException When the result's exit code is invalid.
     *
     * @return CommandResponse What was saved, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $config = $this->staffSeedConfig;
        $missing = $config->adminEmail === '' || $config->adminName === '' || $config->adminPassword === '';

        if (!$commandInput->isEmpty() || $missing) {
            return new CommandResult([
                'usage: bin/aic staff:seed, with LOTLINE_ADMIN_EMAIL, LOTLINE_ADMIN_NAME and LOTLINE_ADMIN_PASSWORD set',
                '       (and LOTLINE_STAFF_PASSWORD for the invented staff).',
            ], 2);
        }

        $adminHash = $this->passwordHasher->hash($config->adminPassword);
        $this->staffRepository->save($config->adminEmail, $config->adminName, Role::Admin, $adminHash);

        if ($config->staffPassword === '') {
            return new CommandResult(['admin ' . $config->adminEmail . ' saved; no LOTLINE_STAFF_PASSWORD, so no invented staff.'], 0);
        }

        $staffHash = $this->passwordHasher->hash($config->staffPassword);
        $this->saveAll(self::SALES_MANAGERS, Role::SalesManager, $staffHash);
        $this->saveAll(self::SALESPEOPLE, Role::Salesperson, $staffHash);

        return new CommandResult(['admin ' . $config->adminEmail . ' and 4 invented staff saved.'], 0);
    }

    /**
     * @param array<string, string> $people       Email => name.
     * @param Role                  $role         Their role.
     * @param string                $passwordHash Their shared password's hash.
     *
     * @throws QueryException When a user cannot be stored.
     */
    private function saveAll(array $people, Role $role, string $passwordHash): void
    {
        foreach ($people as $email => $name) {
            $this->staffRepository->save($email, $name, $role, $passwordHash);
        }
    }
}
