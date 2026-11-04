# Downloads

Files staff download from a vehicle's page, which the app keeps in AIC's private files and serves through its Stream kind (#36). It requires `staff`, `inventory` and `photos`. Its routes sit under the vehicle's page, as `panels`' do: inventory's `Controller/` folder is at its cap of 15, and `photos` knows a vehicle by its id alone, so it cannot read a VIN without requiring `inventory`, which requires it.

| Route | Class | Answer |
|---|---|---|
| `GET /app/inventory/{vin}/photos.zip` | `PhotoBundleStream` | The vehicle's photos as one ZIP, saved as `{VIN}-photos.zip`; 404 for no vehicle or no bundle yet. |

`PhotoBundleDownloads`, in `Service/`, reads the route's VIN through inventory's `VehicleRecords`, which checks it as a VIN before any query, checks that a member of staff is signed in (any role: the ZIP holds the photos the vehicle's page shows them), and answers photos' `PhotoBundles::download()` with the vehicle's VIN as stored. Everything about the answer is AIC's `FileDownload`: the strong ETag, `Last-Modified`, `Cache-Control: private, no-cache`, `Accept-Ranges`, a `Range` (206, 416), `If-Range`, and `If-None-Match` and the other conditions (304, 412). No code here reads a range or writes a header. A signed-out request goes to sign-in from staff's protected `/app` prefix before the route is reached.

The bundle is written by photos' `photo-bundle` job, which inventory's photo routes queue after a change, so the ZIP is there a moment after the worker runs, and the link to it shows on the vehicle's page once it is. See the photos README for the archive.

The brochure the ticket also asks for is not built yet: AIC has no way for a job to store a rendered document, so it waits on an AIC ticket (`tickets/0044`).
