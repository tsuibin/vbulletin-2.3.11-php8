<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

require("./global.php");

// ############################### start do open / close thread ###############################
if ($_POST['action'] == 'openclosethread' OR ($action == 'openclosethread' AND $s == $session['dbsessionhash'])) {

  $threadid=verifyid("thread",$threadid);

  $threadinfo=getthreadinfo($threadid);

  if (!ismoderator($threadinfo['forumid'],"canopenclose")) {
    $permissions=getpermissions($threadinfo['forumid']);
    if (!$permissions['canview'] or !$permissions['canopenclose']) {
      show_nopermission();
    } else {
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  updateuserforum($threadinfo['forumid']);

  if ($threadinfo['open']) {
    $threadinfo['open']=0;
    $action="closed";
  } else {
    $threadinfo['open']=1;
    $action="opened";
  }
  $threadinfo['notes'] = "Thread $action by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";
  $DB_site->query("UPDATE thread SET open=$threadinfo[open],notes='".vb_escape($threadinfo['notes'])."' WHERE threadid='$threadid'");

  eval("standardredirect(\"".gettemplate("redirect_openclose")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");

}

// ############################### start delete thread ###############################
if ($action=="deletethread") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"candeleteposts")) {
    $permissions=getpermissions($threadinfo['forumid']);
    if (!$permissions['canview'] or !$permissions['candelete']) {
      show_nopermission();
    } else {
      if (!$threadinfo['open']) {
        eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      }
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  updateuserforum($threadinfo['forumid']);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

  eval("dooutput(\"".gettemplate("threads_deletethread")."\");");

}

// ############################### start do delete thread ###############################
if ($_POST['action']=="dodeletethread") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);
  $foruminfo=getforuminfo($threadinfo['forumid']);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"candeleteposts")) {
    $permissions=getpermissions($threadinfo['forumid']);
    if (!$permissions['canview'] or !$permissions['candelete']) {
      show_nopermission();
    } else {
      if (!$threadinfo['open']) {
        eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      }
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  updateuserforum($threadinfo['forumid']);

  deletethread($threadid,$foruminfo['countposts']);

  updateforumcount($threadinfo['forumid']);

  eval("standardredirect(\"".gettemplate("redirect_deletethread")."\",\"forumdisplay.php?s=$session[sessionhash]&amp;forumid=$threadinfo[forumid]\");");

}

// ############################### start delete posts ###############################
if ($action=="deleteposts") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"candeleteposts")) {
    show_nopermission();
  }

  // ensure thread notes are run through htmlspecialchars
  $notes = &$threadinfo['notes'];
  $threadinfo['notes'] = htmlspecialchars($threadinfo['notes']);

  updateuserforum($threadinfo['forumid']);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

  $posts=$DB_site->query("SELECT post.* FROM post WHERE post.threadid='$threadid' ORDER BY dateline");
  $counter=0;
  $postbits = '';
  while ($post=$DB_site->fetch_array($posts)) {
    if (++$counter%2==0) {
      $post['backcolor']="{firstaltcolor}";
	  $post['bgclass'] = "alt1";
    } else {
      $post['backcolor']="{secondaltcolor}";
	  $post['bgclass'] = "alt2";
    }
    $post['postdate']=vbdate($dateformat,$post['dateline']);
    $post['posttime']=vbdate($timeformat,$post['dateline']);

    // cut page text short if too long
    if (strlen($post['pagetext'])>100) {
      $spacepos=strpos($post['pagetext']," ",100);
      if ($spacepos!=0) {
        $post['pagetext']=substr($post['pagetext'],0,$spacepos)."...";
      }
    }
    $post['pagetext']=nl2br(htmlspecialchars($post['pagetext']));

    eval("\$postbits .= \"".gettemplate("threads_deletepostsbit")."\";");
  }

  eval("dooutput(\"".gettemplate("threads_deleteposts")."\");");

}

