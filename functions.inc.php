<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function telegrammissedcall_hookGet_config($engine) {
  global $ext;
  global $version;
  $newsplice=0;
  error_log('telegrammissedcall_hookGet_config - triggered');
  switch($engine) {
  case "asterisk":
    if($newsplice){ # Method fpr splicing using modified splice code yet not implemented in 2.10.0.2
      $ext->splice('macro-hangupcall', 's', 'theend', new ext_gosub(1,'s','sub-telegrammissedcall'),'theend',false,true);
    }else{ # Custom method to splice in correct code prior to hangup

      // hook all extens
      $spliceext=array(
          'basetag'=>'n',
          'tag'=>'',
          'addpri'=>'',
          'cmd'=>new ext_execif('$["${ORIGEXTTOCALL}"==""]','Set','__ORIGEXTTOCALL=${ARG2}')
        );
      array_splice($ext->_exts['macro-exten-vm'][telegrammissedcall_padextfix('s')],2,0,array($spliceext));

      // hook on hangup
      $spliceext=array(
          'basetag'=>'n',
          'tag'=>'theend',
          'addpri'=>'',
          'cmd'=>new ext_gosub(1,'s','sub-telegrammissedcall')
        );
      foreach($ext->_exts['macro-hangupcall'][telegrammissedcall_padextfix('s')] as $_ext_k=>&$_ext_v){
        if($_ext_v['tag']!='theend'){continue;}
        $_ext_v['tag']='';
        array_splice($ext->_exts['macro-hangupcall'][telegrammissedcall_padextfix('s')],$_ext_k,0, array($spliceext) );
        break;
      }
    }
  break;
  }
}

/* fix to pad exten if framework ver is >=2.10 */
function telegrammissedcall_padextfix($ext){
  global $version;
  if(version_compare(get_framework_version(), "2.10.1.4", ">=")){
      $ext = ' ' . $ext . ' ';
  }
  return $ext;
}

function telegrammissedcall_get_config($engine) {

  // This generates the dialplan
  global $ext;
  global $amp_conf;
  $mcontext = 'sub-telegrammissedcall';
  //$exten0 = "exten";
  $exten = 's';
  error_log('telegrammissedcall_get_config - triggered');
  $ext->add($mcontext,$exten,'', new ext_noop('CALLERID(number): ${CALLERID(number)}'));
  $ext->add($mcontext,$exten,'', new ext_noop('CALLERID(name): ${CALLERID(name)}'));
  $ext->add($mcontext,$exten,'', new ext_noop('DialStatus: ${DIALSTATUS}'));
  $ext->add($mcontext,$exten,'', new ext_noop('VMSTATUS: ${VMSTATUS}'));
  

  $token1 = '${DB(AMPUSER/${EXTTOCALL}/telegrammissedcall/bot1)}';
  $token2 = '${DB(AMPUSER/${EXTTOCALL}/telegrammissedcall/bot2)}';
  $token = $token1.' '.$token2;
  $chatid = '${DB(AMPUSER/${EXTTOCALL}/telegrammissedcall/telegram)}';
  $telegramstatus = '${DB(AMPUSER/${EXTTOCALL}/telegrammissedcall/status)}';
  $telegramsvoicemail = '${DB(AMPUSER/${EXTTOCALL}/telegrammissedcall/voicemail)}';

  $dialstatus = '${DIALSTATUS}';
  $vmstatus = '${VMSTATUS}';
  $exttolocal = '${EXTTOCALL}';

  $callstatus = '${DB(AMPUSER/${EXTTOCALL}/telegramcall/status_call)}';
  $calltoken1 = '${DB(AMPUSER/${EXTTOCALL}/telegramcall/bot1_call)}';
  $calltoken2 = '${DB(AMPUSER/${EXTTOCALL}/telegramcall/bot2_call)}';
  $calltoken = $calltoken1.' '.$calltoken2;
  $callchatid = '${DB(AMPUSER/${EXTTOCALL}/telegramcall/telegram_call)}';

  $rcstatus = '${DB(AMPUSER/${EXTTOCALL}/telegramrecordcall/status_rc)}';
  $rctoken1 = '${DB(AMPUSER/${EXTTOCALL}/telegramrecordcall/bot1_rc)}';
  $rctoken2 = '${DB(AMPUSER/${EXTTOCALL}/telegramrecordcall/bot2_rc)}';
  $rctoken = $rctoken1.' '.$rctoken2;
  $rchatid = '${DB(AMPUSER/${EXTTOCALL}/telegramrecordcall/telegram_rc)}';



  $ext->add($mcontext,$exten,'', new ext_AGI('telegram_missed_call.php,'.$token.','.$chatid.','.$telegramstatus.','.$dialstatus.','.$vmstatus.','.$exttolocal.','.$callstatus.','.$calltoken.','.$callchatid.','.$rcstatus.','.$rctoken.','.$rchatid.','.$telegramsvoicemail));
}


