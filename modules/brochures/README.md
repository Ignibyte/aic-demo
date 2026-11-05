# Brochures

Each car for sale's one-page brochure (#44), kept in AIC's private files at `brochures/{vehicle id}.html`. This module holds what two other modules share about it: its key, its queue and its download. It requires no module.

It is a module of its own because of who needs it. Inventory's forms change a vehicle and must queue the brochure's job. Storefront links the brochure from the car's page and serves it. Storefront requires inventory, so the shared piece cannot sit in storefront, and inventory's folders are each at their cap of 15 files. Both require this module instead.

`Brochures`, in `Service/`, exported:

- `JOB` is `car-brochure`, the job storefront declares and writes the brochure with; `queue()` dispatches it through AIC's `PostgresQueue` with the payload `vehicle`, the vehicle's id.
- `key()` is the brochure's storage key; `exists()` says whether it is written (false for an id below 1); `forget()` deletes it.
- `download()` answers AIC's `FileDownload` of the file from `PrivateFiles::download()`, `text/html; charset=utf-8`, under the name it is given (empty sends it inline), or throws `RouteNotFoundException`, which AIC answers 404, when it is not written. AIC's `FileDownload` sends the strong ETag and `Last-Modified` and answers ranges and conditions; nothing here reads them.

The brochure is written by storefront's `car-brochure` job through AIC's `PageFiles` (AIC #265), which AIC builds only for the console. `Brochures` is built for web pages too, so it does not take `PageFiles`; the job puts the page at `key()` itself. See storefront's README for what the brochure holds, its layout and its routes, and inventory's for the changes that queue it.
