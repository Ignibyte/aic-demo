<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use App\Settings\Entity\SettingsConfig;
use App\Settings\Form\SettingsSeals;
use App\Settings\Repository\LinkRepository;
use App\Staff\Entity\StaffAccount;
use JsonException;
use LogicException;

use function str_contains;

/**
 * A password link and its notification (#22): a new member's, or one an admin sends. The link is issued (voiding the
 * member's older ones), its id sealed into `/password/{token}`, and AIC's `Notifier` keeps the notification in the
 * member's inbox and mails it when their login is an address, as the app's other notices go. The mail names the
 * dealership from its profile and carries the absolute link, which starts with the configured site address and never
 * with a request's Host.
 */
final readonly class LinkMails
{
    /**
     * Where a link lands, under which its token follows.
     */
    public const string PAGES = '/password/';

    /**
     * @param LinkRepository     $linkRepository     Issues the link.
     * @param SettingsSeals      $settingsSeals      Seals its id.
     * @param DealershipProfiles $dealershipProfiles The dealership's name.
     * @param Notifier           $notifier           Queues the notification's deliveries.
     * @param SettingsConfig     $settingsConfig     The site's address.
     */
    public function __construct(
        private LinkRepository $linkRepository,
        private SettingsSeals $settingsSeals,
        private DealershipProfiles $dealershipProfiles,
        private Notifier $notifier,
        private SettingsConfig $settingsConfig,
    ) {
    }

    /**
     * @param StaffAccount $staffAccount The member.
     * @param bool         $welcome      Whether they were just added, rather than sent a new link.
     *
     * @throws QueryException When the link cannot be stored, or the profile read.
     * @throws JsonException  When a delivery's payload cannot be encoded.
     * @throws LogicException When a value does not fit its field, or the link's id cannot be sealed.
     */
    public function send(StaffAccount $staffAccount, bool $welcome): void
    {
        $path = $this->issue($staffAccount);
        $dealership = $this->dealershipProfiles->profile()->name;
        $subject = $welcome
            ? 'Your ' . $dealership . ' account: set your password'
            : 'Set a new password for your ' . $dealership . ' account';
        $opening = $welcome
            ? 'An admin at ' . $dealership . ' has added you to Lotline as ' . $staffAccount->role->label() . '. You sign in as '
                . $staffAccount->email . '.'
            : 'An admin at ' . $dealership . ' has sent you a link to set a new password for Lotline. You sign in as '
                . $staffAccount->email . ', and your old password works until you set a new one.';
        $body = 'Hello ' . $staffAccount->name . ",\n\n" . $opening
            . "\n\nSet your password here. The link works once, for 24 hours:\n\n" . $this->settingsConfig->siteUrl . $path
            . "\n\n" . $dealership;
        $channels = str_contains($staffAccount->email, '@')
            ? ['database', 'mail']
            : ['database'];
        $this->notifier->send(new Notification((string) $staffAccount->id, $staffAccount->email, $subject, $body, $path), $channels);
    }

    /**
     * Issues a link and tells no one: for an admin who sends one to themselves, and goes straight to it.
     *
     * @param StaffAccount $staffAccount The member.
     *
     * @throws QueryException When the link cannot be stored.
     * @throws LogicException When a value does not fit its field, or the link's id cannot be sealed.
     *
     * @return string The link's path, `/password/{token}`; the member's older links are voided.
     */
    public function issue(StaffAccount $staffAccount): string
    {
        return self::PAGES . $this->settingsSeals->linkToken($this->linkRepository->issue($staffAccount->id));
    }
}