// ############################### start do delete posts ###############################
if ($_POST['action']=="dodeleteposts") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);
  $foruminfo=getforuminfo($threadinfo['forumid']);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"candeleteposts")) {
    show_nopermission();
  }

  updateuserforum($threadinfo['forumid']);

  $deletethread=1;
  // decrement users post counts
  $posts=$DB_site->query("SELECT postid FROM post WHERE threadid='$threadid'");
  while ($post=$DB_site->fetch_array($posts)) {
    if ($deletepost[$post['postid']]=="yes") {
      deletepost($post['postid'],$foruminfo['countposts']);
    } else {
      $deletethread=0;
    }
  }

  // update thread notes
  $threadinfo['notes'] = "Thread had posts deleted by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";
  $DB_site->query("UPDATE thread SET notes='".vb_escape($threadinfo['notes'])."' WHERE threadid=$threadinfo[threadid]");

  if ($deletethread) {
    deletethread($threadid,$foruminfo['countposts']);
  } else {
    updatethreadcount($threadid);
  }
  updateforumcount($threadinfo['forumid']);

  if ($deletethread) {
    eval("standardredirect(\"".gettemplate("redirect_deletethread")."\",\"forumdisplay.php?s=$session[sessionhash]&amp;forumid=$threadinfo[forumid]\");");
  } else {
    eval("standardredirect(\"".gettemplate("redirect_deleteposts")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
  }

}

// ############################### start retrieve ip ###############################
if ($action=="getip") {

  $postid=verifyid("post",$postid);
  $postinfo=getpostinfo($postid);
  $threadinfo=getthreadinfo($postinfo['threadid']);
  $foruminfo=getthreadinfo($threadinfo['forumid']);

  // check moderator permissions for getting ip
  if (!ismoderator($threadinfo['forumid'],"canviewips")) {
    show_nopermission();
  }

  $postinfo['hostaddress']=gethostbyaddr($postinfo['ipaddress']);
  updateuserforum($threadinfo['forumid']);

  eval("standarderror(\"".gettemplate("threads_displayip")."\");");
}

// ###################### Start makeforumjumpbits #######################
function getmoveforums($parentid=-1,$addbox=1,$prependchars="") {
  // this generates the move to.. box for move/copy
  global $DB_site,$optionselected,$jumpforumid,$jumpforumtitle,$jumpforumbits;
  global $hideprivateforums,$bbuserinfo,$session, $useforumjump;

  $olduseforumjump=$useforumjump;
  $useforumjump=1;

  if ($addbox) {
    $jumpforumbits="";
  }

  $forums=$DB_site->query("SELECT forumid,title,displayorder,allowposting,parentid FROM forum WHERE displayorder<>0 AND parentid=".intval($parentid)." ORDER BY displayorder");

  while ($forum=$DB_site->fetch_array($forums)) {

    $getperms=getpermissions($forum['forumid']);
    if ($getperms['canview']) {

      $jumpforumid=$forum['forumid'];
      $jumpforumtitle=$prependchars." $forum[title]".iif($forum['allowposting'],""," (no posting)");

      $optionselected="";
      eval("\$jumpforumbits .= \"".gettemplate("forumjumpbit")."\";");

      makeforumjump($forum['forumid'],0,$prependchars."--");

    } // end if $getperms...
  } // end while

  $useforumjump=$olduseforumjump;

  return $jumpforumbits;
}

// ############################### start move thread ###############################
if ($action=="move") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // check forum permissions for this forum
  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
    $permissions=getpermissions($forumid);
    if (!$permissions['canview'] or !$permissions['canmove']) {
      show_nopermission();
    } else {
      if (!$threadinfo['open']) {
        eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      }
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  // ensure thread notes are run through htmlspecialchars
  $notes = &$threadinfo['notes'];
  $threadinfo['notes'] = htmlspecialchars($threadinfo['notes']);

  updateuserforum($threadinfo['forumid']);

  $moveforumbits=getmoveforums();

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

  eval("dooutput(\"".gettemplate("threads_move")."\");");

}

