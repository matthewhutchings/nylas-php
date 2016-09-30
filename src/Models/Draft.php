<?php

namespace Nylas\Models;

use Nylas\NylasAPIObject;
use Nylas\Models\Person;
use Nylas\Models\Send;

class Draft extends NylasAPIObject
{
    public $collectionName = 'drafts';
    public $attrs = [
        'subject',
        'to',
        'cc',
        'bcc',
        'from',
        'reply_to',
        'reply_to_message_id',
        'body',
        'file_ids',
        'version'
    ];

    public function __construct($api)
    {
        parent::__construct();
    }

    public function create($data, $api)
    {
        $sanitized = [];

        foreach($this->attrs as $attr) {
            if(array_key_exists($attr, $data)) {
                $sanitized[$attr] = $data[$attr];
            }
        }

        $this->data = $sanitized;
        $this->api = $api->api;
        $this->namespace = $api->namespace;

        $this->api->createResource($this->namespace, $this, $this->data);

        return $this;
    }

    public function update($data, $id, $api)
    {
        $sanitized = [];

        foreach($this->attrs as $attr) {
            if(array_key_exists($attr, $data)) {
                $sanitized[$attr] = $data[$attr];
            }
        }

        $this->data = $sanitized;
        $this->api = $api->api;
        $this->namespace = $api->namespace;

        if(array_key_exists('id', $this->data)) {
            $tmpId = $this->data['id'];
        } else {
            $tmpId = $id;
        }

        $this->api->updateResource($this->namespace, $this, $tmpId, $this->data);

        return $this;
    }

    public function delete()
    {
        return $this->klass->deleteResource($this->namespace, $this, $this->data['id'], ['version' => $this->data['version']]);
    }

    public function attach($fileObj)
    {
        if(array_key_exists('file_ids', $this->data)) {
            $this->data['file_ids'][] = $fileObj->id;
        } else {
            $this->data['file_ids'] = [$fileObj->id];
        }

        return $this;
    }

    public function detach($fileObj)
    {
        if(in_array($fileObj->id, $this->data['file_ids'])) {
            $this->data['file_ids'] = array_diff($this->data['file_ids'], [$fileObj->id]);
        }

        return $this;
    }

    public function send()
    {
        $sendObject = new Send($this->api, $this->namespace);
        $sendResult = $sendObject->send($this->data);

        return $sendResult;
    }
}
