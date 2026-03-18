<?php

declare(strict_types=1);

namespace TsmlForUnity\Locations;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Locations\Interfaces\Location;

/**
 * Locations entity class
 *
 * Implements Location with all fields needed for location management
 * including address information and geographic coordinates.
 */
class TsmlLocation implements Location
{
    private int $id;
    private string $name;
    private string $address;
    private string $city;
    private string $state;
    private string $postalCode;
    private string $country;
    private string $region;
    private string $notes;
    private string $link;
    private ?float $latitude;
    private ?float $longitude;
    private string $timezone;
    private array $meetingIds;
    private string $updated;

    /**
     * Locations constructor
     *
     * @param int        $id         WordPress post ID
     * @param string     $name       Locations name/title
     * @param string     $address    Street address
     * @param string     $city       City name
     * @param string     $state      State/province
     * @param string     $postalCode Postal/zip code
     * @param string     $country    Country name
     * @param string     $region     Region name
     * @param string     $notes      Locations notes/description
     * @param string     $link       Permalink URL
     * @param float|null $latitude   Latitude coordinate
     * @param float|null $longitude  Longitude coordinate
     * @param string     $timezone   Timezone identifier
     * @param array      $meetingIds Associated meeting post IDs
     * @param string     $updated    Last updated datetime string
     */
    public function __construct(
        int $id = 0,
        string $name = '',
        string $address = '',
        string $city = '',
        string $state = '',
        string $postalCode = '',
        string $country = '',
        string $region = '',
        string $notes = '',
        string $link = '',
        ?float $latitude = null,
        ?float $longitude = null,
        string $timezone = '',
        array $meetingIds = [],
        string $updated = ''
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->address = $address;
        $this->city = $city;
        $this->state = $state;
        $this->postalCode = $postalCode;
        $this->country = $country;
        $this->region = $region;
        $this->notes = $notes;
        $this->link = $link;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->timezone = $timezone;
        $this->meetingIds = $meetingIds;
        $this->updated = $updated;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * {@inheritdoc}
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * {@inheritdoc}
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * {@inheritdoc}
     */
    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * {@inheritdoc}
     */
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * {@inheritdoc}
     */
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeetingIds(): array
    {
        return $this->meetingIds;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return $this->id > 0
            && !empty($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedAddress(): string
    {
        $parts = [];

        if (!empty($this->address)) {
            $parts[] = $this->address;
        }

        $cityStateZip = [];
        if (!empty($this->city)) {
            $cityStateZip[] = $this->city;
        }
        if (!empty($this->state)) {
            $cityStateZip[] = $this->state;
        }
        if (!empty($this->postalCode)) {
            $cityStateZip[] = $this->postalCode;
        }

        if (!empty($cityStateZip)) {
            $parts[] = implode(', ', $cityStateZip);
        }

        if (!empty($this->country)) {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdated(): string
    {
        return $this->updated;
    }
}