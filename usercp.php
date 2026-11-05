<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "usercp_buddymasspmlink,privfolder_denyreceipt,usercp_pmdeletedbit,usercp_buddy,usercp_pmmessagebit,usercp_messages,usercp_nomessages,forumhome_lastpostby,usercp_forumbit,usercp_forums,usercp_noforums,forumdisplay_multipagenav_more,forumdisplay_multipagenav_pagenumber,forumdisplay_multipagenav,forumdisplay_gotonew,subscribe_threadbit,subscribe_threads,subscribe_nothreads,usercpnav,usercp";

require("./global.php");

if ($bbuserinfo['userid']==0) {
  show_nopermission();
}

// main page:

// ############################### start buddy list ###############################

if ($permissions['maxbuddypm']) {
  eval("\$buddypmlink = \"".gettemplate("usercp_buddymasspmlink")."\";");
} else {
  $buddypmlink = '';
}
$datecut = time() - $cookietimeout;
$buddyuserssql=str_replace(" ","' OR user.userid='",$bbuserinfo['buddylist']);
$sql="SELECT userid,username,invisible,lastactivity,lastvisit
      FROM user
      WHERE (user.userid='$buddyuserssql')
      ORDER BY username";
$buddys=$DB_site->query($sql);

$onlineusers="";
$offlineusers="";
$doneuser = array();
while ($buddy=$DB_site->fetch_array($buddys)) {
	if ($doneuser[$buddy['userid']]) {
		continue;
	}
	$doneuser[$buddy['userid']]=1;

  if ($buddy['lastactivity'] > $datecut and (!$buddy['invisible'] or $bbuserinfo['usergroupid'] == 6) and $buddy['lastvisit'] != $buddy['lastactivity']) {
    $onoff="on";
  } else {
    $onoff="off";
  }

  eval("\$var = \"".gettemplate("usercp_buddy")."\";");

  if ($onoff=="on") {
    $onlineusers.=$var;
  } else {
    $offlineusers.=$var;
  }
}

// ############################### start private messages ###############################
function makefolderjump() {
  global $bbuserinfo;

  //get all folder names (for dropdown)
  //reference with $foldernames[#] .

	$folderjump = '';
  if ($bbuserinfo['pmfolders']) {
    $allfolders = split("\n", trim($bbuserinfo['pmfolders']));
    while (list($key,$val)=each($allfolders)) {
      $thisfolder = split("\|\|\|", $val);
      $folderjump .= "<OPTION value=\"$thisfolder[0]\">$thisfolder[1]</option>";
    }
  }

  return $folderjump;

} #end makefolderjump

//get ignorelist info
//generates a hash, in the form of $ignore[(userid)]
//run checks to it by seeing if $ignore[###] returns anything
//if so, then user is ignored
$folderjump = makefolderjump();
$foldername = $inboxname;

$sender = "Sender";

$foldernames = array();
if ($bbuserinfo['pmfolders']) {
  $allfolders = split("\n", trim($bbuserinfo['pmfolders']));
  while (list($key,$val)=each($allfolders)) {
    $thisfolder = split("\|\|\|", $val);
    $foldernames[$thisfolder[0]] = $thisfolder[1];
  }
}

if (trim($bbuserinfo['ignorelist'])!="") {
  $ignoreusers="AND privatemessage.fromuserid<>".implode(" AND privatemessage.fromuserid<>",explode(" ",trim($bbuserinfo['ignorelist'])));
} else {
  $ignoreusers="";
}

