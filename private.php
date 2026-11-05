<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

// enhanced
// received mass-forward action -> work out what to do with it
if ($action=="dostuff" && $_POST['fmulti']!="") {
  if (!is_array($_POST['privatemessage'])) {
    // no selected messages: go to normal dostuff action and get error
    $move="yes";
  } elseif (count($_POST['privatemessage'])==1) {
    // only one message selected: use standard PM forwarding page
    $action = "newmessage";
	 $forward = true;
	 $privatemessageid = key($_POST['privatemessage']);
  } else {
    // multiple messages selected: build URL and redirect to private2.php
    $urlstring = '';
    while (list($key,) = each ($_POST['privatemessage'])) $urlstring .= "&m[$key]=1";
  	 header("Location: private2.php?s=$_POST[s]&action=fmulti".$urlstring);
	 exit;
  }
}

$templatesused = "usercpnav,privfolder_bit,privfolder_messages,privfolder_nomessages,privfolder,postbit_avatar,postbit_useremail,icq,aim,yahoo,postbit_homepage,postbit_profile,postbit_signature,privmsg,priv_reply,priv_sendprivmsg,redirect_pmthanks,redirect_pmdelete,priv_showfolders_folderbit,priv_showfolders,posticons,posticonbit";
$templatesused .= ",postbit_online,postbit_offline,priv_forwardtobuddylink,privmsg_nextnewest,privmsg_nextoldest,vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";
// enhanced
// templates for new PM functionality
$templatesused .= ",priv_readreceiptsoption,priv_dateselect,privfolder_deletedbit,privfolder_denyreceipt,priv_senttomultiple,priv_requestreceipt,priv_buddymasspmlink,privfolder_massforward,priv_readreceiptslink,";
// /enhanced
require("./global.php");

// intercept request for read receipts page and redirect
if ($folderid=="tracking") {
  header("Location: private2.php?s=$s");
  exit;
}
// /enhanced

// get decent textarea size for user's browser
$textareacols = gettextareawidth();

// select correct part of FORUMJUMP menu
$frmjmpsel['pm'] = "selected";

// ###################### Start pmcodeparse #######################
function pmcodeparse($bbcode) {
  global $privallowhtml, $privallowbbimagecode, $privallowsmilies, $privallowbbcode;
  $bbcode=bbcodeparse2($bbcode,$privallowhtml,$privallowbbimagecode,$privallowsmilies,$privallowbbcode);
  return $bbcode;
}

if (!isset($action) or $action=="") {
  $action="getfolder";
}

if ($enablepms==0) {
  eval("standarderror(\"".gettemplate("error_pmadminoff")."\");");
  exit;
}

//check usergroup of user to see if they can use PMs
$permissions=getpermissions($forumid);
if (!$permissions['canusepm']) {
  show_nopermission();
}

//check if the user will receive PMs
if (!$bbuserinfo['receivepm']) {
  eval("standarderror(\"".gettemplate("error_pmturnedoff")."\");");
  exit;
}

$folderselect = array();
function makefolderjump() {
  global $bbuserinfo,$folderid,$folderselect;

  $folderjump = '';

  // enhanced
  if ($folderid==-1) $folderselect[$sent] = "selected";
  // /enhanced

  //get all folder names (for dropdown)
  //reference with $foldernames[#] .

  if ($bbuserinfo['pmfolders']) {
    $allfolders = explode("\n", trim($bbuserinfo['pmfolders']));
    while (list($key,$val)=each($allfolders)) {
      $thisfolder = explode("|||", $val);
	  // enhanced
	  $thisfolderid = $thisfolder[0]+1;
	  // /enhanced
      $folderjump .= "<option value=\"$thisfolderid\" $folderselect[$thisfolderid]>$thisfolder[1]</option>";
    }
  }

  return $folderjump;

} #end makefolderjump

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

