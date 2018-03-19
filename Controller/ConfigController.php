<?php

use DragonMint\Service\CgminerService;

class ConfigController {

    private $config;

    /*
     * Load the json configuration file into the $config property of the class
     */
    public function __construct(){
        global $config;
        $configContent=@file_get_contents($config["configFile"]);
        if ($configContent!=null&&$configContent!="") {
            $this->config = json_decode($configContent, true);
        }

    }

    /*
     * Return the pools from the cgminer config file in JSON format
     */
    public function getPoolsAction() {
        header('Content-Type: application/json');
        if (isset($this->config)&&isset($this->config["pools"])) {
            echo json_encode(array("success" => true, "pools" => $this->config["pools"]), JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(array("success" => false, "message" => "missing configuration"));
        }
    }

    /*
     * Update the pools received ina POST request and
     * update the config file, then restarts cgminer
     */
    public function updatePoolsAction() {
        header('Content-Type: application/json');

        $keysNeeded=array("Pool","UserName","Password");
        $newKeys=array("url","user","pass");
        $numPools=0;
        $pools=array();
        for ($i=1;$i<=3;$i++) {
            $pool=array();
            foreach ($keysNeeded as $j=>$key) {
                $val=null;
                if (array_key_exists($key . $i, $_POST)&&!is_null($_POST[$key . $i])&&$_POST[$key . $i]!="") {
                    $val=$_POST[$key . $i];
                }
                if (is_null($val)) {
                    $val="";
                }
                $pool[$newKeys[$j]]=$val;
            }
            if ($pool["url"]!=""&&$pool["user"]!=""&&$pool["pass"]!="")
                $pools[]=$pool;
        }
        $this->config["pools"]=$pools;
        if (is_null($this->save())) {
            echo json_encode(array("success"=>false,"message"=>"reboot manually"));
        } else {
            echo json_encode(array("success"=>true));
        }
    }

    /*
     * Write the actual $config property into the configuration file
     * and restart the cgminer service
     */
    private function save() {
        global $config;

        //Save Config
        file_put_contents($config["configFile"],json_encode($this->config,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

        //Shutdown CgMiner
        $returnVar=-1;
        $output=array();
        exec("systemctl restart cgminer.service",$output,$returnVar);

        return ($returnVar==0);
    }



}