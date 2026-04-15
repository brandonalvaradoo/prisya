<?php
require_once "messageapi.php";
use API\MessageAPI;

if(MessageAPI::ValidRequest())
{
    MessageAPI::GetInstance()->ServeApi();

    function api() : MessageAPI
    {
        return MessageAPI::GetInstance();
    }
}
else
{
    function api() : bool
    {
        return false;
    }
}