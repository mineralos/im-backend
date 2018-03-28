<?php


class UpdateController {



    public function __construct(){

    }

    public function getLatestFirmwareVersionAction() {
        global $config;
        header('Content-Type: application/json');

        $latestVersion="";
        $latestVersionDate="";
        $latestUrl="";
        $latestInfo="";
        $isUpdated=true;


        $minerType=getMinerType();
        $hardwareVersion=getHardwareVersion();
        $currentVersions=getVersions();
        $currentVersion=$currentVersions["platform_v"];
        $currentVersionDate=date_format(getDateFromVersion($currentVersion),'jS \of F Y h:i A');

        //Create GET params
        $params=array("minerType"=>$minerType,"hardwareVersion"=>$hardwareVersion,"currentVersion"=>$currentVersion);

        //Send GET request
        $response=getUrlData($config["urlFirmwareVersions"],$params);
        if ($response!=null) {
            $versions = json_decode($response,true);
            if ($versions!=null&&is_array($versions)) {
                $latestVersion = $versions["version"];
                $latestUrl = $versions["url"];
                $latestInfo = $versions["info"];
                $isUpdated = $versions["updated"];
                $latestVersionDate=$versions["versionDate"];
            }
        }

        if ($latestVersion!="")
            echo json_encode(array(
                "success"=>true,
                "version"=>$latestVersion,
                "versionDate"=>$latestVersionDate,
                "url"=>$latestUrl,
                "info"=>$latestInfo,
                "currentVersion"=>$currentVersion,
                "currentVersionDate"=>$currentVersionDate,
                "isUpdated"=>$isUpdated
                ));
        else
            echo json_encode(array("success"=>false,"message"=>"can't fetch firmware versions"));
    }


}