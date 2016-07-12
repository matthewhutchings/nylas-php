<?php

namespace Nylas\Models;

use Nylas\NylasAPIObject;

class Delta extends NylasAPIObject {

    public $collectionName = 'delta';

    public function __construct()
    {
        parent::__construct();
    }
}
