<?php

use Database\Attributes\Foreign;
use Database\Attributes\Get;
use Database\Attributes\Set;

define("TABLE", "");

class Payment extends Database\Datamodel
{
    #[Get,Set]
    protected $registered_person_made_pay_id;
    #[Get,Set]
    protected $user_processed_pay_id;
    #[Get,Foreign(TABLE, "fullname", new Get("e"))]
    protected $user_processed_pay_name;
    #[Get,Set]
    protected $payment_to_pass_id;
    #[Get,Set]
    protected $amount;
    #[Get,Set]
    protected $pay_timestamp;

    public function SetPayTimestampToNow()
    {
        $this->pay_timestamp = date("Y-m-d H:i:s");
    }
}