function telegrammissedcall_configpageinit($pagename) {
        global $currentcomponent;

        $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
        $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
        $extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
        $tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

        // We only want to hook 'users' or 'extensions' pages.
        if ($pagename != 'extensions')
                return true;

	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page.
        if ($tech_hardware != null) {
          telegrammissedcall_applyhooks();
		$currentcomponent->addprocessfunc('telegrammissedcall_configprocess', 8);
        } elseif ($action=="add") {
                // We don't need to display anything on an 'add', but we do need to handle returned data.
                $currentcomponent->addprocessfunc('telegrammissedcall_configprocess', 8);
        } elseif ($extdisplay != '') {
                // We're now viewing an extension, so we need to display _and_ process.
                telegrammissedcall_applyhooks();
                $currentcomponent->addprocessfunc('telegrammissedcall_configprocess', 8);
        }

}

function telegrammissedcall_applyhooks() {
        global $currentcomponent;

        $currentcomponent->addoptlistitem('telegrammissedcall_status', 'disabled', _('Disabled'));
        $currentcomponent->addoptlistitem('telegrammissedcall_status', 'enabled', _('Enabled'));
        $currentcomponent->setoptlistopts('telegrammissedcall_status', 'sort', false);

	$currentcomponent->addguifunc('telegrammissedcall_configpageload');
}

