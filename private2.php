<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

if ($action=="sendtobuddies") {
	extract($_GET);
	extract($_POST);
	if (is_array($buddyid) && count($buddyid)==1) {
		if ($forward==true) {
			header("Location: private.php?s=$s&action=newmessage&forward=$forward&privatemessageid=$privmessageid&userid=".key($buddyid));
		} else {
			header("Location: private.php?s=$s&action=newmessage&userid=".key($buddyid));
		}
		exit;
	}
}

// cache templates used by all actions:
$templatesused = "usercpnav";

// cache only templates used by appropriate actions:
if (empty($action)) $action = "readreceipts";
switch ($action) {
  case "readreceipts":
	$templatesused .= ",privsent,privsent_nomessages,privsent_unreadmessages,privsent_readmessages,privsent_unreadmessagebit,privsent_readmessagebit,privsent_emailnotified,privsent_restorecancelled,privsent_useroffline,privsent_useronline,priv_buddymasspmlink,priv_dateselect";
  break; case "trackingcontrol":
    $templatesused .= ",standardredirect,redirect_pmdelete,redirect_pmundelete,redirect_pmtrackingdelete";
  break; case "choosebuddies":
    $templatesused .= ",priv_choosebuddies,priv_choosebuddybit,postbit_offline,postbit_online,priv_readreceiptslink";
  break; case "sendtobuddies":
    $templatesused .= ",priv_sendtobuddies,priv_sendtobuddies_name,priv_requestreceipt,priv_reply,posticons,posticonbit,vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";
  break; case "dosendtobuddies":
    $templatesused .= ",priv_senttomultiple,privsent_emailnotified,email_pmboxfull,email_pmreceived,emailsubject_pmreceived,emailsubject_pmboxfull,redirect_pmthanks,standardredirect";
  break; case "fmulti":
    $templatesused .= ",priv_forwardmultiple,priv_readreceiptslink,posticons,posticonbit,,vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";
  break; case "dofmulti":
    $templatesused .= ",priv_reply,email_pmboxfull,email_pmreceived,emailsubject_pmreceived,emailsubject_pmboxfull,redirect_pmthanks,standardredirect";
  break;
}
#$showqueries=1;
// ################################################################## //
require ("./global.php");

// get decent textarea size for user's browser
$textareacols = gettextareawidth();

// select correct part of FORUMJUMP menu
$frmjmpsel['pm'] = "selected";
// ################################################################## //
if (!$enablepms) {
	eval("standarderror(\"".gettemplate("error_pmadminoff")."\");");
	exit;
}
if (!$permissions['canusepm']) {
	show_nopermission();
	exit;
}
if (!$bbuserinfo['receivepm']) {
	eval("standarderror(\"".gettemplate("error_pmturnedoff")."\");");
	exit;
}

// draw cp nav bar
$cpnav = array();
$cpmenu = array();
$cpnav[1]="{secondaltcolor}";
$cpnav[2]="{secondaltcolor}";
$cpnav[3]="{secondaltcolor}";
$cpnav[4]="{secondaltcolor}";
$cpnav[5]="{secondaltcolor}";
$cpnav[6]="{secondaltcolor}";
$cpnav[7]="{firstaltcolor}";
	$cpmenu[7]="class=\"fjsel\" selected";
eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

function makecommas($string) {
	if ($string=="") {
		return "''";
	} else {
		return str_replace(" ", ",", trim($string));
	}
}
function checkpmreceipts() {
// check for whether pm receipts are enabled
	global $permissions,$readreceiptsoption,$readreceiptslink;
	if(!$permissions['cantrackpm']) {
		eval("standarderror(\"".gettemplate("error_disabledpmreceipts")."\");");
		exit;
	}
}
function checkvalidbuddypm($buddycount=0) {
// check for permission to use mass-buddy PM, and for other relevant factors
	global $bbuserinfo, $permissions;
	if (!$bbuserinfo['buddylist']) {
		eval("standarderror(\"".gettemplate("error_buddypm_nousers")."\");");
		exit;
	}
	if (!$permissions['maxbuddypm']) {
		eval("standarderror(\"".gettemplate("error_buddypm_nopermission")."\");");
		exit;
	}
	if ($buddycount > $permissions['maxbuddypm']) {
		eval("standarderror(\"".gettemplate("error_buddypm_toomany")."\");");
		exit;
	}
return 1;
}

