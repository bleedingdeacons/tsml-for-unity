<?php

declare(strict_types=1);

/**
 * Test doubles for the Unity plugin's public interfaces.
 *
 * Unity is a sibling WordPress plugin, not a Composer dependency: at runtime
 * WordPress loads both plugins and Unity's own autoloader supplies these
 * interfaces. Under PHPUnit nothing loads them, so tsml-for-unity's classes --
 * which implement 43 of them -- cannot even be constructed. These declarations
 * stand in for that contract.
 *
 * Generated verbatim from the Unity plugin source; do not hand-edit. To
 * refresh after Unity changes its contract, re-copy the interface bodies from
 * unity/src/**\/Interfaces/. Signatures must stay identical to Unity's, or the
 * suite will validate tsml-for-unity against a contract that does not exist.
 */

namespace Unity\Contacts\Interfaces {
    interface Contact
    {
        public function getName(): string;
        public function getEmail(): string;
        public function getPhone(): string;
        public function getUpdated(): string;
    }

    interface ContactFactory
    {
        public function createFromSource(array $source): Contact;
        public function create(string $name = '', string $email = '', string $phone = ''): Contact;
    }
}

namespace Unity\Core\Interfaces {
    use Psr\Container\ContainerInterface;
    use Psr\Container\NotFoundExceptionInterface;

    interface Cache
    {
        public function get(string $key, string $group = '');
        public function set(string $key, mixed $value, string $group = '', int $expire = 0): bool;
        public function delete(string $key, string $group = ''): bool;
        public function flush(): void;
    }

    interface Configuration
    {
        public function setConfig(string $key, array $source): void;
        public function getConfig(string $key): ?array;
    }

    interface Container extends ContainerInterface
    {
        public function register(string $id, callable $factory): void;
        public function get(string $id): mixed;
    }
}

namespace Unity\Groups\Interfaces {
    use Unity\Meetings\Interfaces\Meeting;
    use Unity\Contacts\Interfaces\Contact;
    use Unity\Members\Interfaces\Member;

    interface Group
    {
        public function getId(): int;
        public function getTitle(): string;
        public function getEmail(): string;
        public function getMeetings(): array;
        public function getLink(): string;
        public function isValid(): bool;
        public function getGroupNotes(): string;
        public function getWebsite(): string;
        public function getPhone(): string;
        public function getVenmo(): string;
        public function getPaypal(): string;
        public function getSquare(): string;
        public function getDistrictId(): ?int;
        public function getLastContact(): ?string;
        public function getContacts(): array;
        public function hasContributionOptions(): bool;
        public function getUpdated(): string;
    }

    interface GroupChangeTracker
    {
        public function captureOriginalGroup(int $postId): void;
        public function checkForChanges(int $postId): void;
        public function onGroupDeleted(int $postId): void;
        public function onGroupHidden(string $newStatus, string $oldStatus, \WP_Post $post): void;
    }

    interface GroupFactory
    {
        public function createFromSource(int $sourceId): ?Group;
    }

    interface GroupRepository
    {
        public function findById(int $id): ?Group;
        public function findAll(array $args = []): array;
        public function count(array $args = []): int;
        public function save(Group $group): bool;
        public function update(Group $group): bool;
        public function delete(int $id): bool;
    }

    interface GroupView
    {
        public function getId(): int;
        public function getTitle(): string;
        public function getEmail(): string;
        public function getMeetings(): array;
        public function getLink(): string;
        public function getContacts(): array;
        public function getMembers(): array;
    }

    interface GroupViewFactory
    {
        public function createFrom(int $sourceId): ?GroupView;
    }
}

namespace Unity\IntergroupMeetings\Interfaces {
    interface IntergroupMeeting
    {
        public function getId(): int;
        public function getTitle(): string;
        public function getGroupAttendees(): array;
        public function getOfficersAttending(): array;
        public function getDate(): string;
        public function addGroupAttendee(int $groupId): bool;
        public function removeGroupAttendee(int $groupId): bool;
        public function hasGroupAttendee(int $groupId): bool;
        public function addOfficerAttendee(int $officerId): bool;
        public function removeOfficerAttendee(int $officerId): bool;
        public function hasOfficerAttendee(int $officerId): bool;
        public function getUpdated(): string;
    }

