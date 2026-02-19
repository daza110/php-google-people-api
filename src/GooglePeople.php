<?php

namespace RapidWeb\GooglePeopleAPI;

use Exception;
use RapidWeb\GoogleOAuth2Handler\GoogleOAuth2Handler;

class Contact
{
    public ?string $resourceName = null;
    public ?string $etag = null;
    public ?object $metadata = null;

    public array $addresses = [];
    public array $ageRanges = [];
    public array $biographies = [];
    public array $birthdays = [];
    public array $braggingRights = [];
    public array $coverPhotos = [];
    public array $emailAddresses = [];
    public array $events = [];
    public array $genders = [];
    public array $imClients = [];
    public array $interests = [];
    public array $locales = [];
    public array $memberships = [];
    public array $names = [];
    public array $nicknames = [];
    public array $occupations = [];
    public array $organizations = [];
    public array $phoneNumbers = [];
    public array $photos = [];
    public array $relations = [];
    public array $relationshipInterests = [];
    public array $relationshipStatuses = [];
    public array $residences = [];
    public array $skills = [];
    public array $taglines = [];
    public array $urls = [];

    public function __construct(private GooglePeople $googlePeople)
    {
    }
}

class GooglePeople
{
    private GoogleOAuth2Handler $googleOAuth2Handler;

    private const PERSON_FIELDS = [
        'addresses','ageRanges','biographies','birthdays','braggingRights','coverPhotos','emailAddresses',
        'events','genders','imClients','interests','locales','memberships','metadata','names','nicknames',
        'occupations','organizations','phoneNumbers','photos','relations','relationshipInterests',
        'relationshipStatuses','residences','skills','taglines','urls'
    ];

    private const UPDATE_PERSON_FIELDS = [
        'addresses','biographies','birthdays','braggingRights','emailAddresses','events','genders','imClients',
        'interests','locales','names','nicknames','occupations','organizations','phoneNumbers','relations',
        'residences','skills','urls'
    ];

    private const PEOPLE_BASE_URL = 'https://people.googleapis.com/v1/';

    public function __construct(GoogleOAuth2Handler $googleOAuth2Handler)
    {
        $this->googleOAuth2Handler = $googleOAuth2Handler;
    }

    private function convertResponseConnectionToContact(object $connection): Contact
    {
        $contact = new Contact($this);
        $contact->resourceName = $connection->resourceName ?? null;
        $contact->etag = $connection->etag ?? null;
        $contact->metadata = $connection->metadata ?? null;

        foreach (self::PERSON_FIELDS as $personField) {
            $contact->$personField = $connection->$personField ?? [];
        }

        return $contact;
    }

    public function get(string $resourceName): Contact
    {
        $url = self::PEOPLE_BASE_URL . $resourceName . '?personFields=' . implode(',', self::PERSON_FIELDS);
        $response = $this->googleOAuth2Handler->performRequest('GET', $url);
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() !== 200) {
            throw new Exception($body);
        }

        $contactObj = json_decode($body);
        return $this->convertResponseConnectionToContact($contactObj);
    }

    public function all(): array
    {
        $contacts = [];
        $pageToken = null;

        do {
            $url = self::PEOPLE_BASE_URL . 'people/me/connections?personFields=' . implode(',', self::PERSON_FIELDS) . '&pageSize=2000';
            if ($pageToken) {
                $url .= '&pageToken=' . $pageToken;
            }

            $response = $this->googleOAuth2Handler->performRequest('GET', $url);
            $body = $response->getBody()->getContents();

            if ($response->getStatusCode() !== 200) {
                throw new Exception($body);
            }

            $responseObj = json_decode($body);

            if (!empty($responseObj->connections)) {
                foreach ($responseObj->connections as $connection) {
                    $contacts[] = $this->convertResponseConnectionToContact($connection);
                }
            }

            $pageToken = $responseObj->nextPageToken ?? null;
        } while ($pageToken);

        return $contacts;
    }

    public function me(): Contact
    {
        return $this->get('people/me');
    }

    public function save(Contact $contact): Contact
    {
        $requestData = [];

        if ($contact->resourceName) {
            $method = 'PATCH';
            $url = self::PEOPLE_BASE_URL . $contact->resourceName . ':updateContact?updatePersonFields=' . implode(',', self::UPDATE_PERSON_FIELDS);
            $requestData['etag'] = $contact->etag;
            $requestData['metadata'] = $contact->metadata;
        } else {
            $method = 'POST';
            $url = self::PEOPLE_BASE_URL . 'people:createContact';
        }

        foreach (self::UPDATE_PERSON_FIELDS as $field) {
            $value = $contact->$field ?? null;
            if ($value !== null) {
                $requestData[$field] = $value;
            }
        }

        $response = $this->googleOAuth2Handler->performRequest($method, $url, json_encode($requestData));
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() !== 200) {
            throw new Exception($body);
        }

        $responseObj = json_decode($body);
        return $this->convertResponseConnectionToContact($responseObj);
    }

    public function delete(Contact $contact): bool
    {
        $url = self::PEOPLE_BASE_URL . $contact->resourceName . ':deleteContact';
        $response = $this->googleOAuth2Handler->performRequest('DELETE', $url);
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() !== 200) {
            throw new Exception($body);
        }

        return true;
    }
}