function telegrammissedcall_configpageload() {
  global $amp_conf;
  global $currentcomponent;

  // Init vars from $_REQUEST[]
  $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
  $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

  $mcn0 = telegramcall_getall($extdisplay);
  $section0 = _('Call Notifications');
  $telegramcall_label =      _("Notifications");
  $telegramcall_telegram_label =    _("Telergram ID");
  $telegramcall_bot_label =    _("Telegram Bot token");
  $telegramcall_tt = _("Enable notification of calls");
  $telegramcall_pt = _("Here you can specify the telegram user ID (personal) or the general chat ID. You can specify multiple separated dashes (111-222-333).");

  $currentcomponent->addguielem($section0, new gui_selectbox('telegramcall_status', $currentcomponent->getoptlist('telegrammissedcall_status'), $mcn0['telegramcall_status'], $telegramcall_label, $telegramcall_tt, '', false));
  $currentcomponent->addguielem($section0, new gui_textbox('telegramcall_bot', $mcn0['telegramcall_bot'],$telegramcall_bot_label, '', '' , false));
  $currentcomponent->addguielem($section0, new gui_textbox('telegramcall_telegram', $mcn0['telegramcall_telegram'],$telegramcall_telegram_label, $telegramcall_pt, '' , false));


  $mcn = telegrammissedcall_getall($extdisplay);
  $section = _('Missed Call Notifications');
  $telegrammissedcall_label =      _("Notifications");
  $telegrammissedcall_telegram_label =    _("Telergram ID");
  $telegrammissedcall_bot_label =    _("Telegram Bot token");
  $telegrammissedcall_voicemail_label =    _("VoiceMail Number Extension");
  $telegrammissedcall_tt = _("Enable notification of missed calls");
  $telegrammissedcall_pt = _("Here you can specify the telegram user ID (personal) or the general chat ID. You can specify multiple separated dashes (111-222-333).");
  
  $currentcomponent->addguielem($section, new gui_selectbox('telegrammissedcall_status', $currentcomponent->getoptlist('telegrammissedcall_status'), $mcn['telegrammissedcall_status'], $telegrammissedcall_label, $telegrammissedcall_tt, '', false));
  $currentcomponent->addguielem($section, new gui_textbox('telegrammissedcall_bot', $mcn['telegrammissedcall_bot'],$telegrammissedcall_bot_label, '', '' , false));
  $currentcomponent->addguielem($section, new gui_textbox('telegrammissedcall_telegram', $mcn['telegrammissedcall_telegram'],$telegrammissedcall_telegram_label, $telegrammissedcall_pt, '' , false));
  $currentcomponent->addguielem($section, new gui_textbox('telegrammissedcall_voicemail', $mcn['telegrammissedcall_voicemail'],$telegrammissedcall_voicemail_label, '', '' , false));

  $mcn2 = telegramrecordcall_getall($extdisplay);
  $section2 = _('Call recording notifications');
  $telegramrecordcall_label =      _("Notifications");
  $telegramrecordcall_telegram_label =    _("Telergram ID");
  $telegramrecordcall_bot_label =    _("Telegram Bot token");
  $telegramrecordcall_tt = _("Enable notification of record calls");
  $telegramrecordcall_pt = _("Here you can specify the telegram user ID (personal) or the general chat ID. You can specify multiple separated dashes (111-222-333).");

  $currentcomponent->addguielem($section2, new gui_selectbox('telegramrecordcall_status', $currentcomponent->getoptlist('telegrammissedcall_status'), $mcn2['telegramrecordcall_status'], $telegramrecordcall_label, $telegramrecordcall_tt, '', false));
  $currentcomponent->addguielem($section2, new gui_textbox('telegramrecordcall_bot', $mcn2['telegramrecordcall_bot'],$telegramrecordcall_bot_label, '', '' , false));
  $currentcomponent->addguielem($section2, new gui_textbox('telegramrecordcall_telegram', $mcn2['telegramrecordcall_telegram'],$telegramrecordcall_telegram_label, $telegramrecordcall_pt, '' , false));
}

