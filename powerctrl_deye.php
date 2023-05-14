#!/usr/bin/php
<?php
// Assuming SDM630 has modbus ID 1!!!
$debug = 0;
$runonx64=true; //script runs on x64 HW
// Packet should arrive here from 10.0.0.241 and port 8123 / for diagnosis call ( tshark -f "port 8123" ), pls adapt to fit your rs485-ip converter
$padding = "000000000000000000";
$remoteid = "";
openlog('POWERCTRL', LOG_CONS | LOG_NDELAY | LOG_PID, LOG_USER | LOG_PERROR);
//Reduce errors
error_reporting(~E_WARNING);
syslog(LOG_INFO,'POWERCTRL started!');

//open sh mem obj for reading actual values, need sdm630poller to work
$sh_sdm6301 = shmop_open(0x6301, "a", 0, 0);
if (!$sh_sdm6301) {
    syslog("Couldn't create shared memory segment");
}
    //Create a UDP socket
    if(!($sock = socket_create(AF_INET, SOCK_DGRAM, 0)))
    {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);

        die("Couldn't create socket: [$errorcode] $errormsg \n");
    }
    echo "Socket created \n";
    // Bind the source address, local IP and receiver port
    if( !socket_bind($sock, "10.0.0.2" , 8123) ) // IP of machine running this script
    {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        die("Could not bind socket : [$errorcode] $errormsg \n");
    }
    echo "Socket bind OK \n";

    //MAIN loop
    while(1)
    {
        //Get live values from shared memory filled by sdm630poller
        $pow =   shmop_read($sh_sdm6301, 18, 6);
        $PL1_1 = shmop_read($sh_sdm6301, 0, 6);
        $PL2_1 = shmop_read($sh_sdm6301, 6, 6);
        $PL3_1 = shmop_read($sh_sdm6301, 12, 6);
        $IC_1 =  shmop_read($sh_sdm6301, 32,10);
        $EC_1 =  shmop_read($sh_sdm6301, 42,10);
        if($debug){
                echo "POW: ".$pow."W\n";
                echo "L1: ".$PL1_1."W\n";
                echo "L2: ".$PL2_1."W\n";
                echo "L2: ".$PL3_1."W\n";
                echo "IC: ".$IC_1."Wh\n";
                echo "EC: ".$EC_1."Wh\n";
        }
        //Receive some data
        $r = socket_recvfrom($sock, $byte, 100, 0, $remote_ip, $remote_port);
        //Analyze received datagramm
        if($debug) echo "<<<HEX:".ascii2hex($byte)."\n";
        $remoteid = ascii2hex(substr($byte,0,1));
        $funkt =  ascii2hex(substr($byte,1,1));
        $register_hi = substr(ascii2hex(substr($byte,2,1)),0,2);
        $register_lo = substr(ascii2hex(substr($byte,3,1)),0,2);
        if(substr($remoteid,0,2) != "01") continue; // ignore messages from inverters, react only on requests to SDM630
//      01 04 00 34 00 02 30 05 //Register 0034, 2 Bytes, Total System Power
//      01 04 00 0C 00 06 B0 0B //Register 000C, 6 Bytes, Phase 1 power - Phase 3 power - Phase 3 power
//      01 04 00 48 00 04 71 DF //Register 0048, 8 Bytes, Imported  +Exported Wh since last reset

/*      // Modbus debugging
        echo "<<< SlaveID:".$remoteid."\n";
        echo "<<< Function:".$funkt."\n";
        echo "<<< RegisterHi:".$register_hi."b\n";
        echo "<<< RegisterLo:".$register_lo."b\n";
*/
        if($register_hi=="00" & $register_lo=="34") { //Infinisolar
                if($pow < -17000 || $pow > 20000) echo "Problem: die gelesen Leistung ist ".$pow."\n"; $send = rawSingleHex($pow); //sec. mechansim
                if($debug) echo "INFINI requests total system power\n";
                $send = rawSingleHex($pow);
        }
        if($register_hi=="00" & $register_lo=="0C") { //DEYE Phase 123 power
                if($debug) echo "DEYE requests phase 123 power\n";
                $send = rawSingleHex($PL1_1).rawSingleHex($PL2_1).rawSingleHex($PL3_1);
        }
        if($register_hi=="00" & $register_lo=="48") { //DEYE, Import Wh since last reset
                if($debug) echo "DEYE requests imported+exported counter\n";
                $send = rawSingleHex($IC_1/1000).rawSingleHex($EC_1/1000);
        }
        $length = str_pad(dechex(strlen($send)/2),2, "0", STR_PAD_LEFT);
        $crc = modbus_crc(trim($remoteid).trim($funkt).$length.$send);
        if($runonx64){
                $buf=hex2ascii(trim($remoteid).trim($funkt).$length.$send.$crc);
                } else $buf=hex2ascii(trim($remoteid).trim($funkt).$length.$send.$crc.$padding); //with Padding for RPi
        if($debug) echo ">>>HEX:".ascii2hex($buf)."               ".date("Y-m-d H:i:s")."\n";
        usleep(20000); // simulate sdm's processing time
        //send reply
        socket_sendto($sock, $buf , strlen($buf), 0 , $remote_ip , $remote_port);
        }
