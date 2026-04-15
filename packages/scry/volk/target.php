<?php
namespace Scry\Volk;

enum Target : string
{
    case AttributeFiles = 'attr';
    case SpecificClassFile = 'class';
}