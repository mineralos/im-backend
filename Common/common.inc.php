<?php

use Firebase\JWT\JWT;


function get_jwt_key() {
    global $config;
    $content=null;
    if (file_exists($config["jwtKeyFile"]))
        $content=file_get_contents($config["jwtKeyFile"]);
    if ($content!=null&&$content!="") {
        return $content;
    } else {
        $key=rand_string(20);
        file_put_contents($config["jwtKeyFile"],$key);
        return $key;
    }
}

function rand_string( $length ) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    return substr(str_shuffle($chars),0,$length);
}


function getLoggedUser() {
    if (isset($_POST)&&isset($_POST["jwt"])) {
        $token = $_POST["jwt"];
        if ($token != null) {
            try {
                $decoded = JWT::decode($token, get_jwt_key(), array('HS256'));
                return $decoded->user;
            }catch(\Exception $e) {
                return "expired";
            }
        }
    }
    return null;
}

function isValidIp($ip) {
    if (long2ip(ip2long($ip)) == $ip) {
        return true;
    }
    return false;
}