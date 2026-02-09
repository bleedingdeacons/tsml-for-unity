<?php

declare(strict_types=1);

namespace TsmlForUnity\Contacts;

use Unity\Contacts\Interfaces\ContactFactory;
use Unity\Contacts\Interfaces\Contact;

/**
 * Class TsmlContactFactory
 *
 * Factory for creating TsmlContact objects.
 */
class TsmlContactFactory implements ContactFactory
{
    /**
     * {@inheritdoc}
     */
    public function createFromSource(array $source): Contact
    {
        return new TsmlContact(
            $source['name'] ?? '',
            $source['email'] ?? '',
            $source['phone'] ?? ''
        );
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name = '', string $email = '', string $phone = ''): Contact
    {
        return new TsmlContact($name, $email, $phone);
    }
}