    interface IntergroupMeetingChangeTracker
    {
        public function captureOriginalMeeting(int $postId): void;
        public function checkForChanges(int $postId): void;
        public function onIntergroupMeetingDeleted(int $postId): void;
    }

    interface IntergroupMeetingFactory
    {
        public function createFromSource(int $id): IntergroupMeeting;
    }

    interface IntergroupMeetingGroupAttendance
    {
        public function getId(): int;
        public function getIntergroupMeetingId(): int;
        public function getMeetingLabel(): string;
        public function getGroupId(): int;
        public function getMemberId(): int;
        public function getMeetingGroup(): string;
        public function getGsrName(): string;
        public function isGsrProxy(): bool;
        public function getGsrProxyName(): string;
    }

    interface IntergroupMeetingGroupAttendanceFactory
    {
        public function createFromSource(int $id): IntergroupMeetingGroupAttendance;
        public function createNew(
            int $intergroupMeetingId,
            string $meetingLabel,
            int $groupId,
            int $memberId,
            string $meetingGroup,
            string $gsrName,
            bool $gsrProxy = false,
            string $gsrProxyName = ''
        ): IntergroupMeetingGroupAttendance;
    }

    interface IntergroupMeetingGroupAttendanceRepository
    {
        public function findById(int $id): ?IntergroupMeetingGroupAttendance;
        public function findAll(array $args = []): array;
        public function findByIntergroupMeeting(int $intergroupMeetingId): array;
        public function count(array $args = []): int;
        public function save(IntergroupMeetingGroupAttendance $attendance): bool;
        public function delete(int $id): bool;
        public function deleteByIntergroupMeetingAndMember(int $intergroupMeetingId, int $memberId): bool;
        public function deleteByIntergroupMeetingAndGroup(int $intergroupMeetingId, int $groupId): bool;
        public function existsForMeetingAndGroup(int $intergroupMeetingId, int $groupId): bool;
    }

    interface IntergroupMeetingOfficerAttendance
    {
        public function getId(): int;
        public function getIntergroupMeetingId(): int;
        public function getMeetingLabel(): string;
        public function getOfficerId(): int;
        public function getPositionName(): string;
        public function getOfficerName(): string;
    }

    interface IntergroupMeetingOfficerAttendanceFactory
    {
        public function createFromSource(int $id): IntergroupMeetingOfficerAttendance;
        public function createNew(
            int $intergroupMeetingId,
            string $meetingLabel,
            int $officerId,
            string $positionName,
            string $officerName
        ): IntergroupMeetingOfficerAttendance;
    }

    interface IntergroupMeetingOfficerAttendanceRepository
    {
        public function findById(int $id): ?IntergroupMeetingOfficerAttendance;
        public function findAll(array $args = []): array;
        public function findByIntergroupMeeting(int $intergroupMeetingId): array;
        public function count(array $args = []): int;
        public function save(IntergroupMeetingOfficerAttendance $attendance): bool;
        public function delete(int $id): bool;
        public function deleteByIntergroupMeetingAndOfficer(int $intergroupMeetingId, int $officerId): bool;
        public function updateByMeetingAndOfficer(int $intergroupMeetingId, int $officerId, string $positionName, string $officerName): int;
        public function existsForMeetingAndOfficer(int $intergroupMeetingId, int $officerId): bool;
    }

    interface IntergroupMeetingRepository
    {
        public function findById(int $id): ?IntergroupMeeting;
        public function findAll(array $args = []): array;
        public function count(array $args = []): int;
        public function save(IntergroupMeeting $intergroupMeeting): bool;
        public function delete(int $id): bool;
    }
}

namespace Unity\Locations\Interfaces {
    interface Location
    {
        public function getId(): int;
        public function getName(): string;
        public function getAddress(): string;
        public function getCity(): string;
        public function getState(): string;
        public function getPostalCode(): string;
        public function getCountry(): string;
        public function getRegion(): string;
        public function getNotes(): string;
        public function getLink(): string;
        public function getLatitude(): ?float;
        public function getLongitude(): ?float;
        public function getTimezone(): string;
        public function getMeetingIds(): array;
        public function isValid(): bool;
        public function getFormattedAddress(): string;
        public function hasCoordinates(): bool;
        public function getUpdated(): string;
    }

