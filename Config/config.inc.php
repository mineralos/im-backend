<?php

$config["cgminerPort"]=4028;
$config["configFile"]="/innocfg/www/conf/miner.conf";
$config["usersFile"]="/innocfg/www/conf/users.json";
$config["typeFile"]="/tmp/type";
$config["jwtKeyFile"]="/tmp/jwtK";
$config["jwtTokenExpire"]=21600; //6 hours
$config["serverName"]="DragonMint";
$config["salt"]="8985dee0f2bc1b7a895d58bc4811e373";


$config["interfacesFile"]="/etc/network/interfaces";
$config["resolvFile"]="/etc/resolv.conf";
$config["dhcpPidFile"]="/var/run/udhcpc.eth0.pid";

//Default Passwords in Case Missing or Corrupted Users File
$config["userAdmin"]="admin";
$config["userGuest"]="guest";
$config["passwordAdmin"]="dragonadmin";
$config["passwordGuest"]="dragonguest";

//Versions
$config["hardwareVersionFile"]="/tmp/hwver";
$config["buildLogFile"]="/build_log";

?>