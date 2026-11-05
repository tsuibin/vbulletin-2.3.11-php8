<?php
require_once __DIR__ . '/../admin/php8compat.php';
error_reporting(7);

require("./global.php");

cpheader();

//echo "$parentid -- $forumid -- $destforumid";

if ($parentid and !$forumid) {
  $forumid = $parentid;
}

// ###################### Start Prune #######################
if ($action=="prune") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  doformheader("thread","prunedate");
  maketableheader("Prune by date");
  makeinputcode("Delete threads with last post older than x days:<BR>(intensive if deleting a lot of threads)","daysdelete","");
  makemodchoosercode(-1,-1,'','All forums','In forum',0);

  doformfooter();

  doformheader("thread","pruneuser");
  maketableheader("Prune by username");
  makeinputcode("Username","username");
  makemodchoosercode(-1,-1,'','All forums','In forum',0);

  doformfooter();

}

// ###################### Start Prune by date #######################
if ($action=="prunedate") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  if (isset($daysdelete)==0 or $daysdelete==0) {
    echo "<p>Please enter an age of post to delete</p>";
    exit;
  }

  if ($confirm!=1) {

    if ($forumid==-1) {
      $forumtitle="all forums";
    } else {
      $forum=$DB_site->query_first("SELECT title FROM forum WHERE forumid=$forumid");
      $forumtitle="the \"$forum[title]\" forum";
    }
    echo "<p>You are about to delete all threads from $forumtitle with last post older than $daysdelete day(s).<p>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=prunedate&amp;forumid=$forumid&amp;daysdelete=$daysdelete&amp;confirm=1\"><b>here</b></a> to confirm and delete them all.</p>\n<p>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=prunedatesel&amp;forumid=$forumid&amp;daysdelete=$daysdelete\"><b>here</b></a> to select those to delete.</p>";
    exit;
  }

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $datecut=time()-($daysdelete*86400);
  $threads=$DB_site->query("SELECT threadid FROM thread WHERE $forumcheck thread.lastpost<=$datecut");
  while ($thread=$DB_site->fetch_array($threads)) {
    deletethread($thread['threadid'],0);
  }

  $DB_site->query("DELETE FROM thread WHERE $forumcheck thread.lastpost<=$datecut");

  echo "<p>Posts deleted successfully!</p>";
  if ($forumid != -1) {
      updateforumcount($forumid);
  }
}

// ###################### Start Prune by date selector #######################
if ($action=="prunedatesel") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  doformheader("thread","doprunedate");
  maketableheader("Select items to delete");
  makehiddencode("forumid",$forumid);
  
  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $datecut=time()-($daysdelete*86400);
  $threads=$DB_site->query("SELECT threadid,title FROM thread WHERE $forumcheck thread.lastpost<=$datecut");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("Delete: <a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","delete[$thread[threadid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start Prune by date selected #######################
if ($_POST['action']=="doprunedate") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  echo "<p>Deleting...</p>";

  while (list($key,$val)=each($delete)) {
    if ($val==1) {
      deletethread($key,0);
    }
  }

  echo "<p>Posts deleted successfully!</p>";
  if ($forumid != -1) {
      updateforumcount($forumid);
  }
}

// ###################### Start Prune by user #######################
if ($action=="pruneuser") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  if ($confirm!=1) {

    if (isset($username)==0 or $username=="") {
      echo "<p>Please enter a username to search for</p>";
      exit;
    }

    if ($forumid==-1) {
      $forumtitle="all forums";
    } else {
      $forum=$DB_site->query_first("SELECT title FROM forum WHERE forumid=$forumid");
      $forumtitle="the $forum[title] forum";
    }
    echo "<p>You are about to delete all posts and threads from $forumtitle by one of these users. Please select one:</p>";

    $users=$DB_site->query("SELECT userid,username FROM user WHERE username LIKE '%".vb_escape($username)."%' ORDER BY username");
    while ($user=$DB_site->fetch_array($users)) {
      echo "<p>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=pruneuser&amp;forumid=$forumid&amp;userid=$user[userid]&amp;confirm=1\"><b>here</b></a> to confirm and delete all posts by <b>$user[username]</b>.<br>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=pruneusersel&amp;forumid=$forumid&amp;userid=$user[userid]&amp;confirm=1\"><b>here</b></a> to select which posts to delete by <b>$user[username]</b>.</p>";
    }

    exit;
  }

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid = " . intval( $userid ) );
  $username=$usernames['username'];

  $threads=$DB_site->query("SELECT threadid FROM thread WHERE $forumcheck 1=1");
  while ($thread=$DB_site->fetch_array($threads)) {
    $DB_site->query("DELETE FROM post WHERE threadid=$thread[threadid] AND userid = " . intval( $userid ) );
  }
  $threads=$DB_site->query("SELECT threadid FROM thread WHERE $forumcheck postusername='".vb_escape($username)."'");
  while ($thread=$DB_site->fetch_array($threads)) {
    deletethread($thread['threadid'],0);
  }
  $DB_site->query("DELETE FROM thread WHERE $forumcheck postusername='".vb_escape($username)."'");

  echo "<p>Posts deleted successfully!</p>";
  if ($forumid != -1) {
      updateforumcount($forumid);
  }  
}

