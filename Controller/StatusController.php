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


}