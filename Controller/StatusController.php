<?php

use DragonMint\Service\CgminerService;

class StatusController {

    public function getSummaryAction() {
        $service = new CgminerService();
        header('Content-Type: application/json');
        $response=$service->call("pools+devs");
        $devs=@$response["devs"][0]["DEVS"];
        $pools=@$response["pools"][0]["POOLS"];
        if (is_array($devs)&&is_array($pools)) {
            echo json_encode(array("success" => true, "DEVS" => $devs, "POOLS" => $pools));
        } else {
            echo json_encode(array("success" => false));
        }
    }

}

?>