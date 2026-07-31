<?php

declare(strict_types=1);

/**
 * Stand-ins for WordPress classes the plugin type-checks against.
 *
 * Code guarded by `instanceof \WP_Post` can never match a plain stdClass built
 * in a test, so the classes have to exist for real.
 *
 * These are loaded *before* bleedingdeacons/wp-mocks in tests/bootstrap.php, so
 * they win its class_exists() guards. That matters for Sentinel_Log_Channel in
 * particular: the shared `sentinel` group records in a different shape from the
 * level/message pairs HasLoggerTest asserts on.
 */

if (!class_exists('Sentinel_Log_Channel')) {
    /**
     * Minimal stand-in for Sentinel's log channel.
     *
     * The HasLogger trait caches a `?\Sentinel_Log_Channel`, so tests that
     * exercise the wp_log-present path need this class to exist and to
     * record the level/message pairs it is handed.
     */
    class Sentinel_Log_Channel
    {
        /** @var array<int, array{string, string}> */
        public array $calls = [];

        public function emergency(string $m, array $c = []): void
        {
            $this->calls[] = ['emergency', $m];
        }

        public function alert(string $m, array $c = []): void
        {
            $this->calls[] = ['alert', $m];
        }

        public function critical(string $m, array $c = []): void
        {
            $this->calls[] = ['critical', $m];
        }

        public function error(string $m, array $c = []): void
        {
            $this->calls[] = ['error', $m];
        }

        public function warning(string $m, array $c = []): void
        {
            $this->calls[] = ['warning', $m];
        }

        public function notice(string $m, array $c = []): void
        {
            $this->calls[] = ['notice', $m];
        }

        public function info(string $m, array $c = []): void
        {
            $this->calls[] = ['info', $m];
        }

        public function debug(string $m, array $c = []): void
        {
            $this->calls[] = ['debug', $m];
        }
    }
}

if (!class_exists('WP_Post')) {
    /**
     * Minimal WP_Post.
     *
     * Mirrors WordPress in taking the raw post object and copying its
     * properties across, so tests construct one the same way core does.
     */
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';
        public string $post_title = '';
        public string $post_status = 'publish';
        public string $post_name = '';
        public string $post_content = '';
        public string $post_modified_gmt = '';
        public int $post_parent = 0;
        public int $post_author = 0;
        public string $post_date = '';
        public string $post_excerpt = '';

        /**
         * @param object|array<string, mixed> $post Raw post data.
         */
        public function __construct($post = [])
        {
            foreach ((array) $post as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}