    interface LocationFactory
    {
        public function createFromSource(int $sourceId): ?Location;
    }

    interface LocationRepository
    {
        public function findById(int $id): ?Location;
        public function findAll(array $args = []): array;
        public function findByCity(string $city): array;
        public function findByRegion(string $region): array;
        public function save(Location $location): bool;
        public function update(Location $location): bool;
        public function delete(int $id): bool;
    }
}

namespace Unity\Meetings\Interfaces {
    use Unity\Locations\Interfaces\Location;
    use Unity\Members\Interfaces\Member;
    use DateTime;

    interface Meeting
    {
        public function getId(): int;
        public function getName(): string;
        public function getSlug(): string;
        public function getLocation(): ?Location;
        public function getUrl(): string;
        public function getDay(): int;
        public function getDayOfWeek(): string;
        public function getTime(): string;
        public function getEndTime(): string;
        public function getTypes(): array;
        public function getState(): string;
        public function isOnline(): bool;
        public function getContacts(): array;
        public function getMeta(): array;
        public function getOnlineLink(): string;
        public function getOnlineNotes(): string;
        public function getUpdated(): string;
    }

    interface MeetingFactory
    {
        public function createFromSource(array $source): ?Meeting;
    }

    interface MeetingRepository
    {
        public function findById(int $id): ?Meeting;
        public function findAll(array $args = []): array;
        public function findByDay(int $day, array $args = []): array;
        public function findOnline(array $args = []): array;
        public function findInPerson(array $args = []): array;
        public function findByGroupId(int $groupId, array $args = []): array;
        public function findByLocationId(int $locationId, array $args = []): array;
        public function search(string $keyword, array $args = []): array;
        public function count(array $args = []): int;
    }

    interface MeetingView
    {
        public function getMeeting(): Meeting;
        public function getMembers(): array;
        public function getGsrNames(): array;
    }

    interface MeetingViewFactory
    {
        public function createFrom(int $meetingId): ?MeetingView;
    }
}

namespace Unity\Members\Interfaces {
    interface Member
    {
        public function getId(): int;
        public function getAnonymousName(): string;
        public function showAnonymousName(): bool;
        public function showMemberProfile(): bool;
        public function getAnonymousProfile(): string;
        public function getIntergroupPosition(): int;
        public function getIntergroupPositionRotation(): string;
        public function getHomeGroup(): int;
        public function isGSR(): bool;
        public function getMeetingPO(): mixed;
        public function getPersonalEmail(): string;
        public function getMobileNumber(): string;
        public function isTwelfthStepper(): bool;
        public function isTelephoneResponder(): bool;
        public function getArea(): string;
        public function getAccepts(): array;
        public function isGdprAccepted(): bool;
        public function getGdprAcceptedAt(): string;
        public function getGdprAcceptanceVersion(): string;
        public function getGdprAcceptanceMethod(): string;
        public function getGdprAcceptanceStatement(): string;
        public function getUpdated(): string;
    }

    interface MemberChangeTracker
    {
        public function captureOriginalMember(int $postId): void;
        public function checkForChanges(int $postId): void;
        public function onMemberDeleted(int $postId): void;
    }

    interface MemberFactory
    {
        public function createFromSource(int $id): Member;
        public function createNew(
            int $id,
            string $anonymousName = '',
            bool $showAnonymousName = false,
            bool $showMemberProfile = false,
            string $anonymousProfile = '',
            int $intergroupPosition = 0,
            string $intergroupPositionRotation = '',
            int $homeGroup = 0,
            bool $isGSR = false,
            mixed $meetingPO = null,
            string $personalEmail = '',
            string $mobileNumber = '',
            bool $twelfthStepper = false,
            bool $telephoneResponder = false,
            string $area = '',
            array $accepts = [],
            bool $gdprAccepted = false,
            string $gdprAcceptedAt = '',
            string $gdprAcceptanceVersion = '',
            string $gdprAcceptanceMethod = '',
            string $gdprAcceptanceStatement = '',
            string $updated = ''
        ): Member;
    }