// ############################### start get folder ###############################
if ($action=="getfolder") {

	$privatemessages = '';

  // enhanced
  // templates based on permissions
  if ($permissions['maxbuddypm']) {
    eval("\$buddypmlink = \"".gettemplate("priv_buddymasspmlink")."\";");
  } else {
  	 $buddypmlink = '';
  }
  if ($permissions['maxforwardpm']) {
    eval("\$massforwardlink = \"".gettemplate("privfolder_massforward")."\";");
  } else {
  	 $massforwardlink = '';
  }
  if ($permissions['cantrackpm']) {
  	 eval("\$readreceiptsoption = \"".gettemplate("priv_readreceiptsoption")."\";");
    eval("\$readreceiptslink = \"".gettemplate("priv_readreceiptslink")."\";");
  } else {
  	 $readreceiptsoption = '';
	 $readreceiptslink = '';
  }
  // generate days prune sql condition
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
    $daysprune_days = 0;
    $dateconds = "";
    $daysprune = 1000;
  }

  // get date select menu
  $daysprunesel = array();
  $daysprunesel[$daysprune] = "selected";
  eval("\$priv_dateselect = \"".gettemplate("priv_dateselect")."\";");
  // /enhanced

  if ($folderid==-1) { //Need to move out for translation
    $sender = "Sent To";
  } else {
    $sender = "Sender";
  }

  $folderid = intval($folderid);
  if (!$folderid) {
    $folderid = 0;
  }

  $foldernames = array();
  if ($bbuserinfo['pmfolders']) {
    $allfolders = split("\n", trim($bbuserinfo['pmfolders']));
    while (list($key,$val)=each($allfolders)) {
      $thisfolder = split("\|\|\|", $val);
      $foldernames[($thisfolder[0]+1)] = $thisfolder[1];
    }
  }

  //get correct folder name
  if ($folderid=="0") {
    $foldername = $inboxname;
  } elseif ($folderid=="-1") {
    $foldername = $sentitemsname;
  } else {
    $foldername = $foldernames[$folderid];
  }

  $messagedone=0;

  if (trim($bbuserinfo['ignorelist'])!="") {
    $ignoreusers="AND privatemessage.fromuserid<>".implode(" AND privatemessage.fromuserid<>",explode(" ",trim($bbuserinfo['ignorelist'])));
  } else {
    $ignoreusers="";
  }

  // enhanced - add $dateconds to query
  $messages = $DB_site->query("SELECT
  privatemessage.*,
  IF(ISNULL(touser.username),'[Deleted User]',touser.username) AS tousername,
  IF(ISNULL(fromuser.username),'[Deleted User]',fromuser.username) AS fromusername,
  icon.title AS icontitle,icon.iconpath
  FROM privatemessage
  LEFT JOIN icon ON icon.iconid=privatemessage.iconid
  LEFT JOIN user AS touser ON (touser.userid=privatemessage.touserid)
  LEFT JOIN user AS fromuser ON (fromuser.userid=privatemessage.fromuserid)
  WHERE privatemessage.userid='$bbuserinfo[userid]'
  AND folderid='".intval($folderid)."'
  $ignoreusers
  $dateconds
  ORDER BY dateline DESC");

  $privmsgsbit = '';
  while ($privatemessage=$DB_site->fetch_array($messages)) {

    // get the more useful of the to/from field
    if ($privatemessage['touserid']==$bbuserinfo['userid']) {
      $privatemessage['displayuserid']=$privatemessage['fromuserid'];
      $privatemessage['displayusername']=$privatemessage['fromusername'];
    } else {
      $privatemessage['displayuserid']=$privatemessage['touserid'];
      $privatemessage['displayusername']=$privatemessage['tousername'];
    }

    if ($privatemessage['displayuserid']=="") {
      $privatemessage['displayusername'] = "N/A";
    }

    $privatemessage['datesent']=vbdate($dateformat,$privatemessage['dateline']);
    $privatemessage['timesent']=vbdate($timeformat,$privatemessage['dateline']);

	// enhanced
	// do multiple recipients text
	if ($privatemessage['multiplerecipients']) {
		eval("\$privatemessage[displayusername] = \"".gettemplate("priv_senttomultiple")."\";");
	}
	// blank message if deleteprompt is set
	if ($privatemessage['deleteprompt']==1) {
	  eval("\$privmsgsbit .= \"".gettemplate("privfolder_deletedbit")."\";");
	  $messagedone = 1;
	  continue;
	}
	// add deny receipt link if group permissions allow
	if ($permissions['cantrackpm'] && $permissions['candenypmreceipts'] && $privatemessage['receipt']==1) {
		eval("\$privatemessage[denyreceipt] = \"".gettemplate("privfolder_denyreceipt")."\";");
	}
	// get folder icon - INCLUDING replied icon
    switch ($privatemessage['messageread']) {
	  case 0: // new message
	    $privatemessage['folder'] = "{imagesfolder}/newpm.gif";
	    break;
	  case 1: // old message
	    $privatemessage['folder'] = "{imagesfolder}/pm.gif";
	    break;
	  case 2: // replied to message
	    $privatemessage['folder'] = "{imagesfolder}/pmreplied.gif";
	    break;
	  case 3: // forwarded message
	    $privatemessage['folder'] = "{imagesfolder}/pmforwarded.gif";
	    break;
    }
	// /enhanced

    //get icon for this message
    if ($privatemessage['iconid']) {
      $privatemessage['icon']="<img src=\"$privatemessage[iconpath]\" alt=\"$privatemessage[icontitle]\" border=\"0\">";
    } else {
      $privatemessage['icon']="&nbsp;";
    }

    //run it through the template
    $messagedone=1;
    eval("\$privmsgsbit .= \"".gettemplate("privfolder_bit")."\";");
  } //end while

  if ($messagedone) {
    eval("\$privatemessages .= \"".gettemplate("privfolder_messages")."\";");
  } else {
    eval("\$privatemessages .= \"".gettemplate("privfolder_nomessages")."\";");
  }

  $folderselect[$folderid] = "selected";
  $folderjump = makefolderjump(); //make the forum dropdown

  makeforumjump(); //make the forum dropdown

  eval("dooutput(\"".gettemplate("privfolder")."\");"); //and run everything through the template
} #end getmsgs

