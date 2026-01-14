<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Contact\ContactFactory;
use Unity\Contact\Interfaces\ContactFactoryInterface;
use Unity\Contact\Interfaces\ContactInterface;
use Unity\Groups\Group;
use Unity\Groups\Interfaces\GroupFactoryInterface;
use Unity\Groups\Interfaces\GroupInterface;

/**
 * Factory class for creating TsmlGroup objects from TSML data
 * 
 * This factory creates Group objects from the 12 Step Meeting List plugin's
 * tsml_group custom post type.
 */
class TsmlGroupFactory implements GroupFactoryInterface
{
    private ?ContactFactoryInterface $contactFactory = null;

    /**
     * TsmlGroupFactory constructor.
     *
     * @param ContactFactoryInterface|null $contactFactory Optional contact factory for creating contacts
     */
    public function __construct(?ContactFactoryInterface $contactFactory = null)
    {
        $this->contactFactory = $contactFactory;
    }

    /**
     * Set the contact factory
     *
     * @param ContactFactoryInterface $contactFactory The contact factory
     * @return void
     */
    public function setContactFactory(ContactFactoryInterface $contactFactory): void
    {
        $this->contactFactory = $contactFactory;
    }

    /**
     * Get the contact factory, creating a default one if not set
     *
     * @return ContactFactoryInterface
     */
    private function getContactFactory(): ContactFactoryInterface
    {
        if ($this->contactFactory === null) {
            $this->contactFactory = new ContactFactory();
        }
        return $this->contactFactory;
    }

    /**
     * Create a group from a WordPress post ID
     * 
     * @param int $sourceId The WordPress post ID
     * @return GroupInterface|null The created group or null if not found/invalid
     */
    public function createFromSource(int $sourceId): ?GroupInterface
    {
        if (!function_exists('get_post')) {
            $this->logError('Required WordPress function get_post is not available');
            return null;
        }

        $post = get_post($sourceId);

        if (!$post || $post->post_type !== TsmlGroupFields::GROUP_POST_TYPE) {
            return null;
        }

        $meta = $this->getPostMeta($sourceId);
        $contacts = $this->extractContacts($meta);
        $meetingIds = $this->getMeetingIdsForGroup($sourceId);
        $link = $this->getPermalink($sourceId);

        return new Group(
            id: $sourceId,
            title: $post->post_title ?? '',
            email: $this->getMetaField($meta, TsmlGroupFields::EMAIL, ''),
            meetingIds: $meetingIds,
            link: $link,
            groupNotes: $this->getMetaField($meta, TsmlGroupFields::GROUP_NOTES, ''),
            website: $this->getMetaField($meta, TsmlGroupFields::WEBSITE, ''),
            phone: $this->getMetaField($meta, TsmlGroupFields::PHONE, ''),
            venmo: $this->getMetaField($meta, TsmlGroupFields::VENMO, ''),
            paypal: $this->getMetaField($meta, TsmlGroupFields::PAYPAL, ''),
            square: $this->getMetaField($meta, TsmlGroupFields::SQUARE, ''),
            districtId: $this->getDistrictId($meta),
            lastContact: $this->getMetaField($meta, TsmlGroupFields::LAST_CONTACT, null),
            contacts: $contacts
        );
    }

    /**
     * Get post meta for a post ID
     * 
     * @param int $postId The post ID
     * @return array Post meta data
     */
    private function getPostMeta(int $postId): array
    {
        if (!function_exists('get_post_custom')) {
            return [];
        }

        $meta = get_post_custom($postId);
        return is_array($meta) ? $meta : [];
    }

    /**
     * Get a meta field value with a default
     * 
     * @param array  $meta    Meta data array
     * @param string $field   Field name
     * @param mixed  $default Default value if field not found
     * @return mixed Field value or default
     */
    private function getMetaField(array $meta, string $field, mixed $default = ''): mixed
    {
        if (!isset($meta[$field]) || !is_array($meta[$field]) || empty($meta[$field])) {
            return $default;
        }

        $value = $meta[$field][0] ?? $default;
        
        // Handle serialized data
        if (function_exists('maybe_unserialize')) {
            $value = maybe_unserialize($value);
        }

        return $value;
    }

    /**
     * Get the district ID from meta
     * 
     * @param array $meta Meta data array
     * @return int|null District ID or null
     */
    private function getDistrictId(array $meta): ?int
    {
        $districtId = $this->getMetaField($meta, TsmlGroupFields::DISTRICT_ID, null);
        
        if ($districtId === null || $districtId === '') {
            return null;
        }

        return (int) $districtId;
    }

    /**
     * Extract contact information from post meta
     * 
     * @param array $meta Post meta data
     * @return ContactInterface[] Array of Contact objects
     */
    private function extractContacts(array $meta): array
    {
        $contacts = [];
        $factory = $this->getContactFactory();

        for ($i = 1; $i <= TsmlGroupFields::MAX_CONTACTS; $i++) {
            $name = $this->getMetaField($meta, TsmlGroupFields::CONTACT_PREFIX . $i . '_name', '');
            $email = $this->getMetaField($meta, TsmlGroupFields::CONTACT_PREFIX . $i . '_email', '');
            $phone = $this->getMetaField($meta, TsmlGroupFields::CONTACT_PREFIX . $i . '_phone', '');

            if (!empty($name) || !empty($email) || !empty($phone)) {
                $contacts[] = $factory->create(
                    (string) $name,
                    (string) $email,
                    (string) $phone
                );
            }
        }

        return $contacts;
    }

    /**
     * Get meeting IDs associated with this group
     * 
     * In TSML, meetings reference their group via the group_id post_meta field.
     * 
     * @param int $groupId The group post ID
     * @return array Array of meeting post IDs
     */
    private function getMeetingIdsForGroup(int $groupId): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $meetings = get_posts([
            'post_type' => 'tsml_meeting',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'group_id',
                    'value' => $groupId,
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ]);

        return is_array($meetings) ? $meetings : [];
    }

    /**
     * Get the permalink for a post
     * 
     * @param int $postId The post ID
     * @return string The permalink or empty string
     */
    private function getPermalink(int $postId): string
    {
        if (!function_exists('get_permalink')) {
            return '';
        }

        $permalink = get_permalink($postId);
        return is_string($permalink) ? $permalink : '';
    }

    /**
     * Log an error message
     * 
     * @param string $message The error message
     * @param array  $context Additional context
     */
    private function logError(string $message, array $context = []): void
    {
        if (!isset($context['class'])) {
            $context['class'] = __CLASS__;
        }

        $contextStr = empty($context) ? '' : ' ' . json_encode($context);

        if (function_exists('error_log')) {
            error_log("[TSML Group Factory Error] {$message}{$contextStr}");
        }
    }
}