    interface MemberRepository
    {
        public function findById(int $id): ?Member;
        public function findByEmail(string $email): ?Member;
        public function findAll(array $args = []): array;
        public function findTelephoneResponders(): array;
        public function count(array $args = []): int;
        public function create(string $anonymousName): int;
        public function save(Member $member): bool;
        public function delete(int $id): bool;
        public function update(Member $member): bool;
    }

    interface MemberView
    {
        public function getId(): int;
        public function getAnonymousName(): string;
        public function getPersonalEmail(): string;
        public function getMobileNumber(): string;
        public function getHomeGroupId(): int;
        public function getHomeGroupName(): string;
        public function hasHomeGroup(): bool;
        public function isGSR(): bool;
        public function getPositionId(): int;
        public function getPositionName(): string;
        public function hasPosition(): bool;
        public function getRotationDate(): string;
        public function isTwelfthStepper(): bool;
        public function isTelephoneResponder(): bool;
        public function getArea(): string;
        public function getAccepts(): array;
    }

    interface MemberViewFactory
    {
        public function createFromSource(array $sourceIds): array;
    }
}

namespace Unity\Positions\Interfaces {
    use Unity\Members\Interfaces\Member;
    use DateTime;

    interface Position
    {
        public function getId(): int;
        public function getMinimumSobriety(): int;
        public function getTermYears(): int;
        public function getEmail(): string;
        public function getLongName(): string;
        public function getShortDescription(): string;
        public function getSummary(): string;
        public function getLink(): string;
        public function isValid(): bool;
        public function getUpdated(): string;
    }

    interface PositionChangeTracker
    {
        public function captureOriginalPosition(int $postId): void;
        public function checkForChanges(int $postId): void;
    }

    interface PositionFactory
    {
        public function createFromSource(int $sourceId): ?Position;
        public function createNew(
            int $id,
            int $minimumSobriety = 6,
            int $termYears = 1,
            string $email = '',
            string $longName = '',
            string $shortDescription = '',
            string $summary = ''
        ): Position;
    }

    interface PositionRepository
    {
        public function findById(int $id): ?Position;
        public function findAll(array $args = []): array;
        public function count(array $args = []): int;
        public function save(Position $position): bool;
        public function update(Position $position): bool;
        public function delete(int $id): bool;
    }

    interface PositionView
    {
        public function getPosition(): Position;
        public function getMember(): ?Member;
        public function getMembers(): array;
        public function getOfficerDisplayName(): string;
        public function isVacant(): bool;
        public function getDaysUntilRotation(): ?int;
        public function getMonthsUntilRotation(): ?int;
        public function getRotationDate(): ?DateTime;
        public function getTitle(): ?string;
        public function getPositionEmail(): ?string;
        public function getPublicDisplayName(): ?string;
        public function getPersonalEmail(): ?string;
        public function getMobileNumber(): ?string;
        public function getDescription(): ?string;
        function isArchivist(): bool;
    }

    interface PositionViewFactory
    {
        public function createFrom(int $positionId): ?PositionView;
        public function createAll(array $args = []): array;
    }
}

namespace Unity\PrivacyPolicies\Interfaces {
    interface PrivacyPolicy
    {
        public function getId(): int;
        public function getTitle(): string;
        public function getPolicy(): string;
        public function getVersion(): string;
        public function isActive(): bool;
        public function getUpdated(): string;
    }

    interface PrivacyPolicyFactory
    {
        public function createFromSource(int $id): PrivacyPolicy;
        public function createNew(
            int $id,
            string $title = '',
            string $policy = '',
            string $version = '',
            bool $active = false,
            string $updated = ''
        ): PrivacyPolicy;
    }

    interface PrivacyPolicyRepository
    {
        public function findById(int $id): ?PrivacyPolicy;
        public function findActive(): ?PrivacyPolicy;
        public function findAll(array $args = []): array;
        public function count(array $args = []): int;
        public function create(string $title): int;
        public function save(PrivacyPolicy $policy): bool;
        public function update(PrivacyPolicy $policy): bool;
        public function delete(int $id): bool;
    }
}
