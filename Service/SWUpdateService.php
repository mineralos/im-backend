<?php
namespace DragonMint\Service;


class SWUpdateService {


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


}