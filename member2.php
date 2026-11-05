<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

if ($_GET['HTTP_POST_VARS']['action'] == $_POST['action']) {
  unset($_POST['action']);
}
$_POST['action'] = trim($_POST['action']);
if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

$templatesused = '';
$cpnav = array();
$cpmenu = array();

// buddy list
// ignore list
// ############################### start add to list ###############################
if ($action=="addlist") {
  $templatesused = "error_cantlistself,error_listignoreuser,redirect_addlist";
  include("./global.php");
  //check usergroup of user to see if they can use Profile

  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  if ($bbuserinfo['userid'] == $userid) {
    eval("standarderror(\"".gettemplate("error_cantlistself")."\");");
    exit;
  }

  if ($userlist!="buddy") {
    $userlist="ignore";
  }
  $var=$userlist."list";

  $userid=verifyid("user",$userid);
  $userinfo=getuserinfo($userid);

  if ($var=='ignorelist' and !$ignoremods and $bbuserinfo['usergroupid'] != 6 and ismoderator(0,"",$userid)) {
    $username=$userinfo['username'];
    eval("standarderror(\"".gettemplate("error_listignoreuser")."\");");
    exit;
  }

  $splitlist=explode(" ",$bbuserinfo[$var]);

  $found=0;
  while (list($key,$val)=each($splitlist)) {
    if ($val==$userid) {
      $found=1;
    }
  }
  if (!$found) {
    $bbuserinfo[$var].=" $userid";
  }
  $bbuserinfo[$var]=trim($bbuserinfo[$var]);

  $DB_site->query("UPDATE user SET $var='".vb_escape($bbuserinfo[$var])."' WHERE userid=$bbuserinfo[userid]");
  $url = str_replace("\"", "", $url);
  eval("standardredirect(\"".gettemplate("redirect_addlist")."\",\"\$url\");");

}

// ############################### start remove from ###############################
if ($action=="removelist") {
  $templatesused = "redirect_removelist";
  include("./global.php");
  //check usergroup of user to see if they can use Profile
  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  if ($userlist!="buddy") {
    $userlist="ignore";
  }
  $var=$userlist."list";

  $userid=verifyid("user",$userid);
  $userinfo=getuserinfo($userid);

  $splitlist=explode(" ",$bbuserinfo[$var]);

  while (list($key,$val)=each($splitlist)) {
    if ($val==$userid) {
      unset($splitlist[$key]);
    }
  }

  $bbuserinfo[$var]=implode(" ",$splitlist);
  $bbuserinfo[$var]=trim($bbuserinfo[$var]);

  $DB_site->query("UPDATE user SET $var='".vb_escape($bbuserinfo[$var])."' WHERE userid=$bbuserinfo[userid]");
  $url = str_replace("\"", "", $url);
  eval("standardredirect(\"".gettemplate("redirect_removelist")."\",\"\$url\");");

}

// ############################### start view list ###############################
if ($action=="viewlist") {

  $templatesused = "listbit,usercpnav,listedit";

  include("./global.php");
  //check usergroup of user to see if they can use Profile
  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  if ($userlist!="buddy") {
    $userlist="ignore";
    $listtype = "Ignore";
  } else {
    $listtype = "Buddy";
  }
  $var=$userlist."list";

  $splitlist=explode(" ",$bbuserinfo[$var]);
  $listbits = '';

  while (list($key,$val)=each($splitlist)) {
    if ($val!="") {
      if ($userinfo=getuserinfo($val)) {
        eval("\$listbits .= \"".gettemplate("listbit")."\";");
      }
    }
  }

  // draw cp nav bar
  $cpnav[1]="{secondaltcolor}";
  $cpnav[2]="{secondaltcolor}";
  $cpnav[3]="{secondaltcolor}";
  $cpnav[4]="{secondaltcolor}";
  if ($userlist!="buddy") {
    $cpnav[5]="{secondaltcolor}";
    $cpnav[6]="{firstaltcolor}";
	$cpmenu[6]="class=\"fjsel\" selected";
  } else {
    $cpnav[5]="{firstaltcolor}";
	$cpmenu[5]="class=\"fjsel\" selected";
    $cpnav[6]="{secondaltcolor}";
  }
  $cpnav[7]="{secondaltcolor}";
  eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

  eval("dooutput(\"".gettemplate("listedit")."\");");

}

