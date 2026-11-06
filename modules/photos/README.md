# Photos

A vehicle's photos (#16): the first files the app takes from a browser. The module knows a vehicle by its numeric id alone and requires only `staff`, for the signed-in member's role. `inventory` requires it and owns the routes and the vehicle's page; see its README for the three routes.

## Data

`CreateVehiclePhotosTable` makes `vehicle_photos`: an identity `id`, `vehicle_id` (a foreign key to `vehicles.id` that cascades on delete, indexed), `media_type`, `bytes`, `sha256` (of the file's bytes, in hex) and `created_at`. Its version sorts after inventory's `vehicles`, which the foreign key names: `bin/aic migrate` applies every module's migrations in version order (AIC #228), and gate:26 holds it. `VehiclePhotoTable::type()` is its entity type; `vehicle_id` is an `Integer` field rather than a `Reference`, since a reference would name inventory's entity type and photos cannot require inventory, which requires photos. `VehiclePhotoRepository` reads a vehicle's photos in the order they were added, counts them, adds one and deletes one; `photosOf()` reads several vehicles' photos in one statement (#25), for a page that shows many.

A `VehiclePhoto` is its row's id, its position among the vehicle's photos from 1, its media type and its hash. Its Streams address it by its vehicle's page and its position (`{vehicle page}/photos/{position}`), so gate:23's two passes, whose rows get different ids, ask for the same paths. Its file's key is named by its bytes, `photos/{sha256}.{extension}` (`key()`), and its image's source is that key's URL in AIC's public files, `/files/photos/{sha256}.{extension}` (`source()`): both passes name the same URLs, and since a browser keeps the images a document has shown by their URL whatever their cache headers say, new bytes get a new URL, so a delete that moves another photo to a position never shows the deleted one there.

## Files

The files are kept in AIC's public files (#23), under `var/storage/public/`, which the framework serves at `/files/{key}` before any session: `public, no-cache` with a strong ETag, inline for an image. A key is named by the photo's bytes, never by what the client sent, so two rows with the same bytes share one file, and a file goes only when no row holds its hash any more (`VehiclePhotoRepository::holding()`).

`bin/aic photos:move-files` (`PhotosMoveFiles`) moves the files kept before #23 at the project's `var/photos/{vehicle id}/{photo id}` into the public files under each row's key (hashing a file whose row has no hash yet), removes each emptied folder, `var/photos/` last, and says how many it moved; run again it moves none. It finds the old folder from its own `__DIR__`, as the removed `PhotoFiles` did.

## The service

`VehiclePhotos`, exported:

- `add()` takes the request's `photo` field. It refuses no file, a vehicle with 12 photos already (`MOST`), and whatever AIC's `UploadCheck` refuses: a file that is empty, over 8,000,000 bytes (`BYTES`), or whose first bytes are not a JPEG, PNG or WebP. The client's file name and media type play no part. It returns the refusal for a person, or an empty string once the row and the file are written. The upload is read once, and the row is written with the hash of those bytes, which name the file. The photos are counted again after the row is written, and a row that takes the vehicle past 12 is deleted, so uploads sent together never leave 13; a file that cannot be written deletes the row.
- `remove()` deletes one photo's row, and its file when no other row holds the same bytes; `forget()` takes a deleted vehicle's photos, read before the delete, whose rows went with it, and removes each file no row holds.
- `download()` gives AIC's `FileDownload` for the file of the photo at a position, from the public files, inline, with its stored media type and the request, so AIC answers its ETag, conditions and range; no such photo throws `RouteNotFoundException`, which AIC answers 404.
- `mayChange()` is true for a sales manager or an admin.

The request body's cap is `AppConfig::$maxBodyBytes`, 12 MiB in `config/app.php`: above one photo's limit, so an oversized photo reaches `UploadCheck` and is refused with its reason, while a body over the cap answers 413 from AIC before the session.

## The bundle

A vehicle's photos as one ZIP for staff (#36). `PhotoBundles`, exported, keeps it in AIC's private files at `bundles/{vehicle id}.zip`, out of any URL:

- `queue()` dispatches the `photo-bundle` job (`WritePhotoBundle`) with the vehicle's id, through AIC's `PostgresQueue`; inventory's photo routes call it after a kept upload and a done delete.
- `write()`, which the job calls, reads the vehicle's photos as they are when it runs and puts the whole ZIP through `PrivateFiles::put()`, a write to a temporary file renamed over the old one, so a download never reads half a bundle; with no photos left it deletes the bundle. A photo file it cannot read throws, and the queue retries. A payload that names no vehicle fails at once.
- `exists()` says whether the bundle is written; `download()` answers `PrivateFiles::download()` as `application/zip`, saved as `{VIN}-photos.zip`, so AIC's `FileDownload` sends the strong ETag and `Last-Modified` and answers ranges (206, 416), `If-Range` and conditions (304, 412). No bundle throws `RouteNotFoundException`, which AIC answers 404. `downloads` routes it.

`StoredZip` writes the archive by hand, since elephc compiles no `ZipArchive`: per photo a local file header and its bytes, then the central directory and its end record (APPNOTE 6.3.x). Each record is one `pack()` call, its fields in the APPNOTE's order and every integer little-endian through the `v` and `V` codes (AIC #266 brings `pack()` to the compiled site), and each CRC-32 comes from `crc32()`. The entries are stored, not compressed: JPEG, PNG and WebP are compressed already. Each is a `ZipEntry` named `photo-{position}.{extension}`, in position order, and dated 1980-01-01 00:00, so the same photos give the same bytes, on both of gate:23's passes too. Twelve photos of at most 8,000,000 bytes stay far below zip64's limits.

## The gallery

`PhotoGalleries`, exported, builds the theme's `PhotoGallery` for a vehicle's page: the photos, the first as the main one, larger and captioned, each `alt` naming the vehicle and the photo's number (`2023 Probe Hatch SE, photo 1 of 2`). For a sales manager or an admin it adds, from `PhotoForms` in `Form/`, a delete form under each photo, its envelope sealing that photo's position and id (`StaffSeals`), and the multipart upload form, with the refusal as its error alert when an upload was refused. A salesperson gets the photos alone. Every member of staff gets, once the bundle is written, the theme's `DownloadLink` "Download all photos (ZIP)" (`#vehicle-photo-bundle`, keyed `bundle`) to `{vehicle page}/photos.zip`, before the forms.
