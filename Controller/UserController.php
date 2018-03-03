<?php

use DragonMint\Service\CgminerService;
use Firebase\JWT\JWT;

class UserController {

    private $users=null;
    public function __construct(){
        global $config;
        $configContent=null;
        if (file_exists($config["usersFile"]))
        $configContent=file_get_contents($config["usersFile"]);
        if ($configContent!=null&&$configContent!="") {
            $this->users = json_decode($configContent, true);
        }
        if ($this->users === null || json_last_error() !== JSON_ERROR_NONE) {

            $this->users=array(array("username"=>$config["userAdmin"],"password"=>$this->generatePasswordHash($config["passwordAdmin"])),array("username"=>$config["userGuest"],"password"=>$this->generatePasswordHash($config["passwordGuest"])));
            $this->save();
        }

    }


    public function authAction() {
        global $config;
        if (isset($_POST["username"])&&$_POST["username"]!=""&&isset($_POST["password"])&&$_POST["password"]!="") {
            $username=preg_replace("/[^a-zA-Z0-9_\-]+/","",$_POST["username"]);
            foreach ($this->users as $user) {
                if ($username==$user["username"]) {
                    if ($this->generatePasswordHash($_POST["password"])==$user["password"]) {
                        //Valid Password
                        $key = get_jwt_key();
                        $now=time();
                        $token = array(
                            "iss" => $config["serverName"],
                            "iat" => $now,
                            "exp" => $now+intval($config["jwtTokenExpire"]),
                            "user"=>$user["username"]
                        );
                        $jwt = JWT::encode($token, $key);
                        echo json_encode(array("success"=>true,"jwt"=>$jwt));
                        return;
                    } else {
                        echo json_encode(array("success"=>false,"message"=>"invalid password"));
                        return;
                    }
                }
            }
            echo json_encode(array("success"=>false,"message"=>"user not found"));
            return;
        } else {
            echo json_encode(array("success"=>false,"message"=>"missing username/password fields"));
            return;
        }
    }

    public function updatePasswordAction() {
        global $config;
        header('Content-Type: application/json');
        $validPassword=false;
        if (isset($_POST["currentPassword"]) &&$_POST["currentPassword"]!="") {
            foreach ($this->users as $user) {
                if ($config["userAdmin"] == $user["username"]) {
                    if ($this->generatePasswordHash($_POST["currentPassword"]) == $user["password"]) {
                        $validPassword=true;
                    }
                    break;
                }
            }
        }
        if (!$validPassword) {
            echo json_encode(array("success"=>false,"message"=>"wrong current password"));
            return;
        }
        if (!isset($_POST["user"])||($_POST["user"]!=$config["userAdmin"]&&$_POST["user"]!=$config["userGuest"])) {
            echo json_encode(array("success"=>false,"message"=>"invalid user"));
            return;
        }
        if (!isset($_POST["newPassword"])||$_POST["newPassword"]=="") {
            echo json_encode(array("success"=>false,"message"=>"invalid new password"));
            return;
        }
        $updatedPassword=false;
        $userPos=0;

        foreach ($this->users as $user) {
            if ($_POST["user"] == $user["username"]) {
                $this->users[$userPos]["password"]=$this->generatePasswordHash($_POST["newPassword"]);
                $updatedPassword=true;
                break;
            }
            $userPos++;
        }
        

        if ($updatedPassword) {
            $this->save();
            echo json_encode(array("success"=>true));
            return;
        } else {
            echo json_encode(array("success"=>false,"message"=>"cant update the password"));
            return;
        }

    }

    private function save() {
        global $config;

        //Save Config
        file_put_contents($config["usersFile"],json_encode($this->users,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    }

    private function generatePasswordHash($password) {
        global $config;
        return hash('sha256', $config["salt"].$password);
    }

}

?>