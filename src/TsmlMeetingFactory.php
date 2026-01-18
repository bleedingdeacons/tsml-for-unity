<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Contact\ContactFactory;
use Unity\Contact\Interfaces\ContactFactoryInterface;
use Unity\Contact\Interfaces\ContactInterface;
use Unity\Locations\Location;
use Unity\Locations\Interfaces\LocationRepositoryInterface;
use Unity\Locations\Interfaces\LocationInterface;
use Unity\Meetings\Interfaces\MeetingFactoryInterface;
use Unity\Meetings\Interfaces\MeetingInterface;
use Unity\Meetings\Meeting;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class TsmlMeetingFactory
 *
 * Implementation of Unity's MeetingFactoryInterface that creates Meeting objects
 * from TSML (12 Step Meeting List) data format.
 */
class TsmlMeetingFactory implements MeetingFactoryInterface
{
    private const MAX_CONTACTS = 3;

    private ?LocationRepositoryInterface $locationRepository = null;
    private ?ContactFactoryInterface $contactFactory = null;

    /**
     * Days of the week mapping (TSML uses 0-6, but we use 1-7)
     */
    private const DAYS_OF_WEEK = [
        '0' => 'Sunday',
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
    ];

    /**
     * Meeting type codes lookup table
     * Maps TSML type codes to human-readable names
     */
    private const TYPES_LOOKUP = [
        '12x12' => '12 Steps & 12 Traditions',
        'ABSI' => 'Accessible for Blind or Seriously Impaired',
        'AL-AN' => 'Concurrent with Al-Anon',
        'AL' => 'Concurrent with Alateen',
        'ASL' => 'American Sign Language',
        'BA' => 'Babysitting Available',
        'B' => 'Big Book',
        'H' => 'Birthday',
        'BRK' => 'Breakfast',
        'C' => 'Closed',
        'CAN' => 'Candlelight',
        'CF' => 'Child-Friendly',
        'DIAL' => 'Dial-In',
        'DR' => 'Daily Reflections',
        'D' => 'Discussion',
        'GL' => 'Gay/Lesbian',
        'GR' => 'Grapevine',
        'ITA' => 'Italian',
        'JA' => 'Japanese',
        'KOR' => 'Korean',
        'L' => 'Literature',
        'LGBTQ' => 'LGBTQ',
        'LIT' => 'Literature',
        'LS' => 'Living Sober',
        'MED' => 'Meditation',
        'M' => 'Men',
        'N' => 'Native American',
        'NDG' => 'Non-Designated Smoking/Vaping',
        'O' => 'Open',
        'OUT' => 'Outdoor',
        'POC' => 'People of Color',
        'POL' => 'Polish',
        'POR' => 'Portuguese',
        'P' => 'Professionals',
        'RUS' => 'Russian',
        'SM' => 'Smoking Permitted',
        'S' => 'Spanish',
        'SP' => 'Speaker',
        'ST' => 'Step Study',
        'TR' => 'Tradition Study',
        'TC' => 'Location Temporarily Closed',
        'T' => 'Transgender',
        'X' => 'Wheelchair Access',
        'XS' => 'Excess Stairs',
        'W' => 'Women',
        'Y' => 'Young People',
        'BE' => 'Beginner',
        'BT' => 'Basic Text',
        'CB' => 'Came to Believe',
        'CW' => 'Children Welcome',
        'CH' => 'Closed Holidays',
        'CL' => 'Candlelight',
        'ESH' => 'Experience, Strength & Hope',
        'EW' => 'Emotional Wellness',
        'FF' => 'Fragrance Free',
        'FR' => 'French',
        'G' => 'German',
        'HA' => 'Hawaiian',
        'HE' => 'Hebrew',
        'IP' => 'IP Study',
        'JT' => 'Just for Today',
        'NC' => 'No Children',
        'NS' => 'Non-Smoking',
        'QA' => 'Q&A',
        'RF' => 'Rotating Format',
        'SG' => 'Step Working Guide',
        'SH' => 'Spanish/Hispanic',
        'SK' => 'Speaker/Discussion',
        'SS' => 'Social Setting',
        'Ti' => 'Timer',
        'To' => 'Torch',
        'Tr' => 'Tradition',
        'Va' => 'Vape Friendly',
        'VM' => 'Virtual Meeting',
        'ONL' => 'Online',
        'OSM' => 'Online/Speaker Meeting',
    ];

