<?php

use Database\Datamodel;
use Database\Attributes\Get;

class AccessCode extends Datamodel
{
    #[Get]
    public $access;
}