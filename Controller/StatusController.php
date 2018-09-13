<?php

use DragonMint\Service\CgminerService;
use DragonMint\Service\DMMonitorService;


class StatusController {

    private $max_chain_num = 9;
    private $config;
    /*
     * Consumes pools, devs, stats from cgminer API
     * and create a JSON response with all the information
     * obtained. A new hardware option was created to include
     * the fans speed
     */
    public function getSummaryAction() {

        $service = new CgminerService();
        header('Content-Type: application/json');
        $response=$service->call("pools+devs+stats");
        $devs=@$response["devs"][0]["DEVS"];
        //look for the fans speed
        $fansSpeed=0;
        $isTuning=false;
        
        for ($i=0;$i<$this->max_chain_num;$i++)
        {
            if (isset($response["stats"][0]["STATS"])&&array_key_exists($i,$response["stats"][0]["STATS"]))
            {
                $stats = $response["stats"][0]["STATS"][$i];
                if ($fansSpeed==0&&intval($stats["Fan duty"]) > 0)
                {
                    $fansSpeed = intval($stats["Fan duty"]);
                }

                // is tuning
                if (
                    ((isset($stats["VidOptimal"])&&$stats["VidOptimal"]===false)||
                        (isset($stats["pllOptimal"])&&$stats["pllOptimal"]===false))
                        &&isAutoTuneEnabled())
                {
                    $isTuning=true;
                }

            }
        }

        $hashRates=array();
        $total_hash_rate = 0;
        for ($i=0;$i<$this->max_chain_num;$i++)
        {
            if (isset($devs) && array_key_exists($i, $devs))
            {
                // look for hash rate
                $elapsed=intval($devs[$i]["Device Elapsed"]);

                if (array_key_exists("MHS 5m", $devs[$i])&&array_key_exists("MHS 15m", $devs[$i])) {
                    if ($elapsed < 5 * 60) {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 5s"];
                    } else if ($elapsed < 15 * 60) {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 1m"];
                    } else if ($elapsed < 60 * 60) {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 5m"];
                    } else {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 15m"];
                    }
                } else {
                    $devs[$i]["Hash Rate"] = $devs[$i]["MHS av"];
                }
                //total hash
                $total_hash_rate += $devs[$i]["Hash Rate"];

                //new display
                $new_display = getHashRateShow($devs[$i]["Hash Rate"]);
                $devs[$i]["Hash Rate"] = $new_display['cal_hash'];
                $devs[$i]["Unit"] = $new_display['unit'];
                $devs[$i]["Hash Rate H"] = $new_display['hash_rate'];
            }
        }

        //total hash api
        $total_hash  = array();
        $total_hash_show = getHashRateShow($total_hash_rate);
        $total_hash['Hash Rate'] = $total_hash_show['cal_hash'];
        $total_hash['Unit'] = $total_hash_show['unit'];


        $pools=@$response["pools"][0]["POOLS"];
        if (is_array($devs)&&is_array($pools)) {
            echo json_encode(array("success" => true, "DEVS" => $devs, "POOLS" => $pools, "HARDWARE"=>array("Fan duty"=>$fansSpeed),"tuning"=>$isTuning, "hashrates"=>$hashRates,"TotalHash"=>$total_hash));
        } else {
            echo json_encode(array("success" => false));
        }

    }

    /*
     * Consumes stats from cgminer API and create a JSON response
     * with all the information obtained
     */
    public function getStatsAction() {
        $service = new CgminerService();
        header('Content-Type: application/json');
        $response=$service->call("stats");


        if (isset($response)&&is_array($response)) {
            $response["success"]=true;
            echo json_encode($response);
        } else {
            echo json_encode(array("success" => false));
        }
    }

    /*
     * Consumes stats from cgminer API and create a JSON response
     * with all the information obtained for debug page
     */
    public function getDebugStatsAction() {
        global $config;
        header('Content-Type: application/json');
        // cgminer stats
        $service = new CgminerService();
        $response=$service->call("dbgstats");

        $stats=array();
        if (isset($response)&&is_array($response)) {
            if (array_key_exists("STATS",$response)&&$response["STATS"]) {
                $statsItem=$response["STATS"];
                foreach($statsItem as $stat) {
                    if (substr($stat["ID"],0,4)!="POOL") {
                        $board = array();
                        $chips = array();
                        foreach ($stat as $key => $item) {
                            $firstTwoDigits = substr($key, 0, 2);
                            if (!is_numeric($firstTwoDigits)) {//Is board info
                                $board[$key] = $item;
                            } else { //is chip info
                                $chips[intval($firstTwoDigits)][substr($key, 3)] = $item;
                            }
                        }
                        $stats[] = array("board" => $board, "chips" => $chips);
                    }
                }
            }
        }

        // versions hashes
        $hashes=array();
        if (file_exists($config["gitHashes"])) {
            $hashes=parse_ini_file($config["gitHashes"]);
        }

        echo json_encode(array("success"=>true,"boards"=>$stats,"hashes"=>array_change_key_case($hashes,CASE_LOWER)));

    }

