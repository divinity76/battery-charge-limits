#!/usr/bin/env php
<?php

declare(strict_types=1);
const CHARGE_MINIMUM = 5; // below this point, it will always attempt to charge regardless of temperature.
init();
$b = get_battery_status();
echo "here is your current battery stats: ";
var_dump($b);
$max_charge = ask("stop charging at %", 100);
if (false === ($max_charge = filter_var($max_charge, FILTER_VALIDATE_FLOAT, array(
    'options' => array(
        'min_range' => CHARGE_MINIMUM + 1,
        'max_range' => 100
    )
)))) {
    die("invalid value, must be numeric between 0-100\n");
}
$max_temperature = ask("max battery temperature while charging", 39);
if (false === ($max_temperature = filter_var($max_charge, FILTER_VALIDATE_FLOAT, array(
    'options' => array(
        'min_range' => 0,
        'max_range' => 100
    )
)))) {
    die("invalid value, must be numeric between 0-100\n");
}
$hs110_ip = ask("hs110 ip address", "192.168.1.109");
//port? nah, never seen it be something other than 9999...$hs110_port=ask("hs110 port (")
$hs110_port = 9999;
//TODO: test connection!
echo "testing hs110 connection...";
$hs110 = new tpapi($hs110_ip, $hs110_port);
var_dump($hs110->execCommand("info"));
echo "that's all! will now begin doing my thing.\n";
for (;;) {
    $charge = true;
    $reason = "(default)";
    $bat = get_battery_status();
    if ($bat->percentage < CHARGE_MINIMUM) {
        $charge = true;
        $reason = "percentage below CHARGE_MINIMUM";
    } elseif ($bat->temperature > $max_temperature) {
        $charge = false;
        $reason = "battery temp above max (max: {$max_temperature} now: {$bat->temperature}";
    } elseif ($bat->percentage >= $max_charge) {
        $charge = false;
        $reason = "battery percentage >= max_charge.";
    }
    echo "\nCurrent status: ".($charge ? "charging" : "not charging").". reason: ".$reason;
    $hs110->execCommand( $charge ? "on":"off");
    sleep(3);
}






