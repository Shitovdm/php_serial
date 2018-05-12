# PHP Serial Port
The class allows you to read the data directly from the server from the connected devices.  

**Only Linux is supported!**  

<h3>Example:</h3>  

```  
include 'PHPSerialClass.php';  
  
$serial = new PhpSerial;
$serial->deviceSet("/dev/ttyUSB0");  
$serial->confBaudRate(921600);  
$serial->confParity("none");  
$serial->confCharacterLength(8);  
$serial->confStopBits(1);  
$serial->confFlowControl("none");  
  
$serial->deviceOpen();  
```
