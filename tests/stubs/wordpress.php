<?php

declare(strict_types=1);

/**
 * Stand-ins for WordPress classes the plugin type-checks against.
 *
 * WP_Mock supplies functions, not classes, so code guarded by
 * `instanceof \WP_Post` can never match a plain stdClass built in a test.
 */

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
