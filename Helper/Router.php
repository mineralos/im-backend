<?php
namespace DragonMint\Helper;



class Router {
    private $request;
    public function __construct($request){
        $this->request = $request;
    }
    public function get($route, $controller, $action){

        $uri = trim( $this->request, "/" );
        $uri = explode("/", $uri);
        if(is_array($uri)&&isset($uri[1])&&isset($uri[2])&&$uri[1]."/".$uri[2] == trim($route, "/")){
            //array_shift($uri);
            //$args = $uri;
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