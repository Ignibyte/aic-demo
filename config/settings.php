<?php

declare(strict_types=1);

/*
 * The settings' config (#22): the site's own address, which a mailed password
 * link starts with, from LOTLINE_SITE_URL (`https://host`, no path). It is
 * configured rather than read from a request's Host, which a forged header
 * could point at another site. The default is DDEV's address for this app.
 * `SettingsServices`, the settings module's factory, requires this file.
 */

use App\Settings\Entity\SettingsConfig;

$siteUrl = getenv('LOTLINE_SITE_URL');

return new SettingsConfig(
    siteUrl: is_string($siteUrl) && $siteUrl !== '' ? $siteUrl : 'https://aic-demo.ddev.site',
);
