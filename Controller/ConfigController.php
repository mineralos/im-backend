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
     * Check if AutoTune is enabled
     */
    private function hasAutoTune() {
        $needed="noauto";
        if (isset($this->config)&&is_array($this->config)) {
            foreach ($this->config as $key=>$value) {
                if (strpos($key,$needed)>0) {
                    return false;
                }
            }
        }
        return true;
    }

    /*
     * Returns true or false depending if cgminer.conf have
     * custom Pll and Vdd values
     */
    private function hasAgeingConfig($backup=false) {
        global $config;
        $needed="Pll"; //Indicate if we have predefined Freq and Volt
        $configPointer=null;
        if ($backup) {
            if (file_exists($config["backupConfigFile"])) {
                $bkpConfig = null;
                $configContent = @file_get_contents($config["backupConfigFile"]);
                if (!is_null($configContent) && $configContent != "") {

                    $bkpConfig = json_decode($configContent, true);
                }
                if (!is_null($bkpConfig))
                    $configPointer=$bkpConfig;
            }
        } else {
            $configPointer = $this->config;
        }
        if (!is_null($configPointer)&&is_array($configPointer)) {
            foreach ($configPointer as $key=>$value) {
                if (strpos($key,$needed)!==false) {
                    return true;
                }
            }
        }
        return false;
    }


    /*
     * Restore Pll and Vdd from backup
     */
    private function restoreAgeingBackup() {
        global $config;
        $updated=false;
        if (file_exists($config["backupConfigFile"])) {
            $bkpConfig = null;
            $configContent = @file_get_contents($config["backupConfigFile"]);
            if ($configContent != null && $configContent != "") {
                $bkpConfig = json_decode($configContent, true);
            }
            if (!is_null($bkpConfig)) {
                foreach ($bkpConfig as $key => $value) {
                    if (strpos($key, "Vol") !== false || strpos($key, "Pll") !== false) { //Do this way to support multiple miners types
                        $this->config[$key] = $value;
                        $updated = true;
                    }
                }
            }
        }
        return $updated;
    }


    /*
     * Returns a json with true or false depending if cgminer.conf have
     * custom Pll and Vdd values
     */
    public function minerHasAgeingConfigAction() {
        header('Content-Type: application/json');
        echo json_encode(array("success"=>true,"hasAutoTune"=>$this->hasAutoTune(),"hasSelfTest"=>$this->hasAgeingConfig()||$this->hasAgeingConfig(true)));
    }

    /*
     * Toggle custom voltage and frequencies from Config
     */
    public function setAutoTuneConfigAction() {
        global $config;
        header('Content-Type: application/json');
        if (isset($_POST["autotune"])) {
            $updated=false;
            //Restore values from backup
            if (!$this->hasAgeingConfig()&&$this->hasAgeingConfig(true)) {
                $updated=$this->restoreAgeingBackup();
            }
            if ($_POST["autotune"]=="true") {
                //Unset noauto key
                if (isset($this->config[getMinerType()."noauto"])) {
                    unset($this->config[getMinerType() . "noauto"]);
                    $updated=true;
                }
                if ($updated)
                    $this->save();
                echo json_encode(array("success"=>true));
            } else if ($_POST["autotune"]=="false"){
                $this->config[getMinerType() . "noauto"]=true;
                $this->save();
                echo json_encode(array("success"=>true));
            }
        } else {
            echo json_encode(array("success"=>false,"message"=>"missing autotune value"));
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