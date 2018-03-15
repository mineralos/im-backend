<?php
namespace DragonMint\Service;


class SWUpdateService {

    /* Not being used, created to consume a Unix Socket to get the obtained*/
    public function getProgress() {
        global $config;
        $sock = stream_socket_client($config["swUpdateProgressSocket"], $errno, $errstr);

        $output=fread($sock, 4096)."\n";

        fclose($sock);
        if ($output!=null) {
            return $output;
        }
        return null;
    }

    /*
     * Determine the command line to execute to start the upgrade of the firmware
     * and returns the response of the swupdate system call
     */
    public function runUpgrade() {
        global $config;
        $returnVar=-1;
        $output=array();
        exec("sync");
        //check the update flag
        $imageFlag=exec("fw_printenv -n image_flag");
        $flag=($imageFlag!=null&&$imageFlag==1?"main":"alt");
        $swUpdateCommand="/usr/bin/swupdate --key /etc/swupdate.pem -b \"0 1 2 3 4 5 6 7\" --select stable,".$flag." -v -i ".$config["swUpdateImagePath"];
        exec($swUpdateCommand,$output,$returnVar);
        $output[]="Update flag: ".$flag;
        return array("returnVar"=>$returnVar,"output"=>$output);
    }


}