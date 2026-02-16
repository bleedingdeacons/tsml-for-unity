<?php

declare(strict_types=1);

namespace TsmlForUnity;

use ReflectionClass;
use TsmlForUnity\Contacts\TsmlContactFactory;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Groups\TsmlGroupRepository;
use TsmlForUnity\Groups\TsmlGroupViewFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingAttendanceRepository;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository;
use TsmlForUnity\Locations\TsmlLocationFactory;
use TsmlForUnity\Locations\TsmlLocationRepository;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Meetings\TsmlMeetingRepository;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionFactory;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Positions\TsmlPositionRepository;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionViewFactory;

use Unity\Contacts\Interfaces\ContactFactory;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupChangeTracker;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberChangeTracker;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionChangeTracker;
use Unity\Positions\Interfaces\PositionViewFactory;

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
    private static ?ContactFactory $contactFactory = null;

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
        return interface_exists('Unity\\Groups\\Interfaces\\GroupFactory')
            && interface_exists('Unity\\Groups\\Interfaces\\Group');
    }

    /**
     * Check if Unity's location interfaces are available
     *
     * @return bool
     */
    public static function unityLocationsAvailable(): bool
    {
        return interface_exists('Unity\\Locations\\Interfaces\\LocationFactory')
            && interface_exists('Unity\\Locations\\Interfaces\\Location');
    }

    /**
     * Check if Unity's contact interfaces are available
     *
     * @return bool
     */
    public static function unityContactsAvailable(): bool
    {
        return interface_exists('Unity\\Contacts\\Interfaces\\ContactFactory')
            && interface_exists('Unity\\Contacts\\Interfaces\\Contact');
    }

    /**
     * Check if Unity's member interfaces are available
     *
     * @return bool
     */
    public static function unityMembersAvailable(): bool
    {
        return interface_exists('Unity\\Members\\Interfaces\\MemberFactory')
            && interface_exists('Unity\\Members\\Interfaces\\Member')
            && interface_exists('Unity\\Members\\Interfaces\\MemberRepository');
    }

    /**
     * Check if Unity's position interfaces are available
     *
     * @return bool
     */
    public static function unityPositionsAvailable(): bool
    {
        return interface_exists('Unity\\Positions\\Interfaces\\PositionFactory')
            && interface_exists('Unity\\Positions\\Interfaces\\Position')
            && interface_exists('Unity\\Positions\\Interfaces\\PositionRepository');
    }

    /**
     * Check if Unity's intergroup meeting interfaces are available
     *
     * @return bool
     */
    public static function unityIntergroupMeetingsAvailable(): bool
    {
        return interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactory')
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeeting')
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingRepository');
    }

    /**
     * Check if Unity's intergroup meeting attendance interfaces are available
     *
     * @return bool
     */
    public static function unityIntergroupMeetingAttendanceAvailable(): bool
    {
        return interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendanceFactory')
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendance')
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendanceRepository');
    }
    /**
     * Check if Unity's intergroup meeting interfaces are available
     *
     * @return bool
     */
    public static function unityMeetingsAvailable(): bool
    {
        return interface_exists('Unity\\Meetings\\Interfaces\\MeetingFactory')
            && interface_exists('Unity\\Meetings\\Interfaces\\Meeting')
            && interface_exists('Unity\\Meetings\\Interfaces\\MeetingRepository');
    }

    /**
     * Check if Unity's position view interfaces are available
     *
     * @return bool
     */
    public static function unityPositionViewsAvailable(): bool
    {
        return interface_exists('Unity\\Positions\\Interfaces\\PositionViewFactory')
            && interface_exists('Unity\\Positions\\Interfaces\\PositionView')
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
        return interface_exists('Unity\\Groups\\Interfaces\\GroupViewFactory')
            && interface_exists('Unity\\Groups\\Interfaces\\GroupView')
            && self::unityGroupsAvailable();
    }

    /**
     * @param $class
     * @return array
     * @throws \ReflectionException
     */
    private static function extractConstants($class): array
    {
        return (new ReflectionClass($class))->getConstants();
    }


    /**
     * Register the TSML factories with Unity's dependency container
     *
     * @param mixed $container Unity's dependency container
     * @return void
     * @throws \ReflectionException
     */
    public static function registerWithUnity($container): void
    {
        if (!method_exists($container, 'register') || !method_exists($container, 'get')) {
            return;
        }

        // Configuration
        $config = $container->get('Unity\\Core\\Interfaces\\Configuration');

        // Register Contact Dependencies
        if (self::unityContactsAvailable()) {
            // Register Contact Factory
            $container->register(
                'Unity\\Contacts\\Interfaces\\ContactFactory',
                function ($container) {
                    return new TsmlContactFactory();
                }
            );
        }

        // Register Location Dependencies
        if (self::unityLocationsAvailable()) {

            // Register Location Factory
            $container->register(
                'Unity\\Locations\\Interfaces\\LocationFactory',
                function ($container) {
                    return new TsmlLocationFactory();
                }
            );

            // Register Location Repository
            $container->register(
                'Unity\\Locations\\Interfaces\\LocationRepository',
                function ($container) {
                    $locationFactory = $container->has('Unity\\Locations\\Interfaces\\LocationFactory')
                        ? $container->get('Unity\\Locations\\Interfaces\\LocationFactory')
                        : null;

                    return new TsmlLocationRepository($locationFactory);
                }
            );
        }

        // Register Meeting Dependencies
        if (self::unityMeetingsAvailable()) {

            // Register Meeting Factory
            $container->register(
                'Unity\\Meetings\\Interfaces\\MeetingFactory',
                function ($container) {
                    $contactFactory = $container->has('Unity\\Contacts\\Interfaces\\ContactFactory')
                        ? $container->get('Unity\\Contacts\\Interfaces\\ContactFactory')
                        : null;
                    $locationRepository = $container->has('Unity\\Locations\\Interfaces\\LocationRepository')
                        ? $container->get('Unity\\Locations\\Interfaces\\LocationRepository')
                        : null;

                    return new TsmlMeetingFactory($contactFactory, $locationRepository);
                }
            );

            // Register Meeting Repository
            $container->register(
                'Unity\\Meetings\\Interfaces\\MeetingRepository',
                function ($container) {
                    $meetingFactory = $container->has('Unity\\Meetings\\Interfaces\\MeetingFactory')
                        ? $container->get('Unity\\Meetings\\Interfaces\\MeetingFactory')
                        : null;

                    $cache = $container->has('Unity\\Common\\Interfaces\\Cache')
                        ? $container->get('Unity\\Common\\Interfaces\\Cache')
                        : null;

                    return new TsmlMeetingRepository($meetingFactory, $cache);
                }
            );

            // Store the Position Fields
            $config->setConfig(Meeting::class, self::extractConstants(TsmlMeetingFields::class));

        }

        // Register Group Dependencies
        if (self::unityGroupsAvailable()) {

            // Register Group Factory
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupFactory',
                function ($container) {
                    $contactFactory = $container->has('Unity\\Contacts\\Interfaces\\ContactFactory')
                        ? $container->get('Unity\\Contacts\\Interfaces\\ContactFactory')
                        : null;

                    $meetingRepository = $container->has('Unity\\Meetings\\Interfaces\\MeetingRepository')
                        ? $container->get('Unity\\Meetings\\Interfaces\\MeetingRepository')
                        : null;

                    return new TsmlGroupFactory($contactFactory, $meetingRepository);
                }
            );

            // Register Group Repository
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupRepository',
                function ($container) {
                    $groupFactory = $container->has('Unity\\Groups\\Interfaces\\GroupFactory')
                        ? $container->get('Unity\\Groups\\Interfaces\\GroupFactory')
                        : null;

                    return new TsmlGroupRepository($groupFactory);
                }
            );

            // Register GroupChangeTracker (overrides Unity's stub)
            $container->register(
                GroupChangeTracker::class,
                function ($container) {
                    $groupRepository = $container->has('Unity\\Groups\\Interfaces\\GroupRepository')
                        ? $container->get('Unity\\Groups\\Interfaces\\GroupRepository')
                        : null;

                    return new TsmlGroupChangeTracker($groupRepository);
                }
            );

            // Store the POST_TYPE for Group
            $config->setConfig(Group::class, ['POST_TYPE' => TsmlGroupFields::POST_TYPE]);

        }

        // Register Member Dependencies
        if (self::unityMembersAvailable()) {
            // Register member factory
            $container->register(
                'Unity\\Members\\Interfaces\\MemberFactory',
                function ($container) {
                    return new TsmlMemberFactory();
                }
            );

            // Register Member Repository
            $container->register(
                'Unity\\Members\\Interfaces\\MemberRepository',
                function ($container) {
                    $memberFactory = $container->has('Unity\\Members\\Interfaces\\MemberFactory')
                        ? $container->get('Unity\\Members\\Interfaces\\MemberFactory')
                        : null;

                    return new TsmlMemberRepository($memberFactory);
                }
            );

            // Register MemberChangeTracker
            $container->register(
                MemberChangeTracker::class,
                function ($container) {
                    $memberRepository = $container->has('Unity\\Members\\Interfaces\\MemberRepository')
                        ? $container->get('Unity\\Members\\Interfaces\\MemberRepository')
                        : null;

                    return new TsmlMemberChangeTracker($memberRepository);
                }
            );

            // Store the Member Fields
            $config->setConfig(Member::class, self::extractConstants(TsmlMemberFields::class));

        }

        // Register Position Dependencies
        if (self::unityPositionsAvailable()) {

            // Register Position Factory
            $container->register(
                'Unity\\Positions\\Interfaces\\PositionFactory',
                function ($container) {
                    return new TsmlPositionFactory();
                }
            );

            // Register Position Repository
            $container->register(
                'Unity\\Positions\\Interfaces\\PositionRepository',
                function ($container) {
                    $positionFactory = $container->has('Unity\\Positions\\Interfaces\\PositionFactory')
                        ? $container->get('Unity\\Positions\\Interfaces\\PositionFactory')
                        : null;

                    return new TsmlPositionRepository($positionFactory);
                }
            );

            // Register Position Change Tracker
            $container->register(
                PositionChangeTracker::class,
                function ($container) {
                    $positionRepository = $container->has('Unity\\Positions\\Interfaces\\PositionRepository')
                        ? $container->get('Unity\\Positions\\Interfaces\\PositionRepository')
                        : null;

                    return new TsmlPositionChangeTracker($positionRepository);
                }
            );

            // Register Position View Factory
            if (self::unityPositionViewsAvailable()) {
                $container->register(
                    PositionViewFactory::class,
                    function ($container) {
                        $positionRepository = $container->has('Unity\\Positions\\Interfaces\\PositionRepository')
                            ? $container->get('Unity\\Positions\\Interfaces\\PositionRepository')
                            : null;

                        $memberRepository = $container->has('Unity\\Members\\Interfaces\\MemberRepository')
                            ? $container->get('Unity\\Members\\Interfaces\\MemberRepository')
                            : null;

                        return new TsmlPositionViewFactory($positionRepository, $memberRepository);
                    }
                );
            }

            // Store the Position Fields
            $config->setConfig(Position::class, self::extractConstants(TsmlPositionFields::class));

        }

        // Register intergroup meeting factory and repository if Unity's intergroup meeting interfaces are available
        if (self::unityIntergroupMeetingsAvailable()) {
            // Register Intergroup Meeting Factory
            $container->register(
                'Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactory',
                function ($container) {
                    return new TsmlIntergroupMeetingFactory();
                }
            );

            // Register intergroup meeting repository
            $container->register(
                'Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingRepository',
                function ($container) {
                    $intergroupMeetingFactory = $container->has('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactory')
                        ? $container->get('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingFactory')
                        : null;

                    return new TsmlIntergroupMeetingRepository($intergroupMeetingFactory);
                }
            );
        }

        // Register Intergroup Meeting Attendance Dependencies
        if (self::unityIntergroupMeetingAttendanceAvailable()) {
            // Register Intergroup Meeting Attendance Factory
            $container->register(
                'Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendanceFactory',
                function ($container) {
                    return new TsmlIntergroupMeetingAttendanceFactory();
                }
            );

            // Register Intergroup Meeting Attendance Repository
            $container->register(
                'Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendanceRepository',
                function ($container) {
                    $attendanceFactory = $container->has('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendanceFactory')
                        ? $container->get('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingAttendanceFactory')
                        : null;

                    return new TsmlIntergroupMeetingAttendanceRepository($attendanceFactory);
                }
            );
        }

        // Register GroupViewFactory
        if (self::unityGroupViewsAvailable()) {
            $container->register(
                'Unity\\Groups\\Interfaces\\GroupViewFactory',
                function ($container) {
                    $groupRepository = $container->has('Unity\\Groups\\Interfaces\\GroupRepository')
                        ? $container->get('Unity\\Groups\\Interfaces\\GroupRepository')
                        : null;

                    $meetingRepository = $container->has('Unity\\Meetings\\Interfaces\\MeetingRepository')
                        ? $container->get('Unity\\Meetings\\Interfaces\\MeetingRepository')
                        : null;

                    return new TsmlGroupViewFactory($groupRepository, $meetingRepository);
                }
            );
        }
    }
}