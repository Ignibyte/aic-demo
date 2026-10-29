<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use InvalidArgumentException;

use function preg_match;

/**
 * The settings' config (#22), from config/settings.php: the site's own address, which a mailed password link starts
 * with. It is configured, never taken from a request's Host, which a forged header could point at another site.
 */
final readonly class SettingsConfig
{
    /**
     * @param string $siteUrl The site's address, `https://host` with no path or trailing slash.
     *
     * @throws InvalidArgumentException When it is not such an address.
     */
    public function __construct(
        public string $siteUrl,
    ) {
        if (preg_match('#^https?://[A-Za-z0-9.-]+(:\\d{1,5})?$#', $siteUrl) !== 1) {
            throw new InvalidArgumentException('SettingsConfig: siteUrl is http(s)://host[:port], with no path.');
        }
    }
}
