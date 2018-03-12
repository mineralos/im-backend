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
            echo json_encode(array("result"=>false,"output"=>array("No upgrade file")));
            return;
        }

        // Check $_FILES['upfile']['error'] value.
        $error="";
        switch ($_FILES['upfile']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $error="No file sent";
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error="Exceeded filesize limit.";
                break;
            default:
                $error="'Unknown errors";
        }

        if ($error!="") {
            echo json_encode(array("result"=>false,"output"=>array($error)));
            return;
        }

        // You should also check filesize here.
        if ($_FILES['upfile']['size'] > $config["swUpdateMaxFileSize"]) {
            $error="Exceeded filesize limit.";
            echo json_encode(array("result"=>false,"output"=>array($error)));
            return;
        }

        $ext=pathinfo( $_FILES['upfile']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext)!="swu") {
            echo json_encode(array("result"=>false,"output"=>array("Invalid file format")));
            return;
        }


        if (!move_uploaded_file(
            $_FILES['upfile']['tmp_name'],
            $config["swUpdateImagePath"]
        )) {
            echo json_encode(array("result"=>false,"output"=>array("Failed to move uploaded file.")));
            return;
        }
        $notifyOutput=array();
        exec("sync");
        $notifyOutput[]="MD5 File: ".md5_file($config["swUpdateImagePath"]);

        //Shutdown the swupdate service
        $notifyOutput[]="Shutting down swupdate service";
        exec("systemctl stop swupdate");

        //Run Upgrade
        $swUpdateService=new SWUpdateService();
        $response=$swUpdateService->runUpgrade();

        $success=($response["returnVar"]==0);

        foreach($response["output"] as $lineOutput) {
            if (strpos($lineOutput,"[NOTIFY]")>-1) {
                $parts=explode(":",$lineOutput);
                if ($parts[count($parts)-1]!=""&&strlen($parts[count($parts)-1])>1)
                    $notifyOutput[]=$parts[count($parts)-1];
            }
        }

        if ($success) {
            //Delete User Settings
            if (isset($_POST["keepsettings"])&&$_POST["keepsettings"]=="0") {
                $notifyOutput[]="Removing cg Config File";
                unlink($config["configFile"]);
                $notifyOutput[]="Removing Users Config File";
                unlink($config["usersFile"]);
                $notifyOutput[]="Removing Network Config File";
                unlink($config["interfacesDirectory"].$config["interfacesFile"]="25-wired.network");
                exec("sync");
                /*
                $notifyOutput[]="Unmounting /config ";
                exec("umount /config");
                $notifyOutput[]="Formating /config";
                exec("ubimkvol /dev/ubi1 -N config -m");
                */

            }

            shell_exec('sleep 5; reboot >/dev/null 2>/dev/null &');
            $notifyOutput[]="Rebooting miner";
            echo json_encode(array("success"=>true,"output"=>$notifyOutput));
        } else {
            echo json_encode(array("success"=>false,"output"=>$notifyOutput));
        }



    }



}

?>