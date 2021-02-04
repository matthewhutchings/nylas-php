<?php

namespace Nylas\Models;

use Nylas\NylasAPIObject;

class File extends NylasAPIObject
{
    public $collectionName = 'files';

    public function __construct($api)
    {
        parent::__construct();
        $this->api = $api;
        $this->namespace = NULL;
    }

    public function create($file)
    {
        if (is_array($file)) {
            $filePath = $file['path'];
            $fileName = $file['name'];
        } else {
            $filePath = $file;
            $fileName = basename($file);
        }

        $payload = [
            [
                'name' => 'file',
                'filename' => $fileName,
                'contents' => fopen($filePath, 'r')
            ]
        ];

        $upload = $this->api->createResource($this->namespace, $this, $payload);
        $data = $upload->data[0];
        $this->data = $data;

        return $this;
    }

    public function download()
    {
        $resource = $this->klass->getResourceData($this->namespace, $this, $this->data['id'], ['extra' => 'download']);
        $data = '';

        while (!$resource->eof()) {
            $data .= $resource->read(1024);
        }

        return $data;
    }

}
