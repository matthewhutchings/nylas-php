<?php

namespace Nylas\Models;

use Nylas\NylasAPIObject;

class Account extends NylasAPIObject
{
    public $collectionName = 'account';
    public $collectionAdminName = 'accounts';

    public function __construct()
    {
        parent::__construct();
    }
}
