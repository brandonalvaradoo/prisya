<?php
namespace API;

enum ContentType : int
{
    case JSON_TEXT = 1;
    case FORM_DATA = 2;
    case FORM_URLENCODED = 3;
    case XML = 4;
}