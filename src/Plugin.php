<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Contact\ContactFactory;
use Unity\Contact\Interfaces\ContactFactoryInterface;

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
    private static ?TsmlMemberFactory $memberFactory = null;
    private static ?ContactFactoryInterface $contactFactory = null;

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
            && interface_exists('Unity\\Contact\\Interfaces\\ContactInterface');
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
     * Check if Unity's contact interfaces are available
     *
     * @return bool
     */
    public static function unityContactsAvailable(): bool
    {
        return interface_exists('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
            && interface_exists('Unity\\Contact\\Interfaces\\ContactInterface')
            && class_exists('Unity\\Contact\\Contact');
    }

    /**
     * Check if Unity's member interfaces are available
     *
     * @return bool
     */
    public static function unityMembersAvailable(): bool
    {
        return interface_exists('Unity\\Members\\Interfaces\\MemberFactoryInterface')
            && interface_exists('Unity\\Members\\Interfaces\\MemberInterface')
            && interface_exists('Unity\\Members\\Interfaces\\MemberRepositoryInterface')
            && class_exists('Unity\\Members\\Member');
    }

    /**
     * Check if Unity's position interfaces are available
     *
     * @return bool
     */
    public static function unityPositionsAvailable(): bool
    {
        return interface_exists('Unity\\Positions\\Interfaces\\PositionFactoryInterface')
            && interface_exists('Unity\\Positions\\Interfaces\\PositionInterface')
            && interface_exists('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
            && class_exists('Unity\\Positions\\Position');
    }

    /**
     * Get or create the ContactFactory instance
     *
     * @return ContactFactoryInterface
     */
    private static function getContactFactory(): ContactFactoryInterface
    {
        if (self::$contactFactory === null) {
            self::$contactFactory = new ContactFactory();
        }
        return self::$contactFactory;
    }

    /**
     * Register the TSML factories with Unity's dependency container
     *
     * @param mixed $container Unity's dependency container
     * @return void
     */
    public static function registerWithUnity($container): void
    {
        if (!method_exists($container, 'register') || !method_exists($container, 'get')) {
            return;
        }

        // Register meeting factory
        $container->register(
            'Unity\\Meetings\\Interfaces\\MeetingFactoryInterface',
            function ($container) {
                $contactFactory = $container->has('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                    ? $container->get('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                    : new ContactFactory();

                $locationRepository = $container->has('Unity\\Locations\\Interfaces\\LocationRepositoryInterface')
                    ? $container->get('Unity\\Locations\\Interfaces\\LocationRepositoryInterface')
                    : null;

                return new TsmlMeetingFactory($contactFactory, $locationRepository);
            }
        );

        // Register meeting repository
        $container->register(
            'Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface',
            function ($container) {
                $meetingFactory = $container->has('Unity\\Meetings\\Interfaces\\MeetingFactoryInterface')
                    ? $container->get('Unity\\Meetings\\Interfaces\\MeetingFactoryInterface')
                    : null;

                $cache = $container->has('Unity\\Common\\Interfaces\\CacheInterface')
                    ? $container->get('Unity\\Common\\Interfaces\\CacheInterface')
                    : null;

                return new TsmlMeetingRepository($meetingFactory, $cache);
            }
        );

        // Register group factory if Unity's group interfaces are available
        if (self::unityGroupsAvailable()) {
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupFactoryInterface',
                function ($container) {
                    $contactFactory = $container->has('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                        ? $container->get('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                        : new ContactFactory();

                    $meetingRepository = $container->has('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')
                        ? $container->get('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')
                        : null;

                    return new TsmlGroupFactory($contactFactory, $meetingRepository);
                }
            );

            // Register group repository
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupRepositoryInterface',
                function ($container) {
                    $groupFactory = $container->has('Unity\\Groups\\Interfaces\\GroupFactoryInterface')
                        ? $container->get('Unity\\Groups\\Interfaces\\GroupFactoryInterface')
                        : null;

                    return new TsmlGroupRepository($groupFactory);
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

            // Register location repository
            $container->register(
                'Unity\\Locations\\Interfaces\\LocationRepositoryInterface',
                function ($container) {
                    $locationFactory = $container->has('Unity\\Locations\\Interfaces\\LocationFactoryInterface')
                        ? $container->get('Unity\\Locations\\Interfaces\\LocationFactoryInterface')
                        : null;

                    return new TsmlLocationRepository($locationFactory);
                }
            );
        }

        // Register member repository if Unity's member interfaces are available
        if (self::unityMembersAvailable()) {
            // Register member factory
            $container->register(
                'Unity\\Members\\Interfaces\\MemberFactoryInterface',
                function ($container) {
                    return new TsmlMemberFactory();
                }
            );

            // Register member repository
            $container->register(
                'Unity\\Members\\Interfaces\\MemberRepositoryInterface',
                function ($container) {
                    $memberFactory = $container->has('Unity\\Members\\Interfaces\\MemberFactoryInterface')
                        ? $container->get('Unity\\Members\\Interfaces\\MemberFactoryInterface')
                        : null;

                    return new TsmlMemberRepository($memberFactory);
                }
            );
        }

        // Register position factory and repository if Unity's position interfaces are available
        if (self::unityPositionsAvailable()) {
            // Register position factory
            $container->register(
                'Unity\\Positions\\Interfaces\\PositionFactoryInterface',
                function ($container) {
                    return new TsmlPositionFactory();
                }
            );

            // Register position repository
            $container->register(
                'Unity\\Positions\\Interfaces\\PositionRepositoryInterface',
                function ($container) {
                    $positionFactory = $container->has('Unity\\Positions\\Interfaces\\PositionFactoryInterface')
                        ? $container->get('Unity\\Positions\\Interfaces\\PositionFactoryInterface')
                        : null;

                    return new TsmlPositionRepository($positionFactory);
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
            // Try to get LocationRepository from Unity container if available
            $locationRepository = null;
            if (class_exists('\\Unity\\Plugin') && method_exists('\\Unity\\Plugin', 'getContainer')) {
                try {
                    $container = \Unity\Plugin::getContainer();
                    if ($container && $container->has('Unity\\Locations\\Interfaces\\LocationRepositoryInterface')) {
                        $locationRepository = $container->get('Unity\\Locations\\Interfaces\\LocationRepositoryInterface');
                    }
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }

            self::$meetingFactory = new TsmlMeetingFactory(self::getContactFactory(), $locationRepository);
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
            // Try to get MeetingRepository from Unity container if available
            $meetingRepository = null;
            if (class_exists('\\Unity\\Plugin') && method_exists('\\Unity\\Plugin', 'getContainer')) {
                try {
                    $container = \Unity\Plugin::getContainer();
                    if ($container && $container->has('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')) {
                        $meetingRepository = $container->get('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface');
                    }
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }

            self::$groupFactory = new TsmlGroupFactory(self::getContactFactory(), $meetingRepository);
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
     * Get the TsmlMemberFactory instance
     *
     * @return TsmlMemberFactory|null Returns null if Unity members are not available
     */
    public static function getMemberFactory(): ?TsmlMemberFactory
    {
        if (!self::unityMembersAvailable()) {
            return null;
        }

        if (self::$memberFactory === null) {
            self::$memberFactory = new TsmlMemberFactory();
        }

        return self::$memberFactory;
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
        self::$memberFactory = null;
        self::$contactFactory = null;
    }
}