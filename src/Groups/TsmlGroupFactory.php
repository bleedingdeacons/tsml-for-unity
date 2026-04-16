<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Contacts\Interfaces\ContactFactory;
use Unity\Contacts\Interfaces\Contact;
use Unity\Groups\Interfaces\GroupFactory;
use Unity\Groups\Interfaces\Group;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingRepository;

/**
 * Factory class for creating TsmlGroup objects from TSML data
 *
 * This factory creates Group objects from the 12 Step Meeting List plugin's
 * tsml_group custom post type.
 */
class TsmlGroupFactory implements GroupFactory
{
    private ?ContactFactory $contactFactory = null;
    private ?MeetingRepository $meetingRepository = null;

    /**
     * TsmlGroupFactory constructor.
     *
     * @param ContactFactory|null $contactFactory Optional contact factory for creating contacts
     * @param MeetingRepository|null $meetingRepository Optional meeting repository for retrieving meetings
     */
    public function __construct(
        ?ContactFactory $contactFactory = null,
        ?MeetingRepository $meetingRepository = null
    ) {
        $this->contactFactory = $contactFactory;
        $this->meetingRepository = $meetingRepository;
    }

    /**
     * Set the contact factory
     *
     * @param ContactFactory $contactFactory The contact factory
     * @return void
     */
    public function setContactFactory(ContactFactory $contactFactory): void
    {
        $this->contactFactory = $contactFactory;
    }

    /**
     * Set the meeting repository
     *
     * @param MeetingRepository $meetingRepository The meeting repository
     * @return void
     */
    public function setMeetingRepository(MeetingRepository $meetingRepository): void
    {
        $this->meetingRepository = $meetingRepository;
    }

    /**
     * Get the contact factory, creating a default one if not set
     *
     * @return ContactFactory
     */
    private function getContactFactory(): ContactFactory
    {
        if ($this->contactFactory === null) {
            $this->contactFactory = new TsmlContactFactory();
        }
        return $this->contactFactory;
    }

    /**
     * Get the meeting repository from the container or injected dependency
     *
     * @return MeetingRepository|null
     */
    private function getMeetingRepository(): ?MeetingRepository
    {
        return $this->meetingRepository;
    }

    /**
     * Create a group from a WordPress post ID
     *
     * @param int $sourceId The WordPress post ID
     * @return Group|null The created group or null if not found/invalid
     */
    public function createFromSource(int $sourceId): ?Group
    {
        if (!function_exists('get_post')) {
            $this->logError('Required WordPress function get_post is not available');
            return null;
        }

        $post = get_post($sourceId);

        if (!$post || $post->post_type !== TsmlGroupFields::POST_TYPE) {
            return null;
        }

        $meta = $this->getPostMeta($sourceId);
        $meetings = $this->getMeetingsForGroup($sourceId);
        $contacts = $this->collectContacts($meta, $meetings);
        $link = $this->getPermalink($sourceId);

        return new TsmlGroup(
            id: $sourceId,
            title: html_entity_decode($post->post_title ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
            contacts: $contacts,
            updated: $post->post_modified_gmt ?? ''
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
     * Collect unique contacts from both group meta and meetings
     *
     * Reads contacts stored on the group post meta and collects contacts
     * from all meetings in the group, deduplicating by name+email+phone.
     *
     * @param array $meta Group post meta data
     * @param Meeting[] $meetings Array of Meeting objects
     * @return Contact[] Array of unique Contact objects
     */
    private function collectContacts(array $meta, array $meetings): array
    {
        $contacts = [];
        $seen = [];

        // First, collect contacts from the group's own meta fields
        $factory = $this->getContactFactory();

        for ($i = 1; $i <= TsmlGroupFields::MAX_CONTACTS; $i++) {
            $name = trim((string) $this->getMetaField($meta, TsmlGroupFields::CONTACT_PREFIX . $i . '_name', ''));
            $email = trim((string) $this->getMetaField($meta, TsmlGroupFields::CONTACT_PREFIX . $i . '_email', ''));
            $phone = trim((string) $this->getMetaField($meta, TsmlGroupFields::CONTACT_PREFIX . $i . '_phone', ''));

            if ($name === '' && $email === '' && $phone === '') {
                continue;
            }

            $key = strtolower($name) . '|' . strtolower($email) . '|' . strtolower($phone);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $contacts[] = $factory->create($name, $email, $phone);
            }
        }

        // Then, collect contacts from meetings (deduplicating against group contacts)
        foreach ($meetings as $meeting) {
            foreach ($meeting->getContacts() as $contact) {
                $key = strtolower(trim($contact->getName()))
                    . '|' . strtolower(trim($contact->getEmail()))
                    . '|' . strtolower(trim($contact->getPhone()));

                if ($key === '||') {
                    continue;
                }

                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $contacts[] = $contact;
                }
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
     * @return Meeting[] Array of Meeting objects
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

        \TsmlForUnity\Plugin::logError("[TSML Group Factory Error] {$message}{$contextStr}");
    }
}