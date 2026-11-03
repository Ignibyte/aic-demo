<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Settings\Entity\SettingsWrite;
use App\Settings\Form\ProfileChecks;
use App\Settings\Form\ProfileEntry;
use App\Settings\Form\ProfileFields;
use App\Settings\Form\SettingsSeals;
use App\Settings\Repository\ProfileRepository;
use App\Staff\Service\StaffNotices;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The profile's post, `POST /app/settings/profile` (#22): an admin's save, checked field by field, upserted when the
 * stored profile is still the one the form showed, then 303 to the settings page with a notice. A refused field
 * answers the page again as 422; a save made on a stale copy writes nothing and answers 409 with what changed, the typed
 * values kept and the stored ones sealed, so saving again saves; an envelope that is not the profile form's answers 400.
 * Through Tessera each lands in the same document.
 */
final readonly class ProfileSaves
{
    /**
     * Each field's label in a 409's list, in the form's order.
     */
    private const array LABELS = [
        'address' => 'Address',
        'aged_stock_days' => 'Aged stock after (days)',
        'closes_at' => 'Closes at',
        'default_apr' => 'Default APR (%)',
        'name' => 'Name',
        'opens_at' => 'Opens at',
        'open_days' => 'Open on',
        'phone' => 'Phone',
    ];

    /**
     * @param SettingsAccess    $settingsAccess    Admins only; the 400 page.
     * @param SettingsPanels    $settingsPanels    The settings page.
     * @param ProfileRepository $profileRepository Saves the profile.
     * @param ProfileFields     $profileFields     The profile form.
     * @param SettingsSeals     $settingsSeals     Opens the form's envelope.
     * @param ProfileChecks     $profileChecks     Reads and checks the post.
     * @param ChangeLines       $changeLines       What a 409 says.
     * @param StaffNotices      $staffNotices      Keeps the save's notice.
     */
    public function __construct(
        private SettingsAccess $settingsAccess,
        private SettingsPanels $settingsPanels,
        private ProfileRepository $profileRepository,
        private ProfileFields $profileFields,
        private SettingsSeals $settingsSeals,
        private ProfileChecks $profileChecks = new ProfileChecks(),
        private ChangeLines $changeLines = new ChangeLines(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted profile form, with its session.
     *
     * @throws Throwable When the profile cannot be read or written; a failed save is rolled back.
     *
     * @return PageResponse 303 to the settings; the page again as 422 or 409; 400; 403 for anyone but an admin.
     */
    public function save(ServerRequestInterface $serverRequest): PageResponse
    {
        $refusal = $this->settingsAccess->guard($serverRequest);

        if ($refusal !== null) {
            return $refusal;
        }

        $shown = $this->settingsSeals->shown($serverRequest, ProfileFields::ACTION);

        return $shown === null
            ? $this->settingsAccess->altered()
            : $this->checked($serverRequest, $shown);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted profile form, with its session.
     * @param array<string, string>  $shown         The values the form showed, its envelope whole.
     *
     * @throws Throwable When the profile cannot be read or written; a failed save is rolled back.
     *
     * @return PageResponse 303 to the settings; the page again as 422 or 409.
     */
    private function checked(ServerRequestInterface $serverRequest, array $shown): PageResponse
    {
        $profileEntry = $this->profileChecks->posted($serverRequest);
        $errors = $this->profileChecks->errors($profileEntry);

        if ($errors !== []) {
            $form = $this->profileFields->form($serverRequest, $profileEntry, $errors, $shown, '');

            return $this->settingsPanels->withProfile($serverRequest, $form, 422);
        }

        if ($this->profileRepository->save($shown, $this->profileChecks->profile($profileEntry)) === SettingsWrite::STALE) {
            return $this->stale($serverRequest, $profileEntry, $shown);
        }

        $this->staffNotices->keep($serverRequest, "Saved the dealership's profile.");

        return new PageRedirect(SettingsAccess::PAGE);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted profile form, with its session.
     * @param ProfileEntry           $profileEntry  What was typed, found valid.
     * @param array<string, string>  $shown         The values the form showed.
     *
     * @throws Throwable When the profile cannot be read, or the page cannot be built.
     *
     * @return PageResponse The page as 409: what changed, the typed values, the stored ones sealed.
     */
    private function stale(
        ServerRequestInterface $serverRequest,
        ProfileEntry $profileEntry,
        array $shown,
    ): PageResponse {
        $dealershipProfile = $this->profileRepository->profile();
        $then = $this->profileChecks->profile(new ProfileEntry($shown));
        $message = $this->changeLines->message('the profile', self::LABELS, $then->shown(), $dealershipProfile->shown());
        $form = $this->profileFields->form($serverRequest, $profileEntry, [], $dealershipProfile->values(), $message);

        return $this->settingsPanels->withProfile($serverRequest, $form, 409);
    }
}
