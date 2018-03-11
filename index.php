<?php
namespace DragonMint;
include __DIR__.'/Common/common.inc.php';
include __DIR__.'/Config/config.inc.php';
include __DIR__.'/Helper/Router.php';
include __DIR__.'/Service/CgminerService.php';
include __DIR__.'/Service/SWUpdateService.php';
require_once('vendor/autoload.php');


use DragonMint\Helper\Router;

$loggedUser=getLoggedUser();
if ($loggedUser=="expired") {
    echo json_encode(array("success"=>false,"token"=>"expired"));
    return;
}


$request = $_SERVER['REQUEST_URI'];
$router = new Router($request);

//No Auth Required
//Auth
$router->get('/api/auth', 'User',"auth");
//Guest
$found=false;
if (!is_null($loggedUser)&&($loggedUser===$config["userGuest"]||$loggedUser==$config["userAdmin"])) {
    //Get CgMiner Summary
    if (!$found)
        $found=$router->get('/api/summary', 'Status',"getSummary");

    //Get Pools from Config
    if (!$found)
        $found=$router->get('/api/pools', 'Config',"getPools");

    //Get Miner Type
    if (!$found)
        $found=$router->get('/api/type', 'Miner',"getType");

    //Get Miner Type
    if (!$found)
        $found=$router->get('/api/network', 'Network',"getNetwork");

    //Get Miner Type
    if (!$found)
        $found=$router->get('/api/overview', 'Miner',"getOverview");
}

//Admin
if (!is_null($loggedUser)&&$loggedUser==$config["userAdmin"]) {
    //Update Pools to Config
    if (!$found)
        $found=$router->get('/api/updatePools', 'Config',"updatePools");

    //Update Password
    if (!$found)
        $found=$router->get('/api/updatePassword', 'User',"updatePassword");

    //Update Network
    if (!$found)
        $found=$router->get('/api/updateNetwork', 'Network',"updateNetwork");

    //Ping
    if (!$found)
        $found=$router->get('/api/ping', 'Network',"ping");

    //Reboot
    if (!$found)
        $found=$router->get('/api/reboot', 'Miner',"reboot");

    //SWUpdate progress
    if (!$found)
        $found=$router->get('/api/upgrade', 'Miner',"upgrade");
}







?>