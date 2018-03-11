<?php

use DragonMint\Service\SWUpdateService;

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



        //Version
        $version="undefined";
        $fileContent=@file_get_contents($config["hardwareVersionFile"]);
        if ($fileContent!=null&&$fileContent!="") {
            $versionParts=explode(" ",trim($fileContent));
            if (count($versionParts)>0) {
                $version=$versionParts[0];
            }
        }
        $hardwareVersion=$version;
        $macAddress=exec('cat /sys/class/net/eth0/address');
        $buildContent=parse_ini_file($config["buildFile"]);
        $buildDate=$buildContent["VERSION_ID"];
        $plarformVersion=$buildContent["VERSION"];

        //Network
        $network=getNetwork();

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
            "network"=>$network,
            "version"=>array(
                "hwver"=>$hardwareVersion,
                "ethaddr"=>$macAddress,
                "build_date"=>$buildDate,
                "platform_v"=>$plarformVersion
            )
        ));

    }

    public function upgradeAction() {
        global $config;

        if (
            !isset($_FILES['upfile']['error']) ||
            is_array($_FILES['upfile']['error'])
        ) {
            echo json_encode(array("result"=>false,"message"=>"No upgrade file"));
            return;
        }

        // Check $_FILES['upfile']['error'] value.
        $error="";
        switch ($_FILES['upfile']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $error="No file sent";
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error="Exceeded filesize limit.";
            default:
                $error="'Unknown errors";
        }

        if ($error!="") {
            echo json_encode(array("result"=>false,"message"=>$error));
            return;
        }

        // You should also check filesize here.
        if ($_FILES['upfile']['size'] > $config["swUpdateMaxFileSize"]) {
            $error="Exceeded filesize limit.";
            echo json_encode(array("result"=>false,"message"=>$error));
            return;
        }

        $ext=pathinfo( $_FILES['upfile']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext)!="swu") {
            echo json_encode(array("result"=>false,"message"=>"Invalid file format"));
            return;
        }


        if (!move_uploaded_file(
            $_FILES['upfile']['tmp_name'],
            $config["swUpdateImagePath"]
        )) {
            echo json_encode(array("result"=>false,"message"=>"Failed to move uploaded file."));
            return;
        }
        echo json_encode(array("success"=>true));



    }



}

?>