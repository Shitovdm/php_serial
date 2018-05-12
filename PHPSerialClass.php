<?php

define("DEVICE_UNDEFINED", 0);
define("DEVICE_DEFINED", 1);
define("DEVICE_OPENED", 2);

/**
 * Description of PHPSerialClass
 * 
 * 
 * 
 * @author Shitov Dmitry <shitov.dm@gmail.com>
 * @thanks Rémy Sanchez <remy.sanchez@hyperthese.net>
 * @thanks Rizwan Kassim <rizwank@geekymedia.com>
 * 
 * @copyright Under GPL 2 licence
 */
class PHPSerialClass {

    public $OS = "";
    public $Device = null;
    public $WindowsDevice = null;
    public $DeviceHandle = null;
    public $DeviceCondition = DEVICE_UNDEFINED;
    public $Buffer = "";
    public $AutoReset = true;

    /**
     * Constructor. Perform some checks about the OS and setserial.
     *
     * @return PhpSerial
     */
    public function PHPSerialClass() {
        setlocale(LC_ALL, "en_US");

        $SystemName = php_uname();

        if (substr($SystemName, 0, 5) === "Linux") {
            $this->OS = "linux";

            if ($this->_exec("stty") === 0) {
                register_shutdown_function(array($this, "deviceClose"));
            } else {
                trigger_error("No stty availible, unable to run.", E_USER_ERROR);
            }
        } else {
            trigger_error("OS not supported!", E_USER_ERROR);
            exit();
        }
    }

    /**
     * Device set function : used to set the device name/address.
     * -> linux : use the device address, like /dev/ttyS0
     * -> osx : use the device address, like /dev/tty.serial
     * -> windows : use the COMxx device name, like COM1 (can also be used
     *     with linux)
     *
     * @param  string $device the name of the device to be used
     * @return bool
     */
    public function deviceSet($device) {
        if ($this->DeviceCondition !== DEVICE_OPENED) {
            if ($this->OS === "linux") {
                if (preg_match("@^COM(\\d+):?$@i", $device, $matches)) {
                    $device = "/dev/ttyS" . ($matches[1] - 1);
                }
                if ($this->_exec("stty -F " . $device) === 0) {
                    $this->Device = $device;
                    $this->DeviceCondition = DEVICE_DEFINED;
                    return true;
                }
            }
            trigger_error("Serial port is not valid.", E_USER_WARNING);
            return false;
        } else {
            trigger_error("The device is currently open! Try to close it.", E_USER_WARNING);
            return false;
        }
    }

    /**
     * Opens the device for reading and/or writing.
     *
     * @param  string $mode Opening mode : same parameter as fopen()
     * @return bool
     */
    public function deviceOpen($mode = "r+b") {
        if ($this->DeviceCondition === DEVICE_OPENED) {
            trigger_error("The device is already opened", E_USER_NOTICE);
            return true;
        }
        if ($this->DeviceCondition === DEVICE_UNDEFINED) {
            trigger_error("The device must be set before to be open", E_USER_WARNING);
            return false;
        }
        if (!preg_match("@^[raw]\\+?b?$@", $mode)) {
            trigger_error("Invalid opening mode : " . $mode . ". Use fopen() modes.", E_USER_WARNING);
            return false;
        }
        $this->DeviceHandle = @fopen($this->Device, $mode);

        if ($this->DeviceHandle !== false) {
            stream_set_blocking($this->DeviceHandle, 0);
            $this->DeviceCondition = DEVICE_OPENED;
            return true;
        }
        $this->DeviceHandle = null;
        trigger_error("Unable to open device", E_USER_WARNING);
        return false;
    }

    /**
     * Closes the device
     *
     * @return bool
     */
    public function deviceClose() {
        if ($this->DeviceCondition !== DEVICE_OPENED) {
            return true;
        }
        if (fclose($this->DeviceHandle)) {
            $this->DeviceHandle = null;
            $this->DeviceCondition = DEVICE_DEFINED;
            return true;
        }
        trigger_error("Unable to close the device", E_USER_ERROR);
        return false;
    }

