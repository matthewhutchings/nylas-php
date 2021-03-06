<?php

namespace Nylas\Models;

use Nylas\NylasAPIObject;
use Nylas\Models\Send;


class Message extends NylasAPIObject
{
    public $collectionName = 'messages';

    public function __construct($api)
    {
        parent::__construct();
        $this->api = $api;
        $this->namespace = NULL;
    }

    public function raw()
    {
        $headers = ['Accept' => 'message/rfc822'];
        $resource = $this->klass->getResourceData($this->namespace, $this, $this->data['id'], ['headers' => $headers]);
        $data = '';

        while (!$resource->eof()) {
            $data .= $resource->read(1024);
        }

        return $data;
    }

    public function send()
    {
        $sendObject = new Send($this->api, $this->namespace);
        $sendResult = $sendObject->send($this->data);
        
        return $sendResult;
    }
}