$messagedone=0;
$privmsgsbit = '';
$privatemessages = '';
//different retrival methods based on which table
$messages = $DB_site->query("SELECT privatemessage.*,touser.username AS tousername,fromuser.username AS fromusername,icon.title AS icontitle,icon.iconpath FROM privatemessage,user AS touser,user AS fromuser LEFT JOIN icon ON icon.iconid=privatemessage.iconid WHERE privatemessage.userid='$bbuserinfo[userid]' AND folderid=0 AND touser.userid=privatemessage.touserid AND fromuser.userid=privatemessage.fromuserid AND messageread=0 $ignoreusers ORDER BY dateline DESC");
while ($privatemessage=$DB_site->fetch_array($messages)) {

  // get the more useful of the to/from field
  $privatemessage['displayuserid']=$privatemessage['fromuserid'];
  $privatemessage['displayusername']=$privatemessage['fromusername'];

  $privatemessage['datesent']=vbdate($dateformat,$privatemessage['dateline']);
  $privatemessage['timesent']=vbdate($timeformat,$privatemessage['dateline']);

  //date/time comparisons for new vs. old messages
  //additionally, mark folder if replied to
  $privatemessage['folder'] = "{imagesfolder}/newpm.gif";

  //get icon for this message
  if ($privatemessage['iconid']) {
    $privatemessage['icon']="<img src=\"$privatemessage[iconpath]\" alt=\"$privatemessage[icontitle]\" border=\"0\">";
  } else {
    $privatemessage['icon']="&nbsp;";
  }

  //run it through the template
  if ($privatemessage['deleteprompt']) {
  	eval("\$privmsgsbit .= \"".gettemplate("usercp_pmdeletedbit")."\";");
  } else {
	if ($permissions['cantrackpm'] && $permissions['candenypmreceipts'] && $privatemessage['receipt']==1) {
		eval("\$privatemessage[denyreceipt] = \"".gettemplate("privfolder_denyreceipt")."\";");
	}
    eval("\$privmsgsbit .= \"".gettemplate("usercp_pmmessagebit")."\";");
  }
  $messagedone=1;
} //end while

if ($messagedone) {
  eval("\$privatemessages = \"".gettemplate("usercp_messages")."\";");
} else {
  eval("\$privatemessages = \"".gettemplate("usercp_nomessages")."\";");
}



// ############################### start subscribed forums ###############################
$forums=$DB_site->query("SELECT * FROM forum,subscribeforum WHERE subscribeforum.forumid=forum.forumid AND userid=$bbuserinfo[userid] ORDER BY title");
$totalforums=$DB_site->num_rows($forums);

$forumbits = '';
if ($totalforums>0) {
  while ($forum=$DB_site->fetch_array($forums)) {

    $forumperms=getpermissions($forum['forumid']);
    if (!$hideprivateforums) {
      $forumperms['canview']=1;
    }
    if ($forumperms['canview']) {
      $forumshown=1;

      // do light bulb
      if ($bbuserinfo['lastvisitdate']=="Never") {
        $forum['onoff']="on";
      } else {
        if (($fview = get_bbarraycookie('forumview', $forum['forumid'])) > $bbuserinfo['lastvisit']) {
          $userlastvisit=$fview;
        } else {
          $userlastvisit=$bbuserinfo['lastvisit'];
        }
        if ($userlastvisit<$forum['lastpost']) {
          $forum['onoff']="on";
        } else {
          $forum['onoff']="off";
        }
      }

      if ((!$forumperms['canpostnew'] and $showlocks) or $forum['allowposting']==0) {
        $forum['onoff'].="lock";
      }

      // prepare template vars
      if (!$showforumdescription) {
        $forum['description']="";
      }

      // dates
      if ($forum['lastpost']>0) {
        $forum['lastpostdate']=vbdate($dateformat,$forum['lastpost']);
        $forum['lastposttime']=vbdate($timeformat,$forum['lastpost']);
        eval("\$forum[lastpostinfo] = \"".gettemplate("forumhome_lastpostby")."\";");
      } else {
        $forum['lastpostinfo']="Never";
      }

      eval("\$forumbits .= \"".gettemplate("usercp_forumbit")."\";");

    } // if can view
  } // while forums

  eval("\$forumlist = \"".gettemplate("usercp_forums")."\";");

} else {
  eval("\$forumlist = \"".gettemplate("usercp_noforums")."\";");
}

// ############################### start new subscribed to threads ###############################
if (!$bbuserinfo['lastvisit']) {
  $thelastvisit = time();
} else {
  $thelastvisit = $bbuserinfo['lastvisit'];
}
$daysprune=intval((time()-$thelastvisit)/86400);

$dotuserid = "";
$dotjoin = "";
if ($showdots and $bbuserinfo['userid'] >= 1) {
  $dotuserid = "DISTINCT post.userid,";
  $dotjoin = "LEFT JOIN post ON (thread.threadid = post.threadid AND post.userid = '$bbuserinfo[userid]' AND post.visible = 1)";
}

