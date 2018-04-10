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
     * Return the result of getType() function in JSON format
     */
    public function getTypeAction() {
        header('Content-Type: application/json');
        echo json_encode(array("success"=>true,"type"=>getMinerType()));
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
     * Sends poweroff system call to the miner
     */
    public function poweroffAction() {
        header('Content-Type: application/json');
        shell_exec('sleep 4;/usr/sbin/poweroff >/dev/null 2>/dev/null &');
        echo json_encode(array("success"=>true));
    }

    /*
     * Restart cgminer
     */
    public function restartCgMinerAction() {
        header('Content-Type: application/json');
        shell_exec('/usr/bin/systemctl restart cgminer >/dev/null 2>/dev/null &');
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




    /*
     * Obtains info from the network, cgminer and system and generate
     * a JSON output with all information obtained
     */
    public function getOverviewAction() {
        global $config;
        header('Content-Type: application/json');
        $uptime=trim(exec("uptime"));

        $memory=$this->getMemory();

        $versions=getVersions();

        //Network
        $network=getNetwork();

        echo json_encode(array(
            "success"=>true,
            "type"=>getMinerType(),
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

        //Check if its running
        $isRunning=file_exists($config["selfTestLockFile"]);

        //Get progress from Log File
        $stepsLine="";
        $stepLine="";
        $step=0;
        $steps=0;
        $handle = fopen($config["selfTestProgressFile"], "r");
        if ($handle) {
            $i=0;
            while (($line = fgets($handle)) !== false) {
                $i++;
                if (strpos($line,"Steps=")!== false) {
                    $stepsLine=trim($line);
                } else if (strpos($line,"Step=")!== false) {
                    $stepLine=trim($line);
                }
            }
            fclose($handle);
        }
        if ($stepsLine!="") {
            $lineParts = explode("=", $stepsLine);
            $steps = intval($lineParts[1]);
        }
        if ($stepLine!="") {
            $lineParts = explode("=", $stepLine);
            $step = intval($lineParts[1]);
        }


        //Get Stats from CgMiner
        $chains=array();
        if (isset($response)&&is_array($response)&&array_key_exists("STATS",$response)) {
            foreach($response["STATS"] as $chainStats) {
                $volt=0;
                $chainVolt=0;
                $hashRate=0;
                if (is_array($chainStats)&&array_key_exists("Num active chips",$chainStats)) {
                    $numOfChips=$chainStats["Num active chips"];
                    for ($i=0;$i<$numOfChips;$i++) {
                        $key=sprintf("%02d nVol", $i);
                        if (array_key_exists($key,$chainStats)&&intval($chainStats[$key])>0) {
                            $volt+=intval($chainStats[$key]);
                        }
                    }
                    $chainVolt=$volt/$numOfChips;
                }
                if (is_array($chainStats)&&array_key_exists("MHS av",$chainStats)&&intval($chainStats["MHS av"])>0) {
                    $hashRate=intval($chainStats["MHS av"]);
                }
                $chains[]=array("volt"=>$chainVolt,"hashRate"=>$hashRate);
            }
        }
        echo json_encode(array("success"=>true,"running"=>$isRunning,"steps"=>$steps,"step"=>$step,"chains"=>$chains));
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