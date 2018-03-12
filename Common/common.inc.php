<?php

use Firebase\JWT\JWT;


function get_jwt_key() {
    global $config;
    $content=null;
    if (file_exists($config["jwtKeyFile"]))
        $content=@file_get_contents($config["jwtKeyFile"]);

    if ($content==null||$content=="") {
        $key=rand_string(20);
        file_put_contents($config["jwtKeyFile"],$key);
        return $key;
    }

    return $content;
}

function rand_string( $length ) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    return substr(str_shuffle($chars),0,$length);
}

function getNetwork() {
    global $config;
    $networkFileParsed=@parse_ini_file($config["interfacesDirectory"].$config["interfacesFile"]);
    $dhcp="dhcp";
    if ($networkFileParsed!=null) {
        if (array_key_exists("DHCP", $networkFileParsed) && $networkFileParsed["DHCP"] == "yes") {
            $dhcp = "dhcp";
        } else {
            $dhcp = "static";
        }
    }
    $ip   = exec("ifconfig | grep inet | sed -n '1p' | awk '{print $2}' | awk -F ':' '{print $2}'");
    $netmask= exec("ifconfig |grep inet| sed -n '1p'|awk '{print $4}'|awk -F ':' '{print $2}'");
    $gw = exec("route -n | grep eth0 | grep UG | awk '{print $2}'");
    $dns[0]="";
    $dns[1]="";
    if (file_exists($config["resolvFile"])) {
        $dnsContent = file($config["resolvFile"], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $i=0;
        foreach ($dnsContent as $item) {
            $dnsParts=explode("nameserver ",$item);
            if(isset($dnsParts[1])) {
                $dns[$i]=trim($dnsParts[1]);
                $i++;
            }
        }
    }
    return array(
        "dhcp"=>$dhcp,
        "ipaddress"=>$ip,
        "netmask"=>$netmask,
        "gateway"=>$gw,
        "dns1"=>$dns[0],
        "dns2"=>$dns[1]);
}

function getLoggedUser() {
    global $config;
    $token=getBearerToken();
    if ($token!=null||(isset($_SERVER['PHP_AUTH_USER'])&&isset($_SERVER['PHP_AUTH_USER'])&&isset($_SERVER['PHP_AUTH_PW']))) {

        if ($token == null&&isset($_SERVER['PHP_AUTH_USER'])) {
            if (isset($_SERVER['PHP_AUTH_USER'])&&isset($_SERVER['PHP_AUTH_PW'])) {
                $username=preg_replace("/[^a-zA-Z0-9_\-]+/","",$_SERVER['PHP_AUTH_USER']);
                $users=array();
                if (file_exists($config["usersFile"]))
                    $configContent=@file_get_contents($config["usersFile"]);
                if (isset($configContent)&&$configContent!=null&&$configContent!="") {
                    $users = json_decode($configContent, true);
                }

                foreach ($users as $user) {
                    if ($username == $user["username"]) {
                        if (generatePasswordHash($_SERVER['PHP_AUTH_PW'])==$user["password"]) {
                            return $user["username"];
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
function cleanWorker($string) {
    $string = str_replace(' ', '-', $string);
    return preg_replace('/[^A-Za-z0-9\-\.\_]/', '', $string);
}
function getAuthorizationHeader(){
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        //print_r($requestHeaders);
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

function getBearerToken() {
    $headers = getAuthorizationHeader();
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}