// ############################### start do move thread ###############################
if ($_POST['action']=="domove") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  updateuserforum($threadinfo['forumid']);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // check whether dest can contain posts
  $forumid=verifyid("forum",$forumid);
  $foruminfo=getforuminfo($forumid);
  if (!$foruminfo['cancontainthreads']) {
    eval("standarderror(\"".gettemplate("error_moveillegalforum")."\");");
    exit;
  }

  // check source forum permissions
  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
    $permissions=getpermissions($threadinfo['forumid']);
    if (!$permissions['canview'] or !$permissions['canmove']) {
      show_nopermission();
    } else {
      if (!$threadinfo['open']) {
        eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      }
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  // check destination forum permissions
  $permissions=getpermissions($forumid);
  if (!$permissions['canview']) {
    show_nopermission();
  }

  // check to see if this thread is being returned to a forum it's already been in
  // if a redirect exists already in the destination forum, remove it
  if ($checkprevious=$DB_site->query_first("SELECT threadid FROM thread WHERE forumid='$forumid' AND open='10' AND pollid='$threadid'")) {
  	$DB_site->query("DELETE FROM thread WHERE threadid='$checkprevious[threadid]'");
  }

  // check to see if this thread is being moved to the same forum it's already in but allow copying to the same forum
  $checkforum=$DB_site->query_first("SELECT forumid FROM thread WHERE threadid='$threadid'");
  if ($checkforum['forumid']==$forumid and ($method == "move" or $method == "movered") ) {
    eval("standarderror(\"".gettemplate("error_movesameforum")."\");");
    exit;
  }

  if ($method=="move") { // straight move
	$threadinfo['notes']="Moved to '$foruminfo[title]' by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";
	$DB_site->query("UPDATE thread SET forumid='".vb_escape($forumid)."',notes='".vb_escape($threadinfo['notes'])."',sticky=0 WHERE threadid='$threadid'");

  } elseif ($method=="movered") { // move and leave redirect!

    $threadinfo['notes']="Moved (with redirect) to '$foruminfo[title]' by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";
    $DB_site->query("INSERT INTO thread (threadid,title,lastpost,forumid,pollid,open,replycount,postusername,postuserid,lastposter,dateline,views,iconid,visible) VALUES (NULL,'".vb_escape($threadinfo['title'])."','".vb_escape($threadinfo['lastpost'])."','".vb_escape($threadinfo['forumid'])."','".vb_escape($threadinfo['threadid'])."',10,'".vb_escape($threadinfo['replycount'])."','".vb_escape($threadinfo['postusername'])."','".vb_escape($threadinfo['postuserid'])."','".vb_escape($threadinfo['lastposter'])."','".vb_escape($threadinfo['dateline'])."','".vb_escape($threadinfo['views'])."','".vb_escape($threadinfo['iconid'])."','".vb_escape($threadinfo['visible'])."')");

    $DB_site->query("UPDATE thread SET forumid='".vb_escape($forumid)."', notes='".vb_escape($threadinfo['notes'])."' WHERE threadid='$threadid'");

  } else {

    //copy!!!
    $threadinfo['notes']="Copied to '$foruminfo[title]' by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";

    if ($threadinfo['pollid'] and $threadinfo['open']!=10) {// We have a poll, need to duplicate it!
      if ($pollinfo=$DB_site->query_first("SELECT * FROM poll WHERE pollid='$threadinfo[pollid]'")) {
        $DB_site->query("INSERT INTO poll (question,dateline,options,votes,active,numberoptions,timeout,multiple)
                                           VALUES ('".vb_escape($pollinfo['question'])."','$pollinfo[dateline]','".vb_escape($pollinfo['options'])."','$pollinfo[votes]','$pollinfo[active]','$pollinfo[numberoptions]','$pollinfo[timeout]', '$pollinfo[multiple]')");
        $oldpollid=$threadinfo['pollid'];
        $threadinfo['pollid'] = $DB_site->insert_id();
        $pollvotes=$DB_site->query("SELECT userid, votedate, voteoption FROM pollvote where pollid = '$oldpollid'");

        $insertsql = "";
        while ($pollvote=$DB_site->fetch_array($pollvotes)) {
          if ($insertsql) {
            $insertsql .= ",";
          }
          $insertsql .= "('$threadinfo[pollid]','$pollvote[userid]','$pollvote[votedate]','$pollvote[options]')";
        }
        if ($insertsql) {
          $DB_site->query("INSERT INTO pollvote (pollid,userid,votedate,voteoption) VALUES $insertsql");
        }
      }
    }

    $DB_site->query("INSERT INTO thread (threadid,title,lastpost,forumid,pollid,open,replycount,postusername,postuserid,lastposter,dateline,views,iconid,notes,visible,attach) VALUES (NULL,'".vb_escape($threadinfo['title'])."','".vb_escape($threadinfo['lastpost'])."','".vb_escape($forumid)."','".vb_escape($threadinfo['pollid'])."','".vb_escape($threadinfo['open'])."','".vb_escape($threadinfo['replycount'])."','".vb_escape($threadinfo['postusername'])."','".vb_escape($threadinfo['postuserid'])."','".vb_escape($threadinfo['lastposter'])."','".vb_escape($threadinfo['dateline'])."','".vb_escape($threadinfo['views'])."','".vb_escape($threadinfo['iconid'])."','".vb_escape($threadinfo['notes'])."','".vb_escape($threadinfo['visible'])."','".vb_escape($threadinfo['attach'])."')");
    $newthreadid=$DB_site->insert_id();

    $DB_site->query("UPDATE thread SET notes='".vb_escape($threadinfo['notes'])."' WHERE threadid='$threadid'");

    $posts=$DB_site->query("SELECT * FROM post WHERE threadid='$threadid'");
    while ($post=$DB_site->fetch_array($posts)) {
      $DB_site->query("INSERT INTO post (postid,threadid,username,userid,title,dateline,attachmentid,pagetext,allowsmilie,showsignature,ipaddress,iconid,visible,edituserid,editdate) VALUES (NULL,'$newthreadid','".vb_escape($post['username'])."','".vb_escape($post['userid'])."','".vb_escape($post['title'])."','".vb_escape($post['dateline'])."','".vb_escape($post['attachmentid'])."','".vb_escape($post['pagetext'])."','".vb_escape($post['allowsmilie'])."','".vb_escape($post['showsignature'])."','".vb_escape($post['ipaddress'])."','".vb_escape($post['iconid'])."','".vb_escape($post['visible'])."','".vb_escape($post['edituserid'])."','".vb_escape($post['editdate'])."')");
      $newpostid=$DB_site->insert_id();
      indexpost($newpostid);
    }
    //now update threadid so we get redirected to the one in the new forum
    $threadid = $newthreadid;
  }

  updateforumcount($threadinfo['forumid']);
  updateforumcount($forumid);


  $users=$DB_site->query("SELECT user.userid,usergroupid FROM subscribethread,user WHERE subscribethread.userid=user.userid AND subscribethread.threadid='$threadid'");
  $deleteuser = '0';
  while($thisuser=$DB_site->fetch_array($users)) {
    $perms=getpermissions($forumid, $thisuser['userid'], $thisuser['usergroupid']);
    if ($perms['canview'] AND ($threadinfo['postuserid'] == $thissubuser['userid'] OR $perms['canviewothers'])) {
    	continue;
    }
    $deleteuser .= ",$thisuser[userid]";
  }

  if ($deleteuser) {
    $DB_site->query("DELETE FROM subscribethread WHERE threadid='$threadid' AND userid IN ($deleteuser)");
  }


  eval("standardredirect(\"".gettemplate("redirect_movethread")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
}

// ############################### start edit thread ###############################
if ($action=="editthread") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);
  $foruminfo=getforuminfo($threadinfo['forumid']);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // check forum permissions for this forum
  if (!ismoderator($threadinfo['forumid'],"caneditthreads")) {
    $permissions=getpermissions($forumid);
    if (!$permissions['canview'] or !$permissions['canmove']) {
      show_nopermission();
    } else {
      if (!$threadinfo['open']) {
        eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      }
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  // ensure thread notes are run through htmlspecialchars
  $notes = &$threadinfo['notes'];
  $threadinfo['notes'] = htmlspecialchars($threadinfo['notes']);

  updateuserforum($threadinfo['forumid']);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

  $visiblechecked=iif($threadinfo['visible'],"CHECKED","");
  $openchecked=iif($threadinfo['open'],"CHECKED","");

  if ($foruminfo['allowicons']) {
    $posticons=chooseicons($threadinfo['iconid']);
  }  else {
    $posticons="";
  }

  eval("dooutput(\"".gettemplate("threads_editthread")."\");");

}

// ############################### start update thread ###############################
if ($_POST['action']=="updatethread") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  //if (!$threadinfo[visible]) {
  //  $idname="thread";
  //  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  //}

  // check forum permissions for this forum
  if (!ismoderator($threadinfo['forumid'],"caneditthreads")) {
    $permissions=getpermissions($forumid);
    if (!$permissions['canview'] or !$permissions['canmove']) {
      show_nopermission();
    } else {
      if (!$threadinfo['open']) {
        eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
        exit;
      }
      $firstpostinfo=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
      if ($bbuserinfo['userid']!=$firstpostinfo['userid']) {
        show_nopermission();
      }
    }
  }

  updateuserforum($threadinfo['forumid']);

  $visible=iif($visible=="yes",1,0);
  $open=iif($open=="yes",1,0);

  if (!ismoderator($threadinfo['forumid'],"canopenclose") and !$permissions['canopenclose']) {
    $open=$threadinfo['open'];
  }

  $iconid = intval($iconid);
  if ($iconid=="") {
    $iconid=0;
  }

  $notes = "Thread edited by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $notes";
  $DB_site->query("UPDATE thread SET visible='$visible',open='$open',title='".vb_escape(htmlspecialchars($title))."',iconid='".vb_escape($iconid)."',notes='".vb_escape($notes)."' WHERE threadid='$threadid'");

  // Reindex first post to set up title properly.

  $getfirstpost=$DB_site->query_first("SELECT postid,title,pagetext FROM post WHERE threadid=$threadid ORDER BY dateline LIMIT 1");
  unindexpost($getfirstpost['postid'],$getfirstpost['title'],$getfirstpost['pagetext']);
  indexpost($getfirstpost['postid']);

  updateforumcount($threadinfo['forumid']);


  if ($visible) {
    eval("standardredirect(\"".gettemplate("redirect_editthread")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
  } else {
    eval("standardredirect(\"".gettemplate("redirect_editthread")."\",\"forumdisplay.php?s=$session[sessionhash]&amp;forumid=$threadinfo[forumid]\");");
  }
}

// ############################### start merge threads ###############################
if ($action=="merge") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // check forum permissions for this forum
  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
    show_nopermission();
  }

  // ensure thread notes are run through htmlspecialchars
  $notes = &$threadinfo['notes'];
  $threadinfo['notes'] = htmlspecialchars($threadinfo['notes']);

  updateuserforum($threadinfo['forumid']);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

  eval("dooutput(\"".gettemplate("threads_merge")."\");");

}

