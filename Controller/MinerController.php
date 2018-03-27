<?php


class MinerController {



    public function __construct(){

    }

    /*
     * Dump logs to a file and send it
     */
    public function getLogsAction() {
        global $config;
        exec("journalctl > ".$config["logsDumpedFile"]);
        if (file_exists($config["logsDumpedFile"])) {
            header('Content-Description: File Transfer');
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="'.basename($config["logsDumpedFile"]).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($config["logsDumpedFile"]));
            readfile($config["logsDumpedFile"]);
        }
    }


    /*
     * Obtain the hardware version from a predefined file
     * and return a int generated from the second character
     */
    public  function getType() {
        global $config;
        $hardwareVersion=explode(" ",trim(@file_get_contents($config["hardwareVersionFile"])))[0];
        $typeNum=15+intval($hardwareVersion[1]);
        return $typeNum;
    }

    /*
     * Return the result of getType() function in JSON format
     */
    public function getTypeAction() {
        header('Content-Type: application/json');
        echo json_encode(array("success"=>true,"type"=>$this->getType()));
    }


    /*
     * Sends reboot system call to the miner
     */
    public function rebootAction() {
        header('Content-Type: application/json');
        shell_exec('sleep 4;/bin/systemctl reboot >/dev/null 2>/dev/null &');
        echo json_encode(array("success"=>true));
    }

    /*
     * Reset to factory reset deleting all the content of /config and then reboot the miner
     */
    public function factoryResetAction() {
        global $config;
        header('Content-Type: application/json');
        shell_exec('sleep 4; rm -rf '.$config["configDirectory"].'/*;/bin/systemctl reboot >/dev/null 2>/dev/null &');
        echo json_encode(array("success"=>true));
    }

    /*
     * Get Memory Values
     */
    public function getMemory() {
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
        return array("memTotal"=>$memTotal,"memFree"=>$memFree,"memCached"=>$memCached,"memCachedFree"=>$memCachedFree);
    }


    public function getVersions() {
        global $config;
        //Version
        $version="";
        $buildDate="";
        $hardwareVersion="";

        $fileContent=@file_get_contents($config["hardwareVersionFile"]);
        if ($fileContent!=null&&$fileContent!="") {
            $hwVersionParts=explode(" ",trim($fileContent));
            if (count($hwVersionParts)>0) {
                $hardwareVersion=$hwVersionParts[0];
            }
        }


        $flag=trim(exec('/usr/sbin/fw_printenv -n image_flag'));
        if ($flag!="") {
            if ($flag=="0") {
                $version=trim(exec('/usr/sbin/fw_printenv -n version_0'));
            } else if ($flag=="1") {
                $version=trim(exec('/usr/sbin/fw_printenv -n version_1'));
            }
        }
        if ($version!=""){
            $versionParts=explode("_",$version);
            if (count($versionParts)>=3) { //Well formatted
                $date=date_create_from_format('Ymd His', $versionParts[1]." ".$versionParts[2]);
                $buildDate=date_format($date,'jS \of F Y h:i A');
            }
        }

        $macAddress=exec('cat /sys/class/net/eth0/address');


        return array("hwver"=>$hardwareVersion,
            "ethaddr"=>$macAddress,
            "build_date"=>$buildDate,
            "platform_v"=>$version);
    }

    /*
     * Obtains info from the network, cgminer and system and generate
     * a JSON output with all information obtained
     */
    public function getOverviewAction() {
        global $config;
        header('Content-Type: application/json');
        $uptime=trim(exec("uptime"));

        $memory=$this->getMemory();

        $versions=$this->getVersions();

        //Network
        $network=getNetwork();

        echo json_encode(array(
            "success"=>true,
            "type"=>$this->getType(),
            "hardware"=>array(
                "status"=>$uptime,
                "memUsed"=>$memory["memTotal"]-$memory["memFree"],
                "memFree"=>$memory["memFree"],
                "memTotal"=>$memory["memTotal"],
                "cacheUsed"=>$memory["memCached"],
                "cacheFree"=>$memory["memCachedFree"],
                "cacheTotal"=>$memory["memCachedFree"]+$memory["memCached"]),
            "network"=>$network,
            "version"=>$versions
        ));

    }

    /*
     * Run self-test if it's not running
     */
    public function startSelfTestAction() {
        global $config;
        header('Content-Type: application/json');
        if (!file_exists($config["selfTestLockFile"])) {
            //Run self test
            //Delete Previous Log
            if (file_exists($config["selfTestLogFile"])) {
                unlink($config["selfTestLogFile"]);
            }
            shell_exec($config["selfTestCmd"].' >/dev/null 2>/dev/null &');
            echo json_encode(array("success"=>true));
        } else {
            //Already running
            echo json_encode(array("success"=>false,"message"=>"Self Test is already running"));
        }
    }

    /*
     * Returns true of false depending if the selftest is running
     */
    public function getSelfTestStatusAction() {
        global $config;
        header('Content-Type: application/json');
        $isRunning=file_exists($config["selfTestLockFile"]);
        echo json_encode(array("success"=>true,"running"=>$isRunning));
    }

    /*
     * Get the log file of SelfTest
     */
    public function getSelfTestLogAction() {
        global $config;
        header('Content-Type: application/json');
        $isRunning=file_exists($config["selfTestLockFile"]);
        $fromLine=0;
        if (isset($_POST["line"])) {
            $fromLine=$_POST["line"];
        }

        $lines=array();
        $handle = fopen($config["selfTestLogFile"], "r");
        if ($handle) {
            $i=0;
            while (($line = fgets($handle)) !== false) {
                if ($i>=$fromLine) {
                    $lines[]=$line;
                }
                $i++;
            }
            fclose($handle);
            echo json_encode(array("success"=>true,"running"=>$isRunning,"lines"=>$lines,"lastLine"=>$fromLine));
        } else {
            echo json_encode(array("success"=>false,"running"=>$isRunning,"message"=>"Can't open log file"));
        }

    }


}