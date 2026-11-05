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
use App\Staff\Repository\StaffRepository;

/**
 * The settings module's links (#22): the password links, which hash a chosen password, and their mails, which queue a
 * notification and start with the configured site address, which `MemberServices` hands it from
 * its `settingsConfig()`, the one reader of config/settings.php. `MemberServices` builds it; apart from it,
 * so neither passes PHPMD's coupling limit.
 */
final readonly class LinkServices
{
    /**
     * @param EntityStore    $entityStore    The entity store.
     * @param EnvelopeCodec  $envelopeCodec  Seals a link's id.
     * @param PostgresQueue  $postgresQueue  Queues a link's notification.
     * @param PasswordHasher $passwordHasher Hashes a chosen password.
     * @param SettingsConfig $settingsConfig The site's address, which a link starts with.
     */
    public function __construct(
        private EntityStore $entityStore,
        private EnvelopeCodec $envelopeCodec,
        private PostgresQueue $postgresQueue,
        private PasswordHasher $passwordHasher,
        private SettingsConfig $settingsConfig,
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
     * @return LinkMails A link issued and its notification sent.
     */
    public function linkMails(): LinkMails
    {
        return new LinkMails(
            $this->linkRepository(),
            new SettingsSeals($this->envelopeCodec),
            new DealershipProfiles($this->entityStore),
            new Notifier($this->postgresQueue),
            $this->settingsConfig,
        );
    }
}
