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
 * 0.4.4 (#43): a press drops a pending hover timer, so a click after a hover
 * sends one request (Tessera #43); a keyboard focus prefetches as a hover
 * does; a `data-on:dragstart` drag starts in Firefox; and Back to a page whose
 * recorded target is gone loads the whole page (Tessera #39).
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
    tessera: new TesseraRelease('0.4.4', [
        'tessera.csp.iife.js' => 'e2bdd4b39638468f498cd97d4a1865f250960e9dd5be3c339ceb0c40db7560c5',
        'tessera.csp.iife.js.map' => '6ee44d291c9007b18aadd05155b2f21f8df0340be9e42a9460f972cc036afb72',
    ]),
    maxBodyBytes: 12_582_912,
);
