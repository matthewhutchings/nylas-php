<?php

namespace Nylas\Models;

use Nylas\Models\Message;
use Nylas\Models\Draft;
use Nylas\NylasAPIObject;
use Nylas\NylasModelCollection;

class Thread extends NylasAPIObject
{
    public $collectionName = 'threads';

    public function __construct($api)
    {
        parent::__construct();
        $this->api = $api;
        $this->namespace = NULL;
    }

    public function messages()
    {
        $thread_id = $this->data['id'];
        $msgObj = new Message($this);

        return new NylasModelCollection($msgObj, $this->klass, NULL, ["thread_id" => $thread_id], 0, []);
    }

    public function drafts()
    {
        $thread_id = $this->data['id'];
        $msgObj = new Draft($this);

        return new NylasModelCollection($msgObj, $this->klass, NULL, ["thread_id" => $thread_id], 0, []);
    }

    public function createReply()
    {
        return $this->drafts()->create([
            "subject" => $this->data['subject'],
            "thread_id" => $this->data['id']
        ]);
    }

    public function addTags($tags)
    {
        return $this->_updateTags($tags);
    }

    public function removeTags($tags)
    {
        return $this->_updateTags([], $tags);
    }

    public function archive($type = 'label')
    {
       if($type == 'label') {
           return $this->_updateTags([], ['inbox']);
       } else if($type == 'folder') {
           return $this->_updateFolder('archive');
       }
    }

    public function unarchive($type = 'label')
    {
       if($type == 'label') {
           return $this->_updateTags(['inbox'], ['archive']);
       } else if($type == 'folder') {
           return $this->_updateFolder('inbox');
       }
    }

    public function trash($type = 'label')
    {
       if($type == 'label') {
           return $this->_updateTags(['trash'], ['inbox']);
       } else if($type == 'folder') {
           return $this->_updateFolder('trash');
       }
    }

    public function move($type = 'label', $from, $to)
    {
       if($type == 'label') {
            return $this->_updateTags([$to], [$from]);
       } else if($type == 'folder') {
            return $this->_updateFolder($to);
       }
    }

    public function read() {
        $payload = [
            "unread" => false
        ];

        return $this->klass->updateResource($this->namespace, $this, $this->data['id'], $payload);
    }

    public function unread() {
        $payload = [
            "unread" => true
        ];

        return $this->klass->updateResource($this->namespace, $this, $this->data['id'], $payload);
    }

    public function starred() {
        $payload = [
            "starred" => true
        ];

        return $this->klass->updateResource($this->namespace, $this, $this->data['id'], $payload);
    }

    public function unstarred() {
        $payload = [
            "starred" => false
        ];

        return $this->klass->updateResource($this->namespace, $this, $this->data['id'], $payload);
    }

    private function _updateTags($add = [], $delete = [])
    {
        $allLabels = $this->klass->labels()->all();
        $currentLabels = $this->labels;
        $labels = [];

        foreach($allLabels as $label) {
            if (!empty($label->name) && in_array($label->name, $add) ||
                empty($label->name) && in_array($label->display_name, $add)
            ) {
                array_push($labels, $label->id);
            }
        }

        foreach($currentLabels as $index => $label) {
            if (!empty($label['name']) && in_array($label['name'], $delete) ||
                empty($label['name']) && in_array($label['display_name'], $delete)
            ) {
                continue;
            }

            array_push($labels, $label['id']);
        }

        $payload = [
            "label_ids" => $labels
        ];

        return $this->klass->updateResource($this->namespace, $this, $this->data['id'], $payload);
    }

    private function _updateFolder($folder)
    {
        $allFolders = $this->klass->folders()->all();
        $folderId = null;

        foreach($allFolders as $currentFolder) {
            if ($currentFolder->name == $folder) {
                $folderId = $currentFolder->id;
                break;
            }
        }
        if (!empty($folderId)) {
            $payload = [
                "folder_id" => $folderId
            ];

            return $this->klass->updateResource($this->namespace, $this, $this->data['id'], $payload);
        }

        return ["success" => false];
    }
}