$folderselect = array();
function makefolderjump() {
  global $bbuserinfo,$folderid,$folderselect;

  $folderjump = '';

  if ($folderid==-1) $folderselect[$sent] = "selected";

  if (trim($bbuserinfo['pmfolders'])) {
  	// get selected folder
    $allfolders = explode("\n", trim($bbuserinfo['pmfolders']));
    while (list($key,$val)=each($allfolders)) {
      $thisfolder = explode("|||", $val);
	  	$thisfolderid = intval($thisfolder[0])+1;
      $folderjump .= "<option value=\"$thisfolderid\" $folderselect[$thisfolderid]>$thisfolder[1]</option>";
    }
  }
  return $folderjump;
} #end makefolderjump

// ########################### start read receipts #######################################
if ($action=="readreceipts") {

	checkpmreceipts();

	if (!$daysprune) {
		if ($bbuserinfo['daysprune'] == -1) {
		  $daysprune = "all";
		} else {
		  $daysprune = $bbuserinfo['daysprune'];
		}
	}

	if ($daysprune != "all") {
		$daysprune_days = $daysprune * 86400;
		$dateconds = "AND dateline >= ".(time()-$daysprune_days);
	} else {
	  	$dateconds = "";
	  $daysprune_days = 0;
	  $daysprune = 1000;
	}

	$daysprunesel = array();
	$daysprunesel[$daysprune] = "selected";

	$messages = $DB_site->query("
		SELECT DISTINCT privatemessage.*, user.username AS tousername, user.emailonpm, user.invisible,
			user.lastactivity, icon.iconpath, session.userid AS sessionuserid
		FROM privatemessage
		LEFT JOIN user ON (user.userid = privatemessage.touserid)
		LEFT JOIN icon ON (icon.iconid = privatemessage.iconid)
		LEFT JOIN session ON (session.userid = user.userid AND session.userid <> 0 AND session.lastactivity>0)
		WHERE fromuserid=$bbuserinfo[userid]
		AND folderid <> -1
		AND receipt > 0
		$dateconds
		ORDER BY dateline DESC");

	$datecut = time() - $cookietimeout;

	$readmessages = '';
	$unreadmessages = '';
	$privatemessages = '';
	while ($message = $DB_site->fetch_array($messages)) {

		if (!$message['invisible'] or $bbuserinfo['usergroupid'] == 6)
		{
			if ($message['sessionuserid']>0 && $message['lastactivity']>$datecut)
			{
			  eval("\$message[onlinestatus] = \"".gettemplate("privsent_useronline")."\";");
		    }
			else
			{
			  $message['lastactivity_date'] = vbdate($dateformat,$message['lastactivity']);
			  $message['lastactivity_time'] = vbdate($timeformat,$message['lastactivity']);
			  eval("\$message[onlinestatus] = \"".gettemplate("privsent_useroffline")."\";");
			}
		}
		else
		{
			$message['onlinestatus'] = "&nbsp;";
		}

	    if ($message['iconid']) {
		  $message['icon']="<img src=\"$message[iconpath]\" alt=\"$message[icontitle]\" border=0>";
		} else {
		  $message['icon']="&nbsp;";
		}

		if ($message['receipt']==2) {

		  $message['dateread']=vbdate($dateformat,$message['readtime']);
	      $message['timeread']=vbdate($timeformat,$message['readtime']);

		  // message has been read and receipt granted
		  eval("\$readmessages .= \"".gettemplate("privsent_readmessagebit")."\";");

		} else {

		  $message['datesent']=vbdate($dateformat,$message['dateline']);
		  $message['timesent']=vbdate($timeformat,$message['dateline']);

		  // message has not been read
		  if ($message['emailonpm'] and  (!$message['invisible'] or $bbuserinfo['usergroupid'] == 6))
		  {
		  	eval("\$message[notified] = \"".gettemplate("privsent_emailnotified")."\";");
		  }
		  else
		  {
		  	$message['notified'] = "";
		  }
		  if($message['deleteprompt']) {
			$message['foldericon'] = "trashcan";
			$message['title'] = "<b>$pmcancelledword</b> $message[title]";
			eval("\$message[undelete] = \"".gettemplate("privsent_restorecancelled")."\";");
		  } else {
			$message['foldericon'] = "newpm";
			$message['undelete'] = "&nbsp;";

		  } // end unread messages

		eval("\$unreadmessages .= \"".gettemplate("privsent_unreadmessagebit")."\";");
		}
	  } // end while $messages

	if ($unreadmessages) {
	  eval("\$privatemessages = \"".gettemplate("privsent_unreadmessages")."\";");
	  }
	if ($readmessages) {
	  eval("\$privatemessages .= \"".gettemplate("privsent_readmessages")."\";");
	  }
	if (!$unreadmessages && !$readmessages) {
	  eval("\$privatemessages = \"".gettemplate("privsent_nomessages")."\";");
	  }

	if ($permissions['maxbuddypm']) {
	  eval("\$buddypmlink = \"".gettemplate("priv_buddymasspmlink")."\";");
	} else {
	  $buddypmlink = '';
	}

	$privpage=2;
	eval("\$priv_dateselect = \"".gettemplate("priv_dateselect")."\";");

	$folderselect['tracking'] = "selected";
	$folderjump = makefolderjump();
	makeforumjump(); //make the forum dropdown
	eval("\$readreceiptsoption = \"".gettemplate("priv_readreceiptsoption")."\";");
	eval("dooutput(\"".gettemplate("privsent")."\");");
}

// ########################### start cancel/restore/end tracking #######################################
if ($action == "trackingcontrol") {

	checkpmreceipts();

	if (!is_array($privmsg)) {
		eval("standarderror(\"".gettemplate("error_pmnoselected")."\");");
		exit;
	}

	$templatebit = '';

	if ($cancel) {
	  $doaction = "deleteprompt=1";
	} elseif ($restore) {
	  $doaction = "deleteprompt=0";
	  $templatebit = "un";
	} elseif ($endtracking) {
	  $doaction = "receipt=0";
	  $templatebit = "tracking";
	} else {
	  $idname = "action";
	  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
	  exit;
	}

	$selected_messages = "";
	while (list($key,$val) = each ($privmsg)) {
		if ($val == "yes") {
		  $selected_messages .= intval( $key ) . ' ';
		}
	}

	// verify that messages are from $bbuserinfo[userid]
	$messages = $DB_site->query("
		SELECT privatemessageid FROM privatemessage
		LEFT JOIN user ON (user.userid = privatemessage.touserid)
		WHERE fromuserid='$bbuserinfo[userid]'
		AND folderid<>-1
		AND privatemessageid IN(".makecommas($selected_messages).")");

	$update_messages = "";
	while($message = $DB_site->fetch_array($messages)) {
		$update_messages .= "$message[privatemessageid] ";
		}

	if ($update_messages != "" && $doaction == "deleteprompt=1" && $pmcancelkill) {
		// delete messages completely
		$DB_site->query("DELETE FROM privatemessage WHERE privatemessageid IN(".makecommas($update_messages).")");
	} elseif ($update_messages != "") {
		// cancel, restore or end tracking
		$DB_site->query("UPDATE privatemessage SET $doaction WHERE privatemessageid IN(".makecommas($update_messages).")");
	} else {
	  $idname = "messages";
	  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
	  exit;
	}

	eval("standardredirect(\"".gettemplate("redirect_pm".$templatebit."delete")."\",\"private2.php?s=$session[sessionhash]&amp;daysprune=".intval($daysprune)."\");");
	exit;
}

// ########################### start send to buddies - choose recipients #######################################
if ($action == "choosebuddies") {

	checkvalidbuddypm();

	if ($permissions['cantrackpm']) {
  	  eval("\$readreceiptsoption = \"".gettemplate("priv_folderjump_receipts")."\";");
     eval("\$readreceiptslink = \"".gettemplate("priv_readreceiptslink")."\";");
	} else {
	  $readreceiptsoption = "";
	  $readreceiptslink = "";
	}

	if ($forward==true) {
	  $fwhidden = "<input type=\"hidden\" name=\"privmessageid\" value=\"$privatemessageid\">\n<input type=\"hidden\" name=\"forward\" value=\"true\">";
	} else {
	  $fwhidden = "";
	}

	$folderjump = makefolderjump();

	$datecut = time() - $cookietimeout;

	$users = $DB_site->query("
		SELECT DISTINCT user.*, session.userid AS sessionuserid FROM user
		LEFT JOIN session ON (session.userid = user.userid AND session.userid <> 0 AND session.lastactivity>0)
		WHERE user.userid IN(".makecommas($bbuserinfo['buddylist']).") AND receivepm=1
		GROUP BY user.userid ORDER BY username");
	unset($user);

	$buddybits = "";
	while ($user = $DB_site->fetch_array($users)) {
		$buddyid = $user['userid'];

		if ($bgcounter++%2==0) {
			$backcolor="{firstaltcolor}";
			$bgclass="alt1";
		} else {
			$backcolor="{secondaltcolor}";
			$bgclass="alt2";
		}

		if ($user['sessionuserid']!=0 and $user['lastactivity'] > $datecut and (!$user['invisible'] or $bbuserinfo['usergroupid'] == 6)) {
		  eval("\$onlinestatus = \"".gettemplate("postbit_online")."\";");
		} else {
		  eval("\$onlinestatus = \"".gettemplate("postbit_offline")."\";");
		}

		eval("\$buddybits .= \"".gettemplate("priv_choosebuddybit")."\";");
	}

	eval("dooutput(\"".gettemplate("priv_choosebuddies")."\");");
	exit;
}

// ############################### private message to buddies - compose ###############################
if ($action == "sendtobuddies") {

	if (!is_array($buddyid)) {
	  $idname = "list of buddies";
	  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
	  exit;
	}

	// create list of buddies for SQL query
	$buddycount = 0;
	$buddyidlist = "";
	while (list($key,$val) = each($buddyid)) {
	  if ($val=="yes") {
		  $buddyidlist .= intval( $key ) . ' ';
			$buddycount++;
	  }
	}

	checkvalidbuddypm($buddycount);

	// show error if no entries for list
	$buddyids = makecommas($buddyidlist);
	if(!$buddyidlist) {
	  $idname = "list of buddies";
	  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
	  exit;
	}

	// get username for selected buddies
	$buddynames = "";
	$buddies = $DB_site->query("SELECT userid,username FROM user WHERE userid IN($buddyids) ORDER BY username");
	while ($buddy = $DB_site->fetch_array($buddies)) {
	  eval("\$buddynames .= \"<nobr>".gettemplate("priv_sendtobuddies_name").",</nobr> \";");
	}
	$buddyids = str_replace("\"","",$buddyids);
	$buddynames = str_replace(",</nobr> |||", "</nobr>", ($buddynames."|||"));

	// check if this is a forwarded message
	if ($privmessageid != "") {

	  $privatemessageid = verifyid("privatemessage",$privmessageid);

	  $message = $DB_site->query_first("SELECT * FROM privatemessage WHERE privatemessageid=$privatemessageid");
	  if ($message['userid']!=$bbuserinfo['userid']) {
	    $idname="message";
	    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
	    exit;
	  }

	  $message['postdate']=vbdate($dateformat,$message['dateline']);
	  $message['posttime']=vbdate($timeformat,$message['dateline']);
	  $message['message']=htmlspecialchars($message['message']);

	  $fromuserinfo=getuserinfo($message['fromuserid']);

	  $firsttwoletters = substr($message['title'],0,3);
	  if ($firsttwoletters=="Re:" || $firsttwoletters=="Fw:") {
	    $message['title']=trim(substr($message['title'],3));
	  }
	  $subject="Fw: $message[title]";

	  eval("\$message[message] = \"".gettemplate("priv_reply",1,0)."\";");
	} else {
	  $message = array();
	}
	// end forwarded check

	if ($bbuserinfo['userid']!=0 and $bbuserinfo['signature']!="") {
	  $signaturechecked="checked";
	} else {
	  $signaturechecked = '';
	}

	if ($permissions['cantrackpm']) {
	  eval("\$requestreceipt = \"".gettemplate("priv_requestreceipt")."\";");
	} else {
	  $requestreceipt = '';
	}

	if ($privallowicons) {
	  $posticons=chooseicons(0);
	}  else {
	  $posticons="<input type=\"hidden\" name=\"iconid\" value=\"0\">";
	}

	// generate navbar
	$navbits = '';
	$nav_url="private.php?s=$session[sessionhash]";
	$nav_title="Private Messages";
	eval("\$navbits .= \"".gettemplate("nav_linkon")."\";");
	eval("\$navbar = \"".gettemplate("navbar")."\";");

	if ($bbuserinfo['showvbcode']) {
	  $vbcode_smilies = getclickysmilies($smcolumns,$smtotal);
	  $vbcode_buttons = getcodebuttons();
	} else {
	  $vbcode_smilies = '';
	  $vbcode_buttons = '';
	}
	eval("dooutput(\"".gettemplate("priv_sendtobuddies")."\");");
	exit;
}

// ############################### do send message to buddies ###############################
if ($_POST['action'] == "dosendtobuddies") {

	// check for number of recipients
	$listcheck = explode(",", trim($buddyids));
	$touserids = "";
	$buddycount = 0;
	while (list($key,$val) = each($listcheck)) {
	  $touserids .= intval( $val ) . ' ';
	  $buddycount++;
	}

	checkvalidbuddypm($buddycount);

	$touserids = makecommas($touserids);

	// check for valid message contents
	if ($message=="" or $buddyids=="" or $title=="") {
	  eval("standarderror(\"".gettemplate("error_requiredfields")."\");");
	  exit;
	}

	// check for max message length
	if (strlen($message)>$pmmaxchars && $pmmaxchars!=0 && $bbuserinfo['usergroupid'] != 6) {
	  eval("standarderror(\"".gettemplate("error_pmtoolong")."\");");
	}

	// do flood check
	if (!ismoderator() && $pmfloodtime) {
	  $lastmessagetime=$DB_site->query_first("SELECT MAX(dateline) AS dateline FROM privatemessage WHERE fromuserid=$bbuserinfo[userid]");
	  if ((time() - $lastmessagetime['dateline']) < $pmfloodtime) {
	    eval("standarderror(\"".gettemplate("error_pmfloodcheck")."\");");
		exit;
	  }
	}

	$signature=iif($signature=="yes",1,0);
	$parseurl=iif($parseurl=="yes",1,0);
	$savecopy=iif($savecopy=="yes",1,0);
	$iconid = intval(trim($iconid));
	$receipt=iif($pmreceipt=="yes",1,0);

	$message = stripsession($message);
	if ($parseurl) $message=parseurl($message);

	// get recipients (receivePM check should be unneccessary
	// as choose buddies only displays receivepm=1 users...
	$tousers = $DB_site->query("
		SELECT user.*, COUNT(privatemessageid) AS messagecount
		FROM user LEFT JOIN privatemessage ON (privatemessage.userid = user.userid)
		WHERE user.userid IN($touserids) AND receivepm=1
		GROUP BY user.userid");

	// check for at least one valid recipient
	if (!$DB_site->num_rows($tousers)) {
	  eval("standarderror(\"".gettemplate("error_pminvalidrecipient")."\");");
	  exit;
	}

	// do stuff for each recipient
	$errormessages = '';
	$successmessages = '';
	while ($touserinfo = $DB_site->fetch_array($tousers)) {

	  // check pm quotas
	  if (($pmquota > 0) && ($touserinfo['usergroupid'] != 6) && ($bbuserinfo['usergroupid'] != 6)) {
	    if ($touserinfo['messagecount'] >= $pmquota) {
		  // no further private messages allowed
		  eval("\$email_message = \"".gettemplate("email_pmboxfull",1,0)."\";");
	      eval("\$email_subject = \"".gettemplate("emailsubject_pmfullbox",1,0)."\";");
		  vbmail($touserinfo['email'], $email_subject, $email_message);
		  $errormessages .= "<li>$touserinfo[username] <smallfont>($touserinfo[messagecount] messages in folder)</smallfont></li>";
		  // go straight to the next recipient in the list
		  continue;
	    }
	  } // end check quotas

	  $successmessages .= "<li>$touserinfo[username] <smallfont>($touserinfo[messagecount] messages in folder)</smallfont> ";
	  if ($touserinfo['emailonpm']) {
	    eval("\$successmessages .= \"".gettemplate("privsent_emailnotified")."\";");
	  }

	  // do message sql inserts
	  $DB_site->query("INSERT INTO privatemessage
	    (privatemessageid,userid,touserid,fromuserid,title,message,dateline,showsignature,iconid,messageread,folderid,receipt)
		VALUES
		(NULL,$touserinfo[userid],$touserinfo[userid],$bbuserinfo[userid],'".vb_escape(htmlspecialchars($title))."','".vb_escape($message)."',".time().",'$signature','$iconid',0,0,'$receipt')");

	  // check for sender is ignored
	  $touserinfo['ignorelist'] = "  $touserinfo[ignorelist] ";
	  if (!strpos($touserinfo['ignorelist']," $bbuserinfo[userid] ")) {
	    // user is not ignored
	    if ($touserinfo['pmpopup']==1) {
	      if ($noshutdownfunc) {
	        $DB_site->query("UPDATE user SET pmpopup=2 WHERE userid='$touserinfo[userid]'");
	      } else {
	        $shutdownqueries[]="UPDATE user SET pmpopup=2 WHERE userid='$touserinfo[userid]'";
	      }
	    }
	    if ($touserinfo['emailonpm']) {
	      eval("\$email_message = \"".gettemplate("email_pmreceived",1,0)."\";");
	      eval("\$email_subject = \"".gettemplate("emailsubject_pmreceived",1,0)."\";");
		  vbmail($touserinfo['email'], $email_subject, $email_message);
	    }
	  }
	} // end while $tousers

	// save a copy if required
	if ($savecopy) {
	  $DB_site->query("INSERT INTO privatemessage
	    (privatemessageid,userid,touserid,fromuserid,title,message,dateline,showsignature,iconid,messageread,folderid,multiplerecipients)
		VALUES
		(NULL,'$bbuserinfo[userid]','$touserinfo[userid]','$bbuserinfo[userid]','".vb_escape(htmlspecialchars($title))."','".vb_escape($message)."',".time().",'$signature','$iconid',1,-1,1)");
	}

	// display list of failed messages if any
	if ($errormessages) {
	  eval("standarderror(\"".gettemplate("error_buddypm_fullbox")."\");");
	} else {
	  eval("\$touser = \"".gettemplate("priv_senttomultiple")."\";");
	  eval("standardredirect(\"".gettemplate("redirect_pmthanks")."\",\"private.php?s=$session[sessionhash]\");");
	}
}

// ############################### forward multiple messages to user - choose recipient ###############################
if ($action == "fmulti") {

	// check for number of messages and build list for query
	if (!$permissions['maxforwardpm']) {
		show_nopermission();
		exit;
	}
	if (!is_array($m)) {
		$idname = "list of messages";
		eval("standarderror(\"".gettemplate("error_invalidid")."\");");
		exit;
	}
	$nummessages = count($m);
	if ($nummessages > $permissions['maxforwardpm']) {
		eval("standarderror(\"".gettemplate("error_forwardpm_toomany")."\");");
		exit;
	}
	$messageids = "";
	while (list($key,$val) = each($m)) {
		if ($val) {
			$messageids .= intval( $key ) . ' ';
		}
	}

	// verify that messages belong to $bbuserinfo[userid] and build new list
	// also ensure that deleteprompt is not set, so that users can not fool
	// the system into letting them read cancelled messages.
	$messages = $DB_site->query("
		SELECT privatemessageid,title,fromuserid,username AS fromusername FROM privatemessage
		LEFT JOIN user ON (user.userid=privatemessage.fromuserid)
		WHERE privatemessageid IN(".makecommas($messageids).")
		AND privatemessage.userid='$bbuserinfo[userid]' AND deleteprompt=0");
	$nummessages = $DB_site->num_rows($messages);

	if (!$nummessages) {
		$idname = "list of messages";
		eval("standarderror(\"".gettemplate("error_invalidid")."\");");
		exit;
	}

	$messageids = '';
	$messagetitlebits = '';
	while ($message = $DB_site->fetch_array($messages)) {
		$messageids .= "$message[privatemessageid] ";

		$firsttwoletters = substr($message['title'],0,3);
		if ($firsttwoletters=="Fw:" || $firsttwoletters=="Re:") {
			$message['title']=trim(substr($message['title'],3));
		}
		eval("\$messagetitlebits .= \"".gettemplate("priv_forwardmultiple_titlebit")."\";");

	} // end while messages
	$messageids = makecommas($messageids);

	if ($bbuserinfo['userid']!=0 and $bbuserinfo['signature']!="") {
	  $signaturechecked="checked";
	} else {
	  $signaturechecked = '';
	}

	if ($permissions['cantrackpm']) {
	  eval("\$requestreceipt = \"".gettemplate("priv_requestreceipt")."\";");
	} else {
	  $requestreceipt = '';
	}

	if ($privallowicons) {
	  $posticons=chooseicons(0);
	}  else {
	  $posticons="<input type=\"hidden\" name=\"iconid\" value=\"0\">";
	}

	if ($bbuserinfo['showvbcode']) {
	  $vbcode_smilies = getclickysmilies($smcolumns,$smtotal);
	  $vbcode_buttons = getcodebuttons();
	} else {
	  $vbcode_smilies = '';
	  $vbcode_buttons = '';
	}
	eval("dooutput(\"".gettemplate("priv_forwardmultiple")."\");");
	exit;

}

// ############################### do forward multiple messages to user ###############################
if ($_POST['action'] == "dofmulti") {

	// check for permissions and messages to forward
	if (!$permissions['maxforwardpm']) {
		show_nopermission();
		exit;
	}
	if ($messageids == "") {
		$idname = "list of messages";
		eval("standarderror(\"".gettemplate("error_invalidid")."\");");
		exit;
	}
	if ($touser=="") {
		eval("standarderror(\"".gettemplate("error_requiredfields")."\");");
		exit;
	}
	// this bit actually checks the length of the prefix, rather than the complete forwarded message
	// and checking strlen(prefix + message) could be confusing for the user.
	// I use $pmmaxchars/2 as the upper prefix length limit.
	if (strlen($message)>($pmmaxchars/2) && $pmmaxchars!=0 && $bbuserinfo['usergroupid'] != 5) {
		$pmmaxchars = ceil($pmmaxchars/2);
		eval("standarderror(\"".gettemplate("error_pmtoolong")."\");");
		exit;
	}
	// check max number of messages permissible
	$privmsgids = explode(",", trim($messageids));
	$nummessages = count($privmsgids);
	if ($nummessages > $permissions['maxforwardpm']) {
		eval("standarderror(\"".gettemplate("error_forwardpm_toomany")."\");");
		exit;
	}
	// check for valid recipient
	if ($touserinfo = $DB_site->query_first("
		SELECT user.*, COUNT(privatemessageid) AS messages
		FROM user LEFT JOIN privatemessage ON (privatemessage.userid=user.userid)
		WHERE username='".htmlspecialchars(vb_escape($touser))."'
		GROUP BY user.userid")) {
		$touserid = $touserinfo['userid'];
	} else {
		eval("standarderror(\"".gettemplate("error_pminvalidrecipient")."\");");
		exit;
	}
	// if to-user has PM disabled
	if (!$touserinfo['receivepm']) {
		eval("standarderror(\"".gettemplate("error_pmrecipturnedoff")."\");");
		exit;
	}
	// if to-user's PM quota is exceded by sending these messages...
	if (($pmquota > 0) && (($touserinfo['messages'] + $nummessages) > $pmquota) && ($bbuserinfo['usergroupid'] != 6) && ($touserinfo['usergroupid'] != 6)) {
		eval("\$email_message = \"".gettemplate("email_pmboxfull",1,0)."\";");
		eval("\$email_subject = \"".gettemplate("emailsubject_pmfullbox",1,0)."\";");
		vbmail($touserinfo['email'], $email_subject, $email_message);
		eval("standarderror(\"".gettemplate("error_pmfullmailbox")."\");");
		exit;
	}
	// do flood check
	if (!ismoderator() && $pmfloodtime) {
	  $lastmessagetime=$DB_site->query_first("
	  	SELECT MAX(dateline) AS dateline FROM privatemessage WHERE fromuserid=$bbuserinfo[userid]");
	  if ((time() - $lastmessagetime['dateline']) < $pmfloodtime) {
	    eval("standarderror(\"".gettemplate("error_pmfloodcheck")."\");");
		exit;
	  }
	}

	// END ERROR CHECKING

	// build list of message ids for query
	$forwardmessages = "";
	while (list($key,$val) = each($privmsgids)) {
		$forwardmessageids .= intval( $val ) . ' ';
	}

	// set up variables for message insert
	$signature=iif($signature=="yes",1,0);
	$iconid = intval(trim($iconid));
	$receipt=iif($pmreceipt=="yes",1,0);

	// assign message prefix to new var
	$msgprefix = $message;
	unset($message);

	$messages = $DB_site->query("
		SELECT privatemessage.*, user.username AS fromusername FROM privatemessage
		LEFT JOIN user ON (user.userid=privatemessage.fromuserid)
		WHERE privatemessageid IN(".makecommas($forwardmessageids).")
		AND privatemessage.userid='$bbuserinfo[userid]'
		AND deleteprompt=0");

	while ($message = $DB_site->fetch_array($messages)) {

		// remove Fw: and Re: from previous titles
		$firsttwoletters = substr($message['title'],0,3);
		if ($firsttwoletters=="Fw:" || $firsttwoletters=="Re:") {
			$message['title']=trim(substr($message['title'],3));
		}

		// sort out [quote] bits
		$message['postdate']=vbdate($dateformat,$message['dateline']);
		$message['posttime']=vbdate($timeformat,$message['dateline']);
		$fromuserinfo['username'] = $message['fromusername'];
		eval("\$message[message] = \"".gettemplate("priv_reply",1,0)."\";");

		// add user's prefix to message body
		$message['message'] = $msgprefix.$message['message'];

		$DB_site->query("INSERT INTO privatemessage (privatemessageid,userid,touserid,fromuserid,title,message,dateline,showsignature,iconid,messageread,folderid,receipt)
		VALUES ('','$touserinfo[userid]','$touserinfo[userid]','$bbuserinfo[userid]','".vb_escape(htmlspecialchars("Fw: $message[title]"))."','".vb_escape($message['message'])."','".time()."','$signature','$iconid','0','0','$receipt')");

	} // end while messages

	// check for sender is ignored
	$touserinfo['ignorelist'] = "  $touserinfo[ignorelist] ";
	if (!strpos($touserinfo['ignorelist']," $bbuserinfo[userid] ")) {
	  // user is not ignored
	  if ($touserinfo['pmpopup']==1) {
	    if ($noshutdownfunc) {
	      $DB_site->query("UPDATE user SET pmpopup=2 WHERE userid='$touserinfo[userid]'");
	    } else {
	      $shutdownqueries[]="UPDATE user SET pmpopup=2 WHERE userid='$touserinfo[userid]'";
	    }
	  }
	  if ($touserinfo['emailonpm']) {
	    eval("\$email_message = \"".gettemplate("email_pmreceived",1,0)."\";");
	    eval("\$email_subject = \"".gettemplate("emailsubject_pmreceived",1,0)."\";");
		vbmail($touserinfo['email'], $email_subject, $email_message);
	  }
	}

	eval("standardredirect(\"".gettemplate("redirect_pmthanks")."\",\"private.php?s=$session[sessionhash]\");");
	exit;
}
?>