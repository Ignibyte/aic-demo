<?php

declare(strict_types=1);

/*
 * The application's settings. Each config file returns one typed config
 * object; the worker requires this one once, at boot.
 *
 * The Tessera pin (#70): the release and the SHA-256 of each file the build
 * copies into build/assets/tessera-VERSION/, from the release's SHA256SUMS.
 * Upgrading is a new version and new sums here, then `composer run build`.
 * 0.4.2 (Tessera #41): a removed target touches only what it removed, so a
 * bulk delete's later fragments land after its rows leave (AIC #247).
 *
 * The body cap (AIC #196): 12 MiB, above one vehicle photo's 8,000,000 bytes
 * (#16), so an oversized photo reaches UploadCheck and is refused in place
 * with its reason. A body over the cap answers 413 before the session.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Tessera\Domain\TesseraRelease;

return new AppConfig(
    name: 'Lotline',
    theme: 'lotline',
    tessera: new TesseraRelease('0.4.2', [
        'tessera.csp.iife.js' => '95f7c2c63afd0a48b66cfa9dd524f21a6b2f74e8676586864d37a99c450c9527',
        'tessera.csp.iife.js.map' => '8b751b5ab2d758af64f0d55c4c2fdfe53c6b92f8664914eec8a3d111bc9ea317',
    ]),
    maxBodyBytes: 12_582_912,
);
