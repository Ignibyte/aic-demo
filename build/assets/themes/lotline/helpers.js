// Lotline's one script (#26, Chad's choice (a)): it starts Tessera with the helpers its expressions call. The
// layouts set `tessera-start` to `manual`, so Tessera's IIFE waits for this call, and load this file after it with
// `defer`, so `Tessera` is defined when it runs. `money` writes an amount in US dollars with cents, which the csp
// expression engine cannot do itself; the payment calculator shows its figures with it. No app logic lives here.
(function startTessera() {
    'use strict';

    const dollars = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

    window.Tessera.start({
        helpers: {
            money: (amount) => (Number.isFinite(amount) ? dollars.format(amount) : '—'),
        },
    });
}());
