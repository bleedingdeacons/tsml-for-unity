<?php

declare(strict_types=1);

namespace TsmlForUnity;

use TsmlForUnity\Contacts\TsmlContactFactory;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupRepository;
use TsmlForUnity\Groups\TsmlGroupViewFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository;
use TsmlForUnity\Locations\TsmlLocationFactory;
use TsmlForUnity\Locations\TsmlLocationRepository;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use TsmlForUnity\Meetings\TsmlMeetingRepository;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberRepository;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Positions\TsmlPositionFactory;
use TsmlForUnity\Positions\TsmlPositionRepository;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionViewFactory;
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
            && class_exists('Unity\\Core\\UnityServiceProvider')
            && class_exists('Unity\\Core\\UnityConfiguration');
    }

    /**
     * Check if Unity's group interfaces are available
     *
     * @return bool
     */
    public static function unityGroupsAvailable(): bool
    {
        return interface_exists('Unity\\Groups\\Interfaces\\GroupFactoryInterface')
            && interface_exists('Unity\\Groups\\Interfaces\\GroupInterface');
    }

    /**
     * Check if Unity's location interfaces are available
     *
     * @return bool
     */
    public static function unityLocationsAvailable(): bool
    {
        return interface_exists('Unity\\Locations\\Interfaces\\LocationFactoryInterface')
            && interface_exists('Unity\\Locations\\Interfaces\\LocationInterface');
    }

    /**
     * Check if Unity's contact interfaces are available
     *
     * @return bool
     */
    public static function unityContactsAvailable(): bool
    {
        return interface_exists('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
            && interface_exists('Unity\\Contact\\Interfaces\\ContactInterface');
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
            && interface_exists('Unity\\Members\\Interfaces\\MemberRepositoryInterface');
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
            && interface_exists('Unity\\Positions\\Interfaces\\PositionRepositoryInterface');
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
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingRepositoryInterface');
    }
    /**
     * Check if Unity's intergroup meeting interfaces are available
     *
     * @return bool
     */
    public static function unityMeetingsAvailable(): bool
    {
        return interface_exists('Unity\\Meetings\\Interfaces\\MeetingFactoryInterface')
            && interface_exists('Unity\\Meetings\\Interfaces\\MeetingInterface')
            && interface_exists('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface');
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
            && self::unityGroupsAvailable();
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

        // Register Contact Dependencies
        if (self::unityContactsAvailable()) {
            // Register Contact Factory
            $container->register(
                'Unity\\Contact\\Interfaces\\ContactFactoryInterface',
                function ($container) {
                    return new TsmlContactFactory();
                }
            );
        }

        // Register Location Dependencies
        if (self::unityLocationsAvailable()) {

            // Register Location Factory
            $container->register(
                'Unity\\Locations\\Interfaces\\LocationFactoryInterface',
                function ($container) {
                    return new TsmlLocationFactory();
                }
            );

            // Register Location Repository
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

        // Register Meeting Dependencies
        if (self::unityMeetingsAvailable()) {

            // Register Meeting Factory
            $container->register(
                'Unity\\Meetings\\Interfaces\\MeetingFactoryInterface',
                function ($container) {
                    $contactFactory = $container->has('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                        ? $container->get('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                        : null;
                    $locationRepository = $container->has('Unity\\Locations\\Interfaces\\LocationRepositoryInterface')
                        ? $container->get('Unity\\Locations\\Interfaces\\LocationRepositoryInterface')
                        : null;

                    return new TsmlMeetingFactory($contactFactory, $locationRepository);
                }
            );

            // Register Meeting Repository
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
        }

        // Register Group Dependencies
        if (self::unityGroupsAvailable()) {

            // Register Group Factory
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupFactoryInterface',
                function ($container) {
                    $contactFactory = $container->has('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                        ? $container->get('Unity\\Contact\\Interfaces\\ContactFactoryInterface')
                        : null;

                    $meetingRepository = $container->has('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')
                        ? $container->get('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')
                        : null;

                    return new TsmlGroupFactory($contactFactory, $meetingRepository);
                }
            );

            // Register Group Repository
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

        // Register Member Dependencies
        if (self::unityMembersAvailable()) {
            // Register member factory
            $container->register(
                'Unity\\Members\\Interfaces\\MemberFactoryInterface',
                function ($container) {
                    return new TsmlMemberFactory();
                }
            );

            // Register Member Repository
            $container->register(
                'Unity\\Members\\Interfaces\\MemberRepositoryInterface',
                function ($container) {
                    $memberFactory = $container->has('Unity\\Members\\Interfaces\\MemberFactoryInterface')
                        ? $container->get('Unity\\Members\\Interfaces\\MemberFactoryInterface')
                        : null;

                    return new TsmlMemberRepository($memberFactory);
                }
            );

            // Register MemberChangeTracker
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

        // Register Position Dependencies
        if (self::unityPositionsAvailable()) {

            // Register Position Factory
            $container->register(
                'Unity\\Positions\\Interfaces\\PositionFactoryInterface',
                function ($container) {
                    return new TsmlPositionFactory();
                }
            );

            // Register Position Repository
            $container->register(
                'Unity\\Positions\\Interfaces\\PositionRepositoryInterface',
                function ($container) {
                    $positionFactory = $container->has('Unity\\Positions\\Interfaces\\PositionFactoryInterface')
                        ? $container->get('Unity\\Positions\\Interfaces\\PositionFactoryInterface')
                        : null;

                    return new TsmlPositionRepository($positionFactory);
                }
            );

            // Register Position Change Tracker
            $container->register(
                PositionChangeTrackerInterface::class,
                function ($container) {
                    $positionRepository = $container->has('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
                        ? $container->get('Unity\\Positions\\Interfaces\\PositionRepositoryInterface')
                        : null;

                    return new TsmlPositionChangeTracker($positionRepository);
                }
            );

            // Register Position View Factory
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
            // Register Intergroup Meeting Factory
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

        // Register GroupViewFactory
        if (self::unityGroupViewsAvailable()) {
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupViewFactoryInterface',
                function ($container) {
                    $groupRepository = $container->has('Unity\\Groups\\Interfaces\\GroupRepositoryInterface')
                        ? $container->get('Unity\\Groups\\Interfaces\\GroupRepositoryInterface')
                        : null;

                    $meetingRepository = $container->has('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')
                        ? $container->get('Unity\\Meetings\\Interfaces\\MeetingRepositoryInterface')
                        : null;

                    return new TsmlGroupViewFactory($groupRepository, $meetingRepository);
                }
            );
        }
    }
}