function telegrammissedcall_configprocess() {
  global $amp_conf;

  $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
  $ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
  $extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;

  $mcn0=array();
  $mcn0['status_call'] =      isset($_REQUEST['telegramcall_status']) ? $_REQUEST['telegramcall_status'] : 'disabled';
  $mcn0['telegram_call'] =    isset($_REQUEST['telegramcall_telegram']) ? $_REQUEST['telegramcall_telegram'] : 'enabled';
  $mcn0['bot_call'] =    isset($_REQUEST['telegramcall_bot']) ? $_REQUEST['telegramcall_bot'] : 'enabled';
  $mcn0['bot1_call'] =    isset($_REQUEST['telegramcall_bot']) ? explode(":", $_REQUEST['telegramcall_bot'])[0] : 'enabled';
  $mcn0['bot2_call'] =    isset($_REQUEST['telegramcall_bot']) ? explode(":", $_REQUEST['telegramcall_bot'])[1] : 'enabled';

  $mcn=array();
  $mcn['status'] =      isset($_REQUEST['telegrammissedcall_status']) ? $_REQUEST['telegrammissedcall_status'] : 'disabled';
  $mcn['telegram'] =    isset($_REQUEST['telegrammissedcall_telegram']) ? $_REQUEST['telegrammissedcall_telegram'] : 'enabled';
  $mcn['bot'] =    isset($_REQUEST['telegrammissedcall_bot']) ? $_REQUEST['telegrammissedcall_bot'] : 'enabled';
  $mcn['bot1'] =    isset($_REQUEST['telegrammissedcall_bot']) ? explode(":", $_REQUEST['telegrammissedcall_bot'])[0] : 'enabled';
  $mcn['bot2'] =    isset($_REQUEST['telegrammissedcall_bot']) ? explode(":", $_REQUEST['telegrammissedcall_bot'])[1] : 'enabled';
  $mcn['voicemail'] =    isset($_REQUEST['telegrammissedcall_voicemail']) ? $_REQUEST['telegrammissedcall_voicemail'] : 'enabled';

  $mcn2=array();
  $mcn2['status_rc'] =      isset($_REQUEST['telegramrecordcall_status']) ? $_REQUEST['telegramrecordcall_status'] : 'disabled';
  $mcn2['telegram_rc'] =    isset($_REQUEST['telegramrecordcall_telegram']) ? $_REQUEST['telegramrecordcall_telegram'] : 'enabled';
  $mcn2['bot_rc'] =    isset($_REQUEST['telegramrecordcall_bot']) ? $_REQUEST['telegramrecordcall_bot'] : 'enabled';
  $mcn2['bot1_rc'] =    isset($_REQUEST['telegramrecordcall_bot']) ? explode(":", $_REQUEST['telegramrecordcall_bot'])[0] : 'enabled';
  $mcn2['bot2_rc'] =    isset($_REQUEST['telegramrecordcall_bot']) ? explode(":", $_REQUEST['telegramrecordcall_bot'])[1] : 'enabled';

  if ($ext==='') {
    $extdisplay = $extn;
  } else {
    $extdisplay = $ext;
  }

  if ($action == "add" || $action == "edit" || (isset($mcn0['callnotify']) && $mcn0['callnotify']=="false")) {
    if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
      telegramcall_update($extdisplay, $mcn0);
    }
  } elseif ($action == "del") {
    telegramcall_del($extdisplay);
  }

  if ($action == "add" || $action == "edit" || (isset($mcn['misedcallnotify']) && $mcn['misedcallnotify']=="false")) {
    if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
      telegrammissedcall_update($extdisplay, $mcn);
    }
  } elseif ($action == "del") {
    telegrammissedcall_del($extdisplay);
  }

  if ($action == "add" || $action == "edit" || (isset($mcn2['recordcallnotify']) && $mcn2['recordcallnotify']=="false")) {
    if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
      telegramrecordcall_update($extdisplay, $mcn2);
    }
  } elseif ($action == "del") {
    telegramrecordcall_del($extdisplay);
  }
}


