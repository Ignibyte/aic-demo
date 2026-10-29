<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Settings\Service\SettingsAccess;
use App\Settings\Service\SettingsPanels;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * GET /app/settings (#22): the settings page for an admin: the dealership's profile, the staff and adding a member. Anyone
 * else signed in gets 403; the prefix is protected, so only signed-in staff reach it.
 */
final readonly class SettingsPage implements Page
{
    /**
     * @param SettingsAccess $settingsAccess Admins only.
     * @param SettingsPanels $settingsPanels The settings page.
     */
    public function __construct(
        private SettingsAccess $settingsAccess,
        private SettingsPanels $settingsPanels,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws Throwable When the profile or the members cannot be read, or the page cannot be built.
     *
     * @return PageResponse The page; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->settingsAccess->guard($serverRequest) ?? $this->settingsPanels->page($serverRequest);
    }
}
