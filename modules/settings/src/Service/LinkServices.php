<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Notification\Application\Notifier;
use AIC\Queue\Application\PostgresQueue;
use App\Settings\Entity\SettingsConfig;
use App\Settings\Form\SettingsSeals;
use App\Settings\Repository\LinkRepository;
use App\Settings\Repository\ProfileRepository;
use App\Staff\Repository\StaffRepository;
use RuntimeException;

/**
 * The settings module's links (#22): the password links, which hash a chosen password, and their mails, which queue a
 * notification and start with the configured site address. config/settings.php gives the config, as staff's
 * `StaffSettings` hands out the seed's. `MemberServices` builds it; apart from it, so neither passes PHPMD's coupling
 * limit.
 */
final readonly class LinkServices
{
    /**
     * @param EntityStore    $entityStore    The entity store.
     * @param EnvelopeCodec  $envelopeCodec  Seals a link's id.
     * @param PostgresQueue  $postgresQueue  Queues a link's notification.
     * @param PasswordHasher $passwordHasher Hashes a chosen password.
     */
    public function __construct(
        private EntityStore $entityStore,
        private EnvelopeCodec $envelopeCodec,
        private PostgresQueue $postgresQueue,
        private PasswordHasher $passwordHasher,
    ) {
    }

    /**
     * @return LinkRepository The links, which set a member's password.
     */
    public function linkRepository(): LinkRepository
    {
        return new LinkRepository($this->entityStore, new StaffRepository($this->entityStore), $this->passwordHasher);
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return LinkMails A link issued and its notification sent.
     */
    public function linkMails(): LinkMails
    {
        return new LinkMails(
            $this->linkRepository(),
            new SettingsSeals($this->envelopeCodec),
            new DealershipProfiles(new ProfileRepository($this->entityStore)),
            new Notifier($this->postgresQueue),
            $this->settingsConfig(),
        );
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return SettingsConfig The site's address.
     */
    private function settingsConfig(): SettingsConfig
    {
        $settingsConfig = require __DIR__ . '/../../../../config/settings.php';

        if (!$settingsConfig instanceof SettingsConfig) {
            throw new RuntimeException('config/settings.php must return a ' . SettingsConfig::class . '.');
        }

        return $settingsConfig;
    }
}
