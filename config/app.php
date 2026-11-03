<?php

declare(strict_types=1);

/*
 * The application's settings. Each config file returns one typed config
 * object; the worker requires this one once, at boot.
 *
 * The Tessera pin (#70): the release and the SHA-256 of each file the build
 * copies into build/assets/tessera-VERSION/, from the release's SHA256SUMS.
 * Upgrading is a new version and new sums here, then `composer run build`.
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
    tessera: new TesseraRelease('0.4.1', [
        'tessera.csp.iife.js' => 'b4e3e1c8396689eef446072117502dedff4ae1acf3843c0da5464a50e2056272',
        'tessera.csp.iife.js.map' => '5844f5c6916d49a7e0e5bca5015e64ee902901c03124eb0a3010d7cfc6d2ca3b',
    ]),
    maxBodyBytes: 12_582_912,
);
