<?php
//if(count(debug_backtrace()) == 0 || array_search("autoload.php", array_column(debug_backtrace(), 'file')) === 0)
///{

    require_once "autoload.php";

    $autoload = new Autoload\Autoload();
    $autoload->Execute();
    
//}