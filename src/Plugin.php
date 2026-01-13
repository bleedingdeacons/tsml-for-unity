<?php

declare(strict_types=1);

namespace TsmlForUnity;

/**
 * Main Plugin Class
 *
 * Handles Unity availability checks and factory registration/retrieval.
 */
class Plugin
{
    private static ?TsmlMeetingFactory $meetingFactory = null;
    private static ?TsmlGroupFactory $groupFactory = null;
    private static ?TsmlLocationFactory $locationFactory = null;

    /**
     * Check if Unity plugin is active and has required meeting interfaces
     *
     * @return bool
     */
    public static function unityIsAvailable(): bool
    {
        return interface_exists('Unity\\Meetings\\Interfaces\\MeetingFactoryInterface')
            && interface_exists('Unity\\Meetings\\Interfaces\\MeetingInterface')
            && class_exists('Unity\\Meetings\\Meeting')
            && class_exists('Unity\\Meetings\\Contact');
    }

    /**
     * Check if Unity's group interfaces are available
     *
     * @return bool
     */
    public static function unityGroupsAvailable(): bool
    {
        return interface_exists('Unity\\Groups\\Interfaces\\GroupFactoryInterface')
            && interface_exists('Unity\\Groups\\Interfaces\\GroupInterface')
            && class_exists('Unity\\Groups\\Group');
    }

    /**
     * Check if Unity's location interfaces are available
     *
     * @return bool
     */
    public static function unityLocationsAvailable(): bool
    {
        return interface_exists('Unity\\Locations\\Interfaces\\LocationFactoryInterface')
            && interface_exists('Unity\\Locations\\Interfaces\\LocationInterface')
            && class_exists('Unity\\Locations\\Location');
    }

    /**
     * Register the TSML factories with Unity's dependency container
     *
     * @param mixed $container Unity's dependency container
     * @return void
     */
    public static function registerWithUnity($container): void
    {
        if (!method_exists($container, 'register')) {
            return;
        }

        // Register meeting factory
        $container->register(
            'Unity\\Meetings\\Interfaces\\MeetingFactoryInterface',
            function ($container) {
                return new TsmlMeetingFactory();
            }
        );

        // Register group factory if Unity's group interfaces are available
        if (self::unityGroupsAvailable()) {
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupFactoryInterface',
                function ($container) {
                    return new TsmlGroupFactory();
                }
            );
        }

        // Register location factory if Unity's location interfaces are available
        if (self::unityLocationsAvailable()) {
            $container->register(
                'Unity\\Locations\\Interfaces\\LocationFactoryInterface',
                function ($container) {
                    return new TsmlLocationFactory();
                }
            );
        }
    }

    /**
     * Get the TsmlMeetingFactory instance
     *
     * @return TsmlMeetingFactory|null Returns null if Unity is not available
     */
    public static function getMeetingFactory(): ?TsmlMeetingFactory
    {
        if (!self::unityIsAvailable()) {
            return null;
        }

        if (self::$meetingFactory === null) {
            self::$meetingFactory = new TsmlMeetingFactory();
        }

        return self::$meetingFactory;
    }

    /**
     * Get the TsmlGroupFactory instance
     *
     * @return TsmlGroupFactory|null Returns null if Unity groups are not available
     */
    public static function getGroupFactory(): ?TsmlGroupFactory
    {
        if (!self::unityGroupsAvailable()) {
            return null;
        }

        if (self::$groupFactory === null) {
            self::$groupFactory = new TsmlGroupFactory();
        }

        return self::$groupFactory;
    }

    /**
     * Get the TsmlLocationFactory instance
     *
     * @return TsmlLocationFactory|null Returns null if Unity locations are not available
     */
    public static function getLocationFactory(): ?TsmlLocationFactory
    {
        if (!self::unityLocationsAvailable()) {
            return null;
        }

        if (self::$locationFactory === null) {
            self::$locationFactory = new TsmlLocationFactory();
        }

        return self::$locationFactory;
    }

    /**
     * Reset factory instances (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$meetingFactory = null;
        self::$groupFactory = null;
        self::$locationFactory = null;
    }
}
