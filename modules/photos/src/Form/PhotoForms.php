<?php

declare(strict_types=1);

namespace App\Photos\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormEncoding;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Paragraph;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Service\VehiclePhotos;
use App\Staff\Form\StaffSeals;
use LogicException;

/**
 * The forms a vehicle's gallery shows a sales manager or an admin: the multipart upload form, with the refusal first
 * when an upload was refused, and the delete form under each photo, its envelope sealing that photo.
 */
final readonly class PhotoForms
{
    /**
     * @param StaffSeals $staffSeals Seals the photo a delete form is for.
     */
    public function __construct(
        private StaffSeals $staffSeals,
    ) {
    }

    /**
     * @param string $vehiclePath The vehicle's page.
     * @param string $refusal     Why the last upload was refused; empty when none was.
     * @param string $formToken   The visitor's CSRF token.
     *
     * @return Form The multipart form that posts one photo to `{vehicle page}/photos`.
     */
    public function upload(string $vehiclePath, string $refusal, string $formToken): Form
    {
        $fileInput = new FileInput('vehicle-photo-file', VehiclePhotos::FIELD, 'image/jpeg,image/png,image/webp');
        $formField = new FormField('Add a photo', 'vehicle-photo-file', $fileInput);
        $hint = 'JPEG, PNG or WebP, up to 8 MB each, and up to ' . VehiclePhotos::MOST . ' photos.';
        $paragraph = new Paragraph($hint, 'vehicle-photo-hint');
        $button = new Button('Add photo', 'submit', 'vehicle-photo-add');
        $alerts = $refusal === ''
            ? []
            : [new Alert(AlertSeverity::Error, $refusal, 'vehicle-photo-error')];
        $fields = [...$alerts, $formField, $paragraph, $button];

        return new Form('vehicle-photo-form', $vehiclePath . '/photos', $fields, $formToken, FormEncoding::Multipart);
    }

    /**
     * @param VehiclePhoto $vehiclePhoto A photo.
     * @param string       $vehiclePath  Its vehicle's page.
     * @param string       $formToken    The visitor's CSRF token.
     *
     * @throws LogicException When the photo cannot be sealed.
     *
     * @return Form The form that deletes it, posting to `{photo}/delete` with the photo sealed.
     */
    public function delete(VehiclePhoto $vehiclePhoto, string $vehiclePath, string $formToken): Form
    {
        $id = $this->photoId($vehiclePhoto);
        $fields = [
            $this->staffSeals->field($vehiclePhoto->sealed($vehiclePath)),
            new Button('Delete photo ' . $vehiclePhoto->position, 'submit', $id . '-delete'),
        ];

        return new Form($id . '-delete-form', $vehiclePhoto->path($vehiclePath) . '/delete', $fields, $formToken);
    }

    /**
     * @param VehiclePhoto $vehiclePhoto A photo.
     *
     * @return string Its image's id, `vehicle-photo-{position}`, the stem of its delete form's.
     */
    public function photoId(VehiclePhoto $vehiclePhoto): string
    {
        return 'vehicle-photo-' . $vehiclePhoto->position;
    }
}
