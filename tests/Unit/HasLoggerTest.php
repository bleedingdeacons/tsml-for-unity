<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Logger\HasLogger;
use WP_Mock;

// The global \Sentinel_Log_Channel stand-in the trait's cached-channel
// property is typed against is declared in tests/stubs/wordpress.php.

/**
 * A throwaway class that uses the logging trait so its static behaviour can
 * be exercised in isolation from the plugin classes that mix it in.
 */
class HasLoggerFixture
{
    use HasLogger;
}

/**
 * Tests for the HasLogger trait.
 *
 * The trait degrades to a no-op when Sentinel's wp_log() is unavailable, and
 * routes through a resolved channel when it is present.
 *
 * @covers \TsmlForUnity\Logger\HasLogger
 */
class HasLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->resetChannel();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        $this->resetChannel();
        parent::tearDown();
    }

    /**
     * Clear the trait's cached channel so wp_log-present and wp_log-absent
     * cases don't leak a channel into one another.
     */
    private function resetChannel(): void
    {
        $prop = (new \ReflectionClass(HasLoggerFixture::class))->getProperty('loggerChannel');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * @test
     */
    public function log_returns_null_when_wp_log_is_unavailable(): void
    {
        // wp_log() is not defined in the unit runtime.
        $this->assertFalse(function_exists('wp_log'));
        $this->assertNull(HasLoggerFixture::log());
    }

    /**
     * @test
     */
    public function every_level_is_a_safe_no_op_without_a_channel(): void
    {
        // None of these should error even though there is no channel.
        HasLoggerFixture::logEmergency('a');
        HasLoggerFixture::logAlert('b');
        HasLoggerFixture::logCritical('c');
        HasLoggerFixture::logError('d');
        HasLoggerFixture::logWarning('e');
        HasLoggerFixture::logNotice('f');
        HasLoggerFixture::logInfo('g');
        HasLoggerFixture::logDebug('h');

        $this->assertNull(HasLoggerFixture::log());
    }

    /**
     * @test
     */
    public function it_resolves_and_caches_a_channel_when_wp_log_exists(): void
    {
        $channel = new \Sentinel_Log_Channel();

        // logChannel() sanitises the short class name before asking wp_log
        // for that channel.
        WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn ($v) => strtolower($v));
        WP_Mock::userFunction('wp_log')->once()->with('hasloggerfixture')->andReturn($channel);

        HasLoggerFixture::logError('boom');
        HasLoggerFixture::logInfo('fyi');

        // wp_log is expected exactly once: the channel is cached after the
        // first resolution.
        $this->assertSame($channel, HasLoggerFixture::log());
        $this->assertSame(
            [['error', 'boom'], ['info', 'fyi']],
            $channel->calls
        );
    }
}
