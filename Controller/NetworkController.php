<?php


class NetworkController {


    public function getNetworkAction() {
        global $config;
        header('Content-Type: application/json');


        $dhcp=exec('cat '.$config["interfacesFile"].'| grep ^iface | sed -n \'$p\' | awk \'{print $4}\'');
        $ip   = exec("ifconfig | grep inet | sed -n '1p' | awk '{print $2}' | awk -F ':' '{print $2}'");
        $netmask= exec("ifconfig |grep inet| sed -n '1p'|awk '{print $4}'|awk -F ':' '{print $2}'");
        $gw = exec("route -n | grep eth0 | grep UG | awk '{print $2}'");
        $dns=array();
        if (file_exists($config["resolvFile"])) {
            $dnsContent = file($config["resolvFile"], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($dnsContent as $item) {
                $dnsParts=explode("nameserver ",$item);
                if(isset($dnsParts[1])) {
                    $dns[]=trim($dnsParts[1]);
                }
            }
        }
        echo json_encode(array("success"=>true,"dhcp"=>$dhcp,"ipaddress"=>$ip,"netmask"=>$netmask,"gateway"=>$gw,"dns"=>$dns));
    }

    public function updateNetworkAction() {
        global $config;
        header('Content-Type: application/json');

        if (!isset($_POST["dhcp"])||($_POST["dhcp"]!="dhcp"&&$_POST["dhcp"]!="static")) {
            echo json_encode(array("success"=>false,"message"=>"invalid dhcp setting"));
            return;
        }

        if ($_POST["dhcp"]=="dhcp") {
            $fileContent="[Match]\n";
            $fileContent.="Name=eth0\n";
            $fileContent.="[Network]\n";
            $fileContent.="DHCP=ipv4\n";
            file_put_contents($config["interfacesFile"],$fileContent);
            echo json_encode(array("success"=>true));
        } else if ($_POST["dhcp"]=="static"){
            if (!isset($_POST["ipaddress"])||$_POST["ipaddress"]==""||!isValidIp($_POST["ipaddress"])) {
                echo json_encode(array("success"=>false,"message"=>"invalid ip address"));
                return;
            }
            if (!isset($_POST["netmask"])||$_POST["netmask"]==""||!isValidIp($_POST["netmask"])) {
                echo json_encode(array("success"=>false,"message"=>"invalid netmask "));
                return;
            }
            if (!isset($_POST["gateway"])||$_POST["gateway"]==""||!isValidIp($_POST["gateway"])) {
                echo json_encode(array("success"=>false,"message"=>"invalid gateway "));
                return;
            }
            if (!isset($_POST["dns"][0])||$_POST["dns"][0]==""||!isValidIp($_POST["dns"][0])) {
                echo json_encode(array("success"=>false,"message"=>"invalid dns 1 "));
                return;
            }
            if (!isset($_POST["dns"][1])||$_POST["dns"][1]==""||!isValidIp($_POST["dns"][1])) {
                echo json_encode(array("success"=>false,"message"=>"invalid dns 2 "));
                return;
            }
            $fileContent="[Match]\n";
            $fileContent.="Name=eth0\n";
            $fileContent.="[Network]\n";
            $fileContent.="Address=".$_POST["ipaddress"]."\/".mask2cidr($_POST["netmask"])."\n";
            $fileContent.="Gateway=".$_POST["gateway"]."\n";

            file_put_contents($config["interfacesFile"],$fileContent);

            $fileContentDns="nameserver ".$_POST["dns"][0]."\n";
            $fileContentDns.="nameserver ".$_POST["dns"][1]."\n";
            file_put_contents($config["resolvFile"],$fileContentDns);

            echo json_encode(array("success"=>true));

        }
        $this->restartNetwork();

    }

    private function restartNetwork() {
        exec('systemctl restart systemd-networkd');
    }


    public function pingAction() {
        header('Content-Type: application/json');
        echo json_encode(array("success"=>true));
    }


}

?>