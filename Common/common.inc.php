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
    global $config;
    if (isset($_POST)&&(isset($_POST["jwt"])||(isset($_POST["username"])&&isset($_POST["password"])))) {
        $token=null;
        if (array_key_exists("jwt",$_POST)&&$_POST["jwt"]=!"") {
            $token = $_POST["jwt"];
        }

        if ($token == null) {
            if (isset($_POST["username"])&&isset($_POST["password"])) {
                $username=preg_replace("/[^a-zA-Z0-9_\-]+/","",$_POST["username"]);
                $users=array();
                if (file_exists($config["usersFile"]))
                    $configContent=file_get_contents($config["usersFile"]);
                if (isset($configContent)&&$configContent!=null&&$configContent!="") {
                    $users = json_decode($configContent, true);
                }

                foreach ($users as $user) {
                    if ($username == $user["username"]) {
                        if (generatePasswordHash($_POST["password"])==$user["password"]) {
                            return $user;
                        } else {
                            return null;
                        }
                    }
                }
            }
        } else {
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


function mask2cidr($mask){
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32-log(($long ^ $base)+1,2);
}

function generatePasswordHash($password) {
    global $config;
    return hash('sha256', $config["salt"].$password);
}