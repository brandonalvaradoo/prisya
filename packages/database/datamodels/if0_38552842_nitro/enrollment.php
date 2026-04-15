<?php
namespace hi\Hello;


class Enrollment extends \Database\Datamodel
{
    #[\Database\Attributes\Get]
    protected $enrollment_date;
    #[\Database\Attributes\Get,\Database\Attributes\Set]
    protected $access;


    public function GetAccess()
    {
        return "testing access method";
    }
}
