#!/usr/bin/php -q
<?php
    require('phpagi.php');
    $agi = new AGI();

    $telegramstatus = $agi->request['agi_arg_3'];

    if ($telegramstatus == "enabled") {
        $cid = $agi->request['agi_callerid'];
        $token = $agi->request['agi_arg_1'];
        $token = str_replace(" ", ":", $token);
        $chatid = $agi->request['agi_arg_2'];
        $dialstatus = $agi->request['agi_arg_4'];
        $vmstatus = $agi->request['agi_arg_5'];
        $exttolocal = $agi->request['agi_arg_6'];
        
        $datetime = date("d.m.Y H:i:s");

        $mess = "Missed call\n".$datetime."\n".$cid;

        if ($dialstatus == "CANCEL") {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$chatid."&text=".urlencode($mess));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            $result = curl_exec($ch);
            var_dump($result);
            curl_close($ch);
        } elseif ($dialstatus == "NOANSWER" && $vmstatus == "SUCCESS") {
            $mess .= "\nvoice message";
            $fname = "/var/spool/asterisk/voicemail/default/".$exttolocal."/INBOX/".find_latest($exttolocal);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot".$token."/sendDocument?chat_id=".$chatid."&caption=".urlencode($mess));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            $finfo = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $fname);
            $cFile = new CURLFile($fname, $finfo);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    "document" => $cFile
            ]);
            $result = curl_exec($ch);
            var_dump($result);
            curl_close($ch);
        }
    }

    function find_latest($ext) {
        $l = 0;
        $r = '';
        foreach( new DirectoryIterator('/var/spool/asterisk/voicemail/default/'.$ext.'/INBOX/') as $file ) {
            $ctime = $file->getCTime();
            $fname = $file->getFileName();
            if( $ctime > $l ) {
                $r = $fname;
                $pos = strpos($r, '.wav');
                
                if ($pos !== false) {
                    $l = $ctime;
                }
            }
        }
        return $r;
    }
?>