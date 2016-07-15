<?php

namespace Nylas\Models;

use Nylas\NylasAPIObject;

class Folder extends NylasAPIObject
{
    public $collectionName = 'folders';

    public function __construct($api)
    {
        parent::__construct();
    }
}
