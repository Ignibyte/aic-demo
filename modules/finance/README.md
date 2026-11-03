# Finance

A car's payment calculator (#26), shown beside the price on storefront's car page (`/cars/{slug}`) and on inventory's vehicle page (`/app/inventory/{vin}`). Everything a buyer or a member of staff changes in it is worked out in the browser from Tessera's local signals: the calculator makes no request.

## What it holds

- `FinanceCalculators` builds the theme's `FinanceCalculator` from a price in whole dollars and an APR in basis points (690 is 6.90%). It sets the signals' defaults: the price, a down payment of 10% of the price rounded down to $100, a 60-month term, no trade-in and the APR. It writes the first figures at those defaults, so the page shows them before Tessera starts, and the example payments over 36, 48 and 60 months, which show in their place with JavaScript off.
- `Payments` is the server's arithmetic: the monthly payment of an amortised loan, `financed * rate * growth / (growth - 1)`, with `growth` the monthly rate compounded over the term. The compound is a loop of multiplications rather than `pow()`, so PHP and the compiled site agree. It also writes an amount in cents as `$1,234.56` and a rate in basis points as `6.90`. Its `STANDARD_APR`, 6.90%, is the APR settings' profile starts at.
- `FinanceRates` is a plugin type, `apr(EntityStore): int`, through which the module that keeps the dealership's profile gives its default APR. Settings registers the same class that gives staff the dealership's name (`DealershipNameRepository`), and that class keeps the profile it read for the request, so a staff page that shows the calculator and names the dealership in its footer reads the profile once.
- `DealershipRates` asks the first `FinanceRates` plugin, or answers the standard 6.90% (`Payments::STANDARD_APR`) when none is installed. Inventory's vehicle page takes it. Storefront reads the profile for its header and footer anyway, so it hands that profile's APR to `FinanceCalculators` itself.

The module requires nothing. Storefront, inventory and settings require it.

## In the browser

The template is the theme's (`themes/lotline/components/FinanceCalculator/`). Its root declares the signals (`data-signals`, whole numbers only, the APR divided by 100 in the expression) and the computed ones (`data-computed`: `financed`, `rate`, `months`, `growth`, `payment`, `interest`). The fields bind to them (`data-bind`). The down payment's range and number field share one signal, so `data-bind` alone keeps them in step and no `data-effect` is needed. The range's `max` follows the price (`data-attr`). The figures show through `data-text` and the theme's one script, `helpers.js`, which starts Tessera with a `money` helper. A note shows when the down payment and trade-in cover the price (`data-show`). The payment turns to a warning, with a note, above 3% of the price a month (`data-class`, `data-show`). The calculator is `hidden` until Tessera removes the attribute (`data-attr:hidden`), and the example table is hidden once Tessera runs (`data-show="false"`). With JavaScript off, only the table shows.
