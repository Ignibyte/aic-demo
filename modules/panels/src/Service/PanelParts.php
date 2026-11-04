<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePanel;
use App\Staff\Form\StaffSeals;
use App\Theme\Components\LazyPanel\LazyPanel;
use LogicException;
use RuntimeException;

/**
 * A vehicle panel's part (#31), for every plugin alike: under the panel's key and anchor, the theme's `LazyPanel`
 * either as a skeleton, `<key>-<VIN>-loading`, with no props, which Tessera loads from the panel's route, or arrived,
 * `<key>-<VIN>`, its version sealed in its props, polled from the same route, holding the panel's sources, each read
 * only when it renders. The route is the vehicle's page and the panel's key (`/app/inventory/{VIN}/price-history`).
 */
final readonly class PanelParts
{
    /**
     * @param VehiclePanel $vehiclePanel The panel.
     * @param EntityStore  $entityStore  The entity store.
     * @param StaffSeals   $staffSeals   Seals the arrived panel's version.
     * @param Vehicle      $vehicle      The page's vehicle.
     * @param bool         $lazy         Whether to give the skeleton.
     *
     * @throws RuntimeException When the version cannot be read.
     * @throws LogicException   When a key, an anchor or an entity type is malformed, or the props cannot be sealed.
     *
     * @return KeyedComponent The skeleton, which reads nothing, or the panel with its version read.
     */
    public function part(
        VehiclePanel $vehiclePanel,
        EntityStore $entityStore,
        StaffSeals $staffSeals,
        Vehicle $vehicle,
        bool $lazy,
    ): KeyedComponent {
        if ($lazy) {
            return $this->skeleton($vehiclePanel, $vehicle);
        }

        return $this->arrived($vehiclePanel, $entityStore, $staffSeals, $vehicle, $vehiclePanel->version($entityStore, $vehicle));
    }

    /**
     * @param VehiclePanel $vehiclePanel The panel.
     * @param Vehicle      $vehicle      The page's vehicle.
     *
     * @throws LogicException When the key or the anchor is malformed.
     *
     * @return KeyedComponent The skeleton: busy, loading from the panel's route, with no props.
     */
    public function skeleton(VehiclePanel $vehiclePanel, Vehicle $vehicle): KeyedComponent
    {
        $id = $vehiclePanel->key() . '-' . $vehicle->vin . '-loading';
        $lazyPanel = new LazyPanel($id, $vehiclePanel->heading(), $this->route($vehiclePanel, $vehicle), '', []);

        return new KeyedComponent($vehiclePanel->key(), $lazyPanel, '', $vehiclePanel->anchor());
    }

    /**
     * @param VehiclePanel $vehiclePanel The panel.
     * @param EntityStore  $entityStore  The entity store, handed to its sources.
     * @param StaffSeals   $staffSeals   Seals its version.
     * @param Vehicle      $vehicle      The page's vehicle.
     * @param string       $version      What its data is now.
     *
     * @throws LogicException When a key or the anchor is malformed, or the props cannot be sealed.
     *
     * @return KeyedComponent The panel: its version in its props, polled from its route, its sources inside.
     */
    public function arrived(
        VehiclePanel $vehiclePanel,
        EntityStore $entityStore,
        StaffSeals $staffSeals,
        Vehicle $vehicle,
        string $version,
    ): KeyedComponent {
        $body = [];

        foreach ($vehiclePanel->sources($entityStore, $vehicle) as $key => $componentSource) {
            $body[] = new KeyedComponent($key, new SourcedComponent($componentSource));
        }

        $id = $vehiclePanel->key() . '-' . $vehicle->vin;
        $lazyPanel = new LazyPanel($id, $vehiclePanel->heading(), '', $this->route($vehiclePanel, $vehicle), $body);

        return new KeyedComponent($vehiclePanel->key(), $lazyPanel, $staffSeals->version($version), $vehiclePanel->anchor());
    }

    /**
     * @param VehiclePanel $vehiclePanel The panel.
     * @param Vehicle      $vehicle      The page's vehicle.
     *
     * @return string The panel's route, `/app/inventory/{VIN}/<key>`.
     */
    private function route(VehiclePanel $vehiclePanel, Vehicle $vehicle): string
    {
        return $vehicle->path() . '/' . $vehiclePanel->key();
    }
}
