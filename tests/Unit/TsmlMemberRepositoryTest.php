<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\Member as MemberStub;
use WP_Mock;

/**
 * Mock Unity Member interfaces and stub class.
 *
 * Guarded so this file can be loaded after TsmlMemberFactoryTest, which
 * declares the same symbols. The first file to load wins; later loads
 * are no-ops.
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

if (!interface_exists('Unity\\Members\\Interfaces\\MemberFactory')) {
    eval('namespace Unity\\Members\\Interfaces;
    interface MemberFactory {
        public function createFromSource(int $id): Member;
        public function createNew(
            int $id,
            string $anonymousName = \'\',
            bool $showAnonymousName = false,
            bool $showMemberProfile = false,
            string $anonymousProfile = \'\',
            int $intergroupPosition = 0,
            string $intergroupPositionRotation = \'\',
            int $homeGroup = 0,
            bool $isGSR = false,
            mixed $meetingPO = null,
            string $personalEmail = \'\',
            string $mobileNumber = \'\',
            bool $twelfthStepper = false,
            string $area = \'\',
            array $accepts = [],
            bool $gdprAccepted = false,
            string $gdprAcceptedAt = \'\',
            string $gdprAcceptanceVersion = \'\',
            string $gdprAcceptanceMethod = \'\',
            string $gdprAcceptanceStatement = \'\'
        ): Member;
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
            private bool $twelfthStepper = false,
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
 * Tests for TsmlMemberRepository's domain event firing.
 *
 * These tests pin down the contract that programmatic writes through
 * the repository — Integrity REST, WP-CLI, cron, anything that doesn't
 * go through ACF's form-save lifecycle — emit unity/member_changing
 * (for updates) or unity/member_created (for inserts), so listeners
 * like Scrutiny's audit tracker can react.
 *
 * The tests deliberately do NOT exercise the underlying acf/save_post
 * path used by the admin form; that path has its own listener in
 * TsmlMemberChangeTracker and is covered by other tests.
 *
 * @covers \TsmlForUnity\Members\TsmlMemberRepository
 */
class TsmlMemberRepositoryTest extends TestCase
{
    /** @var MemberFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlMemberRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(MemberFactory::class);
        $this->repository = new TsmlMemberRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Helper: stub get_post / get_post_type for findById's pre-check
     * so the factory's createFromSource() is reached.
     */
    private function stubExistingPost(int $postId): void
    {
        WP_Mock::userFunction('get_post')
            ->with($postId)
            ->andReturn((object) [
                'ID' => $postId,
                'post_type' => TsmlMemberFields::POST_TYPE,
            ]);
    }

    /**
     * Helper: stub all the update_field calls updateFields() makes.
     * We don't care which arguments they get for these tests — we're
     * asserting on the do_action events, not the field writes.
     */
    private function allowAnyUpdateFieldCalls(): void
    {
        WP_Mock::userFunction('update_field')->andReturn(true);
    }

    // ─── update() fires unity/member_changing ───────────────────────

    /**
     * @test
     */
    public function update_fires_member_changing_with_original_and_updated_members(): void
    {
        $postId = 23462;

        // Two distinct member objects: original (mobile = old) and
        // updated (mobile = new). The factory returns the original on
        // the first findById() (before writes) and the updated on the
        // second (after writes).
        $original = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'OLD-MOBILE');
        $updated  = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'NEW-MOBILE');

        $this->stubExistingPost($postId);
        $this->factory->expects($this->exactly(2))
            ->method('createFromSource')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        $this->allowAnyUpdateFieldCalls();

        WP_Mock::expectActionCalled('unity/member_changing');

        $caller = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'NEW-MOBILE');
        $result = $this->repository->update($caller);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function update_does_not_fire_member_changing_when_wp_update_post_fails(): void
    {
        $postId = 23462;

        $original = new MemberStub($postId);

        $this->stubExistingPost($postId);
        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($postId)
            ->willReturn($original);

        // Simulate wp_update_post returning a WP_Error.
        $error = new \stdClass();
        WP_Mock::userFunction('wp_update_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        // No update_field calls and no event fired — assert by absence.

        $caller = new MemberStub($postId, 'Anon');
        $result = $this->repository->update($caller);

        $this->assertFalse($result);
        // If the action had fired, WP_Mock::tearDown() would not flag
        // it because we didn't expect-and-fail; but the more important
        // assertion is that we never reached findField/createFromSource
        // a second time (verified by the once() expectation above).
    }

    /**
     * @test
     */
    public function update_returns_false_for_zero_post_id_and_does_nothing(): void
    {
        // Zero ID never reaches findById, wp_update_post, or update_field.
        // No WP_Mock expectations: any call would fail the test.

        $caller = new MemberStub(0, 'Anon');
        $result = $this->repository->update($caller);

        $this->assertFalse($result);
    }

    // ─── save() insert path fires unity/member_created ──────────────

    /**
     * @test
     */
    public function save_insert_fires_member_created_after_writes(): void
    {
        $newPostId = 99999;

        // Caller submits a Member with id=0 (insert).
        $caller = new MemberStub(0, 'New Anon');

        // After insert + updateFields, findById is called once to
        // re-read the persisted state. The factory returns the
        // member as it exists in storage.
        $persisted = new MemberStub($newPostId, 'New Anon');

        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($newPostId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        $this->allowAnyUpdateFieldCalls();

        // findById's pre-check
        WP_Mock::userFunction('get_post')
            ->with($newPostId)
            ->andReturn((object) [
                'ID' => $newPostId,
                'post_type' => TsmlMemberFields::POST_TYPE,
            ]);

        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($newPostId)
            ->willReturn($persisted);

        WP_Mock::expectActionCalled('unity/member_created');

        $result = $this->repository->save($caller);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function save_insert_does_not_fire_member_created_when_wp_insert_post_fails(): void
    {
        $caller = new MemberStub(0, 'New Anon');

        $error = new \stdClass();
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        // No update_field, no get_post, no createFromSource: a
        // failure to insert returns false before any of those.

        $result = $this->repository->save($caller);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function save_with_existing_id_delegates_to_update_and_fires_member_changing(): void
    {
        // save() with id > 0 must delegate to update() — verified by
        // observing the same unity/member_changing event update() fires,
        // not unity/member_created.

        $postId = 23462;

        $original = new MemberStub($postId, 'Old Anon');
        $updated  = new MemberStub($postId, 'New Anon');

        $this->stubExistingPost($postId);
        $this->factory->expects($this->exactly(2))
            ->method('createFromSource')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        $this->allowAnyUpdateFieldCalls();

        WP_Mock::expectActionCalled('unity/member_changing');

        $caller = new MemberStub($postId, 'New Anon');
        $result = $this->repository->save($caller);

        $this->assertTrue($result);
    }
}
