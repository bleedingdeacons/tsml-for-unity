<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Positions\TsmlPosition;
use Unity\Positions\Interfaces\Position;

/**
 * Tests for TsmlPosition entity
 *
 * @covers \TsmlForUnity\Positions\TsmlPosition
 */
class TsmlPositionTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_position_interface(): void
    {
        $this->assertInstanceOf(Position::class, new TsmlPosition());
    }

    /**
     * @test
     */
    public function it_applies_sensible_defaults(): void
    {
        $position = new TsmlPosition();

        $this->assertSame(0, $position->getId());
        $this->assertSame(6, $position->getMinimumSobriety());
        $this->assertSame(1, $position->getTermYears());
        $this->assertSame('', $position->getEmail());
        $this->assertSame('', $position->getLongName());
        $this->assertSame('', $position->getShortDescription());
        $this->assertSame('', $position->getSummary());
        $this->assertSame('', $position->getLink());
        $this->assertSame('', $position->getUpdated());
    }

    /**
     * @test
     */
    public function it_exposes_every_field_passed_to_the_constructor(): void
    {
        $position = new TsmlPosition(
            id: 8,
            minimumSobriety: 24,
            termYears: 3,
            email: 'chair@example.com',
            longName: 'Intergroup Chair',
            shortDescription: 'Chairs the meeting',
            summary: 'Runs intergroup',
            link: 'https://example.com/chair',
            updated: '2026-06-01 10:00:00'
        );

        $this->assertSame(8, $position->getId());
        $this->assertSame(24, $position->getMinimumSobriety());
        $this->assertSame(3, $position->getTermYears());
        $this->assertSame('chair@example.com', $position->getEmail());
        $this->assertSame('Intergroup Chair', $position->getLongName());
        $this->assertSame('Chairs the meeting', $position->getShortDescription());
        $this->assertSame('Runs intergroup', $position->getSummary());
        $this->assertSame('https://example.com/chair', $position->getLink());
        $this->assertSame('2026-06-01 10:00:00', $position->getUpdated());
    }

    /**
     * @test
     */
    public function a_fully_populated_position_is_valid_even_before_it_is_saved(): void
    {
        $this->assertTrue($this->validPosition(['id' => 0])->isValid());
        $this->assertTrue($this->validPosition(['id' => 5])->isValid());
    }

    /**
     * @test
     * @dataProvider invalidFieldProvider
     */
    public function is_valid_fails_when_any_requirement_is_missing(array $overrides): void
    {
        $this->assertFalse($this->validPosition($overrides)->isValid());
    }

    public static function invalidFieldProvider(): array
    {
        return [
            'no email'             => [['email' => '']],
            'no long name'         => [['longName' => '']],
            'no short description' => [['shortDescription' => '']],
            'no summary'           => [['summary' => '']],
            'sobriety below six'   => [['minimumSobriety' => 5]],
            'term below one year'  => [['termYears' => 0]],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function validPosition(array $overrides = []): TsmlPosition
    {
        $defaults = [
            'id'               => 1,
            'minimumSobriety'  => 6,
            'termYears'        => 1,
            'email'            => 'chair@example.com',
            'longName'         => 'Intergroup Chair',
            'shortDescription' => 'Chairs the meeting',
            'summary'          => 'Runs intergroup',
        ];

        return new TsmlPosition(...array_merge($defaults, $overrides));
    }
}
