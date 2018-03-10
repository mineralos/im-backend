<?php
namespace DragonMint\Helper;



class Router {
    private $request;
    public function __construct($request){
        $this->request = $request;
    }
    public function get($route, $controller, $action){
        $allowedActions=array("auth","getSummary","getPools","getType","getNetwork","getOverview","updatePools","updatePassword","updateNetwork","ping","reboot");
        if(isset($_GET)&&array_key_exists("action",$_GET)&&$_GET["action"]!=""&&in_array($_GET["action"],$allowedActions)) {
            if (file_exists(__DIR__."/../Controller/".$controller . 'Controller.php')) {
                require __DIR__."/../Controller/".$controller . 'Controller.php';
                $controllerClass = $controller."Controller";
                $controller = new $controllerClass();
                if (method_exists($controller,$action."Action")) {
                    $method=$action."Action";
                    $controller->$method();
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }
}

?>