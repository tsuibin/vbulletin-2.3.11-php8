<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

if (function_exists("set_time_limit")==1 and get_cfg_var("safe_mode")==0) {
  @set_time_limit(1200);
}

//suppress gzipping
$nozip=1;

require("./global.php");

adminlog();

cpheader();

if ($action=="") {
	$action = "chooser";
}

// ###################### Start emptying the index #######################
if ($action=="emptyindex") {

	doformheader("misc","doemptyindex");
	maketableheader("Confirm action");
	makedescription("Are you sure you wish to empty the search index?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start emptying the index #######################

if ($_POST['action']=="doemptyindex") {
  $DB_site->query("DELETE FROM searchindex");
  $DB_site->query("DELETE FROM word");

  echo "<p>Index successfully emptied</p>";
  cpredirect("misc.php?s=$session[sessionhash]",1);
}

// ###################### Start build search index #######################
if ($action=="buildsearchindex") {
  if (isset($perpage)==0 or $perpage=="") {
    $perpage=100;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  if (!isset($totalthreads)) {
    $totalthreads = 0;
  }

  $finishat=$startat+$perpage;

  echo "<p>Building index..</p>";

  $threads=$DB_site->query("SELECT DISTINCT thread.threadid,post.postid FROM thread,post WHERE thread.threadid=post.threadid AND thread.threadid>=$startat AND thread.threadid<$finishat AND thread.visible=1 ORDER BY thread.threadid");
  while ($thread=$DB_site->fetch_array($threads)) {
    $totalposts++;
    if ($thread['threadid']>$lastthreadid) {
      $totalthreads++;
    }
    $lastthreadid = $thread['threadid'];
    indexpost($thread['postid']);
    echo "Processing post <b>$thread[postid]</b> in thread <b>$thread[threadid]</b><BR>\n";
    flush();
  }

  if (($totalthreads<$doprocess or $doprocess==0) and $checkmore=$DB_site->query_first("SELECT threadid FROM thread WHERE threadid>=$finishat")) {
    if ($autoredirect==1) {
      cpredirect("misc.php?s=$session[sessionhash]&amp;action=buildsearchindex&amp;startat=$finishat&amp;perpage=$perpage&amp;autoredirect=$autoredirect&amp;totalthreads=$totalthreads&amp;doprocess=$doprocess");
    }
    echo "<p><a href=\"misc.php?s=$session[sessionhash]&amp;action=buildsearchindex&amp;startat=$finishat&amp;perpage=$perpage&amp;autoredirect=$autoredirect&amp;totalthreads=$totalthreads&amp;doprocess=$doprocess\">Click here to continue building search engine</a></p>";
  } else {
    echo "<p>Search index rebuilt!</p>";
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }
}

// ###################### Start update post counts ################
if ($action == 'updateposts') {
  unset($gotforums);
  if (isset($perpage)==0 or $perpage=="") {
    $perpage=1000;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;

  echo "<p>User ids:</p>";

  $forums = $DB_site->query("SELECT forumid
                             FROM forum
                             WHERE countposts = 1");
  while ($forum = $DB_site->fetch_array($forums)) {
    $gotforums .= ",$forum[forumid]";
  }

  $users=$DB_site->query("SELECT userid
                          FROM user
                          WHERE userid>=$startat AND
                                userid<$finishat
                          ORDER BY userid DESC");
  while ($user=$DB_site->fetch_array($users)) {
    $userid = $user['userid'];

    $totalposts = $DB_site->query_first("SELECT COUNT(postid) AS posts
                                         FROM post
                                         LEFT JOIN thread USING (threadid)
                                         WHERE userid='$userid' AND
                                               forumid IN (0$gotforums)");
    $DB_site->query("UPDATE user
                     SET posts='$totalposts[posts]'
                     WHERE userid='$userid'");


    echo "Processing user <b>$user[userid]</b><br>\n";
    flush();

  }


  if ($checkmore = $DB_site->query_first("SELECT userid
                                          FROM user
                                          WHERE userid>=$finishat")) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=updateposts&amp;startat=$finishat&amp;perpage=$perpage");
    echo "<p><a href=\"misc.php?s=$session[sessionhash]&amp;action=updateposts&amp;startat=$finishat&amp;perpage=$perpage\">Click here to continue updating users</a></p>";
  } else {
    echo "<p>User Post Counts updated!</p>";
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }

}

// ###################### Start update user #######################
if ($action=="updateuser") {
  if (isset($perpage)==0 or $perpage=="") {
    $perpage=1000;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;

  echo "<p>User ids:</p>";

  $users=$DB_site->query("SELECT userid,usertitle,usergroupid,customtitle,posts FROM user WHERE userid>=$startat AND userid<$finishat ORDER BY userid DESC");
  while ($user=$DB_site->fetch_array($users)) {
    unset($sql);
    $userid=$user['userid'];

    // update user stuff
    if ($user['customtitle']==0)
    {
      $usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid=$user[usergroupid]");
      if ($usergroup['usertitle']=="")
      {
        $gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=".intval($user['posts'])." ORDER BY minposts DESC LIMIT 1");
        $usertitle=$gettitle['title'];
      }
      else
      {
        $usertitle=$usergroup['usertitle'];
      }

      $sql="usertitle='".vb_escape($usertitle)."',";
    }

    if ($lastpost=$DB_site->query_first("SELECT dateline FROM post WHERE userid=$userid ORDER BY dateline DESC LIMIT 1")) {
      $lastpost['dateline']=intval($lastpost['dateline']);
      if (trim($lastpost['dateline'])=="") {
        $lastpost['dateline']=0;
      }
    } else {
      $lastpost['dateline']=0;
    }

    $DB_site->query("UPDATE user SET $sql"."lastpost='$lastpost[dateline]' WHERE userid='$user[userid]'");

    echo "Processing user <b>$user[userid]</b><br>\n";
    flush();

  }
  if ($checkmore=$DB_site->query_first("SELECT userid FROM user WHERE userid>=$finishat")) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=updateuser&amp;startat=$finishat&amp;perpage=$perpage");
    echo "<p><a href=\"misc.php?s=$session[sessionhash]&amp;action=updateuser&amp;startat=$finishat&amp;perpage=$perpage\">Click here to continue updating users</a></p>";
  } else {
    echo "<p>User titles updated!</p>";
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }

}

// ###################### Start update forum #######################
if ($action=="updateforum") {
  if (isset($perpage)==0 or $perpage=="") {
    $perpage=100;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;

  echo "<p>Forum ids:</p>";

  $forums=$DB_site->query("SELECT forumid FROM forum WHERE forumid>=$startat AND forumid<$finishat ORDER BY forumid DESC");
  while ($forum=$DB_site->fetch_array($forums)) {

    $forumid=$forum['forumid'];

    echo "Processing forum <b>$forumid</b><br>\n";
    flush();

    updateforumcount($forumid);

  }
  if ($checkmore=$DB_site->query_first("SELECT forumid FROM forum WHERE forumid>=$finishat")) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=updateforum&amp;startat=$finishat&amp;perpage=$perpage");
    echo "<p><a href=\"misc.php?s=$session[sessionhash]&amp;action=updateforum&amp;startat=$finishat&amp;perpage=$perpage\">Click here to continue updating forums</a></p>";
  } else {
    echo "<p>Forums updated!</p>";
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }
}

// ###################### Start update threads #######################
if ($action=="updatethread") {

  if (isset($perpage)==0 or $perpage=="") {
    $perpage=2000;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;
  echo "<p>Thread ids:</p>";

  $threads=$DB_site->query("SELECT MIN(post.postid) AS minpost, MAX(post.postid) AS maxpost, thread.threadid, MAX(post.dateline) AS dateline,
 							(COUNT(*)-1) AS posts,
 							  SUM(attachment.visible) AS attachsum
							FROM post,thread
							LEFT JOIN attachment ON attachment.attachmentid=post.attachmentid
							WHERE thread.threadid=post.threadid
							 AND thread.threadid>=$startat
							 AND thread.threadid<$finishat
							GROUP BY thread.threadid
							ORDER BY threadid DESC");
  while ($thread=$DB_site->fetch_array($threads)) {

    $threadid=$thread['threadid'];

    echo "Processing thread <b>$threadid</b><br>\n";
    flush();
    $attachsum=$thread['attachsum'];
    $numberposts=$thread['posts'];
    $lastpost=$thread['dateline'];
    $firstusername=$DB_site->query_first("SELECT username,userid,dateline FROM post WHERE postid=$thread[minpost]");
    $firstuserid=$firstusername['userid'];
    $firstposttime=$firstusername['dateline'];

    if ($firstusername['userid']==0) {
      $firstusername=$firstusername['username'];
    } else {
      $users=$DB_site->query_first("SELECT username FROM user WHERE userid=$firstusername[userid]");
      $firstusername=$users['username'];
    }

    $lastusername=$DB_site->query_first("SELECT username,userid FROM post WHERE postid=$thread[maxpost]");
    if ($lastusername['userid']==0) {
      $lastusername=$lastusername['username'];
    } else {
      $users=$DB_site->query_first("SELECT username FROM user WHERE userid=$lastusername[userid]");
      $lastusername=$users['username'];
    }


    $DB_site->query("UPDATE thread SET lastpost=$lastpost,dateline=$firstposttime,replycount=$numberposts,postusername='".vb_escape($firstusername)."', postuserid='$firstuserid', lastposter='".vb_escape($lastusername)."',attach=" . intval($attachsum) . " WHERE threadid=$threadid");
  }
  if ($checkmore=$DB_site->query_first("SELECT threadid FROM thread WHERE threadid>=$finishat")) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=updatethread&amp;startat=$finishat&amp;perpage=$perpage");
    echo "<p><a href=\"misc.php?s=$session[sessionhash]&amp;action=updatethread&amp;startat=$finishat&amp;perpage=$perpage\">Click here to continue updating threads</a></p>";
  } else {
    echo "<p>Threads updated!</p>";
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }

}
// ###################### Start remove orphan threads #######################
if ($action=="removeorphanthreads") {
  if (isset($perpage)==0 or $perpage=="") {
    $perpage=50;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;

  $threads = $DB_site->query("SELECT thread.threadid FROM thread AS thread LEFT JOIN forum AS forum USING(forumid) WHERE forum.forumid IS NULL LIMIT $startat, $perpage");
  while ($thread = $DB_site->fetch_array($threads)) {
    $deleting++;
    deletethread($thread['threadid']);
    echo "<p>Deleting thread $thread[threadid]</p>\n";
    flush();
  }
  if($deleting) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=removeorphanthreads&amp;startat=$finishat&amp;perpage=$perpage");
  } else {
    echo '<p>No orphan threads were found</p>';
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }

}
// ###################### Start remove orphan posts #######################
if ($action=="removeorphanposts") {
  if (isset($perpage)==0 or $perpage=="") {
    $perpage=50;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;

  $posts = $DB_site->query("SELECT post.threadid, post.postid FROM post AS post LEFT JOIN thread AS thread USING(threadid) WHERE thread.threadid IS NULL LIMIT $startat, $perpage");
  while ($post = $DB_site->fetch_array($posts)) {
    $deleting++;
    deletepost($post['postid']);
    echo "<p>Deleting post $post[postid]</p>\n";
    flush();
  }
  if($deleting) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=removeorphanposts&amp;startat=$finishat&amp;perpage=$perpage");
  } else {
    echo '<p>No orphan posts were found</p>';
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }

}
// ###################### Start remove dupe #######################
if ($action=="removedupe") {

  if (isset($perpage)==0 or $perpage=="") {
    $perpage=500;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }
  $finishat=$startat+$perpage;
  echo "<p>Thread ids:</p>";

  $threads=$DB_site->query("SELECT threadid,title,forumid,postusername,dateline FROM thread WHERE threadid>=$startat AND threadid<$finishat ORDER BY threadid");
  while ($thread=$DB_site->fetch_array($threads)) {

    $threadid=$thread['threadid'];

    echo "Processing thread <b>$threadid</b><br>\n";
    flush();

    $deletethreads=$DB_site->query("SELECT threadid FROM thread WHERE title='".vb_escape($thread['title'])."' AND forumid=$thread[forumid] AND postusername='".vb_escape($thread['postusername'])."' AND dateline=$thread[dateline] AND threadid>$thread[threadid]");

    while ($deletethread=$DB_site->fetch_array($deletethreads)) {
      $DB_site->query("DELETE FROM post WHERE threadid=$deletethread[threadid]");
      $DB_site->query("DELETE FROM thread WHERE threadid=$deletethread[threadid]");
      echo " &nbsp;&nbsp;&nbsp; <p>Deleted thread <b>$deletethread[threadid]</b></p>";
    }

  }
  if ($checkmore=$DB_site->query_first("SELECT threadid FROM thread WHERE threadid>=$finishat")) {
    cpredirect("misc.php?s=$session[sessionhash]&amp;action=removedupe&amp;startat=$finishat&amp;perpage=$perpage");
    echo "<p><a href=\"misc.php?s=$session[sessionhash]&amp;action=removedupe&amp;startat=$finishat&amp;perpage=$perpage\">Click here to continue removing duplicates</a></p>";
  } else {
    echo "<p>Duplicate Threads Removed!</p>";
    cpredirect("misc.php?s=$session[sessionhash]",1);
  }

}

// ###################### Start user choices #######################
if ($action=="chooser") {

  doformheader("misc","updateuser");
  maketableheader("Update User's Titles","",0);
  makeinputcode("Number of users to do per cycle: ","perpage","1000");
  doformfooter("Update");

  doformheader("misc", "updateposts");
  maketableheader("Update User's Post Counts</b> - does not add non-posting forums to total. (This will recalculate all of your user's post counts based on their CURRENT posts in the database. Do not run this if you have pruned posts or manually changed post counts and you wish to maintain their current counts.)<b>", "", 0);
  makeinputcode("Number of users to do per cycle: ","perpage","1000");
  doformfooter("Update");

  doformheader("misc","updateforum");
  maketableheader("Update Forums Info</b> - update forum post counts and last thread titles based on the values in the thread table.<b>","",0);
  makeinputcode("Number of forums to do per cycle: ","perpage","100");
  doformfooter("Update");

  doformheader("misc","updatethread");
  maketableheader("Update Thread Info</b> - update thread post counts, original posters, last post date, thread creation date, attachment totals<b>","",0);
  makeinputcode("Number of threads to do per cycle: ","perpage","2000");
  doformfooter("Update");

  doformheader("misc","buildsearchindex");
  maketableheader("Rebuild search index</b> - (very intensive, but rarely needed)<b>","",0);
  makeinputcode("Number of threads to do per cycle: ","perpage",100);
  makeinputcode("Thread number to start at: ","startat",0);
  makeinputcode("Total number of threads to process: <BR>(0 for unlimited)","doprocess",0);
  makeyesnocode("Include automatic JavaScript<BR>redirect to next page?","autoredirect",1);
  echo "<tr class='".getrowbg()."'><td colspan=2><p><i>If you are reindexing, you may want to empty the indexes.</i> <a href=\"misc.php?action=emptyindex\"><b>Click Here to do so!</b></a></td></tr>";
  doformfooter("Re-index");

  doformheader("misc","removeorphanthreads");

  maketableheader("Remove orphan threads</b> - remove threads that have no forum<b>","",0);
  makeinputcode("Number of threads to do per cycle: ","perpage","50");
  doformfooter("Remove");

  doformheader("misc","removeorphanposts");

  maketableheader("Remove orphan posts</b> - remove posts which have no threads<b>","",0);
  makeinputcode("Number of posts to do per cycle: ","perpage","50");
  doformfooter("Remove");

  doformheader("misc","removedupe");

  maketableheader("Remove dupe threads</b> - (relatively intensive, but useful after importing posts)<b>","",0);
  makeinputcode("Number of threads to do per cycle: ","perpage","500");
  doformfooter("Remove");
}

cpfooter();
?>