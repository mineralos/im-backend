<?php


class UpdateController {



    public function __construct(){

    }

    public function getLatestFirmwareVersionAction() {
        global $config;
        header('Content-Type: application/json');

        $versions=null;
        $latestVersion="";
        $latestVersionDate="";
        $latestUrl="";
        $latestInfo="";
        $currentVersionDate="";

        //Get Current Version and Type
        $minerType=getMinerType();
        $hardwareVersion=getHardwareVersion();
        $currentVersions=getVersions();
        $currentVersion=$currentVersions["platform_v"];
        $currentVersionDate=getDateFromVersion($currentVersion);

        //Create GET params
        $params=array("minerType"=>$minerType,"hardwareVersion"=>$hardwareVersion,"currentVersion"=>$currentVersion);
        $response=getUrlData($config["urlFirmwareVersions"],$params);

        $isUpdated=true;
        if ($response!=null) {
            $versions = json_decode($response,true);
            if ($versions!=null&&is_array($versions)&&array_key_exists($minerType, $versions)) {
                $latestVersion = $versions[$minerType]["version"];
                $latestUrl = $versions[$minerType]["url"];
                $latestInfo = $versions[$minerType]["info"];
                $latestVersionDate=getDateFromVersion($latestVersion);
            }


            //Compare Versions Dates
            if ($latestVersionDate>$currentVersionDate) {
                $isUpdated=false;
            }
        }

        if ($latestVersion!="")
            echo json_encode(array(
                "success"=>true,
                "version"=>$latestVersion,
                "versionDate"=>date_format($latestVersionDate,'jS \of F Y h:i A'),
                "url"=>$latestUrl,
                "info"=>$latestInfo,
                "currentVersion"=>$currentVersion,
                "currentVersionDate"=>date_format($currentVersionDate,'jS \of F Y h:i A'),
                "isUpdated"=>$isUpdated
            ));
        else
            echo json_encode(array("success"=>false,"message"=>"can't fetch firmware versions"));
    }



}