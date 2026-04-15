<?php
namespace Scry\PhpFile\Cursor\Events;

include_once 'advanceeventhandler.php';

use Scry\PhpFile\Cursor\TokenCursor;

interface Seeker
{
    public function Handle(TokenCursor &$handligTokenCursor, AdvanceEventHandler $handler) : void;
}