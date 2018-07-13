<?php

namespace rapidweb\googlecontacts\factories;

use rapidweb\googlecontacts\helpers\GoogleHelper;
use rapidweb\googlecontacts\objects\Contact;

abstract class ContactFactory
{
    public static function getAll($customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full?max-results=10000&updated-min=2007-03-16T00:00:00');

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContacts = simplexml_load_string($response);
        $xmlContacts->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactsArray = array();

        foreach ($xmlContacts->entry as $xmlContactsEntry) {
            $contactDetails = array();

            $contactDetails['id'] = (string) $xmlContactsEntry->id;
            $contactDetails['name'] = (string) $xmlContactsEntry->title;
            $contactDetails['content'] = (string) $xmlContactsEntry->content;

            foreach ($xmlContactsEntry->children() as $key => $value) {
                $attributes = $value->attributes();

                if ($key == 'link') {
                    if ($attributes['rel'] == 'edit') {
                        $contactDetails['editURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'self') {
                        $contactDetails['selfURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
                        $contactDetails['photoURL'] = (string) $attributes['href'];
                    }
                }
            }

            $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
            foreach ($contactGDNodes as $key => $value) {
                switch ($key) {
                    case 'organization':
                        $contactDetails[$key]['orgName'] = (string) $value->orgName;
                        $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                        break;
                    case 'email':
                        $attributes = $value->attributes();
                        $emailadress = (string) $attributes['address'];
                        $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                        $contactDetails[$key][] = ['type' => $emailtype, 'email' => $emailadress];
                        break;
                    case 'phoneNumber':
                        $attributes = $value->attributes();
                        //$uri = (string) $attributes['uri'];
                        $type = substr(strstr($attributes['rel'], '#'), 1);
                        //$e164 = substr(strstr($uri, ':'), 1);
                        $contactDetails[$key][] = ['type' => $type, 'number' => $value->__toString()];
                        break;
                    default:
                        $contactDetails[$key] = (string) $value;
                        break;
                }
            }

            $contactsArray[] = new Contact($contactDetails);
        }

        return $contactsArray;
    }

    public static function getBySelfURL($selfURL, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request($selfURL);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;
        $contactDetails['content'] = (string) $xmlContactsEntry->content;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
                    $contactDetails['photoURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
        foreach ($contactGDNodes as $key => $value) {
            switch ($key) {
                case 'organization':
                    $contactDetails[$key]['orgName'] = (string) $value->orgName;
                    $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                    break;
                case 'email':
                    $attributes = $value->attributes();
                    $emailadress = (string) $attributes['address'];
                    $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                    $contactDetails[$key][] = ['type' => $emailtype, 'email' => $emailadress];
                    break;
                case 'phoneNumber':
                    $attributes = $value->attributes();
                    $uri = (string) $attributes['uri'];
                    $type = substr(strstr($attributes['rel'], '#'), 1);
                    $e164 = substr(strstr($uri, ':'), 1);
                    $contactDetails[$key][] = ['type' => $type, 'number' => $e164];
                    break;
                default:
                    $contactDetails[$key] = (string) $value;
                    break;
            }
        }

        return new Contact($contactDetails);
    }

    public static function submitUpdates(Contact $updatedContact, $contact, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $updatedXML = self::CreateXml($contact);

        //echo"<textarea>"; var_dump($updatedXML); echo"</textarea>";die;

        $req = new \Google_Http_Request($updatedContact->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('PUT');
        $req->setPostBody($updatedXML);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;
        $contactDetails['content'] = (string) $xmlContactsEntry->content;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }
        return new Contact($contactDetails);
    }

    public static function create($contact, $customConfig = NULL){

        $xmlToSend = self::CreateXml($contact);

        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('POST');
        $req->setPostBody($xmlToSend);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }
    
    public static function delete(Contact $toDelete, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request($toDelete->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('DELETE');

        $client->getAuth()->authenticatedRequest($req);
    }
    
    public static function getPhoto($photoURL, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);
        $req = new \Google_Http_Request($photoURL);
        $req->setRequestMethod('GET');
        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();
        return $response;
    }

    public static function CreateXml($contact){
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $entry = $doc->createElement('atom:entry');

        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $doc->appendChild($entry);

        $title = $doc->createElement('title', $contact['name']);
        $entry->appendChild($title);

        if(isset($contact['email']))
            foreach ($contact['email'] as $mail){
                $email = $doc->createElement('gd:email');
                $email->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
                $email->setAttribute('address', $mail);
                $entry->appendChild($email);
            }

        if(isset($contact['numbers']))
            foreach ($contact['numbers'] as $num){
                if(empty($num)) continue;
                $number = $doc->createElement('gd:phoneNumber', $num);
                $number->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
                $entry->appendChild($number);
            }

        if(isset($contact['company'])){
            $company = $doc->createElement('gd:organization');
            $company->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
            $companymane = $doc->createElement('gd:orgName', $contact['company']);
            $company->appendChild($companymane);
            $entry->appendChild($company);
        }

        if(isset($contact['note'])) {
            $note = $doc->createElement('atom:content', $contact['note']);
            $note->setAttribute('rel', 'http://schemas.google.com/g/2005#kind');
            $entry->appendChild($note);
        }

        return $doc->saveXML();
    }
}