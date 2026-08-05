<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Groups\Interfaces\GroupView;

/**
 * Concrete TSML Group View class
 */
class TsmlGroupView implements GroupView
{
    private int $id;
    private string $title;
    private string $email;
    /** @var array<int, \Unity\Meetings\Interfaces\Meeting> */
    private array $meetings;
    private string $link;
    /** @var array<int, \Unity\Contacts\Interfaces\Contact> */
    private array $contacts;
    /** @var array<int, \Unity\Members\Interfaces\Member> */
    private array $members;
    /**
     * TsmlGroupView constructor
     *
     * @param int    $id       Post ID
     * @param string $title    Group title
     * @param string $email    Email address
     * @param array<int, \Unity\Meetings\Interfaces\Meeting>  $meetings Array of Meeting objects
     * @param string $link     Link URL
     * @param array<int, \Unity\Contacts\Interfaces\Contact>  $contacts Array of Contact objects
     * @param array<int, \Unity\Members\Interfaces\Member>  $members  Array of Member objects
     */
    public function __construct(
        int $id = 0,
        string $title = '',
        string $email = '',
        array $meetings = [],
        string $link = '',
        array $contacts = [],
        array $members = [],
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->email = $email;
        $this->meetings = $meetings;
        $this->link = $link;
        $this->contacts = $contacts;
        $this->members = $members;
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
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeetings(): array
    {
        return $this->meetings;
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
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * {@inheritdoc}
     */
    public function getMembers(): array
    {
        return $this->members;
    }
}