$getthreadids=$DB_site->query("
SELECT thread.threadid
	FROM thread,subscribethread
	WHERE subscribethread.threadid=thread.threadid
	AND subscribethread.userid='$bbuserinfo[userid]'
	AND thread.visible=1
	AND lastpost>$bbuserinfo[lastvisit]
");
$totalthreads=$DB_site->num_rows($getthreadids);

$threadids="thread.threadid IN (0";
while ($thread=$DB_site->fetch_array($getthreadids)) {
  $threadids.=",".$thread['threadid'];
}
$threadids.=")";

$threads=$DB_site->query("SELECT $dotuserid icon.title as icontitle,icon.iconpath,thread.threadid,thread.title,
	lastpost,forumid,pollid,open,replycount,postusername,postuserid,lastposter,thread.dateline,views,
	thread.iconid,notes,thread.visible
	FROM thread
	LEFT JOIN icon ON (icon.iconid = thread.iconid)
	$dotjoin
	WHERE $threadids
	ORDER BY lastpost DESC
");

$pagenumbers = '';
$threadbits = '';
if ($totalthreads>0) {
  // check to see if there are any threads to display. If there are, do so, otherwise, show message

  if (($bbuserinfo['maxposts'] != -1) and ($bbuserinfo['maxposts'] != 0)) {
		$maxposts = $bbuserinfo['maxposts'];
	}

  $threadbits = '';
  while ($thread=$DB_site->fetch_array($threads)) {

    if (($thread['replycount']+1)>$maxposts and $linktopages) {

      $totalpages=($thread['replycount']+1)/$maxposts;
      if ($totalpages!=intval($totalpages)) {
        $totalpages=intval($totalpages)+1;
      }

      $acurpage=0;
      $pagenumbers="";
      while ($acurpage++<$totalpages) {
        if ($acurpage==$maxmultipage) {
          eval("\$pagenumbers .= \"".gettemplate("forumdisplay_multipagenav_more")."\";");
          break;
        } else {
          eval("\$pagenumbers .= \"".gettemplate("forumdisplay_multipagenav_pagenumber")."\";");
        }
      }
      eval("\$thread[pagenav] = \"".gettemplate("forumdisplay_multipagenav")."\";");
    } else {
      $thread['pagenav']="";
    }

    $thread['icon']="&nbsp;";
    if ($thread['iconid']!=0) {
      $thread['icon']="<img src=\"$thread[iconpath]\" alt=\"$thread[icontitle]\" width=\"15\" height=\"15\" border=\"0\">";
    }

    if ($thread['pollid']!=0) {
      $thread['icon']="<img src=\"{imagesfolder}/poll.gif\" alt=\"Poll\" width=\"15\" height=\"15\" border=\"0\">";
    }

    if ($wordwrap!=0) {
      $thread['title']=dowordwrap($thread['title']);
    }

    $replies=$thread['replycount'];
    $views=$thread['views'];
    $thread['lastreplydate']=vbdate($dateformat,$thread['lastpost']);
    $thread['lastreplytime']=vbdate($timeformat,$thread['lastpost']);

    $thread['gotonew']="";

    $thread['newoldhot']="folder";
    if (!$thread['open']) {
      $thread['newoldhot']="lock".$thread['newoldhot'];
    }
    if ($thread['replycount']>=$hotnumberposts or $thread['views']>=$hotnumberviews and $usehotthreads) {
      $thread['newoldhot']="hot".$thread['newoldhot'];
    }
    if ($bbuserinfo['lastvisitdate']=="Never") {
      $thread['newoldhot']="new".$thread['newoldhot'];
    } elseif ($thread['lastpost']>$bbuserinfo['lastvisit']) {
      if (get_bbarraycookie('threadview', $thread['threadid']) <$thread['lastpost']) {
        $thread['newoldhot']="new".$thread['newoldhot'];
      }
      eval("\$thread[gotonew] = \"".gettemplate("forumdisplay_gotonew")."\";");
    }
    if ($showdots and $bbuserinfo['userid'] >= 1 and $bbuserinfo['userid'] == $thread['userid']) {
        $thread['newoldhot'] = "dot_" . $thread['newoldhot'];
    }

    eval("\$threadbits .= \"".gettemplate("subscribe_threadbit")."\";");

  }

  eval("\$threadslist = \"".gettemplate("subscribe_threads")."\";");
} else {
  eval("\$threadslist = \"".gettemplate("subscribe_nothreads")."\";");
}

// draw cp nav bar
$cpnav = array();
$cpmenu = array();
$cpnav[1]="{firstaltcolor}";
	$cpmenu[1]="class=\"fjsel\" selected";
$cpnav[2]="{secondaltcolor}";
$cpnav[3]="{secondaltcolor}";
$cpnav[4]="{secondaltcolor}";
$cpnav[5]="{secondaltcolor}";
$cpnav[6]="{secondaltcolor}";
$cpnav[7]="{secondaltcolor}";
eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

$frmjmpsel['usercp'] = "selected";
makeforumjump();

eval("dooutput(\"".gettemplate("usercp")."\");");

?>