<?php



class MinerController {


    public function __construct(){

    }

    private  function getType() {
        global $config;
        $hardwareVersion=explode(" ",trim(@file_get_contents($config["hardwareVersionFile"])))[0];
        $typeNum=15+intval($hardwareVersion[1]);
        return $typeNum;
    }

    public function getTypeAction() {
        echo json_encode(array("success"=>true,"type"=>$this->getType()));
    }

    public function rebootAction() {
        header('Content-Type: application/json');
        echo json_encode(array("success"=>true));
        exec("reboot");
    }

    private function getLock() {

    }


    public function getOverviewAction() {
        global $config;
        //Memory and Uptime
        $uptime=exec("uptime");
        $memTotal=0;
        $memFree=0;
        $memCached=0;
        $memCachedFree=0;
        $memArray=@file('/proc/meminfo');
        foreach ($memArray as $item) {
            $parts=preg_split( "/[ ]{1,}/", $item );
            switch ($parts[0]) {
                case "MemTotal:":
                    $memTotal=intval($parts[1]);
                    break;
                case "MemFree:":
                    $memFree=intval($parts[1]);
                    break;
                case "Cached:":
                    $memCached=intval($parts[1]);
                    break;
                case "MemAvailable:":
                    $memCachedFree=intval($parts[1]);
                break;
            }
        }

        //Network
        $dhcp=exec('cat '.$config["interfacesFile"].'| grep ^iface | sed -n \'$p\' | awk \'{print $4}\'');
        $ip   = exec("ifconfig | grep inet | sed -n '1p' | awk '{print $2}' | awk -F ':' '{print $2}'");
        $netmask= exec("ifconfig |grep inet| sed -n '1p'|awk '{print $4}'|awk -F ':' '{print $2}'");
        $gw = exec("route -n | grep eth0 | grep UG | awk '{print $2}'");
        $dns=array();
        if (file_exists($config["resolvFile"])) {
            $dnsContent = file($config["resolvFile"], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($dnsContent as $item) {
                $dns[]=trim(explode("nameserver ",$item)[1]);
            }
        }

        //Version
        $hardwareVersion=explode(" ",trim(@file_get_contents($config["hardwareVersionFile"])))[0];
        $macAddress=exec('cat /sys/class/net/eth0/address');
        $buildContent=parse_ini_file($config["buildFile"]);
        $buildDate=$buildContent["VERSION_ID"];
        $plarformVersion=$buildContent["VERSION"];


        echo json_encode(array(
            "success"=>true,
            "type"=>$this->getType(),
            "hardware"=>array(
                "status"=>$uptime,
                "memUsed"=>$memTotal-$memFree,
                "memFree"=>$memFree,
                "memTotal"=>$memTotal,
                "cacheUsed"=>$memCached,
                "cacheFree"=>$memCachedFree,
                "cacheTotal"=>$memCachedFree+$memCached),
            "network"=>array(
                "dhcp"=>$dhcp,
                "ipaddress"=>$ip,
                "netmask"=>$netmask,
                "gateway"=>$gw,
                "dns1"=>$dns[0],
                "dns2"=>$dns[1]),
            "version"=>array(
                "hwver"=>$hardwareVersion,
                "ethaddr"=>$macAddress,
                "build_date"=>$buildDate,
                "platform_v"=>$plarformVersion
            )
        ));

    }

}

?>