# Photos

A vehicle's photos (#16): the first files the app takes from a browser. The module knows a vehicle by its numeric id alone and requires only `staff`, for the signed-in member's role. `inventory` requires it and owns the routes and the vehicle's page; see its README for the three routes.

## Data

`CreateVehiclePhotosTable` makes `vehicle_photos`: an identity `id`, `vehicle_id` (a foreign key to `vehicles.id` that cascades on delete, indexed), `media_type`, `bytes`, `sha256` (of the file's bytes, in hex) and `created_at`. Its version sorts after inventory's `vehicles`, which the foreign key names. `VehiclePhotoTable::type()` is its entity type; `vehicle_id` is an `Integer` field rather than a `Reference`, since a reference would name inventory's entity type and photos cannot require inventory, which requires photos. `VehiclePhotoRepository` reads a vehicle's photos in the order they were added, counts them, adds one and deletes one.

A `VehiclePhoto` is its row's id, its position among the vehicle's photos from 1, its media type and its hash. It is addressed by its vehicle's page and its position (`{vehicle page}/photos/{position}`), so gate:23's two passes, whose rows get different ids, ask for the same paths. Its image's source adds `?v=` and the start of its hash: a browser keeps the images a document has shown by their URL whatever their cache headers say, so without it a delete that moves another photo to a position would show the deleted photo there again.

## Files

`PhotoFiles` keeps each file at the project's `var/photos/{vehicle id}/{photo id}`, outside `public/`, named from the row and never from what the client sent; it finds `var/` from its own `__DIR__`. It moves an upload there with `UploadedFileInterface::moveTo()`, removes one file, and forgets a deleted vehicle: every file in its directory, then the directory.

## The service

`VehiclePhotos`, exported:

- `add()` takes the request's `photo` field. It refuses no file, a vehicle with 12 photos already (`MOST`), and whatever AIC's `UploadCheck` refuses: a file that is empty, over 8,000,000 bytes (`BYTES`), or whose first bytes are not a JPEG, PNG or WebP. The client's file name and media type play no part. It returns the refusal for a person, or an empty string once the row is written, the file moved and its hash recorded; the hash is read from the moved file with `hash_file()`, never from the upload held in memory. The photos are counted again after the row is written, and a row that takes the vehicle past 12 is deleted, so uploads sent together never leave 13; a move or a hash that fails deletes the file and the row.
- `remove()` deletes one photo's row and file; `forget()` removes a deleted vehicle's files, whose rows went with it.
- `download()` gives AIC's `FileDownload` for the photo at a position, inline, with its stored media type and the request, so AIC answers its ETag, conditions and range; no such photo throws `RouteNotFoundException`, which AIC answers 404.
- `mayChange()` is true for a sales manager or an admin.

The request body's cap is `AppConfig::$maxBodyBytes`, 12 MiB in `config/app.php`: above one photo's limit, so an oversized photo reaches `UploadCheck` and is refused with its reason, while a body over the cap answers 413 from AIC before the session.

## The gallery

`PhotoGalleries`, exported, builds the theme's `PhotoGallery` for a vehicle's page: the photos, the first as the main one, larger and captioned, each `alt` naming the vehicle and the photo's number (`2023 Probe Hatch SE, photo 1 of 2`). For a sales manager or an admin it adds, from `PhotoForms` in `Form/`, a delete form under each photo, its envelope sealing that photo's position and id (`StaffSeals`), and the multipart upload form, with the refusal as its error alert when an upload was refused. A salesperson gets the photos alone.
