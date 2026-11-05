<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

require("./global.php");

if ($s!=$session['dbsessionhash']) {
  $newurl = replacesession($scriptpath);
  eval("standarderror(\"".gettemplate("error_invalidsession")."\");");
}

$permissions=getpermissions();
if (!$permissions['canthreadrate']) {
	show_nopermission();
}
$vote = intval($vote);
if ($vote < 1 or $vote > 5) {
  eval("standarderror(\"".gettemplate("error_invalidvote")."\");");
  exit;
}

$threadid = verifyid("thread",$threadid);
$threadinfo=getthreadinfo($threadid);
updateuserforum($threadinfo['forumid']);
if ($threadinfo['visible']==0 or $threadinfo['open']==0) {
  eval("standarderror(\"".gettemplate("error_threadrateclosed")."\");");
  exit;
}

if ($bbuserinfo['userid'] != 0) {
   if ($rating=$DB_site->query_first("SELECT vote, threadrateid
                                      FROM threadrate
                                      WHERE userid = $bbuserinfo[userid]
                                      AND threadid = '$threadid'")) {
      if ($votechange) {
        if ($vote != $rating['vote']) {
           $voteupdate = $vote - $rating['vote'];
           $DB_site->query("UPDATE threadrate
                            SET vote = '$vote'
                            WHERE threadrateid = $rating[threadrateid]");
           $DB_site->query("UPDATE thread
                            SET votetotal = votetotal + $voteupdate
                            WHERE threadid = '$threadid'");
        }
        eval("standardredirect(\"".gettemplate("redirect_threadrate_update")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      } else {
        eval("standarderror(\"".gettemplate("error_threadratevoted")."\");");
        exit;
      }
   } else {
      $DB_site->query("INSERT INTO threadrate (threadid,userid,vote)
                       VALUES ('$threadid','$bbuserinfo[userid]','$vote')");
      $DB_site->query("UPDATE thread
                       SET votetotal = votetotal + $vote, votenum = votenum + 1
                       WHERE threadid = '$threadid'");
      eval("standardredirect(\"".gettemplate("redirect_threadrate_add")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
      exit;
   }
} else {
   // Check for entry in Database for this Ip Addr/Threadid
   // Check for cookie on user's computer for this threadid
   if ($rating=$DB_site->query_first("SELECT vote, threadrateid
                                      FROM threadrate
                                      WHERE ipaddress = '".vb_escape($ipaddress)."'
                                      AND threadid = '$threadid'")) {
      if ($votechange) {
        if ($vote != $rating['vote']) {
           $voteupdate = $vote - $rating['vote'];
           $DB_site->query("UPDATE threadrate
                            SET vote = '$vote'
                            WHERE threadrateid = $rating[threadrateid]");
           $DB_site->query("UPDATE thread
                            SET votetotal = votetotal + $voteupdate
                            WHERE threadid = '$threadid'");
        }
        eval("standardredirect(\"".gettemplate("redirect_threadrate_update")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      } else {
        eval("standarderror(\"".gettemplate("error_threadratevoted")."\");");
        exit;
      }
   } elseif (get_bbarraycookie('threadrate', $threadid)) {
      eval("standarderror(\"".gettemplate("error_threadratevoted")."\");");
      exit;
   } else {
      $DB_site->query("INSERT INTO threadrate (threadid,vote,ipaddress)
                       VALUES ('$threadid','$vote','".vb_escape($ipaddress)."')");
      $DB_site->query("UPDATE thread
                       SET votetotal = votetotal + $vote, votenum = votenum + 1
                       WHERE threadid = '$threadid'");
      set_bbarraycookie('threadrate', $threadid, $vote, 1);
      eval("standardredirect(\"".gettemplate("redirect_threadrate_add")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
	  exit;
   }
}


?>