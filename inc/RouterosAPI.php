<?php
/**
 * Safe & Lightweight MikroTik API class (with timeout and error handling)
 */
class RouterosAPI {
    public $debug = false;
    private $connected = false;
    private $port = 8728;
    public $timeout = 3;
    private $socket;

    public function connect($ip, $login, $password) {
        $this->socket = @fsockopen($ip, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            error_log("RouterOS connection failed to $ip:$this->port — $errstr ($errno)");
            $this->connected = false;
            return false;
        }
        stream_set_timeout($this->socket, $this->timeout);

        $this->write('/login');
        $RE = $this->read(false);

        if (!isset($RE[0]['=ret'])) {
            error_log("RouterOS login challenge missing or invalid response from $ip");
            fclose($this->socket);
            return false;
        }

        $challenge = substr($RE[0]['=ret'], 2);
        $this->write('/login', false);
        $this->write('=name=' . $login, false);
        $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $challenge)));

        $RE = $this->read(false);
        if (isset($RE[0]['!done'])) {
            $this->connected = true;
            return true;
        } else {
            error_log("RouterOS authentication failed for $login@$ip");
            fclose($this->socket);
            $this->connected = false;
            return false;
        }
    }

    public function connect_plain($ip, $login, $password) {
        $this->socket = @fsockopen($ip, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) return false;

        stream_set_timeout($this->socket, $this->timeout);

        $this->write('/login', false);
        $this->write('=name=' . $login, false);
        $this->write('=password=' . $password);

        $RE = $this->read();
        if (!empty($RE)) {
            return true;
        }

        return false;
    }


    public function write($command, $param = true) {
        if ($this->debug) echo ">>> $command\n";
        if (!$this->socket) return false;
        fwrite($this->socket, $command . "\n");
        return true;
    }

    public function read($parse = true) {
        $resp = [];
        if (!$this->socket) return $resp;

        while (!feof($this->socket)) {
            $line = trim(fgets($this->socket));
            if ($line === '' || $line === '!done') break;
            $resp[] = $line;
        }

        if (!$parse) return [['=ret' => $resp[0] ?? '']];

        $parsed = [];
        foreach ($resp as $line) {
            $entry = [];
            preg_match_all('/=([a-zA-Z0-9\-]+)=(.*?)(?= =|$)/', $line, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $entry[$m[1]] = $m[2];
            }
            if (!empty($entry)) $parsed[] = $entry;
        }
        return $parsed;
    }

    // public function disconnect() {
    //     if ($this->socket) fclose($this->socket);
    //     $this->connected = false;
    // }
    public function disconnect() {
    if (isset($this->socket) && is_resource($this->socket)) {
        fclose($this->socket);
    }
    $this->connected = false;
    $this->socket = null;
}

}

// MikroTik helper functions
if (!function_exists('mikrotik_list_active')) {
    function mikrotik_list_active() {
        $API = new RouterosAPI();
        $API->debug = false;

        $host = '192.168.10.67'; 
        $user = 'admin';
        $pass = 'Dvdjesse1998???';

        if (!$API->connect_plain($host, $user, $pass)) {
            throw new Exception("Unable to connect to MikroTik router at $host");
        }

        $API->write('/ppp/active/print');
        $lines = $API->read();
        $active = [];

        foreach ($lines as $line) {
            $entry = [];
            preg_match_all('/=([a-zA-Z0-9\-]+)=(.*?)(?= =|$)/', $line, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $entry[$m[1]] = $m[2];
            }
            if (!empty($entry)) $active[] = $entry;
        }

        $API->disconnect();
        return $active;
    }
}

