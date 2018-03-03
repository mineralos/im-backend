<?php
namespace DragonMint\Service;



class CgminerService {


    public function call($cmd,$parameter=null) {
        global $config;
        $sock=$this->getSock("127.0.0.1",$config["cgminerPort"]);
        $response=null;
        if ($sock!=null) {
                $response=$this->request($sock,$cmd,$parameter);
        }
        return $response;
    }

    public function getSock($addr, $port)
    {
        try {
            $socket = null;
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false || $socket === null) {
                $error = socket_strerror(socket_last_error());
                return null;
            }
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 1, 'usec' => 0));
            $res = @socket_connect($socket, $addr, $port);
            if ($res === false) {
                $error = socket_strerror(socket_last_error());
                socket_close($socket);
                return null;
            }
            return $socket;
        } catch(\Exception $e) {
            return null;
        }
    }

    public function readSockLine($socket)
    {
        $line = '';
        while (true)
        {
            $byte = socket_read($socket, 1);
            if ($byte === false || $byte === '')
                break;
            if ($byte === "\0")
                break;
            $line .= $byte;
        }
        return $line;
    }

    public function request($socket,$cmd,$parameter=null)
    {
        try {
            if ($socket != null)
            {
                $parameterStr=($parameter==null?"":$parameter);
                $cmd='{"command":"'.$cmd.'","parameter":"'.$parameterStr.'"}';
                socket_write($socket, $cmd, strlen($cmd));
                $line = $this->readsockline($socket);

                socket_close($socket);

                if (strlen($line) == 0)
                {
                    return $line;
                }
                if (substr($line,0,1) == '{')
                    return json_decode($line, true);
            }

        } catch(\Exception $e) {
            return null;
        }
        return null;
    }

    public function requestOld($socket,$cmd)
    {
        try {
            if ($socket != null)
            {
                socket_write($socket, $cmd, strlen($cmd));
                $line = $this->readsockline($socket);

                socket_close($socket);
                if (strlen($line) == 0)
                {
                    return $line;
                }
                if (substr($line,0,1) == '{')
                    return json_decode($line, true);
                $data = array();
                $objs = explode('|', $line);
                foreach ($objs as $obj)
                {
                    if (strlen($obj) > 0)
                    {
                        $items = explode(',', $obj);
                        $item = $items[0];
                        $id = explode('=', $items[0], 2);
                        if (count($id) == 1 or !is_numeric($id[1]))
                            $name = $id[0];
                        else
                            $name = $id[0].$id[1];
                        if (strlen($name) == 0)
                            $name = 'null';
                        if (isset($data[$name]))
                        {
                            $num = 1;
                            while (isset($data[$name.$num]))
                                $num++;
                            $name .= $num;
                        }
                        $counter = 0;
                        foreach ($items as $item)
                        {
                            $id = explode('=', $item, 2);
                            if (count($id) == 2)
                                $data[$name][$id[0]] = $id[1];
                            else
                                $data[$name][$counter] = $id[0];
                            $counter++;
                        }
                    }
                }
                return $data;
            }

        } catch(\Exception $e) {
            return null;
        }
        return null;
    }
}