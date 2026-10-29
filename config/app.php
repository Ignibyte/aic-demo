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
    tessera: new TesseraRelease('0.3.1', [
        'tessera.csp.iife.js' => 'f1c584bffb3c63197908f762b102b2fdf97fe4f5b86de48e66fd7ea6ea3fcd6d',
        'tessera.csp.iife.js.map' => 'd19fd3aa0bf65cc28c6bfe11a534f561a4d3baf397c1dbe8c3891cd496a7dd51',
    ]),
    maxBodyBytes: 12_582_912,
);
