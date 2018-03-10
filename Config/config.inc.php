<?php

$config["cgminerPort"]=4028;
$config["configFile"]="/config/miner.conf";
$config["usersFile"]="/config/web-users.json";
$config["buildFile"]="/etc/os-release";
$config["jwtKeyFile"]="/tmp/jwtK";
$config["jwtTokenExpire"]=21600; //6 hours
$config["serverName"]="DragonMint";
$config["salt"]="8985dee0f2bc1b7a895d58bc4811e373";


$config["interfacesFile"]="/etc/systemd/network/wired.network";
$config["resolvFile"]="/etc/resolv.conf";
#$config["dhcpPidFile"]="/var/run/udhcpc.eth0.pid";

//Default Passwords in Case Missing or Corrupted Users File
$config["userAdmin"]="admin";
$config["userGuest"]="guest";
$config["passwordAdmin"]="dragonadmin";
$config["passwordGuest"]="dragonguest";

//Versions
$config["hardwareVersionFile"]="/etc/hwrevision";

?>