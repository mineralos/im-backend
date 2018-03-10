<?php

use DragonMint\Service\CgminerService;

class ConfigController {

    private $config;
    public function __construct(){
        global $config;
        $configContent=file_get_contents($config["configFile"]);
        if ($configContent!=null&&$configContent!="") {
            $this->config = json_decode($configContent, true);
        }

    }

    public function getPoolsAction() {
        header('Content-Type: application/json');
        /*
        $keysNeeded=array("Pool","UserName","Password");
        $pools=array();
        for ($i=1;$i<=3;$i++) {
            foreach ($keysNeeded as $key) {
                $val="";
                if (array_key_exists($key . $i, $this->config)&&!is_null($this->config[$key . $i])&&$this->config[$key . $i]!="") {
                    $val=($key=="Pool"?stripcslashes($this->config[$key . $i]):$this->config[$key . $i]);
                }

            }
        }
        */
        echo json_encode(array("success"=>true,"pools"=>$this->config["pools"]),JSON_UNESCAPED_SLASHES);
    }

    public function updatePoolsAction() {
        header('Content-Type: application/json');

        $keysNeeded=array("Pool","UserName","Password");
        $newKeys=array("Pool","UserName","Password");
        $numPools=0;
        $pools=array();
        for ($i=1;$i<=3;$i++) {
            $pool=array();
            foreach ($keysNeeded as $j=>$key) {
                $val=null;
                if (array_key_exists($key . $i, $_POST)&&!is_null($_POST[$key . $i])&&$_POST[$key . $i]!="") {
                    $val=$_POST[$key . $i];
                }
                $pool[$newKeys[$j]]=$val;
                $this->config[$key . $i] = $val;
            }
            $pools[]=$pool;
        }
        $this->config["pools"]=$pools;
        if (is_null($this->save())) {
            echo json_encode(array("success"=>false,"message"=>"reboot manually"));
        } else {
            echo json_encode(array("success"=>true));
        }



    }

    private function save() {
        global $config;

        //Save Config
        file_put_contents($config["configFile"],json_encode($this->config,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

        //Shutdown CgMiner
        $service = new CgminerService();
        $response=$service->call("systemctl restart cgminer.service",3);
        return ($response==null);
    }

}

?>