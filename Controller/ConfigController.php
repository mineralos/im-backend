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
            /*****set record******/
            writerecord($_POST,'pool'.$i,stripslashes(json_encode($this->config["pools"][$i-1])),stripslashes(json_encode($pools[$i-1])));
            /*****set record******/
        }
        $this->config["pools"]=$pools;
        if (is_null($this->save()))
        {
            echo json_encode(array("success"=>false,"message"=>"reboot manually"));
        }
        else
        {
            //judge the lock to set or not
            if(readlockstate() == "1")
            {
                echo json_encode(array("success"=>true,"message"=>"lock"));
            }
            else
            {
                echo json_encode(array("success"=>true,"message"=>"nolock"));
            }
        }
    }

    /*
     * Returns cgminer autotune mode
     */
    public function getAutoTuneConfigAction() {
        echo json_encode(array("success"=>true,"autoTuneMode"=>getAutoTuneConfig()));
    }

    /*
     * removes keys with specified names
     */
    private function clearAutoTuneOptions() {
        $keys=array("noauto","efficient","factory","performance");
        $type=getMinerType();
        $updated=false;
        if (!is_null($this->config)&&is_array($this->config)) {
            foreach ($keys as $key) {
                /*if (array_key_exists($type.$key,$this->config)) {
                    unset($this->config[$type.$key]);
                    $updated=true;
	               }*/
                //keys
                $array_keys = array_keys($this->config);
                foreach ($array_keys as $array_key) 
                {
                    if(strpos($array_key, $key) !== FALSE)
                    {
                        unset($this->config[$array_key]);
                        $updated=true;
                    }
                }
            }
        }
        return $updated;

    }

    /*
     * Toggle custom voltage and frequencies from Config
     */
    public function setAutoTuneConfigAction() {
        global $config;
        header('Content-Type: application/json');
        if (isset($_POST["autotune"])) {
            $updated=false;
            $mode="";
            $level = "2";
            if(isset($_POST['level']))
            {
                $level = trim($_POST['level']);
            }
            $prefix = "T1";
            switch($_POST["autotune"]) {
                case "efficient":
                    $mode="efficient";
                    $this->clearAutoTuneOptions();
                    $this->config[$prefix."efficient"]=true;
                    $updated=true;
                    break;
                case "balanced":
                    $mode="balanced";
                    $updated=$this->clearAutoTuneOptions();
                    break;
                case "factory":
                    $mode="factory";
                    $this->clearAutoTuneOptions();
                    $this->config[$prefix."factory"]=true;
                    $updated=true;
                    break;
                case "performance":
                    $mode="performance";
                    $this->clearAutoTuneOptions();
                    $this->config[$prefix."performance"]=true;
                    $updated=true;
            }
            //add mode level
            $this->config[$prefix."adjust"]=$level;

            //record
            $beforerecord = getAutoTuneConfig();
            if ($updated) {
                //Save Profile Setting
                $profile=array("mode"=>$mode,"level"=>$level);
                file_put_contents($config["profileFile"],json_encode($profile));
                /*****set record******/
                writerecord($_POST,'run_mode',json_encode($beforerecord),json_encode($profile));
                /*****set record******/
                //Save CgMiner config
                $this->save();
            }
            echo json_encode(array("success"=>true));
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

    /*
     * Return the freq and vol from the cgminer config file in JSON format
     */
    public function getFreqVolAction() 
    {
        header('Content-Type: application/json');
        if (isset($this->config)&&isset($this->config["T1Pll1"])&&isset($this->config["T1VID1"])) 
        {
            echo json_encode(array("success" => true, "freq" => $this->config["T1Pll1"], "vol" => $this->config["T1VID1"]), JSON_UNESCAPED_SLASHES);
        } 
        else 
        {
            echo json_encode(array("success" => false, "message" => "missing configuration"));
        }
    }

    /*
     * Update the freq and vol received ina POST request and
     * update the config file, then restarts cgminer
     */
    public function setFreqVolAction() 
    {
        header('Content-Type: application/json');

        /*一共八个pll和vid，现在暂时都相同*/
        $pll_num = 8;
        $vid_num = 8;

        $pll_value  = htmlspecialchars($_POST['Freq']);
        $vid_value  = htmlspecialchars($_POST['Vol']);
        // 赋值Pll
        for ($i=1;$i<=$pll_num;$i++) 
        {
            $this->config["T1Pll".$i] = $pll_value;
        }
        // 赋值VID
        for ($i=1;$i<=$vid_num;$i++) 
        {
            $this->config["T1VID".$i] = $vid_value;
        }

        // if (is_null($this->save())) 
        // {
        //     echo json_encode(array("success"=>false,"message"=>"reboot manually"));
        // } 
        // else 
        // {
        //     echo json_encode(array("success"=>true));
        // }
    }

}
