<?php

declare(strict_types=1);

namespace TsmlForUnity;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Psr\Container\ContainerInterface;
use ReflectionClass;
use TsmlForUnity\Contacts\TsmlContactFactory;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Groups\TsmlGroupRepository;
use TsmlForUnity\Groups\TsmlGroupViewFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceRepository;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceRepository;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
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
use Unity\Core\Interfaces\Cache;
use Unity\Core\Interfaces\Configuration;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupChangeTracker;
use Unity\Groups\Interfaces\GroupFactory;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Groups\Interfaces\GroupViewFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingChangeTracker;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceRepository;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceRepository;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;
use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\LocationRepository;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberChangeTracker;
use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionChangeTracker;
use Unity\Positions\Interfaces\PositionFactory;
use Unity\Positions\Interfaces\PositionRepository;
use Unity\Positions\Interfaces\PositionViewFactory;

/**
 * Main Plugin Class
 *
 * Handles Unity availability checks and factory registration/retrieval.
 */
class Plugin
{
    use \TsmlForUnity\Logger\HasLogger;

    protected static function logChannel(): string
    {
        return 'tsml-for-unity';
    }

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
        return interface_exists(GroupFactory::class)
            && interface_exists(Group::class);
    }

    /**
     * Check if Unity's location interfaces are available
     *
     * @return bool
     */
    public static function unityLocationsAvailable(): bool
    {
        return interface_exists(LocationFactory::class)
            && interface_exists('Unity\\Locations\\Interfaces\\Location');
    }

    /**
     * Check if Unity's contact interfaces are available
     *
     * @return bool
     */
    public static function unityContactsAvailable(): bool
    {
        return interface_exists(ContactFactory::class)
            && interface_exists('Unity\\Contacts\\Interfaces\\Contact');
    }

    /**
     * Check if Unity's member interfaces are available
     *
     * @return bool
     */
    public static function unityMembersAvailable(): bool
    {
        return interface_exists(MemberFactory::class)
            && interface_exists(Member::class)
            && interface_exists(MemberRepository::class);
    }

    /**
     * Check if Unity's position interfaces are available
     *
     * @return bool
     */
    public static function unityPositionsAvailable(): bool
    {
        return interface_exists(PositionFactory::class)
            && interface_exists(Position::class)
            && interface_exists(PositionRepository::class);
    }

    /**
     * Check if Unity's intergroup meeting interfaces are available
     *
     * @return bool
     */
    public static function unityIntergroupMeetingsAvailable(): bool
    {
        return interface_exists(IntergroupMeetingFactory::class)
            && interface_exists(IntergroupMeeting::class)
            && interface_exists(IntergroupMeetingRepository::class);
    }

    /**
     * Check if Unity's intergroup meeting attendance interfaces are available
     *
     * @return bool
     */
    public static function unityIntergroupMeetingGroupAttendanceAvailable(): bool
    {
        return interface_exists(IntergroupMeetingGroupAttendanceFactory::class)
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingGroupAttendance')
            && interface_exists(IntergroupMeetingGroupAttendanceRepository::class);
    }

    /**
     * Check if Unity's intergroup meeting officer attendance interfaces are available
     *
     * @return bool
     */
    public static function unityIntergroupMeetingOfficerAttendanceAvailable(): bool
    {
        return interface_exists(IntergroupMeetingOfficerAttendanceFactory::class)
            && interface_exists('Unity\\IntergroupMeetings\\Interfaces\\IntergroupMeetingOfficerAttendance')
            && interface_exists(IntergroupMeetingOfficerAttendanceRepository::class);
    }
    /**
     * Check if Unity's intergroup meeting interfaces are available
     *
     * @return bool
     */
    public static function unityMeetingsAvailable(): bool
    {
        return interface_exists(MeetingFactory::class)
            && interface_exists(Meeting::class)
            && interface_exists(MeetingRepository::class);
    }

    /**
     * Check if Unity's position view interfaces are available
     *
     * @return bool
     */
    public static function unityPositionViewsAvailable(): bool
    {
        return interface_exists(PositionViewFactory::class)
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
        return interface_exists(GroupViewFactory::class)
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
     * @param ContainerInterface $container Unity's PSR-11 dependency container
     * @return void
     * @throws \ReflectionException
     */
    public static function registerWithUnity(ContainerInterface $container): void
    {
        if (!method_exists($container, 'register')) {
            return;

            self::logInfo('TSML for Unity initialised', ['version' => defined('TSML_FOR_UNITY_VERSION') ? TSML_FOR_UNITY_VERSION : 'unknown']);
        }

        // Configuration
        $config = $container->get(Configuration::class);

        // Register Contact Dependencies
        if (self::unityContactsAvailable()) {
            // Register Contact Factory
            $container->register(
                ContactFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlContactFactory();
                }
            );
        }

        // Register Location Dependencies
        if (self::unityLocationsAvailable()) {

            // Register Location Factory
            $container->register(
                LocationFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlLocationFactory();
                }
            );

            // Register Location Repository
            $container->register(
                LocationRepository::class,
                function (ContainerInterface $container) {
                    $locationFactory = $container->has(LocationFactory::class)
                        ? $container->get(LocationFactory::class)
                        : null;

                    return new TsmlLocationRepository($locationFactory);
                }
            );
        }

        // Register Meeting Dependencies
        if (self::unityMeetingsAvailable()) {

            // Register Meeting Factory
            $container->register(
                MeetingFactory::class,
                function (ContainerInterface $container) {
                    $contactFactory = $container->has(ContactFactory::class)
                        ? $container->get(ContactFactory::class)
                        : null;
                    $locationRepository = $container->has(LocationRepository::class)
                        ? $container->get(LocationRepository::class)
                        : null;

                    return new TsmlMeetingFactory($contactFactory, $locationRepository);
                }
            );

            // Register Meeting Repository
            $container->register(
                MeetingRepository::class,
                function (ContainerInterface $container) {
                    $meetingFactory = $container->has(MeetingFactory::class)
                        ? $container->get(MeetingFactory::class)
                        : null;

                    $cache = $container->has(Cache::class)
                        ? $container->get(Cache::class)
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
                GroupFactory::class,
                function (ContainerInterface $container) {
                    $contactFactory = $container->has(ContactFactory::class)
                        ? $container->get(ContactFactory::class)
                        : null;

                    $meetingRepository = $container->has(MeetingRepository::class)
                        ? $container->get(MeetingRepository::class)
                        : null;

                    return new TsmlGroupFactory($contactFactory, $meetingRepository);
                }
            );

            // Register Group Repository
            $container->register(
                GroupRepository::class,
                function (ContainerInterface $container) {
                    $groupFactory = $container->has(GroupFactory::class)
                        ? $container->get(GroupFactory::class)
                        : null;

                    return new TsmlGroupRepository($groupFactory);
                }
            );

            // Register GroupChangeTracker (overrides Unity's stub)
            $container->register(
                GroupChangeTracker::class,
                function (ContainerInterface $container) {
                    $groupRepository = $container->has(GroupRepository::class)
                        ? $container->get(GroupRepository::class)
                        : null;

                    return new TsmlGroupChangeTracker($groupRepository);
                }
            );

            // Store the Group Fields
            $config->setConfig(Group::class, self::extractConstants(TsmlGroupFields::class));

        }

        // Register Member Dependencies
        if (self::unityMembersAvailable()) {
            // Register member factory
            $container->register(
                MemberFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlMemberFactory();
                }
            );

            // Register Member Repository
            $container->register(
                MemberRepository::class,
                function (ContainerInterface $container) {
                    $memberFactory = $container->has(MemberFactory::class)
                        ? $container->get(MemberFactory::class)
                        : null;

                    return new TsmlMemberRepository($memberFactory);
                }
            );

            // Register MemberChangeTracker
            $container->register(
                MemberChangeTracker::class,
                function (ContainerInterface $container) {
                    $memberRepository = $container->has(MemberRepository::class)
                        ? $container->get(MemberRepository::class)
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
                PositionFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlPositionFactory();
                }
            );

            // Register Position Repository
            $container->register(
                PositionRepository::class,
                function (ContainerInterface $container) {
                    $positionFactory = $container->has(PositionFactory::class)
                        ? $container->get(PositionFactory::class)
                        : null;

                    return new TsmlPositionRepository($positionFactory);
                }
            );

            // Register Position Change Tracker
            $container->register(
                PositionChangeTracker::class,
                function (ContainerInterface $container) {
                    $positionRepository = $container->has(PositionRepository::class)
                        ? $container->get(PositionRepository::class)
                        : null;

                    return new TsmlPositionChangeTracker($positionRepository);
                }
            );

            // Register Position View Factory
            if (self::unityPositionViewsAvailable()) {
                $container->register(
                    PositionViewFactory::class,
                    function (ContainerInterface $container) {
                        $positionRepository = $container->has(PositionRepository::class)
                            ? $container->get(PositionRepository::class)
                            : null;

                        $memberRepository = $container->has(MemberRepository::class)
                            ? $container->get(MemberRepository::class)
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
                IntergroupMeetingFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlIntergroupMeetingFactory();
                }
            );

            // Register intergroup meeting repository
            $container->register(
                IntergroupMeetingRepository::class,
                function (ContainerInterface $container) {
                    $intergroupMeetingFactory = $container->has(IntergroupMeetingFactory::class)
                        ? $container->get(IntergroupMeetingFactory::class)
                        : null;

                    return new TsmlIntergroupMeetingRepository($intergroupMeetingFactory);
                }
            );

            // Store the Group Fields
            $config->setConfig(IntergroupMeeting::class, self::extractConstants(TsmlIntergroupMeetingFields::class));

            // Register Intergroup Meeting Change Tracker
            $container->register(
                IntergroupMeetingChangeTracker::class,
                function (ContainerInterface $container) {
                    $intergroupMeetingRepository = $container->has(IntergroupMeetingRepository::class)
                        ? $container->get(IntergroupMeetingRepository::class)
                        : null;

                    return new TsmlIntergroupMeetingChangeTracker($intergroupMeetingRepository);
                }
            );

        }

        // Register Intergroup Meeting Attendance Dependencies
        if (self::unityIntergroupMeetingGroupAttendanceAvailable()) {
            // Register Intergroup Meeting Attendance Factory
            $container->register(
                IntergroupMeetingGroupAttendanceFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlIntergroupMeetingGroupAttendanceFactory();
                }
            );

            // Register Intergroup Meeting Attendance Repository
            $container->register(
                IntergroupMeetingGroupAttendanceRepository::class,
                function (ContainerInterface $container) {
                    $attendanceFactory = $container->has(IntergroupMeetingGroupAttendanceFactory::class)
                        ? $container->get(IntergroupMeetingGroupAttendanceFactory::class)
                        : null;

                    return new TsmlIntergroupMeetingGroupAttendanceRepository($attendanceFactory);
                }
            );
        }

        // Register Intergroup Meeting Officer Attendance Dependencies
        if (self::unityIntergroupMeetingOfficerAttendanceAvailable()) {
            // Register Intergroup Meeting Officer Attendance Factory
            $container->register(
                IntergroupMeetingOfficerAttendanceFactory::class,
                function (ContainerInterface $container) {
                    return new TsmlIntergroupMeetingOfficerAttendanceFactory();
                }
            );

            // Register Intergroup Meeting Officer Attendance Repository
            $container->register(
                IntergroupMeetingOfficerAttendanceRepository::class,
                function (ContainerInterface $container) {
                    $attendanceFactory = $container->has(IntergroupMeetingOfficerAttendanceFactory::class)
                        ? $container->get(IntergroupMeetingOfficerAttendanceFactory::class)
                        : null;

                    return new TsmlIntergroupMeetingOfficerAttendanceRepository($attendanceFactory);
                }
            );
        }

        // Register GroupViewFactory
        if (self::unityGroupViewsAvailable()) {
            $container->register(
                GroupViewFactory::class,
                function (ContainerInterface $container) {
                    $groupRepository = $container->has(GroupRepository::class)
                        ? $container->get(GroupRepository::class)
                        : null;

                    $meetingRepository = $container->has(MeetingRepository::class)
                        ? $container->get(MeetingRepository::class)
                        : null;

                    $memberRepository = $container->has(MemberRepository::class)
                        ? $container->get(MemberRepository::class)
                        : null;

                    return new TsmlGroupViewFactory($groupRepository, $meetingRepository, $memberRepository);
                }
            );
        }
    }
}