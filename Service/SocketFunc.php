<?php 
namespace DragonMint\Service;

class SocketFunc
{
    /*
     * Create a sock from especified address and port
     */
    public function getSock($addr, $port)
    {
        try {
            $socket = null;
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket,SOL_SOCKET,SO_RCVTIMEO,array("sec"=>10, "usec"=>0 ) );
            socket_set_option($socket,SOL_SOCKET,SO_SNDTIMEO,array("sec"=>10, "usec"=>0 ) );
            if ($socket === false || $socket === null) {
                $error = socket_strerror(socket_last_error());
                return null;
            }
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



    /*
     * Read one line from the received buffer of the cgminer API call
     */
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
}

 ?>