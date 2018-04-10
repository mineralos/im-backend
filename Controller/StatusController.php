<?php

use DragonMint\Service\CgminerService;

class StatusController {


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

        for ($i=0;$i<3;$i++) {
            if (isset($response["stats"][0]["STATS"])&&array_key_exists($i,$response["stats"][0]["STATS"])) {
                $stats = $response["stats"][0]["STATS"][$i];
                if (intval($stats["Fan duty"]) > 0) {
                    $fansSpeed = intval($stats["Fan duty"]);
                    break;
                }
            }
        }

        $pools=@$response["pools"][0]["POOLS"];
        if (is_array($devs)&&is_array($pools)) {
            echo json_encode(array("success" => true, "DEVS" => $devs, "POOLS" => $pools, "HARDWARE"=>array("Fan duty"=>$fansSpeed)));
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
        $response=$service->call("stats");

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
        $minTime=strtotime('-5 hours'); //Avoid NTP out of sync
        if (file_exists($config["statsJsonFile"])) {
            $configContent=@file_get_contents($config["statsJsonFile"]);
            if ($configContent!=null&&$configContent!="") {
                $jsonStats = json_decode($configContent);
                $chainsStats = array();
                $times=array();
                if (!is_null($jsonStats)) {
                    //detect max number of chains
                    $chains=0;
                    foreach ($jsonStats as $stats) {
                        foreach ($stats as $time=>$statPerTime) {
                            if ($time>$minTime) { //Avoid NTP out of sync
                                $timeChains = count((array)$statPerTime);
                                if ($timeChains > $chains)
                                    $chains = $timeChains;
                            }

                        }
                    }
                    $timeI=0;
                    foreach ($jsonStats as $stats) {
                        foreach ($stats as $time=>$statPerTime) {
                            if ($time>$minTime) { //Avoid NTP out of sync
                                $times[] = $time;
                                for ($i = 0; $i < $chains; $i++) {
                                    if (isset($statPerTime->{$i})) {
                                        $chainsStats[$i][] = $statPerTime->{$i};
                                    } else {
                                        $chainsStats[$i][] = 0;
                                    }
                                }
                                $timeI++;
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
}