function ask(string $question, $default = null)
{
    static $first = true;
    static $custom_defaults = [];
    if ($first) {
        $first = false;
        if (file_exists(__FILE__ . ".custom_default.db.json")) {
            $custom_defaults = json_decode(file_get_contents(__FILE__ . ".custom_default.db.json"), true, 999, JSON_THROW_ON_ERROR);
        }
    }
    if (isset($custom_defaults[$question])) {
        $default = $custom_defaults[$question];
    }

    echo "{$question}? ";
    if ($default !== null) {
        echo " (default: {$default})";
    }
    echo ": ";
    $ret = trim(fgets(STDIN));
    if (empty($ret)) {
        if ($default === null) {
            die("Error: you *must* enter a value!\n");
        } else {
            return $default;
        }
    }
    $custom_defaults[$question] = $ret;
    file_put_contents(__FILE__ . ".custom_default.db.json", json_encode($custom_defaults, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
    return $ret;
}

class Battery_status
{
    public $health = ""; // one possible value is "GOOD", dunno what the others are
    public $percentage = 0; // seems to be int, not float
    public $plugged = false; // actually seems to say "PLUGGED" or "UNPLUGGED" but ill convert them to bool (strictly) 
    public $status = "";
    public $temperature = 0.0;
}
function get_battery_status(): Battery_status
{
    static $firstrun = true;
    if ($firstrun) {
        echo "(if we freeze now, you (probably) suffer from https://github.com/termux/termux-packages/issues/334 ..";
    }
    $json = trim(shell_exec("termux-battery-status 2>&1"));
    if ($firstrun) {
        echo ". no you don't!)\n";
        $firstrun = false;
    }
    try {
        $decoded = json_decode($json, true, 1337, JSON_THROW_ON_ERROR);
    } catch (JsonException $ex) {
        echo ("error: could not decode json from termux-battery-status - most likely you don't have the \"Termux API\" package installed! (look it up on Google Play Store)\n");
        var_dump($json);
        die(1);
    }
    $ret = new Battery_status();
    $props = array_keys((array) $ret);
    foreach ($decoded as $key => $val) {
        if (false === ($prop_key = array_search($key, $props, true))) {
            throw new \LogicException("got unknown property \"{$key}\" from termux-battery-status! json: \"{$json}\"");
        }
        unset($props[$prop_key]);
        $lkey = strtolower($key);
        if ($lkey === "plugged") {
            if ($val === "PLUGGED" || $val === "PLUGGED_AC") {
                $ret->plugged = true;
            } elseif ($val === "UNPLUGGED") {
                $ret->plugged = false;
            } else {
                throw new \LogicException("DID NOT UNDERSTAND plugged VALUE FROM termux-battery-status! json: \"{$json}\"");
            }
        } else {
            $ret->$key = $val;
        }
    }
    if (!empty($props)) {
        throw new \LogicException("Failed to get the following information from termux-battery-status: " . print_r($props, true));
    }
    return $ret;
}
// https://github.com/divinity76/hs110-api-php
class tpapi
{
    protected static function encrypt(string $string): string
    {
        $key = 171;
        $ret = "\0\0\0\0";
        // TODO: should it be str_split or mb_strsplit ?
        // Warning: Might have a bug with unicode encodings (æøå)
        foreach (str_split($string, 1) as $chr) {
            $a = $key ^ ord($chr);
            $key = $a;
            $ret .= chr($a);
        }
        return $ret;
    }
    protected static function decrypt(string $encrypted): string
    {
        $key = 171;
        $ret = "";
        foreach (str_split($encrypted, 1) as $byte) {
            $a = $key ^ ord($byte);
            $key = ord($byte);
            $ret .= chr($a);
        }
        return $ret;
    }
    public function execRaw(string $command, bool $prettifyReturn = true, bool $encryptInput = true, bool $decryptOutput = true): string
    {
        try {
            $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$sock) {
                throw new \RuntimeException('failed to create socket!');
            }
            socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array(
                'sec' => 4,
                'usec' => 0
            ));
            socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, array(
                'sec' => 4,
                'usec' => 0
            ));
            if (!socket_connect($sock, $this->ip, $this->port)) {
                throw new \RuntimeException('failed to connect!');
            }
            $commandEnc = ($encryptInput ? self::encrypt($command) : $command);
            // TODO: socket_write_all
            $written = socket_write($sock, $commandEnc);
            if (strlen($commandEnc) !== $written) {
                throw new RuntimeException('tried to write ' . strlen($written) . ' bytes, but could only write ' . var_export($written, true) . ' bytes!');
            }
            $recievedRaw = '';
            while (false != ($last = socket_read($sock, 4096))) {
                // waiting for remote host to close connection
                $recievedRaw .= $last;
            }
            if (empty($recievedRaw)) {
                // mhm
                return $recievedRaw;
            }
            // var_dump ( $recievedRaw );
            $recieved = ($decryptOutput ? self::decrypt($recievedRaw) : $recievedRaw);
            // var_dump ( substr ( $recieved, 5 ) ); //
            // var_dump ( $recieved );
            if ($prettifyReturn) {
                // why skip the first 5 bytes? idk, some protocol weirdness (perhaps a checksum?)
                // why add the weird { ? idk, some corruption somewhere...
                $json = json_decode('{' . substr($recieved, 5), true, 512, JSON_BIGINT_AS_STRING);
                if (json_last_error()) {
                    throw new \RuntimeException('1failed to prettify return! (only json can be prettified, turn off $prettifyReturn if its not json) json_last_error: ' . json_last_error() . '. json_last_error_msg: ' . json_last_error_msg());
                }
                $json = json_encode($json, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                if (json_last_error()) {
                    throw new \RuntimeException('2failed to prettify return! (only json can be prettified, turn off $prettifyReturn if its not json) json_last_error: ' . json_last_error() . '. json_last_error_msg: ' . json_last_error_msg());
                }
                $recieved = $json;
            }
            return $recieved;
        } finally {
            socket_close($sock);
        }
    }
    public $commands = array(
        'info' => '{"system":{"get_sysinfo":null}}',
        'reboot' => '{"system":{"reboot":{"delay":1}}}',
        'reset' => '{"system":{"reset":{"delay":1}}}',
        'on' => '{"system":{"set_relay_state":{"state":1}}}',
        'off' => '{"system":{"set_relay_state":{"state":0}}}',
        'realtime' => '{"emeter":{"get_realtime":{}}}',
        'now' => '{"emeter":{"get_realtime":{}}}',
        'month' => '{"emeter":{"get_daystat":{"month":01,"year":2018}}}',
        'lastmonth' => '{"emeter":{"get_daystat":{"month":0,"year":2018}}}',
        'year' => '{"emeter":{"get_monthstat":{"year":2018}}}'
    );
    public function execCommand(string $command): string
    {
        // https://github.com/softScheck/tplink-smartplug/blob/master/tplink-smarthome-commands.txt
        $this->commands['month'] = '{"emeter":{"get_daystat":{"month":' . (date("m")) . ',"year":' . (date("Y")) . '}}}';
        $this->commands['lastmonth'] = (date("m") === '1' ? '{"emeter":{"get_daystat":{"month":12,"year":' . (date("Y") - 1) . '}}}' : '{"emeter":{"get_daystat":{"month":' . (date("m") - 1) . ',"year":' . (date("Y")) . '}}}');
        $this->commands['year'] = '{"emeter":{"get_monthstat":{"year":' . (date("Y")) . '}}}';
        $command = strtolower($command);
        if (!in_array($command, array_keys($this->commands), true)) {
            throw new \InvalidArgumentException('unknown command! supported commands: ' . implode(' - ', array_keys($this->commands)));
        }
        return $this->execRaw($this->commands[$command], true);
    }
    public $ip;
    public $port;
    function __construct(string $ip, int $port = 9999)
    {
        if ($port < 0 || $port > 0xFFFF) {
            throw new InvalidArgumentException('port must be between 0-65535, ps, the default port is 9999');
        }
        $this->port = $port;
        // TODO: FILTER_VALIDATE__IP?
        $this->ip = $ip;
    }
}

function init()
{
    hhb_init();
}

/**
 * enables hhb_exception_handler and hhb_assert_handler and sets error_reporting to max
 */
function hhb_init()
{
    static $firstrun = true;
    if ($firstrun !== true) {
        return;
    }
    $firstrun = false;
    error_reporting(E_ALL);
    set_error_handler("hhb_exception_error_handler");
    // ini_set("log_errors",'On');
    // ini_set("display_errors",'On');
    // ini_set("log_errors_max_len",'0');
    // ini_set("error_prepend_string",'<error>');
    // ini_set("error_append_string",'</error>'.PHP_EOL);
    // ini_set("error_log",__DIR__.DIRECTORY_SEPARATOR.'error_log.php.txt');
    assert_options(ASSERT_ACTIVE, 1);
    assert_options(ASSERT_WARNING, 0);
    assert_options(ASSERT_QUIET_EVAL, 1);
    assert_options(ASSERT_CALLBACK, 'hhb_assert_handler');
}
function hhb_exception_error_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
function hhb_assert_handler($file, $line, $code, $desc = null)
{
    $errstr = 'Assertion failed at ' . $file . ':' . $line . ' ' . $desc . ' code: ' . $code;
    throw new ErrorException($errstr, 0, 1, $file, $line);
}