// ###################### Start prune by user selector #######################
if ($action=="pruneusersel") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  doformheader("thread","dopruneuser");
  maketableheader("Select items to delete");
  makehiddencode("forumid",$forumid);
  
  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid = " . intval( $userid ) );
  $username=$usernames['username'];

  $forumcheck=iif($forumid!=-1,"thread.forumid=$forumid AND ","");

  $threads=$DB_site->query("SELECT threadid,title FROM thread WHERE $forumcheck postusername='".vb_escape($username)."'");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("Delete thread: <a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","deletethread[$thread[threadid]]",1);
  }
  $threads=$DB_site->query("SELECT post.postid,thread.threadid,thread.title FROM thread,post WHERE thread.threadid=post.threadid AND $forumcheck post.userid = " . intval( $userid ) );
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("Delete post: <a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>, post id $thread[postid]","deletepost[$thread[postid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start Prune by user selected #######################
if ($_POST['action']=="dopruneuser") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  echo "<p>Deleting...</p>";

  while (list($key,$val)=each($deletethread)) {
    if ($val==1) {
      deletethread($key,0);
    }
  }

  while (list($key,$val)=each($deletepost)) {
    if ($val==1) {
      deletepost($key,0);
    }
  }

  echo "<p>Posts deleted successfully!</p>";
  if ($forumid != -1) {
      updateforumcount($forumid);
  }  
}

// ###################### Start Move #######################
if ($action=="move") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  echo "<p>Note: This will only move the threads, and not copy them or leave a redirect</p>";

  doformheader("thread","movedate");
  maketableheader("Move by date");
  makeinputcode("Move threads with last post older than x days:","daysmove","");
  //makeforumchoosercode("Source Forum","forumid",-1,"All forums");
  makemodchoosercode(-1,-1,'','All forums','In forum',0);
  //makeforumchoosercode("Destination Forum","destforumid");
  makeforumchooser("destforumid",-1,-1,"","----- all -----","Destination Forum:",0);

  doformfooter();

  doformheader("thread","moveuser");
  maketableheader("Move by username");
  makeinputcode("Username","username");
  //makeforumchoosercode("Source Forum","forumid",-1,"All forums");
  makemodchoosercode(-1,-1,'','All forums','In forum',0);
  //makeforumchoosercode("Destination Forum","destforumid");
  makeforumchooser("destforumid",-1,-1,"","----- all -----","Destination Forum:",0);

  doformfooter();

}

// ###################### Start by Move Date #######################
if ($action=="movedate") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  if (isset($daysmove)==0 or $daysmove=="") {
    echo "<p>Please enter an age of post to move</p>";
    exit;
  }

  if ($confirm!=1) {

    if ($forumid==-1) {
      $forumtitle="all";
    } else {
      $forum=$DB_site->query_first("SELECT title FROM forum WHERE forumid=$forumid");
      $forumtitle=$forum['title'];
    }
    echo "<p>You are about to move all threads from the $forumtitle forum older than $daysmove day(s).<p>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=movedate&amp;forumid=$forumid&amp;daysmove=$daysmove&amp;destforumid=$destforumid&amp;confirm=1\">here</a> to confirm and move them all.</p>\n<p>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=movedatesel&amp;forumid=$forumid&amp;destforumid=$destforumid&amp;daysmove=$daysmove\">here</a> to confirm and select those to move.</p>";
    exit;
  }

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $datecut=time()-($daysmove*86400);
  $DB_site->query("UPDATE thread SET forumid=$destforumid WHERE $forumcheck thread.lastpost<=$datecut");

  echo "<p>Posts moved successfully!</p>";
  if ($forumid != -1) {
    updateforumcount($forumid);
  } 
  updateforumcount($destforumid);
}

