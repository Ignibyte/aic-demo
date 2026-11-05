<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Queue\Application\PostgresQueue;
use App\Settings\Entity\SettingsConfig;
use App\Settings\Form\MemberChecks;
use App\Staff\Repository\AccountRepository;
use RuntimeException;

/**
 * The settings module's member and link factory (#22): the desks a member's routes and a password link's use, built
 * from the pages `SettingsServices` makes and the links `LinkServices` makes. It takes the framework's services alone,
 * so each generated handler builds it in a few lines and makes one call per controller, inside PHPCS's 20-line method
 * and PHPMD's coupling limit. It is the one reader of config/settings.php, the site's address, which the password
 * mails' links start with and which storefront's XML feed (#37) asks for by type to write absolute URLs.
 */
final readonly class MemberServices
{
    /**
     * @param PasswordHasher $passwordHasher Hashes a chosen password.
     * @param PageDocuments  $pageDocuments  The theme's documents.
     * @param EnvelopeCodec  $envelopeCodec  Seals and opens the envelopes and the links.
     * @param EntityStore    $entityStore    The entity store.
     * @param PostgresQueue  $postgresQueue  Queues a link's notification.
     */
    public function __construct(
        private PasswordHasher $passwordHasher,
        private PageDocuments $pageDocuments,
        private EnvelopeCodec $envelopeCodec,
        private EntityStore $entityStore,
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return MemberSaves A member added and shown.
     */
    public function memberSaves(): MemberSaves
    {
        $settingsServices = $this->settingsServices();
        $accountRepository = new AccountRepository($this->entityStore);

        return new MemberSaves(
            $settingsServices->settingsAccess(),
            $settingsServices->settingsPanels(),
            $settingsServices->memberPanels(),
            $accountRepository,
            new MemberChecks($accountRepository),
            $settingsServices->memberFields(),
            $this->linkServices()->linkMails(),
        );
    }

    /**
     * @return MemberEdits A member's edit form saved.
     */
    public function memberEdits(): MemberEdits
    {
        $settingsServices = $this->settingsServices();
        $accountRepository = new AccountRepository($this->entityStore);

        return new MemberEdits(
            $settingsServices->settingsAccess(),
            $settingsServices->memberPanels(),
            $accountRepository,
            new MemberChecks($accountRepository),
            $settingsServices->memberFields(),
            $settingsServices->settingsSeals(),
        );
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return MemberMoves A member's access changed, or a link mailed.
     */
    public function memberMoves(): MemberMoves
    {
        $settingsServices = $this->settingsServices();
        $accountRepository = new AccountRepository($this->entityStore);

        $linkMails = $this->linkServices()->linkMails();

        return new MemberMoves($settingsServices->settingsAccess(), $settingsServices->memberPanels(), $accountRepository, $linkMails);
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return PasswordDesk The page a password link lands on, and its post.
     */
    public function passwordDesk(): PasswordDesk
    {
        $settingsServices = $this->settingsServices();
        $settingsAccess = $settingsServices->settingsAccess();
        $linkRepository = $this->linkServices()->linkRepository();

        $accountRepository = new AccountRepository($this->entityStore);

        return new PasswordDesk($settingsAccess, $linkRepository, $accountRepository, $settingsServices->settingsSeals());
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return SettingsConfig The site's address.
     */
    public function settingsConfig(): SettingsConfig
    {
        $settingsConfig = require __DIR__ . '/../../../../config/settings.php';

        if (!$settingsConfig instanceof SettingsConfig) {
            throw new RuntimeException('config/settings.php must return a ' . SettingsConfig::class . '.');
        }

        return $settingsConfig;
    }

    /**
     * @return SettingsServices The pages.
     */
    private function settingsServices(): SettingsServices
    {
        return new SettingsServices($this->entityStore, $this->envelopeCodec, $this->pageDocuments);
    }

    /**
     * @throws RuntimeException When config/settings.php does not return a SettingsConfig.
     *
     * @return LinkServices The links and their mails, which start with the site's address.
     */
    private function linkServices(): LinkServices
    {
        return new LinkServices(
            $this->entityStore,
            $this->envelopeCodec,
            $this->postgresQueue,
            $this->passwordHasher,
            $this->settingsConfig(),
        );
    }
}