    /*
     * Get Hash Rates from stats.json generated by dm-monitor
     */
    public function getHashRatesAction() {
        global $config;
        header('Content-Type: application/json');
        $minTime=strtotime('-24 hours'); //Avoid NTP out of sync
        if (file_exists($config["statsJsonFile"])) {
            $configContent=@file_get_contents($config["statsJsonFile"]);
            if ($configContent!=null&&$configContent!="") {
                $jsonStats = json_decode($configContent,true);

                $times=array();
                $chainsStats = array();

                foreach ($jsonStats as $stats) {


                    if (is_array($stats)&&count($stats)==1) {

                        $statsTime=array_values($stats)[0];
                        $time=array_keys($stats)[0];

                        if ($time>$minTime) { //Avoid NTP out of sync
                            if (!in_array($time,$times)) {
                                $times[]=$time;
                            }
                            foreach ($statsTime as $chainId => $stat) {
                                $chainsStats[$chainId][] = getHashRateShow($stat)['hash_rate'];
                            }
                        }
                    }
                }

                echo json_encode(array("success"=>true,"stats"=>$chainsStats,"times"=>$times));
                return;
            }
        }
        echo json_encode(array("success"=>false,"message"=>"No data to show"));
    }

    /*
     * Get Auto-Tuning Report
     */
    public function getAutoTuneStatusAction() {
        header('Content-Type: application/json');
        $service = new CgminerService();
        $response=$service->call("stats");
        $isTuning=false;
        $isRunning=false;
        if (isset($response["STATS"])) {
            $isRunning=true;
            for ($i = 0; $i < 8; $i++) {
                if (array_key_exists($i, $response["STATS"])) {
                    $stats = $response["STATS"][$i];
                    // is tuning
                    if (
                        ((isset($stats["VidOptimal"]) && $stats["VidOptimal"] === false) ||
                            (isset($stats["pllOptimal"]) && $stats["pllOptimal"] === false))
                        && isAutoTuneEnabled()) {
                        $isTuning = true;
                        break;
                    }
                }
            }
        }
        $autotune_status = getAutoTuneConfig();
        echo json_encode(array("success"=>true,"isRunning"=>$isRunning,"isTuning"=>$isTuning,"mode"=>$autotune_status['mode'],"level"=>$autotune_status['level']));
    }

    /*
     * Consumes light state from dm-monitor and create a JSON response
     * with all the information obtained
     */
    public function SetMonitorAction() 
    {
        $service = new DMMonitorService();
        header('Content-Type: application/json');
        if(isset($_POST['red_light']))
        {
            $light_state = trim($_POST['red_light']);
            $response=$service->call($light_state);
            if (isset($response)&&is_array($response)) 
            {
                // $response["success"]=true;
                echo json_encode($response);
            } 
            else 
            {
                echo json_encode(array("success" => false));
            }            
        }

    }

    /*
     * get msg from err file
     */
    public function getErrorMessageAction()
    {
        global $config;
        // header('Content-Type: application/json');
        if (file_exists($config["errfile"]))
        {
            $configContent=@file_get_contents($config["errfile"]);
            if ($configContent!=null&&$configContent!="")
            {
                $msg_arr = explode(PHP_EOL, $configContent);
                $code = $errmsg = "";
                $code = trim($msg_arr[0]);
                $errmsg = isset($msg_arr[1]) ? $msg_arr[1] : "";

                echo json_encode(array("success"=>true,"code"=>$code,"errmsg"=>$errmsg));
                return;
            }
        }
        else
        {
            echo json_encode(array("success"=>true,"code"=>"0","errmsg"=>""));
        }
    }

    /*
     * get miner unit
     */
    public function getMinerUnitAction()
    {
        header('Content-Type: application/json');
        echo json_encode(array("success"=>true,"unit"=>getHashRateShow(0)['unit_h']));
    }

    /*
     * get the lock state
     */
    public function getLockStateAction()
    {
        header('Content-Type: application/json');
        $status = readlockstate();
        if(strlen($status) > 0)
        {
            echo json_encode(array("success"=>true,"status"=>$status));
        }
        else
        {
            echo json_encode(array("success"=>false));
        }
    }

    /*
     * set the lock
     */
    public function setLockAction()
    {
        header('Content-Type: application/json');
        /*****set record******/
        writerecord($_POST,'lock',readlockstate(),1);
        /*****set record******/
        if(setlockstate())
        {
            echo json_encode(array("success"=>true));
        }
        else
        {
            echo json_encode(array("success"=>false));
        }
    }

    /*
     * get dna
     */
    public function getDNAAction()
    {
        header('Content-Type: application/json');
        $dna_info = getDNA_common();
        if(!empty($dna_info) && strlen(trim($dna_info)) > 0)
        {
            echo json_encode(array("success"=>true,"dna"=>$dna_info));
        }
        else
        {
            echo json_encode(array("success"=>false));
        }
    }

    /*
     * get runtime pools
     */
    public function getRuntimePoolsAction()
    {
        $service = new CgminerService();
        header('Content-Type: application/json');
        $response=$service->call("pools");
        $pools=@$response["POOLS"];

        if(is_array($pools))
        {
            echo json_encode(array("success" => true, "POOLS" => $pools));
        }
        else
        {
            echo json_encode(array("success" => false));
        }
    }

