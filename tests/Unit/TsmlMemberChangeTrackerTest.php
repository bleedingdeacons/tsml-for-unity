<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\Member as MemberStub;
use WP_Mock;

/**
 * Mock Unity Member interfaces and stub class.
 *
 * Guarded so this file can be loaded after TsmlMemberFactoryTest /
 * TsmlMemberRepositoryTest, which declare the same symbols. The first
 * file to load wins; later loads are no-ops.
 */
if (!interface_exists('Unity\\Members\\Interfaces\\Member')) {
    eval('namespace Unity\\Members\\Interfaces;
    interface Member {
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
        public function isGdprAccepted(): bool;
        public function getGdprAcceptedAt(): string;
        public function getGdprAcceptanceVersion(): string;
        public function getGdprAcceptanceMethod(): string;
        public function getGdprAcceptanceStatement(): string;
        public function getUpdated(): string;
    }');
}

if (!interface_exists('Unity\\Members\\Interfaces\\MemberRepository')) {
    eval('namespace Unity\\Members\\Interfaces;
    interface MemberRepository {
        public function findById(int $id): ?Member;
        public function findAll(array $args = []): array;
        public function count(array $args = []): int;
        public function save(Member $member): bool;
        public function update(Member $member): bool;
        public function delete(int $id): bool;
    }');
}

if (!class_exists('Unity\\Members\\Member')) {
    eval('namespace Unity\\Members;
    class Member implements Interfaces\\Member {
        public function __construct(
            private int $id,
            private string $anonymousName = "",
            private bool $showAnonymousName = false,
            private bool $showMemberProfile = false,
            private string $anonymousProfile = "",
            private int $intergroupPosition = 0,
            private string $intergroupPositionRotation = "",
            private int $homeGroup = 0,
            private bool $isGSR = false,
            private mixed $meetingPO = null,
            private string $personalEmail = "",
            private string $mobileNumber = "",
            private bool $gdprAccepted = false,
            private string $gdprAcceptedAt = "",
            private string $gdprAcceptanceVersion = "",
            private string $gdprAcceptanceMethod = "",
            private string $gdprAcceptanceStatement = "",
            private string $updated = ""
        ) {}
        public function getId(): int { return $this->id; }
        public function getAnonymousName(): string { return $this->anonymousName; }
        public function showAnonymousName(): bool { return $this->showAnonymousName; }
        public function showMemberProfile(): bool { return $this->showMemberProfile; }
        public function getAnonymousProfile(): string { return $this->anonymousProfile; }
        public function getIntergroupPosition(): int { return $this->intergroupPosition; }
        public function getIntergroupPositionRotation(): string { return $this->intergroupPositionRotation; }
        public function getHomeGroup(): int { return $this->homeGroup; }
        public function isGSR(): bool { return $this->isGSR; }
        public function getMeetingPO(): mixed { return $this->meetingPO; }
        public function getPersonalEmail(): string { return $this->personalEmail; }
        public function getMobileNumber(): string { return $this->mobileNumber; }
        public function isGdprAccepted(): bool { return $this->gdprAccepted; }
        public function getGdprAcceptedAt(): string { return $this->gdprAcceptedAt; }
        public function getGdprAcceptanceVersion(): string { return $this->gdprAcceptanceVersion; }
        public function getGdprAcceptanceMethod(): string { return $this->gdprAcceptanceMethod; }
        public function getGdprAcceptanceStatement(): string { return $this->gdprAcceptanceStatement; }
        public function getUpdated(): string { return $this->updated; }
    }');
}

/**
 * Tests for TsmlMemberChangeTracker's admin-form save lifecycle.
 *
 * The change tracker hooks acf/save_post twice — once at priority 1 to
 * snapshot the original member and detect whether this is the first
 * save, and once at priority 20 to fire the appropriate domain event
 * after ACF has written every field. These tests exercise that
 * captureOriginalMember → checkForChanges pair, focusing on the
 * branching between unity/member_created and unity/member_changing.
 *
 * @covers \TsmlForUnity\Members\TsmlMemberChangeTracker
 */
class TsmlMemberChangeTrackerTest extends TestCase
{
    /** @var MemberRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    private TsmlMemberChangeTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // Allow add_action calls fired by the constructor without
        // pinning their exact arguments — the constructor wiring is
        // not under test here.
        WP_Mock::userFunction('add_action')->andReturn(true);

        $this->repository = $this->createMock(MemberRepository::class);
        $this->tracker = new TsmlMemberChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();

        // Reset static state so test order can't leak isNewMember /
        // originalMember between cases.
        $reflection = new \ReflectionClass(TsmlMemberChangeTracker::class);
        foreach (['originalMember', 'isNewMember'] as $name) {
            $prop = $reflection->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, $name === 'originalMember' ? null : false);
        }

        parent::tearDown();
    }

    /**
     * Stub the post-type guard at the top of capture/check, so the
     * methods proceed past their early return.
     */
    private function stubPostTypeGuard(int $postId): void
    {
        WP_Mock::userFunction('get_post_type')
            ->with($postId)
            ->andReturn(TsmlMemberFields::POST_TYPE);
    }

    private function stubPostStatus(int $postId, string $status): void
    {
        WP_Mock::userFunction('get_post_status')
            ->with($postId)
            ->andReturn($status);
    }

    /**
     * Stub get_post + the title-sync wp_update_post that runs in
     * checkForChanges. We don't care which title is passed — only that
     * the method completes — so any update is allowed and any get_post
     * call returns a record whose title already matches, suppressing
     * the wp_update_post call entirely. Tests that need to vary this
     * can override these expectations.
     */
    private function stubTitleSyncIsNoop(int $postId, string $existingTitle = ''): void
    {
        WP_Mock::userFunction('get_post')
            ->with($postId)
            ->andReturn((object) [
                'ID' => $postId,
                'post_title' => $existingTitle,
            ]);
    }

    // ─── First save of an admin-created member fires member_created ──

    /**
     * @test
     */
    public function first_save_of_admin_created_member_fires_member_created(): void
    {
        $postId = 1234;

        // Auto-draft snapshot has no fields populated; the post-save
        // snapshot has the form's submitted values. The change tracker
        // sees both via repository->findById.
        $autoDraft = new MemberStub($postId);
        $populated = new MemberStub(
            $postId,
            'New Anon',
            false, false, '', 0, '', 0, false, null,
            'new@example.com', '07700 900000'
        );

        $this->stubPostTypeGuard($postId);
        $this->stubPostStatus($postId, 'auto-draft');
        $this->stubTitleSyncIsNoop($postId, 'New Anon');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($autoDraft, $populated);

        WP_Mock::expectActionCalled('unity/member_before_save');
        WP_Mock::expectActionCalled('unity/member_created');
        WP_Mock::expectActionCalled('unity/member_changed');
        // member_changing must NOT fire on a first save.
        WP_Mock::expectActionNotCalled('unity/member_changing');

        $this->tracker->captureOriginalMember($postId);
        $this->tracker->checkForChanges($postId);
    }

    /**
     * @test
     */
    public function first_save_fires_member_created_even_when_no_fields_were_populated(): void
    {
        // The "is this a new member" decision is taken from the post
        // status alone, not from the field diff. Even if every field
        // remains empty after submit, the create event must still fire.
        $postId = 4321;

        $before = new MemberStub($postId);
        $after  = new MemberStub($postId);

        $this->stubPostTypeGuard($postId);
        $this->stubPostStatus($postId, 'auto-draft');
        $this->stubTitleSyncIsNoop($postId);

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($before, $after);

        WP_Mock::expectActionCalled('unity/member_created');
        WP_Mock::expectActionNotCalled('unity/member_changing');

        $this->tracker->captureOriginalMember($postId);
        $this->tracker->checkForChanges($postId);
    }

    // ─── Subsequent saves of an existing member fire member_changing ─

    /**
     * @test
     */
    public function edit_of_existing_published_member_fires_member_changing(): void
    {
        $postId = 5678;

        $original = new MemberStub(
            $postId, 'Anon', false, false, '', 0, '', 0, false, null,
            '', 'OLD-MOBILE'
        );
        $updated = new MemberStub(
            $postId, 'Anon', false, false, '', 0, '', 0, false, null,
            '', 'NEW-MOBILE'
        );

        $this->stubPostTypeGuard($postId);
        $this->stubPostStatus($postId, 'publish');
        $this->stubTitleSyncIsNoop($postId, 'Anon');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::expectActionCalled('unity/member_changing');
        WP_Mock::expectActionNotCalled('unity/member_created');

        $this->tracker->captureOriginalMember($postId);
        $this->tracker->checkForChanges($postId);
    }

    /**
     * @test
     */
    public function edit_with_no_actual_field_changes_fires_no_create_or_update_event(): void
    {
        $postId = 91011;

        // Same data on both sides — diff is empty.
        $args = [$postId, 'Anon', false, false, '', 0, '', 0, false, null, 'a@b.com', '07700 900000'];
        $original = new MemberStub(...$args);
        $updated  = new MemberStub(...$args);

        $this->stubPostTypeGuard($postId);
        $this->stubPostStatus($postId, 'publish');
        $this->stubTitleSyncIsNoop($postId, 'Anon');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        // Only the catch-all "save completed" event fires; create and
        // changing both stay silent.
        WP_Mock::expectActionCalled('unity/member_changed');
        WP_Mock::expectActionNotCalled('unity/member_created');
        WP_Mock::expectActionNotCalled('unity/member_changing');

        $this->tracker->captureOriginalMember($postId);
        $this->tracker->checkForChanges($postId);
    }

    // ─── Static state isolation ──────────────────────────────────────

    /**
     * @test
     */
    public function isNewMember_flag_does_not_leak_into_a_following_update_request(): void
    {
        // A creation in one request followed by an unrelated edit in
        // another must not cause the second save to be misclassified.
        // We simulate the two requests back-to-back; the static reset
        // happens at the end of checkForChanges in request 1.

        $createId = 111;
        $createAutoDraft = new MemberStub($createId);
        $createPopulated = new MemberStub($createId, 'A', false, false, '', 0, '', 0, false, null, 'a@b.com');

        $this->stubPostTypeGuard($createId);
        $this->stubPostStatus($createId, 'auto-draft');
        $this->stubTitleSyncIsNoop($createId, 'A');

        $editId = 222;
        $editOriginal = new MemberStub($editId, 'B', false, false, '', 0, '', 0, false, null, 'b@b.com', 'OLD');
        $editUpdated  = new MemberStub($editId, 'B', false, false, '', 0, '', 0, false, null, 'b@b.com', 'NEW');

        $this->stubPostTypeGuard($editId);
        $this->stubPostStatus($editId, 'publish');
        $this->stubTitleSyncIsNoop($editId, 'B');

        $this->repository->expects($this->exactly(4))
            ->method('findById')
            ->willReturnOnConsecutiveCalls(
                $createAutoDraft,
                $createPopulated,
                $editOriginal,
                $editUpdated,
            );

        WP_Mock::expectActionCalled('unity/member_created');
        WP_Mock::expectActionCalled('unity/member_changing');

        // Request 1 — create
        $this->tracker->captureOriginalMember($createId);
        $this->tracker->checkForChanges($createId);

        // Request 2 — edit on a different post, status publish
        $this->tracker->captureOriginalMember($editId);
        $this->tracker->checkForChanges($editId);
    }
}
