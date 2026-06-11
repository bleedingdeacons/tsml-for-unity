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
    }');
}

if (!interface_exists('Unity\\Members\\Interfaces\\MemberRepository')) {
    eval('namespace Unity\\Members\\Interfaces;
    interface MemberRepository {
        public function findById(int $id): ?Member;
        public function findByEmail(string $email): ?Member;
        public function findAll(array $args = []): array;
        public function findTelephoneResponders(): array;
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
            private bool $twelfthStepper = false,
            private bool $telephoneResponder = false,
            private string $area = "",
            private array $accepts = [],
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
        public function isTwelfthStepper(): bool { return $this->twelfthStepper; }
        public function isTelephoneResponder(): bool { return $this->telephoneResponder; }
        public function getArea(): string { return $this->area; }
        public function getAccepts(): array { return $this->accepts; }
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

        // Reset static state so test order can't leak originalMember /
        // newMemberIds between cases.
        $reflection = new \ReflectionClass(TsmlMemberChangeTracker::class);
        $reflection->getProperty('originalMember')->setValue(null, null);
        $reflection->getProperty('newMemberIds')->setValue(null, []);

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

    /**
     * Simulate the wp_insert_post lifecycle that flags a post as a creation:
     * fire onPostStatusTransition with auto-draft → publish for a member post.
     * After this the next checkForChanges call for that post id will treat
     * the save as a creation.
     */
    private function flagAsNewMember(int $postId): void
    {
        $this->tracker->onPostStatusTransition(
            'publish',
            'auto-draft',
            (object) ['ID' => $postId, 'post_type' => TsmlMemberFields::POST_TYPE]
        );
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
        $this->flagAsNewMember($postId);
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
        // The "is this a new member" decision is taken from the
        // earlier transition_post_status hook, not from the field
        // diff. Even if every field remains empty after submit, the
        // create event must still fire.
        $postId = 4321;

        $before = new MemberStub($postId);
        $after  = new MemberStub($postId);

        $this->stubPostTypeGuard($postId);
        $this->flagAsNewMember($postId);
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
    public function toggling_telephone_responder_fires_member_changing(): void
    {
        $postId = 5679;

        // Original: not a responder. Updated: is a responder. Everything
        // else is identical so the only diff lives in the new flag.
        $original = new MemberStub(
            $postId, 'Anon', false, false, '', 0, '', 0, false, null,
            '', '', false, false
        );
        $updated = new MemberStub(
            $postId, 'Anon', false, false, '', 0, '', 0, false, null,
            '', '', false, true
        );

        $this->stubPostTypeGuard($postId);
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

    // ─── onPostStatusTransition filters correctly ────────────────────

    /**
     * Inspect the static $newMemberIds map by reflection so each
     * transition test can assert on the exact effect of the call.
     */
    private function newMemberIds(): array
    {
        $r = new \ReflectionClass(TsmlMemberChangeTracker::class);
        $prop = $r->getProperty('newMemberIds');
        $prop->setAccessible(true);
        return $prop->getValue();
    }

    /**
     * @test
     */
    public function transition_from_auto_draft_to_publish_flags_the_post(): void
    {
        $post = (object) ['ID' => 314, 'post_type' => TsmlMemberFields::POST_TYPE];

        $this->tracker->onPostStatusTransition('publish', 'auto-draft', $post);

        $this->assertSame([314 => true], $this->newMemberIds());
    }

    /**
     * @test
     */
    public function transition_from_auto_draft_to_draft_also_flags_the_post(): void
    {
        // "Save Draft" on a brand-new Add New form is still a creation.
        $post = (object) ['ID' => 315, 'post_type' => TsmlMemberFields::POST_TYPE];

        $this->tracker->onPostStatusTransition('draft', 'auto-draft', $post);

        $this->assertSame([315 => true], $this->newMemberIds());
    }

    /**
     * @test
     */
    public function transition_between_two_live_statuses_does_not_flag_the_post(): void
    {
        // Editing an existing member and changing draft → publish is a
        // status change, not a creation.
        $post = (object) ['ID' => 316, 'post_type' => TsmlMemberFields::POST_TYPE];

        $this->tracker->onPostStatusTransition('publish', 'draft', $post);

        $this->assertSame([], $this->newMemberIds());
    }

    /**
     * @test
     */
    public function transition_into_auto_draft_does_not_flag_the_post(): void
    {
        // The new → auto-draft transition fires when WordPress creates
        // the scaffolding row on /post-new.php load. No fields will be
        // saved for that, so it must not be flagged as a creation.
        $post = (object) ['ID' => 317, 'post_type' => TsmlMemberFields::POST_TYPE];

        $this->tracker->onPostStatusTransition('auto-draft', 'new', $post);

        $this->assertSame([], $this->newMemberIds());
    }

    /**
     * @test
     */
    public function transition_for_non_member_post_types_is_ignored(): void
    {
        // Posts, pages, and other CPTs share transition_post_status; we
        // must not touch the flag map for them.
        $post = (object) ['ID' => 318, 'post_type' => 'post'];

        $this->tracker->onPostStatusTransition('publish', 'auto-draft', $post);

        $this->assertSame([], $this->newMemberIds());
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
        $this->flagAsNewMember($createId);
        $this->stubTitleSyncIsNoop($createId, 'A');

        $editId = 222;
        $editOriginal = new MemberStub($editId, 'B', false, false, '', 0, '', 0, false, null, 'b@b.com', 'OLD');
        $editUpdated  = new MemberStub($editId, 'B', false, false, '', 0, '', 0, false, null, 'b@b.com', 'NEW');

        $this->stubPostTypeGuard($editId);
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

        // Request 2 — edit on a different post; no creation flag set
        $this->tracker->captureOriginalMember($editId);
        $this->tracker->checkForChanges($editId);
    }
}
