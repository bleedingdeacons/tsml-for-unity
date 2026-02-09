<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Contact\ContactFactory;
use Unity\Contact\Interfaces\ContactFactoryInterface;
use Unity\Groups\Interfaces\GroupChangeTrackerInterface;
use Unity\Members\Interfaces\MemberChangeTrackerInterface;
use Unity\Positions\Interfaces\PositionChangeTrackerInterface;
use Unity\Positions\Interfaces\PositionViewFactoryInterface;

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
    private static ?TsmlIntergroupMeetingFactory $intergroupMeetingFactory = null;
    private static ?ContactFactoryInterface $contactFactory = null;

    /**
     * Check if Unity plugin is active and has required meeting interfaces
     *
     * @return bool
     */
    public static function unityIsAvailable(): bool
    {
        return class_exists('Unity\\Core\\DependencyContainer')
            && class_exists('Unity\\Core\\UnityServiceProvider');
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
     * Check if Unity's intergroup meeting interfaces are available
     *
     * @return bool
     */
    public static function unityIntergroupMeetingsAvailable(): bool
    {
        return interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactoryInterface')
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingInterface')
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingRepositoryInterface')
            && class_exists('Unity\\IntergroupMeetings\\IntergroupMeeting');
    }

    /**
     * Check if Unity's position view interfaces are available
     *
     * @return bool
     */
    public static function unityPositionViewsAvailable(): bool
    {
        return interface_exists('Unity\\Positions\\Interfaces\\PositionViewFactoryInterface')
            && interface_exists('Unity\\Positions\\Interfaces\\PositionViewInterface')
            && self::unityPositionsAvailable()
            && self::unityMembersAvailable();
    }

    /**
     * Check if Unity's group view interfaces are available
     *
     * @return bool
     */
    public static function unityGroupViewsAvailable(): bool
    {
        return interface_exists('Unity\\Groups\\Interfaces\\GroupViewFactoryInterface')
            && interface_exists('Unity\\Groups\\Interfaces\\GroupViewInterface')
            && class_exists('Unity\\Groups\\GroupView')
            && self::unityGroupsAvailable();
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

            // Register GroupChangeTracker (overrides Unity's stub)
            $container->register(
                GroupChangeTrackerInterface::class,
                function ($container) {
                    $groupRepository = $container->has('Unity\\Groups\\Interfaces\\GroupRepositoryInterface')
                        ? $container->get('Unity\\Groups\\Interfaces\\GroupRepositoryInterface')
                        : null;

                    return new TsmlGroupChangeTracker($groupRepository);
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

            // Register MemberChangeTracker (overrides Unity's stub)
            $container->register(
                MemberChangeTrackerInterface::class,
                function ($container) {
                    $memberRepository = $container->has('Unity\\Members\\Interfaces\\MemberRepositoryInterface')
                        ? $container->get('Unity\\Members\\Interfaces\\MemberRepositoryInterface')
                        : null;

                    return new TsmlMemberChangeTracker($memberRepository);
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

            // Register PositionChangeTracker (overrides Unity's stub)
            $container->register(
                PositionChangeTrackerInterface::class,
                function ($container) {
                    $positionRepository = $container->has('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
                        ? $container->get('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
                        : null;

                    return new TsmlPositionChangeTracker($positionRepository);
                }
            );

            // Register PositionViewFactory (overrides Unity's stub)
            if (self::unityPositionViewsAvailable()) {
                $container->register(
                    PositionViewFactoryInterface::class,
                    function ($container) {
                        $positionRepository = $container->has('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
                            ? $container->get('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
                            : null;

                        $memberRepository = $container->has('Unity\\Members\\Interfaces\\MemberRepositoryInterface')
                            ? $container->get('Unity\\Members\\Interfaces\\MemberRepositoryInterface')
                            : null;

                        return new TsmlPositionViewFactory($positionRepository, $memberRepository);
                    }
                );
            }
        }

        // Register intergroup meeting factory and repository if Unity's intergroup meeting interfaces are available
        if (self::unityIntergroupMeetingsAvailable()) {
            // Register intergroup meeting factory
            $container->register(
                'Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactoryInterface',
                function ($container) {
                    return new TsmlIntergroupMeetingFactory();
                }
            );

            // Register intergroup meeting repository
            $container->register(
                'Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingRepositoryInterface',
                function ($container) {
                    $intergroupMeetingFactory = $container->has('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactoryInterface')
                        ? $container->get('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactoryInterface')
                        : null;

                    return new TsmlIntergroupMeetingRepository($intergroupMeetingFactory);
                }
            );
        }

        // Note: GroupViewFactory is not overridden here.
        // Unity's built-in factory will be used, which will work with the TSML
        // implementations of GroupRepository and MeetingRepository registered above.
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
     * Get the TsmlIntergroupMeetingFactory instance
     *
     * @return TsmlIntergroupMeetingFactory|null Returns null if Unity intergroup meetings are not available
     */
    public static function getIntergroupMeetingFactory(): ?TsmlIntergroupMeetingFactory
    {
        if (!self::unityIntergroupMeetingsAvailable()) {
            return null;
        }

        if (self::$intergroupMeetingFactory === null) {
            self::$intergroupMeetingFactory = new TsmlIntergroupMeetingFactory();
        }

        return self::$intergroupMeetingFactory;
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
        self::$intergroupMeetingFactory = null;
        self::$contactFactory = null;
    }
}