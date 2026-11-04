<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Entity\Application\EntityStore;
use App\Staff\Form\StaffSeals;
use LogicException;
use RuntimeException;

/**
 * The plugin type through which a vehicle's page places a panel it does not build (#31): the panels module registers
 * one per panel (its price history, the vehicles like it, its leads and holds), and the page asks each for its part
 * after the sale, as a skeleton that Tessera loads from the panel's own route or as the panel inline. The generated
 * provider builds each plugin with no arguments, so it reads through the entity store and seals through staff's seals
 * each call hands it. Exported, so the panels module implements it.
 */
interface VehiclePanel
{
    /**
     * @return string The panel's key in the vehicle's panel and the last segment of its route (`price-history`).
     */
    public function key(): string;

    /**
     * @return string The anchor its part carries, unique on the page (`vehicle-price-history`).
     */
    public function anchor(): string;

    /**
     * @return string Its heading (`Price history`).
     */
    public function heading(): string;

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @throws RuntimeException When what the version needs cannot be read.
     * @throws LogicException   When an entity type is malformed.
     *
     * @return string What the panel's data is now, read on its own, before the panel is built.
     */
    public function version(EntityStore $entityStore, Vehicle $vehicle): string;

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @return array<string, ComponentSource> What the panel shows, by key, each read only when it renders.
     */
    public function sources(EntityStore $entityStore, Vehicle $vehicle): array;

    /**
     * @param EntityStore $entityStore The entity store.
     * @param StaffSeals  $staffSeals  Seals the arrived panel's version in its props.
     * @param Vehicle     $vehicle     The page's vehicle.
     * @param bool        $lazy        Whether to give the skeleton, which reads nothing, rather than the panel.
     *
     * @throws RuntimeException When the version cannot be read.
     * @throws LogicException   When a key, an anchor or an entity type is malformed, or the props cannot be sealed.
     *
     * @return KeyedComponent The part under its key and anchor.
     */
    public function part(
        EntityStore $entityStore,
        StaffSeals $staffSeals,
        Vehicle $vehicle,
        bool $lazy,
    ): KeyedComponent;
}
