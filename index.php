<?php
require_once 'packages/autoload/config/main.php';

use Router\Router;
use Composer\Composer;
use Router\URI;


$cssFile = BASE_URL . Router::GetCSSFolder() . URI::TrimUri(router()->GetSelfRouterObject()->GetRoutingAmbitName()) . '/' . URI::TrimUri(router()->GetSelfRouterObject()->GetRoutingUriDestination()) . ".css";
$cssFile = str_replace("\\", "/", $cssFile);
?>

<meta name="BASE_URL" content="<?=BASE_URL?>">
<meta name="ABSOLUTE_PROJECT_ROOT" content="<?=Composer::GetAbsoluteProjectRoot()?>">
<meta name="SERVER_ROOT" content="<?=Composer::GetServerRoot()?>">
<link rel="stylesheet" href="<?=$cssFile?>">