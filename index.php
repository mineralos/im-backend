<?php
namespace DragonMint;
include __DIR__.'/Common/common.inc.php';
include __DIR__.'/Config/config.inc.php';
include __DIR__.'/Helper/Router.php';
include __DIR__.'/Service/CgminerService.php';
require_once('vendor/autoload.php');


use DragonMint\Helper\Router;

/*
 * Get the user logged, from JWT or Basic Auth
 *
 */
$loggedUser=getLoggedUser();
if ($loggedUser=="expired") {
    header('Content-Type: application/json');
    echo json_encode(array("success"=>false,"token"=>"expired"));
    return;
}

/*
 * Get the request URI from the server and create a Route object
 */
$request = $_SERVER['REQUEST_URI'];
$router = new Router($request);


/*
 * Auth action for not authenticated users
 */
$router->get('/api/auth', 'User',"auth");

/*
 * User logged is guest
 */
if (!is_null($loggedUser)&&($loggedUser===$config["userGuest"]||$loggedUser==$config["userAdmin"])) {
    //Get CgMiner Summary
    $router->get('/api/summary', 'Status',"getSummary");

    //Get Pools from Config
    $router->get('/api/pools', 'Config',"getPools");

    //Get Miner Type
    $router->get('/api/type', 'Miner',"getType");

    //Get Miner Type
    $router->get('/api/network', 'Network',"getNetwork");

    //Get Miner Type
    $router->get('/api/overview', 'Miner',"getOverview");

    //Ping
    $router->get('/api/ping', 'Network',"ping");

    //Factory Stats
    $router->get('/api/stats', 'Status','getStats');


}

/*
 * User logged is Admin
 */
if (!is_null($loggedUser)&&$loggedUser==$config["userAdmin"]) {

    //Update Pools to Config
    $router->get('/api/updatePools', 'Config',"updatePools");

    //Update Password
    $router->get('/api/updatePassword', 'User',"updatePassword");

    //Update Network
    $router->get('/api/updateNetwork', 'Network',"updateNetwork");

    //Reboot
    $router->get('/api/reboot', 'Miner',"reboot");

    //Dump Logs
    $router->get('/api/getLogs', 'Miner','getLogs');

    //Factory Reset
    $router->get('/api/factoryReset', 'Miner','factoryReset');

    //Miner Config has Ageing Parameters
    $router->get('/api/hasAgeing', 'Config',"minerHasAgeingConfig");

    //Returns true if self test is running or false if not
    $router->get('/api/selfTestStatus', 'Miner',"getSelfTestStatus");

    //Start Self Test
    $router->get('/api/startSelfTest', 'Miner',"startSelfTest");

    //Get Self Test Logs
    $router->get('/api/getSelfTestLog', 'Miner',"getSelfTestLog");

    //Set Auto Ageing Config
    $router->get('/api/setAutoTune', 'Config',"setAutoTuneConfig");

    //Latest Firmware Version
    $router->get('/api/getLatestFirmwareVersion', 'Update','getLatestFirmwareVersion');

    /*
     * Interface with the old API
     */
    //Get Type
    $router->get('/cgi-bin/type.py', 'MinerApiV1',"getTypePy");

    //Api call
    $router->get('/cgi-bin/api.py', 'StatusApiV1',"getApiPy");

    //System call
    $router->get('/cgi-bin/system.py', 'MinerApiV1',"getSystemPy");

    //Network call
    $router->get('/cgi-bin/network.py', 'NetworkApiV1',"getNetworkPy");

    //Versions call
    $router->get('/cgi-bin/version.py', 'MinerApiV1',"getVersionPy");

    //Reboot
    $router->get('/cgi-bin/reboot.py', 'Miner',"reboot");
}

/*
 * If the request hit this place is because it didn't find a Route
 */
header('Content-Type: application/json');
echo json_encode(array("success"=>false,"message"=>"invalid request"));