// ############################### start show message ###############################
if ($action=="show") {
  makeforumjump();

  $privatemessageid = verifyid("privatemessage",$privatemessageid);

  $message = $DB_site->query_first("SELECT privatemessage.*,icon.title as icontitle,icon.iconpath
                                    FROM privatemessage
                                    LEFT JOIN icon ON (privatemessage.iconid = icon.iconid)
                                    WHERE privatemessageid=$privatemessageid");
  if ($message['userid']!=$bbuserinfo['userid']) {
    $idname="privatemessage";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
    exit;
  }

  // enhanced
  if ($permissions['cantrackpm']) {
  	 eval("\$readreceiptsoption = \"".gettemplate("priv_readreceiptsoption")."\";");
    eval("\$readreceiptslink = \"".gettemplate("priv_readreceiptslink")."\";");
  } else {
    $readreceiptsoption = '';
    $readreceiptslink = '';
  }
  $receiptSQL = "";
  if ($message['receipt']==1) {
  	if (($permissions['candenypmreceipts']) && ($noreceipt=="yes")) {
	  $receiptSQL = ", receipt=0";
	} else {
	  $receiptSQL = ", receipt=2";
	}
  }
  // show error if message has been cancelled by sender
  if ($message['deleteprompt']) {
    $idname="privatemessage";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
    exit;
  }
  // check for forward-to-buddylist permission
  if ($permissions['maxbuddypm']) {
  	eval("\$buddyforwardlink = \"".gettemplate("priv_forwardtobuddylink")."\";");
  } else {
    $buddyforwardlink = '';
  }
  // /enhanced

  if ($message['messageread']==0) {
  // enhanced - added $receiptSQL
    $DB_site->query("UPDATE privatemessage SET messageread=1, readtime='".time()."' $receiptSQL WHERE privatemessageid=$privatemessageid");
  // /enhanced
  }

  $message['postdate']=vbdate($dateformat,$message['dateline']);
  $message['posttime']=vbdate($timeformat,$message['dateline']);

  if ($message['messageread']==0) {
    $message['foldericon']="<img src=\"{imagesfolder}/posticonnew.gif\" border=\"0\" alt=\"\">";
  } else {
    $message['foldericon']="<img src=\"{imagesfolder}/posticon.gif\" border=\"0\" alt=\"\">";
  }
  if (!$privallowicons or $message['iconid']==0) {
    $message['icon']="";
  } else {
    $message['icon']="<img src=\"$message[iconpath]\" alt=\"$message[icontitle]\" border=\"0\">";
  }

  $datecut = time() - $cookietimeout;
  $fromuserinfo = array();
  if ($message['fromuserid']!=0) {

    $post=$DB_site->query_first("SELECT
                                 user.*,userfield.*".iif($avatarenabled,",avatar.avatarpath,customavatar.dateline AS avatardateline,NOT ISNULL(customavatar.avatardata) AS hascustomavatar ","")."
                                 FROM user,userfield
                                 ".iif ($avatarenabled,"LEFT JOIN avatar ON avatar.avatarid=user.avatarid
                                                        LEFT JOIN customavatar ON customavatar.userid=user.userid ","")."
                                 WHERE userfield.userid=user.userid AND user.userid=$message[fromuserid]");
    $userinfo=$post;

	unset($onlinestatus);
	if ($post['lastactivity'] > $datecut and (!$post['invisible'] or $bbuserinfo['usergroupid'] == 6) and $post['lastvisit'] != $post['lastactivity']) {
	   eval("\$onlinestatus = \"".gettemplate("postbit_online")."\";");
	} else {
	   eval("\$onlinestatus = \"".gettemplate("postbit_offline")."\";");
	}

	if ($bbuserinfo['showavatars']) {
	    if ($post['avatarid']!=0) {
	      $avatarurl=$post['avatarpath'];
	    } else {
	      if ($post['hascustomavatar'] and $avatarenabled) {
	        $avatarurl="avatar.php?userid=$post[userid]&amp;dateline=$post[avatardateline]";
	      } else {
	        $avatarurl="";
	      }
	    }
	    if ($avatarurl=="") {
	      $post['avatar']="";
	    } else {
	      eval("\$post[avatar] = \"".gettemplate("postbit_avatar")."\";");
	    }
	}

    $post['joindate']=vbdate($registereddateformat,$post['joindate']);
    if ($post['customtitle']==2) {
      $post['usertitle'] = htmlspecialchars($post['usertitle']);
    }
    if ($post['showemail'] and $displayemails) {
      eval("\$post[useremail] = \"".gettemplate("postbit_useremail")."\";");
    } else {
      $post['useremail']="";
    }
    if ($post['icq']!="") {
      eval("\$post[icqicon] = \"".gettemplate("icq")."\";");
    } else {
      $post['icqicon']="";
    }
    if ($post['aim']!="") {
      eval("\$post[aimicon] = \"".gettemplate("aim")."\";");
    } else {
      $post['aimicon']="";
    }
    if ($post['yahoo']!="") {
      eval("\$post[yahooicon] = \"".gettemplate("yahoo")."\";");
    } else {
      $post['yahooicon']="";
    }

    if ($post['homepage']!="" and $post['homepage']!="http://") {
      eval("\$post[homepage] = \"".gettemplate("postbit_homepage")."\";");
    } else {
      $post['homepage']="";
    }

    eval("\$post[profile] = \"".gettemplate("postbit_profile")."\";");

    if ($message['showsignature'] and $allowsignatures and trim($post['signature'])!="" && $bbuserinfo['showsignatures']) {
      $post['signature']=bbcodeparse($post['signature'],0,$allowsmilies);
      eval("\$post[signature] = \"".gettemplate("postbit_signature")."\";");
    } else {
      $post['signature'] = "";
    }
    $fromuserinfo=$post;
  } else {
    $fromuserinfo['username'] = "N/A";
  }

  $touserinfo = getuserinfo($message['touserid']);

  $message['message']=pmcodeparse($message['message'],0,$allowsmilies);
  $message['message'] .= $fromuserinfo['signature'];

  if ($getnextnewest=$DB_site->query_first("SELECT privatemessageid
                                            FROM privatemessage
                                            WHERE folderid='$message[folderid]'
                                              AND dateline>'$message[dateline]'
                                              AND userid='$message[userid]'
                                            ORDER BY dateline LIMIT 1")) {
    $nextnewestid=$getnextnewest['privatemessageid'];
    eval("\$nextnewest = \"".gettemplate("privmsg_nextnewest")."\";");
  } else {
    $nextnewest = '';
  }

  if ($getnextoldest=$DB_site->query_first("SELECT privatemessageid
                                            FROM privatemessage
                                            WHERE folderid='$message[folderid]'
                                              AND dateline<'$message[dateline]'
                                              AND userid='$message[userid]'
                                            ORDER BY dateline DESC LIMIT 1")) {
    $nextoldestid=$getnextoldest['privatemessageid'];
    eval("\$nextoldest = \"".gettemplate("privmsg_nextoldest")."\";");
  } else {
    $nextoldest = '';
  }

  // generate navbar
  $nav_url="private.php?s=$session[sessionhash]";
  $nav_title="Private Messages";
  $navbits = '';
  eval("\$navbits .= \"".gettemplate("nav_linkon")."\";");
  $navbits.=gettemplate("nav_joiner",0);
  $nav_url="private.php?s=$session[sessionhash]&amp;folderid=$message[folderid]";
  $nav_title=$foldername;
  eval("\$navbits .= \"".gettemplate("nav_linkon")."\";");
  eval("\$navbar = \"".gettemplate("navbar")."\";");
  $nav_title=$message['title'];
  eval("\$navbits .= \"".gettemplate("nav_linkoff")."\";");
  eval("\$navbar = \"".gettemplate("navbar")."\";");

  // add folderjump
  $folderid = $message['folderid'];
  $folderselect[$folderid] = "selected";
  $folderjump = makefolderjump();

  eval("dooutput(\"".gettemplate("privmsg")."\");");

  } #end show

// ############################### start new message ###############################
if  ($action=="newmessage") {
  //show new message form

  $subject = '';
  if (isset($privatemessageid)) {
    // check to see if replying or forwarding

    $privatemesageid=verifyid("privatemessage",$privatemessageid);

    $message = $DB_site->query_first("SELECT * FROM privatemessage WHERE privatemessageid=$privatemessageid");

    //check to make sure that this owner owns this message, and that the message has not been cancelled.
    if ($message['userid']!=$bbuserinfo['userid'] or $message['deleteprompt']==1) {
      $idname="privatemessage";
      eval("standarderror(\"".gettemplate("error_invalidid")."\");");
      exit;
    }

    $message['postdate']=vbdate($dateformat,$message['dateline']);
    $message['posttime']=vbdate($timeformat,$message['dateline']);
    $message['message']=htmlspecialchars($message['message']);

    $fromuserinfo=getuserinfo($message['fromuserid']);

    $fromusergroup=$DB_site->query_first("SELECT canusepm FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND user.userid='$fromuserinfo[userid]'");

    if ((!$fromuserinfo['receivepm'] or !$fromusergroup['canusepm']) and $forward != "true") {
      $touser = $fromuserinfo['username'];
      eval("standarderror(\"".gettemplate("error_pmrecipturnedoff")."\");");
    }

    if (strtolower(substr($message['title'],0,3))=="re:" or strtolower(substr($message['title'],0,3))=="fw:") {
      $message['title']=trim(substr($message['title'],3));
    }
    if ($forward=="true") {
      $subject="Fw: $message[title]";
    } else {
      $userid=$message['fromuserid'];
      $subject="Re: $message[title]";
    }
    eval("\$message[message] = \"".gettemplate("priv_reply",1,0)."\";");
  } else {
    $message = array();
  }

  if (isset($userid)) {
    $touserinfo=getuserinfo($userid);
    $tousergroup=$DB_site->query_first("SELECT canusepm FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND user.userid='$touserinfo[userid]'");

    if (!$touserinfo['receivepm'] or !$tousergroup['canusepm']) {
      $touser = $touserinfo['username'];
      eval("standarderror(\"".gettemplate("error_pmrecipturnedoff")."\");");
    }
  }

  if ($bbuserinfo['userid']!=0 and !$previewpost and $bbuserinfo['signature']!="") {
    $signaturechecked="CHECKED";
  } else {
    $signaturechecked = '';
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
  if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons) {
    $vbcode_smilies = getclickysmilies();
    $vbcode_buttons = getcodebuttons();
  } else {
    $vbcode_smilies = '';
    $vbcode_buttons = '';
  }
  // enhanced
  if ($permissions['cantrackpm']) {
  	 eval("\$requestreceipt = \"".gettemplate("priv_requestreceipt")."\";");
  } else {
    $requestreceipt = "";
  }
  // /enhanced
  $forward = htmlspecialchars($forward);
  eval("dooutput(\"".gettemplate("priv_sendprivmsg")."\");");

} #end newmessage

// ############################### start send message ###############################
if ($_POST['action']=="dosend") {

  $message = trim($message);
  $title = trim($title);

  if ($message=="" or $touser=="" or $title=="") {
    eval("standarderror(\"".gettemplate("error_requiredfields")."\");");
    exit;
  }
  $touser = htmlspecialchars($touser);

  if ($touserinfo = $DB_site->query_first("SELECT user.*, usergroup.canusepm FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND username='".vb_escape($touser)."'")) {
    $touserid = $touserinfo['userid'];
  } else {
    eval("standarderror(\"".gettemplate("error_pminvalidrecipient")."\");");
    exit;
  }

  if (!$touserinfo['receivepm'] or !$touserinfo['canusepm']) {
    eval("standarderror(\"".gettemplate("error_pmrecipturnedoff")."\");");
    exit;
  }

  if (strlen($message)>$pmmaxchars and $pmmaxchars!=0 and $bbuserinfo['usergroupid'] != 6) {
    eval("standarderror(\"".gettemplate("error_pmtoolong")."\");");
  }

  $prevtime=$DB_site->query_first("SELECT MAX(dateline) AS dateline FROM privatemessage WHERE fromuserid=$bbuserinfo[userid]");
  if (time()-$prevtime['dateline']<$pmfloodtime and $pmfloodtime!=0 and !ismoderator()) {
    eval("standarderror(\"".gettemplate("error_pmfloodcheck")."\");");
    exit;
  }

  $signature=iif($signature=="yes",1,0);
  $parseurl=iif($parseurl=="yes",1,0);
  $savecopy=iif($savecopy=="yes",1,0);
  $iconid = intval(trim($iconid));
  $title = censortext($title);

  if ($parseurl) {
    $message = parseurl($message);
  }

  $message = stripsession($message);

  if ($pmquota>0 and $touserinfo['usergroupid'] != 6 and $bbuserinfo['usergroupid'] != 6) {
    $msgcount = $DB_site->query_first("SELECT COUNT(*) AS messages FROM privatemessage WHERE userid=$touserinfo[userid]");

    if ($msgcount['messages']>=$pmquota) {
      // mailbox full. email user and display error

      eval("\$emailmsg = \"".gettemplate("email_pmboxfull",1,0)."\";");
      eval("\$emailsubject = \"".gettemplate("emailsubject_pmfullbox",1,0)."\";");
	  vbmail($touserinfo['email'], $emailsubject, $emailmsg);

      eval("standarderror(\"".gettemplate("error_pmfullmailbox")."\");");
      exit;
    }
  }

  // enhanced
  $receipt=iif($pmreceipt=="yes",1,0);
  // /enhanced

  // enhanced - added $receipt to insert
  $DB_site->query("INSERT INTO privatemessage (privatemessageid,userid,touserid,fromuserid,title,message,dateline,showsignature,iconid,messageread,folderid,receipt) VALUES (NULL,$touserinfo[userid],$touserinfo[userid],$bbuserinfo[userid],'".vb_escape(htmlspecialchars($title))."','".vb_escape($message)."',".time().",'$signature','$iconid',0,0,$receipt)");

  // enhanced
  if ($prevmessageid) {
    if ($forward) {
	  $DB_site->query("UPDATE privatemessage SET messageread=3 WHERE privatemessageid=".intval($prevmessageid));
	} else {
	  $DB_site->query("UPDATE privatemessage SET messageread=2 WHERE privatemessageid=".intval($prevmessageid));
	}
  }
  // /enhanced

  if ($savecopy) {
    $DB_site->query("INSERT INTO privatemessage (privatemessageid,userid,touserid,fromuserid,title,message,dateline,showsignature,iconid,messageread,folderid) VALUES (NULL,$bbuserinfo[userid],$touserinfo[userid],$bbuserinfo[userid],'".vb_escape(htmlspecialchars($title))."','".vb_escape($message)."',".time().",'$signature','$iconid',1,-1)");
  }

  $touserinfo['ignorelist'] = '  '.$touserinfo['ignorelist'].' ';
  if (strpos($touserinfo['ignorelist']," $bbuserinfo[userid] ")) {
    $userignored = 1;
  } else {
    $userignored = 0;
  }

  if ($userignored!=1) {
    if ($touserinfo['pmpopup']==1) {
      if ($noshutdownfunc) {
        $DB_site->query("UPDATE user SET pmpopup=2 WHERE userid=$touserinfo[userid]");
      } else {
        $shutdownqueries[]="UPDATE LOW_PRIORITY user SET pmpopup=2 WHERE userid=$touserinfo[userid]";
      }
    }
    if ($touserinfo['emailonpm']) {
      eval("\$emailmsg = \"".gettemplate("email_pmreceived",1,0)."\";");
      eval("\$emailsubject = \"".gettemplate("emailsubject_pmreceived",1,0)."\";");
	  vbmail($touserinfo['email'], $emailsubject, $emailmsg);
    }
  }

  eval("standardredirect(\"".gettemplate("redirect_pmthanks")."\",\"private.php?s=$session[sessionhash]\");");
} #end dosend

// ############################### start do stuff (move, etc) ###############################
if ($_POST['action']=="dostuff") {
  $what = '';
  if ($delete!="") {
    $what="delete";
  }
  if ($move!="") {
    $what="move";
  }
  if ($what=="mark") {
    // ************************
    // mark as (un)read
  }
  if ($what=="move") {
    if (is_array($privatemessage)) {
      while(list($key,$val)=each($privatemessage)) {
        $DB_site->query("UPDATE privatemessage SET folderid=".intval($folderid)." WHERE privatemessageid=".intval($key)." AND userid=$bbuserinfo[userid]");
      } //end while
    } else {
      eval("standarderror(\"".gettemplate("error_pmnoselected")."\");");
      exit;
    }
    eval("standardredirect(\"".gettemplate("redirect_pmmove")."\",\"private.php?s=$session[sessionhash]&amp;folderid=".intval($folderid)."\");");
  }
  if ($what=="delete") {
    if (is_array($privatemessage)) {
      while(list($key,$val)=each($privatemessage)) {
        $DB_site->query("DELETE FROM privatemessage WHERE privatemessageid=".intval($key)." AND userid=$bbuserinfo[userid]");
      } //end while
    } else {
      eval("standarderror(\"".gettemplate("error_pmnoselected")."\");");
      exit;
    }
    eval("standardredirect(\"".gettemplate("redirect_pmdelete")."\",\"private.php?s=$session[sessionhash]&amp;folderid=".intval($thisfolder)."\");");
  }

}

// ############################### start delete ###############################
if ($_POST['action']=="dodelete") {
  if (!$delete) {
    eval("standarderror(\"".gettemplate("error_pmnotchecked")."\");");
    exit;
  }

  $privatemessageid=verifyid("privatemessage",$privatemessageid);
  $DB_site->query("DELETE FROM privatemessage WHERE privatemessageid=$privatemessageid AND userid=$bbuserinfo[userid]");

  eval("standardredirect(\"".gettemplate("redirect_pmdelete")."\",\"private.php?s=$session[sessionhash]&amp;folderid=".intval($folderid)."\");");
} #end dodelete

// ############################### start edit folders ###############################
if ($action=="editfolders") {

  $highestnum = 0;
  $folderboxes = '';
  $newfolderboxes = '';
  $folder = array();

  if ($bbuserinfo['pmfolders']) {
    $allfolders = split("\n", trim($bbuserinfo['pmfolders']));
    $foldercount = 0;
    while (list($key,$val)=each($allfolders)) {
      $folder = split("\|\|\|", $val);
      $foldercount++;
      $highestnum = $folder[0];

      $folder['folderid']=$folder[0]+1;
      $folder['title']=$folder[1];
      eval("\$folderboxes .= \"".gettemplate("priv_showfolders_folderbit",1,0)."\";");
    }
  }

  $foldercount=0;
  while ($foldercount<3) {
    $foldercount++;

    $folder['folderid']=$foldercount+$highestnum+1;
    $folder['title']="";
    eval("\$newfolderboxes .= \"".gettemplate("priv_showfolders_folderbit",1,0)."\";");
  }
  $folderboxes .= "<INPUT TYPE=\"hidden\" name=\"highest\" value=\"".($highestnum+3)."\">";

  // generate navbar
  $navbits = '';
  $nav_url="private.php?s=$session[sessionhash]";
  $nav_title="Private Messages";
  eval("\$navbits .= \"".gettemplate("nav_linkon")."\";");
  $navbits.=gettemplate("nav_joiner",0);
  $nav_url="private.php?s=$session[sessionhash]&amp;folderid=$message[folderid]";
  $nav_title="Edit Folders";
  eval("\$navbits .= \"".gettemplate("nav_linkoff")."\";");
  eval("\$navbar = \"".gettemplate("navbar")."\";");

  eval("dooutput(\"".gettemplate("priv_showfolders")."\");");

} #end editfolders

// ############################### start update folders ###############################
if ($_POST['action']=="doeditfolders") {

  if (is_array($folderlist)) {
    $foldersql="";
    while (list($key,$val)=each($folderlist)) {
      if ($val) {
        $key = intval($key)-1;
        $val=str_replace("|||"," || | ",$val);
        $val=str_replace("\n","nl",$val);
        $val=htmlspecialchars($val);

        if ($foldersql) {
          $foldersql .= "\n$key|||$val";
        } else {
          $foldersql = "$key|||$val";
        }
      } else {
        $DB_site->query("UPDATE privatemessage SET folderid=0 WHERE folderid=".intval($key)." AND userid=$bbuserinfo[userid]");
      }
    }

    $DB_site->query("UPDATE user SET pmfolders='".vb_escape("$foldersql")."' WHERE userid=$bbuserinfo[userid]");
  }

  eval("standardredirect(\"".gettemplate("redirect_pmfoldersedited")."\",\"private.php?s=$session[sessionhash]\");");

} #end doeditfolders


?>