// ############################### start do merge threads ###############################
if ($_POST['action']=="domergethread") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // check forum permissions for this forum
  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
    show_nopermission();
  }

  // relative URLs will do bad things here, so don't let them through; thanks Paul! :)
  if (stristr($mergethreadurl, 'goto=next')) {
    // do invalid url
    eval("standarderror(\"".gettemplate("error_mergebadurl")."\");");
    exit;
  }

  // get other threadid
  $getthreadid=intval(substr($mergethreadurl,strpos($mergethreadurl,"threadid=")+9));
  if ($getthreadid==0) {
    $getpostid=intval(substr($mergethreadurl,strpos($mergethreadurl,"postid=")+7));
    if ($getpostid==0) {
      // do invalid url
      eval("standarderror(\"".gettemplate("error_mergebadurl")."\");");
      exit;
    }
    $getpostid=verifyid("post",$getpostid,0);
    if ($getpostid==0) {
      // do invalid url
      eval("standarderror(\"".gettemplate("error_mergebadurl")."\");");
      exit;
    }

    $postinfo=getpostinfo($getpostid);
    $mergethreadid=$postinfo['threadid'];
  } else {
    $getthreadid=verifyid("thread",$getthreadid,0);
    if ($getthreadid==0) {
      // do invalid url
      eval("standarderror(\"".gettemplate("error_mergebadurl")."\");");
      exit;
    }
    $mergethreadid=$getthreadid;
  }

	if ($mergethreadid==$threadid) {
		// check for merging with self!
		eval("standarderror(\"".gettemplate("error_mergewithself")."\");");
	}

  $mergethreadinfo=getthreadinfo($mergethreadid);

  if (!$mergethreadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // check forum permissions for this forum
  if (!ismoderator($mergethreadinfo['forumid'],"canmanagethreads")) {
    show_nopermission();
  }

  updateuserforum($threadinfo['forumid']);

  // get the first post from each thread -- we only need to reindex those
  list($firstpostid)=$DB_site->query_first("SELECT postid FROM post WHERE threadid='$threadinfo[threadid]' ORDER BY dateline ASC LIMIT 1");
  list($secondpostid)=$DB_site->query_first("SELECT postid FROM post WHERE threadid='$mergethreadinfo[threadid]' ORDER BY dateline ASC LIMIT 1");

  // update notes
  $threadinfo['notes'] = "Thread merged by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]. $mergethreadinfo[notes]";

  // sort out polls
  $pollcode="";
  if ($mergethreadinfo['pollid']!=0) {
    if ($threadinfo['pollid']==0) {
      $pollcode=",pollid=$mergethreadinfo[pollid]";
    } else {
      if (!$poll=$DB_site->query_first("SELECT threadid FROM thread WHERE pollid=$mergethreadinfo[pollid] AND threadid<>$mergethreadinfo[threadid]")) {
        $DB_site->query("DELETE FROM poll WHERE pollid=$mergethreadinfo[pollid]");
        $DB_site->query("DELETE FROM pollvote WHERE pollid=$mergethreadinfo[pollid]");
      }
    }
  }

  // move posts
  $DB_site->query("UPDATE post SET threadid='$threadid' WHERE threadid='$mergethreadid'");
  $DB_site->query("UPDATE thread SET title='".vb_escape(htmlspecialchars($title))."',notes='".vb_escape($threadinfo['notes'])."'$pollcode WHERE threadid='$threadid'");
  $DB_site->query("DELETE FROM thread WHERE threadid='$mergethreadid'");
  $DB_site->query("UPDATE thread SET pollid='$threadid' WHERE open=10 AND pollid='$mergethreadid'"); // update redirects
  $DB_site->query("DELETE FROM threadrate WHERE threadid='$mergethreadid'");

  // move subscribed users without making duplicate entries
  $subusers = $DB_site->query("SELECT user.userid, usergroupid FROM subscribethread,user WHERE subscribethread.userid=user.userid AND subscribethread.threadid='$threadid'");
  $subuserlist = '0';
  while ($thissubuser = $DB_site->fetch_array($subusers)) {
    if ($mergethreadinfo['forumid']!=$threadinfo['forumid']) {
    	$perms = getpermissions($threadinfo['forumid'], $thissubuser['userid'], $thissubuser['usergroupid']);
    	if ($perms['canview'] AND ($threadinfo['postuserid'] == $thissubuser['userid'] OR $perms['canviewothers'])) {
			$subuserlist .= ",$thissubuser[userid]";
		}
    } else {
    	$subuserlist .= ",$thissubuser[userid]";
    }
  }
  $DB_site->query("UPDATE subscribethread SET threadid='$threadid' WHERE threadid='$mergethreadid' AND userid NOT IN ($subuserlist)");
  $DB_site->query("DELETE FROM subscribethread WHERE threadid='$mergethreadid'");

  // update searchindex for the 2 posts who's titles may have changed (first post of each thread)
	unindexpost($firstpostid);
  unindexpost($secondpostid);
  indexpost($firstpostid);
  indexpost($secondpostid);

  updatethreadcount($threadid);
  updateforumcount($threadinfo['forumid']);
  if ($mergethreadinfo['forumid']!=$threadinfo['forumid']) {
    updateforumcount($mergethreadinfo['forumid']);
  }

  eval("standardredirect(\"".gettemplate("redirect_mergethread")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");

}

// ############################### start split thread ###############################
if ($action=="split") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
    show_nopermission();
  }

  // ensure thread notes are run through htmlspecialchars
  $notes = &$threadinfo['notes'];
  $threadinfo['notes'] = htmlspecialchars($threadinfo['notes']);

  updateuserforum($threadinfo['forumid']);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

  $posts=$DB_site->query("SELECT post.* FROM post WHERE post.threadid='$threadid' ORDER BY dateline");
  if ($DB_site->num_rows($posts) <= 1) {
     eval("standarderror(\"".gettemplate("error_cantsplitone")."\");");
  }
  $counter=0;
  $postbits = '';
  while ($post=$DB_site->fetch_array($posts)) {
    if (++$counter%2==0) {
      $post['backcolor']="{firstaltcolor}";
	  $post['bgclass'] = "alt1";
    } else {
      $post['backcolor']="{secondaltcolor}";
	  $post['bgclass'] = "alt2";
    }
    $post['postdate']=vbdate($dateformat,$post['dateline']);
    $post['posttime']=vbdate($timeformat,$post['dateline']);

    // cut page text short if too long
    if (strlen($post['pagetext'])>100) {
      $spacepos=strpos($post['pagetext']," ",100);
      if ($spacepos!=0) {
        $post['pagetext']=substr($post['pagetext'],0,$spacepos)."...";
      }
    }
    $post['pagetext']=nl2br(htmlspecialchars($post['pagetext']));

    eval("\$postbits .= \"".gettemplate("threads_splitthreadbit")."\";");
  }

  $moveforumbits=getmoveforums();
  eval("dooutput(\"".gettemplate("threads_splitthread")."\");");

}

