<?php

declare(strict_types=1);

namespace TsmlForUnity\Contacts;

use Unity\Contacts\Interfaces\Contact;

/**
 * Class TsmlContact
 *
 * Represents a single contact.
 */
class TsmlContact implements Contact
{
    private string $name;
    private string $email;
    private string $phone;
    private string $updated;

    /**
     * Constructor.
     *
     * @param string $name The contact's name.
     * @param string $email The contact's email address.
     * @param string $phone The contact's phone number.
     * @param string $updated Last updated datetime string.
     */
    public function __construct(string $name = '', string $email = '', string $phone = '', string $updated = '')
    {
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->updated = $updated;
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
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdated(): string
    {
        return $this->updated;
    }
}
