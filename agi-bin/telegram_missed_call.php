#!/usr/bin/php -q
<?php
    require('phpagi.php');
    $agi = new AGI();

    $cid = $agi->request['agi_callerid'];
    $exttolocal = $agi->request['agi_arg_6'];
    $dialstatus = $agi->request['agi_arg_4'];
    $vmstatus = $agi->request['agi_arg_5'];

    $telegramstatus = $agi->request['agi_arg_3'];

    $datetime = date("d.m.Y H:i:s");

    sleep(3);

    if ($vmstatus == "SUCCESS" && $telegramstatus == "enabled") {
        $token = $agi->request['agi_arg_1'];
        $token = str_replace(" ", ":", $token);
        $chatid = $agi->request['agi_arg_2'];
        $voicemail = $agi->request['agi_arg_13'];
            
        $mess = "Missed call + voice message\n".$datetime."\n".$cid." -> ".$exttolocal;
        //$path = '/var/spool/asterisk/voicemail/default/'.$exttolocal.'/INBOX/';
        $path = '/var/spool/asterisk/voicemail/default/'.$voicemail.'/INBOX/';
        $fname = find_last_file($path, '.wav');
        if ($fname != '') {
            sendDocumentTel($mess, $path.$fname);
        }
    } elseif ($dialstatus != "ANSWER" && $telegramstatus == "enabled") {
        $token = $agi->request['agi_arg_1'];
        $token = str_replace(" ", ":", $token);
        $chatid = $agi->request['agi_arg_2'];

        $mess = "Missed call\n".$datetime."\n".$cid." -> ".$exttolocal;
        sendMessageTel($mess);
    } elseif ($dialstatus == "ANSWER") {
        $path = '/var/spool/asterisk/monitor/'.date("Y").'/'.date("m").'/'.date("d").'/';
        $fname = find_last_file($path, $agi->request['agi_uniqueid'].'.wav');

        $callstatus = $agi->request['agi_arg_7'];
        if ($callstatus == "enabled") {
            $calltoken = $agi->request['agi_arg_8'];
            $token = str_replace(" ", ":", $calltoken);
            $chatid = $agi->request['agi_arg_9'];

            $mess = "Call\n".$datetime."\n".$cid." -> ".$exttolocal;
            sendMessageTel($mess);
        }

        $rcstatus = $agi->request['agi_arg_10'];
        if ($fname != '' && $rcstatus == "enabled") {
            $rctoken = $agi->request['agi_arg_11'];
            $token = str_replace(" ", ":", $rctoken);
            $chatid = $agi->request['agi_arg_12'];

            $mess = "Record call\n".$datetime."\n".$cid." -> ".$exttolocal;
            sendDocumentTel($mess, $path.$fname);
        }
    }

    function sendDocumentTel($mess, $fname) {
        global $token, $chatid;
        $schatid = explode("-", $chatid);
        foreach( $schatid as $c ) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot".$token."/sendDocument?chat_id=".$c."&caption=".urlencode($mess));
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
            sleep(3);
        }
    }

    function sendMessageTel($mess) {
        global $token, $chatid;
        $schatid = explode("-", $chatid);
        foreach( $schatid as $c ) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$c."&text=".urlencode($mess));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            $result = curl_exec($ch);
            var_dump($result);
            curl_close($ch);
            sleep(3);
        }
    }

    function find_last_file($path, $s) {
        $l = 0;
        $r = '';
        foreach( new DirectoryIterator($path) as $file ) {
            $ctime = $file->getCTime();
            $fname = $file->getFileName();
            if( $ctime > $l ) {
                $r = $fname;
                $pos = strpos($r, $s);
                
                if ($pos !== false) {
                    $l = $ctime;
                } else {
                    $r = '';
                }
            }
        }
        return $r;
    }
?>