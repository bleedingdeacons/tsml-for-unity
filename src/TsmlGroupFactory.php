<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Contact\ContactFactory;
use Unity\Contact\Interfaces\ContactFactoryInterface;
use Unity\Contact\Interfaces\ContactInterface;
use Unity\Groups\Group;
use Unity\Groups\Interfaces\GroupFactoryInterface;
use Unity\Groups\Interfaces\GroupInterface;
use Unity\Meetings\Interfaces\MeetingRepositoryInterface;
use Unity\Meetings\Interfaces\MeetingInterface;

/**
 * Factory class for creating TsmlGroup objects from TSML data
 *
 * This factory creates Group objects from the 12 Step Meeting List plugin's
 * tsml_group custom post type.
 */
class TsmlGroupFactory implements GroupFactoryInterface
{
    private ?ContactFactoryInterface $contactFactory = null;
    private ?MeetingRepositoryInterface $meetingRepository = null;

    /**
     * TsmlGroupFactory constructor.
     *
     * @param ContactFactoryInterface|null $contactFactory Optional contact factory for creating contacts
     * @param MeetingRepositoryInterface|null $meetingRepository Optional meeting repository for retrieving meetings
     */
    public function __construct(
        ?ContactFactoryInterface $contactFactory = null,
        ?MeetingRepositoryInterface $meetingRepository = null
    ) {
        $this->contactFactory = $contactFactory;
        $this->meetingRepository = $meetingRepository;
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
     * Set the meeting repository
     *
     * @param MeetingRepositoryInterface $meetingRepository The meeting repository
     * @return void
     */
    public function setMeetingRepository(MeetingRepositoryInterface $meetingRepository): void
    {
        $this->meetingRepository = $meetingRepository;
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
     * Get the meeting repository from the container or injected dependency
     *
     * @return MeetingRepositoryInterface|null
     */
    private function getMeetingRepository(): ?MeetingRepositoryInterface
    {
        return $this->meetingRepository;
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
        $meetings = $this->getMeetingsForGroup($sourceId);
        $link = $this->getPermalink($sourceId);

        return new TsmlGroup(
            id: $sourceId,
            title: $post->post_title ?? '',
            email: $this->getMetaField($meta, TsmlGroupFields::EMAIL, ''),
            meetings: $meetings,
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
     * Get meetings associated with this group
     *
     * Uses the MeetingRepository to retrieve meetings by group ID.
     *
     * @param int $groupId The group post ID
     * @return MeetingInterface[] Array of Meeting objects
     */
    private function getMeetingsForGroup(int $groupId): array
    {
        try {
            $repository = $this->getMeetingRepository();

            // If repository is not available, return empty array
            if ($repository === null) {
                $this->logError('MeetingRepository not available for group', [
                    'group_id' => $groupId
                ]);
                return [];
            }

            // Use the repository's method to find meetings by group ID
            return $repository->findByGroupId($groupId);
        } catch (\Exception $e) {
            // If repository is unavailable or throws an error, return empty array
            $this->logError('Failed to retrieve meetings for group: ' . $e->getMessage(), [
                'group_id' => $groupId
            ]);
            return [];
        }
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