    public function setBaudRate($rate) {
        if ($this->DeviceCondition !== DEVICE_DEFINED) {
            trigger_error("The device needs to be opened or installed.", E_USER_WARNING);
            return false;
        }

        $validBauds = array(
            110 => 11,
            150 => 15,
            300 => 30,
            600 => 60,
            1200 => 12,
            2400 => 24,
            4800 => 48,
            9600 => 96,
            19200 => 19,
            38400 => 38400,
            57600 => 57600,
            115200 => 115200,
            921600 => 921600,
            1000000 => 1000000,
            3000000 => 3000000
        );

        if (isset($validBauds[$rate])) {
            if ($this->OS === "linux") {
                $ret = $this->_exec("stty -F " . $this->Device . " " . (int) $rate, $out);
            } else {
                return false;
            }
            if ($ret !== 0) {
                trigger_error("Unable to set baud rate: " . $out[1], E_USER_WARNING);
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Configure parity.
     * Modes : odd, even, none
     *
     * @param  string $parity one of the modes
     * @return bool
     */
    public function setParity($parity) {
        if ($this->DeviceCondition !== DEVICE_DEFINED) {
            trigger_error("Unable to set parity : the device is either not set or opened", E_USER_WARNING);
            return false;
        }
        $args = array(
            "none" => "-parenb",
            "odd" => "parenb parodd",
            "even" => "parenb -parodd",
        );
        if (!isset($args[$parity])) {
            trigger_error("Parity mode not supported", E_USER_WARNING);
            return false;
        }
        if ($this->OS === "linux") {
            $ret = $this->_exec("stty -F " . $this->Device . " " . $args[$parity], $out);
        }
        if ($ret === 0) {
            return true;
        }
        trigger_error("Unable to set parity : " . $out[1], E_USER_WARNING);
        return false;
    }

    /**
     * Sets the length of a character.
     *
     * @param  int  $int length of a character (5 <= length <= 8)
     * @return bool
     */
    public function setCharacterLength($int) {
        if ($this->DeviceCondition !== DEVICE_DEFINED) {
            trigger_error("Can not set length", E_USER_WARNING);
            return false;
        }
        if ((int) $int < 5) {
            $int = 5;
        } elseif ((int) $int > 8) {
            $int = 8;
        }
        if ($this->OS === "linux") {
            $ret = $this->_exec("stty -F " . $this->Device . " cs" . (int) $int, $out);
        }
        if ($ret === 0) {
            return true;
        }
        trigger_error("Unable to set character length : " . $out[1], E_USER_WARNING);
        return false;
    }

    /**
     * Sets the length of stop bits.
     *
     * @param  float $length the length of a stop bit. It must be either 1,
     *                       1.5 or 2. 1.5 is not supported under linux and on
     *                       some computers.
     * @return bool
     */
    public function setStopBits($length) {
        if ($this->DeviceCondition !== DEVICE_DEFINED) {
            trigger_error("Unable to set the length of a stop bit : the device is either not set or opened", E_USER_WARNING);
            return false;
        }
        if ($length != 1 && $length != 2 && $length != 1.5 && !($length == 1.5 && $this->OS === "linux")) {
            trigger_error("Specified stop bit length is invalid", E_USER_WARNING);
            return false;
        }
        if ($this->OS === "linux") {
            $ret = $this->_exec("stty -F " . $this->Device . " " . (($length == 1) ? "-" : "") . "cstopb", $out);
        }
        if ($ret === 0) {
            return true;
        }
        trigger_error("Unable to set stop bit length : " . $out[1], E_USER_WARNING);
        return false;
    }

    /**
     * Configures the flow control
     *
     * @param  string $mode Set the flow control mode. Availible modes :
     *                      -> "none" : no flow control
     *                      -> "rts/cts" : use RTS/CTS handshaking
     *                      -> "xon/xoff" : use XON/XOFF protocol
     * @return bool
     */
    public function setFlowControl($mode) {
        if ($this->DeviceCondition !== DEVICE_DEFINED) {
            trigger_error("Unable to set flow control mode : the device is either not set or opened", E_USER_WARNING);
            return false;
        }
        $linuxModes = array(
            "none" => "clocal -crtscts -ixon -ixoff",
            "rts/cts" => "-clocal crtscts -ixon -ixoff",
            "xon/xoff" => "-clocal -crtscts ixon ixoff"
        );
        if ($mode !== "none" and $mode !== "rts/cts" and $mode !== "xon/xoff") {
            trigger_error("Invalid flow control mode specified", E_USER_ERROR);
            return false;
        }
        if ($this->OS === "linux") {
            $ret = $this->_exec("stty -F " . $this->Device . " " . $linuxModes[$mode], $out);
        }
        if ($ret === 0) {
            return true;
        } else {
            trigger_error("Unable to set flow control : " . $out[1], E_USER_ERROR);
            return false;
        }
    }

    /**
     * Sets a setserial parameter (cf man setserial)
     * NO MORE USEFUL !
     * 	-> No longer supported
     * 	-> Only use it if you need it
     *
     * @param  string $param parameter name
     * @param  string $arg   parameter value
     * @return bool
     */
    public function setSetserialFlag($param, $arg = "") {
        if (!$this->_ckOpened()) {
            return false;
        }
        $return = exec("setserial " . $this->Device . " " . $param . " " . $arg . " 2>&1");
        if ($return{0} === "I") {
            trigger_error("setserial: Invalid flag", E_USER_WARNING);
            return false;
        } elseif ($return{0} === "/") {
            trigger_error("setserial: Error with device file", E_USER_WARNING);
            return false;
        } else {
            return true;
        }
    }

    /**
     * Sends a string to the device
     *
     * @param string $str          string to be sent to the device
     * @param float  $waitForReply time to wait for the reply (in seconds)
     */
    public function sendMessage($str, $waitForReply = 0.1) {
        $this->Buffer .= $str;
        if ($this->AutoReset === true) {
            $this->serialflush();
        }
        usleep((int) ($waitForReply * 1000000));
    }

    /**
     * Reads the port until no new datas are availible, then return the content.
     *
     * @param int $count Number of characters to be read (will stop before
     *                   if less characters are in the buffer)
     * @return string
     */
    public function readPort($count = 0) {
        if ($this->DeviceCondition !== DEVICE_OPENED) {
            trigger_error("Device must be opened to read it", E_USER_WARNING);
            return false;
        }

        if ($this->OS === "linux") {
            $content = "";
            $i = 0;
            if ($count !== 0) {
                do {
                    if ($i > $count) {
                        $content .= fread($this->DeviceHandle, ($count - $i));
                    } else {
                        $content .= fread($this->DeviceHandle, 128);
                    }
                } while (($i += 128) === strlen($content));
            } else {
                do {
                    $content .= fread($this->DeviceHandle, 128);
                } while (($i += 128) === strlen($content));
            }
            return $content;
        }
        return false;
    }

    /**
     * Flushes the output buffer
     * Renamed from flush for osx compat. issues
     *
     * @return bool
     */
    public function serialflush() {
        if (!$this->_ckOpened()) {
            return false;
        }

        if (fwrite($this->DeviceHandle, $this->Buffer) !== false) {
            $this->Buffer = "";

            return true;
        } else {
            $this->Buffer = "";
            trigger_error("Error while sending message", E_USER_WARNING);

            return false;
        }
    }

    public function _ckOpened() {
        if ($this->DeviceCondition !== DEVICE_OPENED) {
            trigger_error("Device must be opened", E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function _ckClosed() {
        if ($this->DeviceCondition === DEVICE_OPENED) {
            trigger_error("Device must be closed", E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function _exec($cmd, &$out = null) {
        $desc = array(
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        $proc = proc_open($cmd, $desc, $pipes);

        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $retVal = proc_close($proc);

        if (func_num_args() == 2) {
            $out = array($ret, $err);
        }
        return $retVal;
    }

}
