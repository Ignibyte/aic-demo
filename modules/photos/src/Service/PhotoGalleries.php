<?php

declare(strict_types=1);

namespace App\Photos\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Form\PhotoForms;
use App\Staff\Form\StaffSeals;
use App\Theme\Components\PhotoGallery\GalleryPhoto;
use App\Theme\Components\PhotoGallery\PhotoGallery;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function count;

/**
 * A vehicle's photos as the theme's `PhotoGallery`, for its page: every photo, the first as the main one, each with
 * an `alt` that names the vehicle; and, for a sales manager or an admin, `PhotoForms`' delete form under each photo and
 * its upload form after them. A salesperson gets the photos alone.
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
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private VehiclePhotos $vehiclePhotos,
        StaffSeals $staffSeals,
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
        $mayChange = $this->vehiclePhotos->mayChange($serverRequest);
        $formToken = $mayChange
            ? $this->formTokens->issue($serverRequest)->value()
            : '';
        $vehiclePhotos = $this->vehiclePhotos->photos($vehicleId);
        $photos = [];

        foreach ($vehiclePhotos as $vehiclePhoto) {
            $alt = $vehicleTitle . ', photo ' . $vehiclePhoto->position . ' of ' . count($vehiclePhotos);
            $photos[] = $this->galleryPhoto($vehiclePhoto, $vehiclePath, $alt, $formToken);
        }

        $controls = $mayChange
            ? [new KeyedComponent('upload', $this->photoForms->upload($vehiclePath, $refusal, $formToken))]
            : [];

        return new PhotoGallery('vehicle-photos', 'Photos', 'No photos of this vehicle yet.', $photos, $controls);
    }

    /**
     * @param VehiclePhoto $vehiclePhoto A photo.
     * @param string       $vehiclePath  Its vehicle's page.
     * @param string       $alt          What it shows.
     * @param string       $formToken    The visitor's CSRF token; empty for one who may not change the photos.
     *
     * @throws LogicException When the photo cannot be sealed.
     *
     * @return GalleryPhoto The photo in the gallery, its delete form under it when there is a token.
     */
    private function galleryPhoto(
        VehiclePhoto $vehiclePhoto,
        string $vehiclePath,
        string $alt,
        string $formToken,
    ): GalleryPhoto {
        $actions = [];

        if ($formToken !== '') {
            $deleteForm = $this->photoForms->delete($vehiclePhoto, $vehiclePath, $formToken);
            $actions[] = new KeyedComponent('delete-' . $vehiclePhoto->position, $deleteForm);
        }

        $src = $vehiclePhoto->source($vehiclePath);

        return new GalleryPhoto($this->photoForms->photoId($vehiclePhoto), $src, $alt, $vehiclePhoto->position === 1, $actions);
    }
}
