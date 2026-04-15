<?php
namespace API;

enum Method : int
{
    case GET = 1;
    case POST = 2;
    case PUT = 3;
    case DELETE = 4;
    case PATCH = 5;
    case OPTIONS = 6;
    case HEAD = 7;
    case CONNECT = 8;
    case TRACE = 9;
    case UNKNOWN = 10;
}