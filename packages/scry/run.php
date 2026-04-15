<?php
require 'volk/volk.php';
require 'recolector.php';

use Scry\Volk\Volk;
use Scry\Recolector;


$recolector = new Recolector();
$volk = new Volk();

$time = microtime(true);
$map = $recolector->All('/', true);

$volk->Save($map, Scry\Volk\Target::AttributeFiles);

//echo "Scanning and dumping took: " . (microtime(true) - $time) . " seconds.\n";