// ############################### start update list ###############################
if ($_POST['action']=="updatelist") {
  $templatesused = "redirect_updatelist";
  include("./global.php");
  //check usergroup of user to see if they can use Profile
  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  if ($userlist!="buddy") {
    $userlist="ignore";
  }
  $var=$userlist."list";

  $listarr = array();

  while (list($key,$val)=each($listbits)) {
    if (get_magic_quotes_gpc())
    {
      $val = stripslashes($val);
    }
    if ($userid=$DB_site->query_first("SELECT usergroupid, user.userid, moderator.userid as moduserid
                                       FROM user
                                       LEFT JOIN moderator ON (user.userid = moderator.userid)
                                       WHERE username='".vb_escape(htmlspecialchars($val))."'")) {
      if ($var=='ignorelist' and !$ignoremods and ($userid['usergroupid']==6 or $userid['usergroupid']==5 or $userid['moduserid']) and $bbuserinfo['usergroupid'] != 6) {
        $username=htmlspecialchars($val);
        eval("standarderror(\"".gettemplate("error_listignoreuser")."\");");
        exit;
      } else if ($bbuserinfo['userid'] == $userid['userid']) {
        eval("standarderror(\"".gettemplate("error_cantlistself")."\");");
        exit;
      } else {
        $listarr["$userid[userid]"] = 1;
      }
    } else {
      if (trim($val)!="") {
        $username=htmlspecialchars($val);
        eval("standarderror(\"".gettemplate("error_listbaduser")."\");");
        exit;
      }
    }
  }

  $listids = '';
  while (list($key, $val) = each($listarr))
  {
    $listids .= " $key";
  }
  $listids = trim($listids);

  $DB_site->query("UPDATE user SET $var='".vb_escape($listids)."' WHERE userid=$bbuserinfo[userid]");

  eval("standardredirect(\"".gettemplate("redirect_updatelist")."\",\"usercp.php?s=$session[sessionhash]\");");

}

// ############################### start add subscription ###############################
if ($action=="addsubscription") {
  $templatesused = "redirect_subsadd";
  include("./global.php");
  //check usergroup of user to see if they can use Profile
  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  if (isset($threadid)) {
    $type="thread";
    $id=$threadid;
    $id=verifyid($type,$id);
    $threadinfo=getthreadinfo($id);

    $permissions=getpermissions($threadinfo['forumid']);
    if (!$permissions['canview']) {
      show_nopermission();
    }

  } else {
    $type="forum";
    $id=$forumid;

    $permissions=getpermissions($forumid);
    if (!$permissions['canview']) {
      show_nopermission();
    }

  }

  $table="subscribe$type";
  $tableid=$table."id";
  $typeid=$type."id";

  if (!$checkid=$DB_site->query_first("SELECT $tableid FROM $table WHERE userid=$bbuserinfo[userid] AND $typeid=".intval($id))) {
    $id=verifyid($type,$id);
    $DB_site->query("INSERT INTO $table ($tableid,userid,$typeid) VALUES (NULL,$bbuserinfo[userid],".intval($id).")");
  }

  $url = str_replace("\"", "", $url);
  eval("standardredirect(\"".gettemplate("redirect_subsadd")."\",\"\$url\");");
}

// ############################### start remove subscription ###############################
if ($action=="removesubscription" or $action=="usub") {
  $templatesused = "redirect_subsremoveall,redirect_subsremove";
  include("./global.php");
  //check usergroup of user to see if they can use Profile
  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  if (!$url) {
    $url = "usercp.php?s=$session[sessionhash]";
  }

  if ($type=="allthread") {
    $DB_site->query("DELETE FROM subscribethread WHERE userid=$bbuserinfo[userid]");

    $type="threads";
    $url = str_replace("\"", "", $url);
    eval("standardredirect(\"".gettemplate("redirect_subsremoveall")."\",\"\$url\");");
  } elseif ($type=="allforum") {
    $DB_site->query("DELETE FROM subscribeforum WHERE userid=$bbuserinfo[userid]");

    $type="forums";
    $url = str_replace("\"", "", $url);
    eval("standardredirect(\"".gettemplate("redirect_subsremoveall")."\",\"\$url\");");
  } else {
    if (isset($threadid)) {
      $type="thread";
      $id=$threadid;
    } else {
      $type="forum";
      $id=$forumid;
    }

    $table="subscribe$type";
    $tableid=$table."id";
    $typeid=$type."id";
    $id=verifyid($type,$id);

    $DB_site->query("DELETE FROM $table WHERE userid=$bbuserinfo[userid] AND $typeid='$id'");

    $url = str_replace("\"", "", $url);
    eval("standardredirect(\"".gettemplate("redirect_subsremove")."\",\"\$url\");");
  }
}


// ############################### start view threads ###############################
if ($action=="viewsubscription") {

  $templatesused = "forumdisplay_multipagenav_more,forumdisplay_multipagenav_pagenumber,forumdisplay_multipagenav,forumdisplay_gotonew,subscribe_threadbit,subscribe_threads,subscribe_nothreads,usercpnav,subscribe";

  include("./global.php");
  //check usergroup of user to see if they can use Profile
  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  makeforumjump();

  // set default value for $daysprune
  if (empty($daysprune) OR $daysprune < 1) {
  	$daysprune = 30;
  }

  // look at thread limiting options
  $datecut='';
  unset ($daysprunesel);
  if ($daysprune!=1000) {
    $datecut="AND lastpost >= ".(time() - ($daysprune*86400));
  }
  $daysprunesel[$daysprune]="selected";

  if (trim($bbuserinfo['ignorelist'])!="") {
    $ignoreusers="AND thread.postuserid<>".implode(" AND thread.postuserid<>",explode(" ",trim($bbuserinfo['ignorelist'])));
  } else {
    $ignoreusers="";
  }

  $threadscount=$DB_site->query_first("SELECT COUNT(*) AS threads
                                       FROM thread,subscribethread
                                       WHERE subscribethread.threadid=thread.threadid
									     AND subscribethread.userid='$bbuserinfo[userid]'
		                                 AND thread.visible=1 $datecut $ignoreusers");
  $totalallthreads=$threadscount['threads'];

  sanitize_pageresults($totalallthreads, $pagenumber, $perpage, 200, $maxthreads);

  // display threads
  $limitlower=($pagenumber-1)*$perpage+1;
  $limitupper=($pagenumber)*$perpage;

  if ($limitupper>$totalallthreads) {
    $limitupper=$totalallthreads;
    if ($limitlower>$totalallthreads) {
      $limitlower=$totalallthreads-$perpage;
    }
  }
  if ($limitlower<=0) {
    $limitlower=1;
  }

  if ($showdots and $bbuserinfo['userid'] >= 1) {
    $dotuserid = "DISTINCT post.userid,";
    $dotjoin = "LEFT JOIN post ON (thread.threadid = post.threadid AND post.userid = '$bbuserinfo[userid]' AND post.visible = 1)";
  } else {
    $dotuserid = "";
    $dojoin = "";
  }

  $getthreadids=$DB_site->query("SELECT thread.threadid
		FROM thread,subscribethread
		WHERE subscribethread.threadid=thread.threadid
		  AND subscribethread.userid='$bbuserinfo[userid]'
	 	  AND thread.visible=1 $datecut $ignoreusers
	 	ORDER BY lastpost DESC
        LIMIT ".($limitlower-1).",$perpage
	");
  $totalthreads=$DB_site->num_rows($getthreadids);

  if ($totalthreads>0) {
   // check to see if there are any threads to display. If there are, do so, otherwise, show message

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
		ORDER BY lastpost DESC");

    $pagenumbers = '';
    $threadbits = '';
    while ($thread=$DB_site->fetch_array($threads)) {
      if (($bbuserinfo['maxposts'] != -1) and ($bbuserinfo['maxposts'] != 0))
      {   $maxposts = $bbuserinfo['maxposts'];  }
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
      if ($thread['iconid']!=0)
      {
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
          if (get_bbarraycookie('threadview', $thread['threadid']) < $thread['lastpost']) {
         $thread['newoldhot']="new".$thread['newoldhot'];
          }
          eval("\$thread[gotonew] = \"".gettemplate("forumdisplay_gotonew")."\";");
      }
      if ($showdots and $bbuserinfo['userid'] >= 1 and $bbuserinfo['userid'] == $thread['userid']) {
         $thread['newoldhot'] = "dot_" . $thread['newoldhot'];
      }

      eval("\$threadbits .= \"".gettemplate("subscribe_threadbit")."\";");

    }

    $DB_site->free_result($threads);

	$pagenav = getpagenav($totalallthreads,"member2.php?s=$session[sessionhash]&amp;action=viewsubscription&amp;daysprune=$daysprune&amp;perpage=$perpage");

    eval("\$threadslist = \"".gettemplate("subscribe_threads")."\";");
  } else {
    eval("\$threadslist = \"".gettemplate("subscribe_nothreads")."\";");
  }

  // draw cp nav bar
  $cpnav[1]="{secondaltcolor}";
  $cpnav[2]="{secondaltcolor}";
  $cpnav[3]="{secondaltcolor}";
  $cpnav[4]="{secondaltcolor}";
  $cpnav[5]="{secondaltcolor}";
  $cpnav[6]="{secondaltcolor}";
  $cpnav[7]="{secondaltcolor}";
	$cpmenu[8]="class=\"fjsel\" selected";

  eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

  eval("dooutput(\"".gettemplate("subscribe")."\");");
}

?>