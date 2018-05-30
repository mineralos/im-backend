<?php
namespace DragonMint\Service;



class DMMonitorService extends SocketFunc{


    /*
     * Open a socket connection to localhost and a predefined port and return the response
     * the cgminer API
     */
    public function call($cmd,$parameter=null) 
    {
        global $config;
        $sock=$this->getSock("127.0.0.1",$config["dmmonitorPort"]);
        $response=null;
        if ($sock!=null) 
        {
                $response=$this->request($sock,$cmd,$parameter);
        }
        return $response;
    }

    /*
     * Send the command(s) to the socket and reply a JSON response from the data obtained
     */
    public function request($socket,$cmd,$parameter=null)
    {
        try {
            if ($socket != null)
            {
                $parameterStr=($parameter==null?"":$parameter);
                // $cmd='{"command":"'.$cmd.'","parameter":"'.$parameterStr.'"}';
                $cmd='{"red_light":"'.$cmd.'"}';
                socket_write($socket, $cmd, strlen($cmd));
                $line = $this->readsockline($socket);

                socket_close($socket);
                if (strlen($line) == 0)
                {
                    return null;
                }

                if (substr($line,0,1) == '{') {
                    $line=preg_replace('/[^(\x20-\x7F)]*/','', $line);
                    return json_decode($line, true);

                }
            }

        } catch(\Exception $e) {
            return null;
        }
        return null;
    }


}