    /**
     * TsmlMeetingFactory constructor.
     *
     * @param ContactFactoryInterface|null $contactFactory Optional contact factory for creating contacts
     * @param LocationRepositoryInterface|null $locationRepository Optional location repository for retrieving locations
     */
    public function __construct(
        ?ContactFactoryInterface $contactFactory = null,
        ?LocationRepositoryInterface $locationRepository = null
    ) {
        $this->contactFactory = $contactFactory;
        $this->locationRepository = $locationRepository;
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
     * Set the location repository
     *
     * @param LocationRepositoryInterface $locationRepository The location repository
     * @return void
     */
    public function setLocationRepository(LocationRepositoryInterface $locationRepository): void
    {
        $this->locationRepository = $locationRepository;
    }

    /**
     * Get the location repository, creating a default one if not set
     *
     * @return LocationRepositoryInterface|null
     */
    private function getLocationRepository(): ?LocationRepositoryInterface
    {
        if ($this->locationRepository === null && Plugin::unityLocationsAvailable()) {
            $this->locationRepository = new TsmlLocationRepository();
        }
        return $this->locationRepository;
    }

    /**
     * Create a Meeting object from TSML source data.
     *
     * @param array<string, mixed> $source The meeting source data.
     * @return MeetingInterface|null Meeting object or null if creation fails.
     * @throws InvalidArgumentException If source data is invalid.
     */
    public function createFromSource(array $source): ?MeetingInterface
    {
        if (empty($source) || !is_array($source)) {
            return null;
        }

        $requiredFields = ['id', 'name', 'slug'];
        foreach ($requiredFields as $field) {
            if (!isset($source[$field])) {
                $this->logError("Missing required field: {$field}");
                return null;
            }
        }

        try {
            $id = (int)($source['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException("Invalid meeting ID: {$id}");
            }

            $name = $source['name'];
            $slug = $source['slug'];

            // Resolve location using LocationRepository if available
            $locationData = $this->resolveLocation($source);

            // Create Location object from location data
            $location = $this->createLocationFromData($locationData, $source);

            if (!function_exists('get_permalink') || !function_exists('get_post_status') || !function_exists('get_post') || !function_exists('is_wp_error') || !function_exists('get_post_meta')) {
                throw new RuntimeException("Required WordPress functions are not available");
            }

            $url = get_permalink($id);
            $state = get_post_status($id);

            $day = (int)$this->getMeetingField($source, 'day', 0);
            $time = $this->getMeetingField($source, 'time', '');
            $endTime = $this->getMeetingField($source, 'end_time', '');

            $dayOfWeek = '';
            $dayKey = (string)$day;
            if (isset(self::DAYS_OF_WEEK[$dayKey])) {
                $dayOfWeek = self::DAYS_OF_WEEK[$dayKey];
            }

            $online = $this->getMeetingField($source, 'attendance_option') === 'online';

            // Get types from serialized 'types' meta field (TSML stores types as serialized array of codes)
            $types = [];
            if (function_exists('get_post_meta')) {
                $typesMetaRaw = get_post_meta($id, 'types', true);

                if (!empty($typesMetaRaw) && is_array($typesMetaRaw)) {
                    // Check if 'ONL' (Online) code is present
                    if (in_array('ONL', $typesMetaRaw, true)) {
                        $online = true;
                    }

                    // Convert type codes to full names using the TYPES_LOOKUP map
                    foreach ($typesMetaRaw as $typeCode) {
                        if (isset(self::TYPES_LOOKUP[$typeCode])) {
                            $types[] = self::TYPES_LOOKUP[$typeCode];
                        }
                    }
                }
            }

            // Also check if types are in the source array (for compatibility)
            if (isset($source['types']) && is_array($source['types'])) {
                $types = array_merge($types, $source['types']);
                $types = array_unique($types);
            }

            if (!empty($types)) {
                $types = $this->formatMeetingTypes($types);
            }

            // Check if 'Online' type exists (before we remove it from the array)
            $key = array_search('Online', $types);
            if ($key !== false) {
                $online = true;  // If 'Online' type exists, mark as online
                unset($types[$key]);
                $types = array_values($types);
            }

            if (!function_exists('get_post_custom')) {
                throw new RuntimeException("Required WordPress function get_post_custom is not available");
            }

            $meta = get_post_custom($id);
            if (!is_array($meta)) {
                $meta = [];
            }

            $processedMeta = $this->processMeta($meta);
            $contacts = $this->extractContacts($meta);

            $onlineLink = $this->getMetaField($meta, 'conference_url', '');
            $onlineNotes = $this->getMetaField($meta, 'conference_url_notes', '');

            return new Meeting(
                $id,
                $name,
                $slug,
                $location,
                $url,
                (int)$day,
                $dayOfWeek,
                $time,
                $endTime,
                $types,
                $state,
                $online,
                $contacts,
                $processedMeta,
                $onlineLink,
                $onlineNotes
            );
        } catch (Exception $e) {
            $this->logError('Error creating Meeting: ' . $e->getMessage(), [
                'class' => __CLASS__,
                'method' => __METHOD__,
                'source' => $source
            ]);
            return null;
        }
    }

    /**
     * Create a Location object from location data array
     *
     * @param array<string, string> $locationData Location data from resolveLocation
     * @param array<string, mixed> $source Original source data for additional fields
     * @return LocationInterface|null Location object or null if no location data
     */
    private function createLocationFromData(array $locationData, array $source): ?LocationInterface
    {
        // If no location name, return null
        if (empty($locationData['name']) && empty($locationData['address'])) {
            return null;
        }

        $locationId = (int)($source['location_id'] ?? 0);
        $latitude = isset($source['latitude']) ? (float)$source['latitude'] : null;
        $longitude = isset($source['longitude']) ? (float)$source['longitude'] : null;
        $timezone = $source['timezone'] ?? '';
        $link = '';

        // Get location permalink if we have a location ID
        if ($locationId > 0 && function_exists('get_permalink')) {
            $link = get_permalink($locationId) ?: '';
        }

        return new Location(
            $locationId,
            $locationData['name'],
            $locationData['address'],
            $locationData['city'],
            $locationData['state'],
            $locationData['postalCode'],
            $locationData['country'],
            $locationData['region'],
            $locationData['notes'],
            $link,
            $latitude,
            $longitude,
            $timezone,
            [] // meetingIds - not needed here as this is from meeting's perspective
        );
    }

    /**
     * Resolve location data from source using LocationRepository if available
     *
     * @param array<string, mixed> $source The meeting source data
     * @return array<string, string> Location data array
     */
    private function resolveLocation(array $source): array
    {
        $locationData = [
            'name' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'postalCode' => '',
            'country' => '',
            'region' => '',
            'notes' => '',
        ];

        // Try to get location from location_id using LocationRepository
        if (isset($source['location_id']) && !empty($source['location_id'])) {
            $locationId = (int)$source['location_id'];
            if ($locationId > 0) {
                $repository = $this->getLocationRepository();
                if ($repository !== null) {
                    $location = $repository->findById($locationId);
                    if ($location !== null) {
                        $locationData['name'] = $location->getName();
                        $locationData['address'] = $location->getAddress();
                        $locationData['city'] = $location->getCity();
                        $locationData['state'] = $location->getState();
                        $locationData['postalCode'] = $location->getPostalCode();
                        $locationData['country'] = $location->getCountry();
                        $locationData['region'] = $location->getRegion();
                        $locationData['notes'] = $location->getNotes();
                        return $locationData;
                    }
                }
            }
        }

        // Fallback to source location field if no location resolved
        if (isset($source['location'])) {
            $locationData['name'] = (string)$source['location'];
        }

        // Also check for inline location data in source
        if (isset($source['formatted_address'])) {
            $locationData['address'] = (string)$source['formatted_address'];
        }
        if (isset($source['city'])) {
            $locationData['city'] = (string)$source['city'];
        }
        if (isset($source['state'])) {
            $locationData['state'] = (string)$source['state'];
        }
        if (isset($source['postal_code'])) {
            $locationData['postalCode'] = (string)$source['postal_code'];
        }
        if (isset($source['country'])) {
            $locationData['country'] = (string)$source['country'];
        }
        if (isset($source['region'])) {
            $locationData['region'] = (string)$source['region'];
        }
        if (isset($source['location_notes'])) {
            $locationData['notes'] = (string)$source['location_notes'];
        }

        return $locationData;
    }

    /**
     * Format meeting types by converting type codes to their full names.
     *
     * @param array<string> $types Array of type codes.
     * @return array<string> Array of formatted type names.
     */
    private function formatMeetingTypes(array $types): array
    {
        $formattedTypes = [];
        foreach ($types as $typeCode) {
            if (isset(self::TYPES_LOOKUP[$typeCode])) {
                $formattedTypes[] = self::TYPES_LOOKUP[$typeCode];
            } else {
                $formattedTypes[] = $typeCode;
            }
        }

        return $formattedTypes;
    }

    /**
     * Extract contact information from post meta.
     *
     * @param array<string, array<string>> $meta Post meta data.
     * @return array<ContactInterface> Array of Contact objects.
     */
    private function extractContacts(array $meta): array
    {
        $contacts = [];
        $factory = $this->getContactFactory();

        for ($count = 1; $count <= self::MAX_CONTACTS; $count++) {
            $name = $this->getMetaField($meta, "contact_{$count}_name", '');
            $email = $this->getMetaField($meta, "contact_{$count}_email", '');
            $phone = $this->getMetaField($meta, "contact_{$count}_phone", '');

            if (!empty($name) || !empty($email) || !empty($phone)) {
                $contacts[] = $factory->create($name, $email, $phone);
            }
        }

        return $contacts;
    }

    /**
     * Get a meeting field with a default value if not set.
     *
     * @param array<string, mixed> $source Source data.
     * @param string $field Field name.
     * @param mixed $default Default value.
     * @return mixed Field value or default.
     */
    private function getMeetingField(array $source, string $field, mixed $default = ''): mixed
    {
        return $source[$field] ?? $default;
    }

    /**
     * Get a meta field with a default value if not set.
     *
     * @param array<string, array<string>> $meta Meta data.
     * @param string $field Field name.
     * @param mixed $default Default value.
     * @return mixed Field value or default.
     */
    private function getMetaField(array $meta, string $field, mixed $default = ''): mixed
    {
        return $meta[$field][0] ?? $default;
    }

    /**
     * Process meta to convert any object references to IDs.
     *
     * @param array<string, array<mixed>> $meta Raw meta data.
     * @return array<string, array<mixed>> Processed meta data.
     */
    private function processMeta(array $meta): array
    {
        $processedMeta = [];

        if (!function_exists('is_serialized') || !function_exists('maybe_unserialize')) {
            $this->logError("Required WordPress serialization functions are not available");
            return $meta;
        }

        foreach ($meta as $key => $values) {
            $processedValues = [];

            foreach ($values as $value) {
                if (is_serialized($value)) {
                    try {
                        $unserialized = maybe_unserialize($value);

                        if (is_object($unserialized)) {
                            if (isset($unserialized->ID)) {
                                $processedValues[] = $unserialized->ID;
                            } elseif (isset($unserialized->id)) {
                                $processedValues[] = $unserialized->id;
                            } elseif (method_exists($unserialized, 'getId')) {
                                $processedValues[] = $unserialized->getId();
                            } elseif (method_exists($unserialized, 'get_id')) {
                                $processedValues[] = $unserialized->get_id();
                            } else {
                                $processedValues[] = get_class($unserialized);
                            }
                        } elseif (is_array($unserialized)) {
                            $processedValues[] = $this->processNestedValues($unserialized);
                        } else {
                            $processedValues[] = $unserialized;
                        }
                    } catch (Exception $e) {
                        $this->logError('Error unserializing meta data: ' . $e->getMessage(), [
                            'key' => $key,
                            'value' => $value
                        ]);
                        $processedValues[] = $value;
                    }
                } else {
                    $processedValues[] = $value;
                }
            }

            $processedMeta[$key] = $processedValues;
        }

        return $processedMeta;
    }

    /**
     * Process nested values recursively to convert objects to IDs.
     *
     * @param mixed $data Data to process.
     * @return mixed Processed data.
     */
    private function processNestedValues(mixed $data): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->processNestedValues($value);
            }
            return $result;
        } elseif (is_object($data)) {
            if (isset($data->ID)) {
                return $data->ID;
            } elseif (isset($data->id)) {
                return $data->id;
            } elseif (method_exists($data, 'getId')) {
                return $data->getId();
            } elseif (method_exists($data, 'get_id')) {
                return $data->get_id();
            } else {
                return get_class($data);
            }
        } else {
            return $data;
        }
    }

    /**
     * Log an error message with context.
     *
     * @param string $message Error message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    private function logError(string $message, array $context = []): void
    {
        if (!isset($context['class'])) {
            $context['class'] = __CLASS__;
        }

        if (!isset($context['method'])) {
            $context['method'] = __METHOD__;
        }

        $contextStr = empty($context) ? '' : ' ' . wp_json_encode($context);

        if (function_exists('error_log')) {
            error_log("[TSML Meeting Factory Error] {$message}{$contextStr}");
        }
    }

    /**
     * Get the type code for a given type name.
     *
     * @param string $typeName The type name to look up.
     * @return string|null The type code or null if not found.
     */
    public function getTypeCode(string $typeName): ?string
    {
        $flipped = array_flip(self::TYPES_LOOKUP);
        return $flipped[$typeName] ?? null;
    }

    /**
     * Get the type name for a given type code.
     *
     * @param string $typeCode The type code to look up.
     * @return string|null The type name or null if not found.
     */
    public function getTypeName(string $typeCode): ?string
    {
        return self::TYPES_LOOKUP[$typeCode] ?? null;
    }

    /**
     * Get all available type codes and their names.
     *
     * @return array<string, string> Associative array of type codes to names.
     */
    public function getAllTypes(): array
    {
        return self::TYPES_LOOKUP;
    }

    /**
     * Get day of week name from day number.
     *
     * @param int $day Day number (0-6).
     * @return string|null Day name or null if invalid.
     */
    public function getDayName(int $day): ?string
    {
        return self::DAYS_OF_WEEK[(string)$day] ?? null;
    }
}