function telegramcall_getall($ext, $base='AMPUSER') {
  global $amp_conf;
  global $astman;
  $mcn=array();

  if ($astman) {
    $telegramcall_status = telegramcall_get($ext,"status_call", $base);
    $mcn['telegramcall_status'] = $telegramcall_status ? $telegramcall_status : 'disabled';
    $telegramcall_telegram = telegramcall_get($ext,"telegram_call", $base);
    $mcn['telegramcall_telegram'] = $telegramcall_telegram ?  $telegramcall_telegram : '';
    $telegramcall_bot = telegramcall_get($ext,"bot_call", $base);
    $mcn['telegramcall_bot'] = $telegramcall_bot ?  $telegramcall_bot : '';


  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
  return $mcn;
}

function telegrammissedcall_getall($ext, $base='AMPUSER') {
  global $amp_conf;
  global $astman;
  $mcn=array();

  if ($astman) {
    $telegrammissedcall_status = telegrammissedcall_get($ext,"status", $base);
    $mcn['telegrammissedcall_status'] = $telegrammissedcall_status ? $telegrammissedcall_status : 'disabled';
    $telegrammissedcall_telegram = telegrammissedcall_get($ext,"telegram", $base);
    $mcn['telegrammissedcall_telegram'] = $telegrammissedcall_telegram ?  $telegrammissedcall_telegram : '';
    $telegrammissedcall_bot = telegrammissedcall_get($ext,"bot", $base);
    $mcn['telegrammissedcall_bot'] = $telegrammissedcall_bot ?  $telegrammissedcall_bot : '';
    $telegrammissedcall_voicemail = telegrammissedcall_get($ext,"voicemail", $base);
    $mcn['telegrammissedcall_voicemail'] = $telegrammissedcall_voicemail ?  $telegrammissedcall_voicemail : '';


  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
  return $mcn;
}

function telegramrecordcall_getall($ext, $base='AMPUSER') {
  global $amp_conf;
  global $astman;
  $mcn=array();

  if ($astman) {
    $telegramrecordcall_status = telegramrecordcall_get($ext,"status_rc", $base);
    $mcn['telegramrecordcall_status'] = $telegramrecordcall_status ? $telegramrecordcall_status : 'disabled';
    $telegramrecordcall_telegram = telegramrecordcall_get($ext,"telegram_rc", $base);
    $mcn['telegramrecordcall_telegram'] = $telegramrecordcall_telegram ?  $telegramrecordcall_telegram : '';
    $telegramrecordcall_bot = telegramrecordcall_get($ext,"bot_rc", $base);
    $mcn['telegramrecordcall_bot'] = $telegramrecordcall_bot ?  $telegramrecordcall_bot : '';


  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
  return $mcn;
}


function telegramcall_get($ext, $key, $base='AMPUSER', $sub='telegramcall') {
  global $astman;
  global $amp_conf;

  if ($astman) {
    if(!empty($sub) && $sub!=false)$key=$sub.'/'.$key;
    return $astman->database_get($base,$ext.'/'.$key);
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function telegrammissedcall_get($ext, $key, $base='AMPUSER', $sub='telegrammissedcall') {
  global $astman;
  global $amp_conf;

  if ($astman) {
    if(!empty($sub) && $sub!=false)$key=$sub.'/'.$key;
    return $astman->database_get($base,$ext.'/'.$key);
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function telegramrecordcall_get($ext, $key, $base='AMPUSER', $sub='telegramrecordcall') {
  global $astman;
  global $amp_conf;

  if ($astman) {
    if(!empty($sub) && $sub!=false)$key=$sub.'/'.$key;
    return $astman->database_get($base,$ext.'/'.$key);
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}







function telegramcall_update($ext, $options, $base='AMPUSER', $sub='telegramcall') {
  global $astman;
  global $amp_conf;

  if ($astman) {
    foreach ($options as $key => $value) {
      if(!empty($sub) && $sub!=false)$key=$sub.'/'.$key;
      $astman->database_put($base,$ext."/$key",$value);
    }
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function telegrammissedcall_update($ext, $options, $base='AMPUSER', $sub='telegrammissedcall') {
  global $astman;
  global $amp_conf;

  if ($astman) {
    foreach ($options as $key => $value) {
      if(!empty($sub) && $sub!=false)$key=$sub.'/'.$key;
      $astman->database_put($base,$ext."/$key",$value);
    }
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function telegramrecordcall_update($ext, $options, $base='AMPUSER', $sub='telegramrecordcall') {
  global $astman;
  global $amp_conf;

  if ($astman) {
    foreach ($options as $key => $value) {
      if(!empty($sub) && $sub!=false)$key=$sub.'/'.$key;
      $astman->database_put($base,$ext."/$key",$value);
    }
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}


function telegramcall_del($ext, $base='AMPUSER', $sub='telegramcall') {
  global $astman;
  global $amp_conf;

  // Clean up the tree when the user is deleted
  if ($astman) {
    $astman->database_deltree("$base/$ext/$sub");
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function telegrammissedcall_del($ext, $base='AMPUSER', $sub='telegrammissedcall') {
  global $astman;
  global $amp_conf;

  // Clean up the tree when the user is deleted
  if ($astman) {
    $astman->database_deltree("$base/$ext/$sub");
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function telegramrecordcall_del($ext, $base='AMPUSER', $sub='telegramrecordcall') {
  global $astman;
  global $amp_conf;

  // Clean up the tree when the user is deleted
  if ($astman) {
    $astman->database_deltree("$base/$ext/$sub");
  } else {
    fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}


?>