// ############################### start do split thread ###############################
if ($_POST['action']=="dosplitthread") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
    show_nopermission();
  }

  updateuserforum($threadinfo['forumid']);

  $doyes = 0;
  $dono = 0;
  while ( list($key,$val)=each($splitpost) ) {
    if ($val=="yes") {
      $splitids .= ',' . $key;
      $doyes = 1;
    } else {
      $dono = 1;
    }
  }
  if ($doyes==0 and $dono==1) { // Selected no posts to split
    eval("standarderror(\"".gettemplate("error_nosplitposts")."\");");
  } elseif ($doyes==1 and $dono==0) { // Selected all posts to split
    eval("standarderror(\"".gettemplate("error_cantsplitall")."\");");
  }
  $oldforumid = 0;
  if ($newforumid!="") {
    $oldforumid = $threadinfo['forumid'];
    $threadinfo['forumid'] = $newforumid;
  }

  // Move post info to new thread...
  $posts=$DB_site->query("SELECT postid,attachmentid,userid FROM post WHERE threadid='$threadid'");
  while ($post=$DB_site->fetch_array($posts)) {
    if ($splitpost[$post['postid']]=="yes") {
      if (!$newthreadid)
      {
        $DB_site->query("INSERT INTO thread (threadid,title,lastpost,forumid,open,replycount,postusername,postuserid,lastposter,dateline,views,iconid,notes,visible) VALUES (NULL,'".vb_escape(htmlspecialchars($title))."','".vb_escape($threadinfo['lastpost'])."','".vb_escape($threadinfo['forumid'])."','".vb_escape($threadinfo['open'])."','".vb_escape($threadinfo['replycount'])."','".vb_escape($threadinfo['postusername'])."','".vb_escape($threadinfo['postuserid'])."','".vb_escape($threadinfo['lastposter'])."','".vb_escape($threadinfo['dateline'])."','".vb_escape($threadinfo['views'])."','".vb_escape($threadinfo['iconid'])."','Thread split from threadid $threadid by ".vb_escape(unhtmlspecialchars($bbuserinfo['username']))." on ".vb_escape(vbdate($dateformat." ".$timeformat,time())).". ".vb_escape($threadinfo['notes'])."','".vb_escape($threadinfo['visible'])."')");
        $newthreadid=$DB_site->insert_id();
      }
      $DB_site->query("UPDATE post SET threadid=$newthreadid WHERE postid=$post[postid]");
    }
  }

  if (!$newthreadid)
  { //error no thread was created, so they must have already been split
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  // update thread notes
  $threadinfo['notes'] = "Thread split to threadid $newthreadid by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";
  $DB_site->query("UPDATE thread SET notes='".vb_escape($notes)."' WHERE threadid='$threadid'");

  // Update first post in each thread as title information in relation to the sames words being in the first post may have changed now.
  $getfirstpost=$DB_site->query_first("SELECT postid,title,pagetext FROM post WHERE threadid=$threadid ORDER BY dateline LIMIT 1");
  $DB_site->query("DELETE FROM searchindex WHERE postid=$getfirstpost[postid]");
 	unindexpost($getfirstpost['postid'],$getfirstpost['title'],$getfirstpost['pagetext']);
 	indexpost($getfirstpost['postid']);

  $getfirstpost=$DB_site->query_first("SELECT postid,title,pagetext FROM post WHERE threadid=$newthreadid ORDER BY dateline LIMIT 1");
 	unindexpost($getfirstpost['postid'],$getfirstpost['title'],$getfirstpost['pagetext']);
  indexpost($getfirstpost['postid']);

  updatethreadcount($threadid);
  updatethreadcount($newthreadid);
  updateforumcount($threadinfo['forumid']);
  if ($newforumid!="") {
    updateforumcount($oldforumid);
  }
  eval("standardredirect(\"".gettemplate("redirect_splitthread")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$newthreadid\");");
}

// ############################### start stick / unstick thread ###############################
if ($_POST['action'] == 'stick' OR ($action == 'stick' AND $s == $session['dbsessionhash'])) {

  $threadid=verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
  }

  if (!ismoderator($threadinfo['forumid'],"canmanagethreads")) {
      show_nopermission();
  }

  updateuserforum($threadinfo['forumid']);

  if ($threadinfo['sticky']) {
    $threadinfo['sticky']=0;
    $action="unstuck";
  } else {
    $threadinfo['sticky']=1;
    $action="stuck";
  }
  $threadinfo['notes']= "Thread $action by " . unhtmlspecialchars($bbuserinfo['username']) . " on ".vbdate($dateformat." ".$timeformat,time()).". $threadinfo[notes]";
  $DB_site->query("UPDATE thread SET sticky=$threadinfo[sticky],notes='".vb_escape($threadinfo['notes'])."' WHERE threadid='$threadid'");

  eval("standardredirect(\"".gettemplate("redirect_sticky")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");

}


?>