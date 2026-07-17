<?php

declare(strict_types=1);

namespace TsmlForUnity\Meetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Meetings\Interfaces\Meeting;
use Unity\Locations\Interfaces\Location;

/**
 * Class Meeting
 *
 * Implementation of Meeting.
 *
 * Immutable: properties are `readonly`, so a maintainer who adds a setter by
 * mistake gets a runtime error rather than a silent mutation. Meetings are
 * read-only in this suite anyway — they are owned by the TSML plugin and Unity
 * only reads them, so there is no revisor and no `with()`.
 *
 * The constructor has twelve required positional parameters, several of them
 * adjacent same-typed strings (dayOfWeek/time/endTime, onlineLink/onlineNotes/
 * updated). Its one construction site — TsmlMeetingFactory::createFromSource —
 * passes named arguments precisely because a positional call there would
 * silently rebind on any future mid-list parameter insertion, and PHPStan
 * cannot catch a same-typed swap.
 */
class TsmlMeeting implements Meeting
{
    /**
     * Constructor.
     *
     * @param int           $id          Meeting ID
     * @param string        $name        Meeting name
     * @param string        $slug        Meeting slug
     * @param Location|null $location    Meeting location
     * @param string        $url         Meeting URL
     * @param int           $day         Meeting day
     * @param string        $dayOfWeek   Day of the week
     * @param string        $time        Meeting start time
     * @param string        $endTime     Meeting end time
     * @param array         $types       Meeting types
     * @param string        $state       Meeting state
     * @param bool          $online      Whether meeting is online
     * @param array         $contacts    Array of Contact objects
     * @param array         $meta        Meta data
     * @param string        $onlineLink  Online meeting link
     * @param string        $onlineNotes Online meeting notes
     * @param string        $updated     Last updated datetime string
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $slug,
        private readonly ?Location $location,
        private readonly string $url,
        private readonly int $day,
        private readonly string $dayOfWeek,
        private readonly string $time,
        private readonly string $endTime,
        private readonly array $types,
        private readonly string $state,
        private readonly bool $online,
        private readonly array $contacts = [],
        private readonly array $meta = [],
        private readonly string $onlineLink = '',
        private readonly string $onlineNotes = '',
        private readonly string $updated = ''
    ) {
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
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocation(): ?Location
    {
        return $this->location;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function getDay(): int
    {
        return $this->day;
    }

    /**
     * {@inheritdoc}
     */
    public function getDayOfWeek(): string
    {
        return $this->dayOfWeek;
    }

    /**
     * {@inheritdoc}
     */
    public function getTime(): string
    {
        return $this->time;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndTime(): string
    {
        return $this->endTime;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes(): array
    {
        return $this->types;
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
    public function isOnline(): bool
    {
        return $this->online;
    }

    /**
     * {@inheritdoc}
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnlineLink(): string
    {
        return $this->onlineLink;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnlineNotes(): string
    {
        return $this->onlineNotes;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdated(): string
    {
        return $this->updated;
    }
}
