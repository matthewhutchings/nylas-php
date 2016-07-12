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

    public function markAsRead()
    {
        return $this->_updateTags([], ['unread']);
    }

    public function markAsSeen()
    {
        return $this->_updateTags([], ['unseen']);
    }

    public function archive()
    {
        return $this->_updateTags(['archive'], ['inbox']);
    }

    public function unarchive()
    {
        return $this->_updateTags(['inbox'], ['archive']);
    }

    public function trash()
    {
        return $this->_updateTags(['trash'], ['inbox']);
    }

    public function star()
    {
        return $this->_updateTags(['starred'], []);
    }

    public function unstar()
    {
        return $this->_updateTags([], ['starred']);
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

        return $this->klass->_updateResource($this->namespace, $this, $this->data['id'], $payload);
    }
}