// ###################### Start Move by date selector #######################
if ($action=="movedatesel") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  doformheader("thread","domovedatesel");
  maketableheader("Select threads to move");
  makehiddencode("srcforumid",$forumid);
  makehiddencode("destforumid",$destforumid);

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $datecut=time()-($daysmove*86400);
  $threads=$DB_site->query("SELECT threadid,title FROM thread WHERE $forumcheck thread.lastpost<=$datecut");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("Move: <a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","move[$thread[threadid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start Move by date selected #######################
if ($_POST['action']=="domovedatesel") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  $threadids = '';
  while (list($key,$val)=each($move)) {
      if ($val==1) {
        $threadids .= ",$key";
      }
    }
    if ($threadids) {
        $DB_site->query("UPDATE thread
        				SET forumid='$destforumid'
        				WHERE threadid IN (0$threadids)");
  }

  echo "<p>Posts moved successfully!</p>";
  //update forums
  if ($srcforumid != -1) {
    updateforumcount($srcforumid);
  } 
  updateforumcount($destforumid);
}

// ###################### Start Move by user #######################
if ($action=="moveuser") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  if ($confirm!=1) {

    if (isset($username)==0 or $username=="") {
      echo "<p>Please enter a username to search for</p>";
      exit;
    }

    if ($forumid==-1) {
      $forumtitle="all forums";
    } else {
      $forum=$DB_site->query_first("SELECT title FROM forum WHERE forumid=$forumid");
      $forumtitle="the $forum[title] forum";
    }
    echo "<p>You are about to move all posts and threads from $forumtitle by one of these users. Please select one:</p>";

    $users=$DB_site->query("SELECT userid,username FROM user WHERE username LIKE '%".vb_escape($username)."%' ORDER BY username");
    while ($user=$DB_site->fetch_array($users)) {
      echo "<p>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=moveuser&amp;forumid=$forumid&amp;destforumid=$destforumid&amp;srcforumid=$forumid&amp;userid=$user[userid]&amp;confirm=1\">here</a> to confirm and move all posts by <b>$user[username]</b>.<br>Click <a href=\"thread.php?s=$session[sessionhash]&amp;action=moveusersel&amp;forumid=$forumid&amp;destforumid=$destforumid&amp;userid=$user[userid]&amp;confirm=1\">here</a> to select which posts to move by <b>$user[username]</b>.</p>";
    }

    exit;
  }

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid = " . intval( $userid ) );
  $username=$usernames['username'];

  $DB_site->query("UPDATE thread SET forumid=$destforumid WHERE $forumcheck postusername='".vb_escape($username)."'");

  echo "<p>Posts moved successfully!</p>";
  //update forums
  if ($forumid != -1) {
    updateforumcount($forumid);
  } 
  updateforumcount($destforumid);
}

// ###################### Start move by user selector #######################
if ($action=="moveusersel") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  doformheader("thread","domoveuser");
  maketableheader("Select threads to move");
  makehiddencode("srcforumid",$forumid);
  makehiddencode("destforumid",$destforumid);

  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid = " . intval( $userid ) );
  $username=$usernames['username'];

  $forumcheck=iif($forumid!=-1,"thread.forumid=$forumid AND ","");

  $threads=$DB_site->query("SELECT threadid,title FROM thread WHERE $forumcheck postusername='".vb_escape($username)."'");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("Move thread: <a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","movethread[$thread[threadid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start move by user selected #######################
if ($_POST['action']=="domoveuser") {

  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  echo "<p>Moving...</p>";
  $threadids = '';
  while (list($key,$val)=each($movethread)) {
      if ($val==1) {
        $threadids .= ",$key";
      }
    }
    if ($threadids) {
        $DB_site->query("UPDATE thread
        				SET forumid='$destforumid'
        				WHERE threadid IN (0$threadids)");
  }

  echo "<p>Posts moved successfully!</p>";
  //update forums
  if ($srcforumid != -1) {
    updateforumcount($srcforumid);
  } 
  updateforumcount($destforumid);
}

cpfooter();

?>