    /*
     * get All info
     */
    public function getAllAction()
    {
        $service = new CgminerService();
        header('Content-Type: application/json');
        //param
        $miner_type = "";
        $fansSpeed=0;
        $chain_rate = array();
        $total_hash_rate = 0;
        $total_hash = array();
        $mac = "";
        $hwver = "";
        $uptime = "";
        $network = "";
        $running_mode = "";
        $dna = "";
        $pools_return = array();
        // echo json_encode(array("time1"=>microtime()));
        //////////////////////////////////////////
        //1. get type
        $miner_type = getMinerType();

        // 2.1 chain hash rate
        // 2.2 chain temp
        // 2.3 fan speed
        $response=$service->call("pools+devs+stats");
        $devs=@$response["devs"][0]["DEVS"];
        for ($i=0;$i<$this->max_chain_num;$i++)
        {
            //hash rate
            if (isset($devs) && array_key_exists($i, $devs))
            {
                // look for hash rate
                $elapsed=intval($devs[$i]["Device Elapsed"]);

                if (array_key_exists("MHS 5m", $devs[$i])&&array_key_exists("MHS 15m", $devs[$i]))
                {
                    if ($elapsed < 5 * 60)
                    {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 5s"];
                    }
                    else if ($elapsed < 15 * 60)
                    {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 1m"];
                    }
                    else if ($elapsed < 60 * 60)
                    {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 5m"];
                    }
                    else
                    {
                        $devs[$i]["Hash Rate"] = $devs[$i]["MHS 15m"];
                    }
                }
                else
                {
                    $devs[$i]["Hash Rate"] = $devs[$i]["MHS av"];
                }

                //total hash
                $total_hash_rate += $devs[$i]["Hash Rate"];

                //temp
                $chain_rate[$i]["Average Temp"] = $devs[$i]["Temperature"];

                //new display
                $new_display = getHashRateShow($devs[$i]["Hash Rate"]);
                $chain_rate[$i]["Hash Rate"] = $new_display['cal_hash'];
                $chain_rate[$i]["Unit"] = $new_display['unit'];
                $chain_rate[$i]["Hash Rate H"] = (int)$new_display['hash_rate'];

                //fan speed
                if (isset($response["stats"][0]["STATS"])&&array_key_exists($i,$response["stats"][0]["STATS"]))
                {
                    $stats = $response["stats"][0]["STATS"][$i];
                    if ($fansSpeed==0&&intval($stats["Fan duty"]) > 0)
                    {
                        $fansSpeed = intval($stats["Fan duty"]);
                    }

                    $chain_rate[$i]["ASC"]  = $i;
                    $chain_rate[$i]["Temp max"] = $stats["Temp max"];
                }
            }
        }
        // 2.4 total hash
        $total_hash_show = getHashRateShow($total_hash_rate);
        $total_hash['Hash Rate'] = $total_hash_show['cal_hash'];
        $total_hash['Unit'] = $total_hash_show['unit'];
        $total_hash['Hash Rate H'] = (int)$total_hash_show['hash_rate'];

        $versions = getVersions();
        // 3. mac address        
        $mac = $versions['ethaddr'];

        // 4. hwver
        $hwver = $versions['hwver'];

        // 5. platform versions
        $platform = $versions['platform_v'];

        // 6. uptime
        $uptime=trim(exec("uptime"));

        // 7. network
        $network=getNetwork();

        // 8. performance mode
        $running_mode = getAutoTuneConfig();

        // 9. dna
        $dna = getDNA_common();

        // 10. pools
        $pools=@$response["pools"][0]["POOLS"];
        if(is_array($pools))
        {
            global $config;
            $configContent=@file_get_contents($config["configFile"]);
            if ($configContent!=null&&$configContent!="")
            {
                $this->config = json_decode($configContent, true);
            }
            $pools_config = $this->config['pools'];

            //temp arr
            $temp_arr = array();
            foreach ($pools as $key => $value) 
            {
                $pools_return[$key]['url'] = $value['URL'];
                $pools_return[$key]['user'] = $value['User'];
                $pools_return[$key]['password'] = $pools_config[$key]['pass'];
                if($value['Stratum Active'])
                {
                    $temp_arr = $pools_return[$key];
                    unset($pools_return[$key]);
                }
            }
            array_unshift($pools_return, $temp_arr);
        }        

        $return_arr = array(
            "type"          =>  $miner_type,
            "chain"         =>  $chain_rate,
            "fansSpeed"     =>  $fansSpeed,
            "total_hash"    =>  $total_hash,
            "mac"           =>  $mac,
            "hwver"         =>  $hwver,
            "platform"      =>  $platform,
            "uptime"        =>  $uptime,
            "network"       =>  $network,
            "running_mode"  =>  $running_mode,
            "dna"           =>  $dna,
            "pools"         =>  $pools_return

        );
        echo json_encode(array("success"=>true,"all"=>$return_arr));
        // echo json_encode(array("time2"=>microtime()));
    }
}
