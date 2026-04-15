<?php
require_once "router.php";
use Router\Router;

/**
 * Returns the instance of the Router using the Singleton pattern.
 *
 * @return Router An instance of the Router.
 */
function router() : Router
{
    return Router::Singleton();
}

router();

// Whitelist of allowed URIs
$allowedURIs = [
    'info',
    'authenticate',
    'login',
    'accesscard'
];

$head = router()->GetServerRequest()->GetUriObject()->GetHeadParticle();
$middleware = true; // Modify for access restrictions

if(!$middleware)
{
    router()->GetSelfRouterObject()->Route("views", "login");
}
else
{
    router()->Execute();
}