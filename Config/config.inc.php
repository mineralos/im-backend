<?php

$config["cgminerPort"]=4028;
$config["configFile"]="/config/cgminer.conf";
$config["usersFile"]="/config/web-users.json";
$config["buildFile"]="/etc/os-release";
$config["jwtKeyFile"]="/tmp/jwtK";
$config["jwtTokenExpire"]=21600; //6 hours
$config["serverName"]="DragonMint";
$config["salt"]="8985dee0f2bc1b7a895d58bc4811e373";

$config["interfacesDirectory"]="/config/network/";
$config["interfacesFile"]="25-wired.network";
$config["resolvFile"]="/etc/resolv.conf";
#$config["dhcpPidFile"]="/var/run/udhcpc.eth0.pid";

//Default Passwords in Case Missing or Corrupted Users File
$config["userAdmin"]="admin";
$config["userGuest"]="guest";
$config["passwordAdmin"]="dragonadmin";
$config["passwordGuest"]="dragonguest";

//Versions
$config["hardwareVersionFile"]="/etc/hwrevision";

//Update
$config["swUpdateProgressSocket"]="unix:///tmp/swupdateprog";
$config["swUpdateMaxFileSize"]=100*1024*1024;//100Mb
$config["swUpdateImagePath"]="/tmp/update.swu";

?>