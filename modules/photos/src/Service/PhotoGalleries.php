<?php

declare(strict_types=1);

namespace App\Photos\Service;

use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Form\PhotoForms;
use App\Staff\Form\StaffSeals;
use App\Theme\Components\DownloadLink\DownloadLink;
use App\Theme\Components\PhotoGallery\GalleryPhoto;
use App\Theme\Components\PhotoGallery\PhotoGallery;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function count;
use function strrpos;
use function substr;

/**
 * A vehicle's photos as the theme's `PhotoGallery`, for its page: every photo, the first as the main one, each with
 * an `alt` that names the vehicle; and, for a sales manager or an admin, `PhotoForms`' delete form under each photo and
 * its upload form after them. A salesperson gets the photos alone. Once the vehicle's photo bundle (#36) is written,
 * every member of staff gets its download link before the forms. Its strip (#31) is named by the vehicle's VIN, the
 * number of photos and the newest photo's bytes, so Tessera keeps it across a redraw with the same photos.
 */
final readonly class PhotoGalleries
{
    /**
     * The gallery's forms.
     */
    private PhotoForms $photoForms;

    /**
     * @param VehiclePhotos $vehiclePhotos The vehicle's photos, and who may change them.
     * @param StaffSeals    $staffSeals    Seals the photo a delete form is for.
     * @param PhotoBundles  $photoBundles  Whether the vehicle's photos are bundled as a ZIP.
     * @param FormTokens    $formTokens    Issues the visitor's guard fields.
     */
    public function __construct(
        private VehiclePhotos $vehiclePhotos,
        StaffSeals $staffSeals,
        private PhotoBundles $photoBundles,
        private FormTokens $formTokens = new FormTokens(),
    ) {
        $this->photoForms = new PhotoForms($staffSeals);
    }

    /**
     * @param ServerRequestInterface $serverRequest The vehicle page's request, with its session.
     * @param int                    $vehicleId     The vehicle's id.
     * @param string                 $vehiclePath   Its page, `/app/inventory/{VIN}`.
     * @param string                 $vehicleTitle  What staff call it, for the photos' `alt`.
     * @param string                 $refusal       Why an upload was refused; empty when none was.
     *
     * @throws LogicException   When the request has no session, or an envelope or a key cannot be made.
     * @throws RuntimeException When the photos cannot be read, or the signed-in member's kept role is no role.
     *
     * @return PhotoGallery The gallery.
     */
    public function gallery(
        ServerRequestInterface $serverRequest,
        int $vehicleId,
        string $vehiclePath,
        string $vehicleTitle,
        string $refusal,
    ): PhotoGallery {
        $guardFields = $this->vehiclePhotos->mayChange($serverRequest)
            ? $this->formTokens->guard($serverRequest)
            : null;
        $vehiclePhotos = $this->vehiclePhotos->photos($vehicleId);
        $photos = [];

        foreach ($vehiclePhotos as $vehiclePhoto) {
            $alt = $vehicleTitle . ', photo ' . $vehiclePhoto->position . ' of ' . count($vehiclePhotos);
            $photos[] = $this->galleryPhoto($vehiclePhoto, $vehiclePath, $alt, $guardFields);
        }

        $controls = $this->controls($vehicleId, $vehiclePath, $refusal, $guardFields);
        $strip = $this->strip($vehiclePath, $vehiclePhotos);

        return new PhotoGallery('vehicle-photos', 'Photos', 'No photos of this vehicle yet.', $photos, $controls, $strip);
    }

    /**
     * @param int          $vehicleId   The vehicle's id.
     * @param string       $vehiclePath Its page, `/app/inventory/{VIN}`.
     * @param string       $refusal     Why an upload was refused; empty when none was.
     * @param ?GuardFields $guardFields The visitor's CSRF token and the honeypot; null for one who may not change the
     *                                  photos.
     *
     * @throws LogicException When a key does not fit the theme, or the bundle's key is not a storage key.
     *
     * @return list<KeyedComponent> The ZIP's link once the vehicle's bundle is written (#36), for every member of
     *                              staff; then the upload form, for one who may change the photos.
     */
    private function controls(int $vehicleId, string $vehiclePath, string $refusal, ?GuardFields $guardFields): array
    {
        $controls = [];

        if ($this->photoBundles->exists($vehicleId)) {
            $downloadLink = new DownloadLink('vehicle-photo-bundle', 'Download all photos (ZIP)', $vehiclePath . '/photos.zip');
            $controls[] = new KeyedComponent('bundle', $downloadLink);
        }

        if ($guardFields instanceof GuardFields) {
            $keyedComponent = new KeyedComponent('upload', $this->photoForms->upload($vehiclePath, $refusal, $guardFields));
            $controls[] = $keyedComponent;
        }

        return $controls;
    }

    /**
     * @param string             $vehiclePath   A vehicle's page, `/app/inventory/{VIN}`.
     * @param list<VehiclePhoto> $vehiclePhotos Its photos.
     *
     * @return string Its strip's id, `vehicle-photos-<VIN>-<count>-<tag>`: the VIN is the page's last segment, and the
     *                tag the first 12 hex digits of the newest photo's SHA-256, `none` with no photo. Its bytes, not its
     *                id, so both of gate:23's passes write the same id.
     */
    private function strip(string $vehiclePath, array $vehiclePhotos): string
    {
        $newest = 0;
        $tag = 'none';

        foreach ($vehiclePhotos as $vehiclePhoto) {
            if ($vehiclePhoto->id <= $newest) {
                continue;
            }

            $newest = $vehiclePhoto->id;
            $tag = substr($vehiclePhoto->sha256, 0, 12);
        }

        $vin = substr($vehiclePath, (int) strrpos($vehiclePath, '/') + 1);

        return 'vehicle-photos-' . $vin . '-' . count($vehiclePhotos) . '-' . $tag;
    }

    /**
     * @param VehiclePhoto $vehiclePhoto A photo.
     * @param string       $vehiclePath  Its vehicle's page.
     * @param string       $alt          What it shows.
     * @param ?GuardFields $guardFields  The visitor's CSRF token and the honeypot; null for one who may not change the
     *                                   photos.
     *
     * @throws LogicException When the photo cannot be sealed.
     *
     * @return GalleryPhoto The photo in the gallery, its delete form under it when there are guard fields.
     */
    private function galleryPhoto(
        VehiclePhoto $vehiclePhoto,
        string $vehiclePath,
        string $alt,
        ?GuardFields $guardFields,
    ): GalleryPhoto {
        $actions = [];

        if ($guardFields instanceof GuardFields) {
            $deleteForm = $this->photoForms->delete($vehiclePhoto, $vehiclePath, $guardFields);
            $actions[] = new KeyedComponent('delete-' . $vehiclePhoto->position, $deleteForm);
        }

        $src = $vehiclePhoto->source();

        return new GalleryPhoto($this->photoForms->photoId($vehiclePhoto), $src, $alt, $vehiclePhoto->position === 1, $actions);
    }
}