socket_close($sock);
// END of MAIN loop

// HEX to STRING conversation
function hex2str($hex) {
    $str = '';
    for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}
//CRC16 calculation
function modbus_crc($modbus_msg){
        $crctab16 = [0X0000, 0XC0C1, 0XC181, 0X0140, 0XC301, 0X03C0, 0X0280, 0XC241,
                0XC601, 0X06C0, 0X0780, 0XC741, 0X0500, 0XC5C1, 0XC481, 0X0440,
                0XCC01, 0X0CC0, 0X0D80, 0XCD41, 0X0F00, 0XCFC1, 0XCE81, 0X0E40,
                0X0A00, 0XCAC1, 0XCB81, 0X0B40, 0XC901, 0X09C0, 0X0880, 0XC841,
                0XD801, 0X18C0, 0X1980, 0XD941, 0X1B00, 0XDBC1, 0XDA81, 0X1A40,
                0X1E00, 0XDEC1, 0XDF81, 0X1F40, 0XDD01, 0X1DC0, 0X1C80, 0XDC41,
                0X1400, 0XD4C1, 0XD581, 0X1540, 0XD701, 0X17C0, 0X1680, 0XD641,
                0XD201, 0X12C0, 0X1380, 0XD341, 0X1100, 0XD1C1, 0XD081, 0X1040,
                0XF001, 0X30C0, 0X3180, 0XF141, 0X3300, 0XF3C1, 0XF281, 0X3240,
                0X3600, 0XF6C1, 0XF781, 0X3740, 0XF501, 0X35C0, 0X3480, 0XF441,
                0X3C00, 0XFCC1, 0XFD81, 0X3D40, 0XFF01, 0X3FC0, 0X3E80, 0XFE41,
                0XFA01, 0X3AC0, 0X3B80, 0XFB41, 0X3900, 0XF9C1, 0XF881, 0X3840,
                0X2800, 0XE8C1, 0XE981, 0X2940, 0XEB01, 0X2BC0, 0X2A80, 0XEA41,
                0XEE01, 0X2EC0, 0X2F80, 0XEF41, 0X2D00, 0XEDC1, 0XEC81, 0X2C40,
                0XE401, 0X24C0, 0X2580, 0XE541, 0X2700, 0XE7C1, 0XE681, 0X2640,
                0X2200, 0XE2C1, 0XE381, 0X2340, 0XE101, 0X21C0, 0X2080, 0XE041,
                0XA001, 0X60C0, 0X6180, 0XA141, 0X6300, 0XA3C1, 0XA281, 0X6240,
                0X6600, 0XA6C1, 0XA781, 0X6740, 0XA501, 0X65C0, 0X6480, 0XA441,
                0X6C00, 0XACC1, 0XAD81, 0X6D40, 0XAF01, 0X6FC0, 0X6E80, 0XAE41,
                0XAA01, 0X6AC0, 0X6B80, 0XAB41, 0X6900, 0XA9C1, 0XA881, 0X6840,
                0X7800, 0XB8C1, 0XB981, 0X7940, 0XBB01, 0X7BC0, 0X7A80, 0XBA41,
                0XBE01, 0X7EC0, 0X7F80, 0XBF41, 0X7D00, 0XBDC1, 0XBC81, 0X7C40,
                0XB401, 0X74C0, 0X7580, 0XB541, 0X7700, 0XB7C1, 0XB681, 0X7640,
                0X7200, 0XB2C1, 0XB381, 0X7340, 0XB101, 0X71C0, 0X7080, 0XB041,
                0X5000, 0X90C1, 0X9181, 0X5140, 0X9301, 0X53C0, 0X5280, 0X9241,
                0X9601, 0X56C0, 0X5780, 0X9741, 0X5500, 0X95C1, 0X9481, 0X5440,
                0X9C01, 0X5CC0, 0X5D80, 0X9D41, 0X5F00, 0X9FC1, 0X9E81, 0X5E40,
                0X5A00, 0X9AC1, 0X9B81, 0X5B40, 0X9901, 0X59C0, 0X5880, 0X9841,
                0X8801, 0X48C0, 0X4980, 0X8941, 0X4B00, 0X8BC1, 0X8A81, 0X4A40,
                0X4E00, 0X8EC1, 0X8F81, 0X4F40, 0X8D01, 0X4DC0, 0X4C80, 0X8C41,
                0X4400, 0X84C1, 0X8581, 0X4540, 0X8701, 0X47C0, 0X4680, 0X8641,
                0X8201, 0X42C0, 0X4380, 0X8341, 0X4100, 0X81C1, 0X8081, 0X4040];

        $hexdata = pack('H*',$modbus_msg);
        $nLength = strlen($hexdata);
        $fcs = 0xFFFF;
        $pos = 0;
        while($nLength > 0)
        {
                $fcs = ($fcs >> 8) ^ $crctab16[($fcs ^ ord($hexdata[$pos])) & 0xFF];
                $nLength--;
                $pos++;
        }
        $crc_semi_inverted = sprintf('%04X', $fcs);//modbus crc invert the hight and low bit so we need to put the last two letter in the begining
        $crc_modbus = substr($crc_semi_inverted,2,2).substr($crc_semi_inverted,0,2);
        return $crc_modbus;
        }
function ascii2hex($ascii) {
        $hex = '';
        for ($i = 0; $i < strlen($ascii); $i++) {
                $byte = strtoupper(dechex(ord($ascii{$i})));
                $byte = str_repeat('0', 2 - strlen($byte)).$byte;
                $hex.=$byte." ";
        }
        return $hex;
}
function hex2ascii($hex){
        $ascii='';
        $hex=str_replace(" ", "", $hex);
        for($i=0; $i<strlen($hex); $i=$i+2) {
                $ascii.=chr(hexdec(substr($hex, $i, 2)));
        }
        return($ascii);
}
function rawSingleHex($num) {
    $tmp = unpack('h*', pack('f', $num));
    return strrev($tmp[1]);
}
function hex2ieee754($strHex){
        $bin = str_pad(base_convert($strHex, 16, 2), 32, "0", STR_PAD_LEFT);
        $sign = $bin[0];
        $exp = bindec(substr($bin, 1, 8)) - 127;
        $man = (2 << 22) + bindec(substr($bin, 9, 23));
        $dec = $man * pow(2, $exp - 23) * ($sign ? -1 : 1);
        return($dec);
}
?>
