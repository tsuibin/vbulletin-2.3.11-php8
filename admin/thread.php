<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

//suppress gzipping
$nozip=1;

require("./global.php");

adminlog(iif($forumid!=0,"forum id = $forumid",""));

cpheader();

// ###################### Do who voted ####################
if ($_POST['action'] == "dovotes") {

	$pollid = intval($pollid);
	$poll = $DB_site->query_first("
		SELECT poll.*, thread.threadid, thread.title
		FROM poll
		LEFT JOIN thread USING(pollid)
		WHERE poll.pollid='$pollid'
	");
	echo "<p>Poll: <b>
	<a href=\"../poll.php?s=$session[sessionhash]&amp;action=showresults&amp;pollid=$poll[pollid]\" target=\"_blank\">$poll[question]</a>
	</b> in thread: <b>
	<a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$poll[threadid]\" target=\"_blank\">$poll[title]</a>
	</b></p>\n";
	$options = explode("|||",$poll['options']);

	$votes = $DB_site->query("
		SELECT pollvote.*, user.username
		FROM pollvote
		LEFT JOIN user ON(user.userid=pollvote.userid)
		WHERE pollid='$pollid' ORDER BY voteoption, username ASC
	");

	$lastoption = 0;
	$users = '';

	doformheader("","");
	maketableheader("$poll[question] </b>(Poll ID: $poll[pollid])","",0);

	while ($vote = $DB_site->fetch_array($votes)) {

		if ($vote['username']=="") {
			$username = "<font size='1'>Guest</font>";
		} else {
			$username = "<a href=\"../member.php?s=$session[sessionhash]&amp;action=getinfo&amp;userid=$vote[userid]\" target=\"_blank\">$vote[username]</a>";
		}

		if ($lastoption != $vote['voteoption']) {
			$option = $options[($vote['voteoption']-1)];
			echo "</td></tr>\n<tr class='".getrowbg()."'><td><b>$option</b></td><td>\n";
			echo "$username &nbsp;\n";
		} else {
			echo "$username &nbsp;\n";
		}

		$lastoption = $vote['voteoption'];
	}

	echo "</table></td></tr></table></form><hr size='1' noshade>";

	$action = "votes";
}

// ###################### Start who voted ####################
if ($action=="votes") {

// JAVASCRIPT CODE
?>
<script language="JavaScript">
function JSthreadTitle(formid,threadid) {
	if (threadid) {
		formid.threadtitle.value = t[threadid];
	}
}
t = new Array();
<?php
// END JAVASCRIPT CODE

	$polloptions = '';
	$polls = $DB_site->query("
		SELECT poll.pollid, poll.question, thread.title
		FROM thread
		LEFT JOIN poll ON (thread.pollid=poll.pollid) 
        	WHERE thread.open<>10 AND thread.pollid<>0 
		ORDER BY thread.dateline DESC
	");
	while ($poll = $DB_site->fetch_array($polls)) {
		if (!$firsttitle) {
			$firsttitle = $poll['title'];
		}
		$polloptions .= "<option value=\"$poll[pollid]\">[$poll[pollid]] $poll[question]</option>\n";
		echo "t[$poll[pollid]] = \"$poll[title]\";\n";
	}

	echo "</script>\n\n";

	doformheader("thread","dovotes");
	maketableheader("Who voted in poll x?");
	makelabelcode("Poll Question","<select name=\"pollid\" onchange=\"JSthreadTitle(this.form,this.options[this.selectedIndex].value)\">$polloptions</select>");
	makelabelcode("(from thread:)","<input type=\"text\" size=\"50\" name=\"threadtitle\" value=\"$firsttitle\" readonly disabled>");
	doformfooter("List Votes",0);
}

// ###################### Start Prune #######################
if ($action=="prune") {

  doformheader("thread","prunedate");
  maketableheader("Prune by date");
  makeinputcode("Delete threads with last post older than x days:<BR>(intensive if deleting a lot of threads)","daysdelete","0");
  //makeforumchoosercode("Forum","forumid",-1,"All forums");
  makeforumchooser("forumid",-1,-1,"","----- all -----","Forum:");
  makeyesnocode("Include sub forums","subforums");
  doformfooter("Prune");

  doformheader("thread","pruneuser");
  maketableheader("Prune by username");
  makeinputcode("Username","username");
  //makeforumchoosercode("Forum","forumid",-1,"All forums");
  makeforumchooser("forumid",-1,-1,"","----- all -----","Forum:");
  makeyesnocode("Include sub forums","subforums");
  doformfooter("Prune");

}

// ###################### Start Prune by date #######################
if ($action=="prunedate") {
  if ($daysdelete=="") {
    echo "<p>Please enter an age of post to delete</p>";
    exit;
  }

  if ($confirm!=1) {

    if ($forumid==-1) {
      $forumtitle="all forums";
    } else {
      $forum=$DB_site->query_first("SELECT title FROM forum WHERE forumid=$forumid");
      $forumtitle="the \"$forum[title]\" forum";
      if ($subforums) {
        $forumtitle.=" and sub forums";
      }
    }

    doformheader("thread","prunedate");
	 maketableheader("Prune All Threads Automatically");
	 makehiddencode("forumid", "$forumid");
	 makehiddencode("daysdelete", "$daysdelete");
	 makehiddencode("subforums", "$subforums");
	 makehiddencode("confirm", "1");
	 doformfooter("Click Here to Prune All Threads Automatically","",2);

	 doformheader("thread","prunedatesel");
	 maketableheader("Prune Threads Selectively");
	 makehiddencode("forumid", "$forumid");
	 makehiddencode("daysdelete", "$daysdelete");
	 makehiddencode("subforums", "$subforums");
	 doformfooter("Click Here to Prune Threads Selectively","",2);

    //<a href=\"thread.php?s=$session[sessionhash]&amp;action=prunedatesel&amp;forumid=$forumid&amp;daysdelete=$daysdelete&amp;subforums=$subforums\"><b>here</b></a> to select those to delete.</p>";
    exit;
  }

  if ($forumid!=-1) {
    if ($subforums) {
      $forumcheck="(thread.forumid=$forumid OR INSTR(parentlist,',$forumid,')>0) AND ";
    } else {
      $forumcheck="thread.forumid=$forumid AND ";
    }
  }

  $datecut=time()-($daysdelete*86400);
  $threads=$DB_site->query("SELECT threadid FROM thread LEFT JOIN forum USING (forumid) WHERE $forumcheck thread.lastpost<=$datecut AND thread.sticky=0");
  while ($thread=$DB_site->fetch_array($threads)) {
    deletethread($thread['threadid'],0);
  }

  echo "<p>Posts deleted successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start Prune by date selector #######################
if ($action=="prunedatesel") {

  doformheader("thread","doprunedate");

  if ($forumid!=-1) {
    if ($subforums) {
      $forumcheck="(thread.forumid=$forumid OR INSTR(parentlist,',$forumid,')>0) AND ";
    } else {
      $forumcheck="thread.forumid=$forumid AND ";
    }
  }

  echo "<tr class='tblhead'><td><font size='1'><b><span class='tblhead'>Thread Title</span></b></font></td><td><font size='1'><b><span class='tblhead'>Delete?</span></b></font></td></tr>\n";

  $datecut=time()-($daysdelete*86400);
  $threads=$DB_site->query("SELECT threadid,thread.title FROM thread LEFT JOIN forum USING (forumid) WHERE $forumcheck thread.lastpost<=$datecut ORDER BY thread.lastpost DESC");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("<a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","delete[$thread[threadid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start Prune by date selected #######################
if ($_POST['action']=="doprunedate") {

  echo "<p>Deleting...</p>";

  while (list($key,$val)=each($delete)) {
    if ($val==1) {
      deletethread($key,0);
    }
  }

  echo "<p>Threads deleted successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start Prune by user #######################
if ($action=="pruneuser") {
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
      if ($subforums) {
        $forumtitle.=" and sub forums";
      }
    }
    echo "<p>You are about to delete all posts and threads from $forumtitle by one of these users. Please select one:</p>";

//    $users=$DB_site->query("SELECT userid,username FROM user WHERE INSTR(username,'".vb_escape($username)."')>0 ORDER BY username");
    $users=$DB_site->query("SELECT userid,username FROM user WHERE username like '%".vb_escape($username)."%' ORDER BY username");
    while ($user=$DB_site->fetch_array($users)) {

      doformheader("thread","pruneuser");
	   maketableheader("Prune All of \"$user[username]'s\" Posts Automatically","",0,"");
	   makehiddencode("forumid", "$forumid");
	   makehiddencode("userid", "$user[userid]");
	   makehiddencode("subforums", "$subforums");
	   makehiddencode("confirm", "1");
	   doformfooter("Click Here to Prune All of &quot;$user[username]'s&quot; Posts Automatically","",2);

	   doformheader("thread","pruneusersel");
	   maketableheader("Prune \"$user[username]'s\" Posts Selectively","",0,"");
	   makehiddencode("forumid", "$forumid");
	   makehiddencode("userid", "$user[userid]");
	   makehiddencode("subforums", "$subforums");
	   makehiddencode("confirm", "1");
	   doformfooter("Click Here to Prune &quot;$user[username]'s&quot; Posts Selectively","",2);

      echo "\n<hr>\n";

      //<a href=\"thread.php?s=$session[sessionhash]&amp;action=pruneusersel&amp;forumid=$forumid&amp;userid=$user[userid]&amp;confirm=1&amp;subforums=$subforums\"><b>here</b></a> to select which posts to delete by <i>$user[username]</i>.</p>";
    }

    exit;
  }

  if ($forumid!=-1) {
    if ($subforums) {
      $forumcheck="(thread.forumid=$forumid OR INSTR(parentlist,',$forumid,')>0) AND ";
    } else {
      $forumcheck="thread.forumid=$forumid AND ";
    }
  }

  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid=$userid");
  $username=$usernames['username'];

  $posts=$DB_site->query("SELECT postid FROM post,thread LEFT JOIN forum USING (forumid) WHERE $forumcheck post.threadid=thread.threadid AND post.userid=$userid");
  while ($post=$DB_site->fetch_array($posts)) {
    deletepost($post['postid']);
  }
  $threads=$DB_site->query("SELECT threadid FROM thread LEFT JOIN forum USING (forumid) WHERE $forumcheck postusername='".vb_escape($username)."'");
  while ($thread=$DB_site->fetch_array($threads)) {
    deletethread($thread['threadid'],0);
  }

  echo "<p>Posts deleted successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start prune by user selector #######################
if ($action=="pruneusersel") {

  doformheader("thread","dopruneuser");
  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid=$userid");
  $username=$usernames['username'];

  if ($forumid!=-1) {
    if ($subforums) {
      $forumcheck="(thread.forumid=$forumid OR INSTR(parentlist,',$forumid,')>0) AND ";
    } else {
      $forumcheck="thread.forumid=$forumid AND ";
    }
  }
  echo "<tr class='tblhead'><td><font size='1'><b><span class='tblhead'>Thread Title</span></b></font></td><td><font size='1'><b><span class='tblhead'>Delete?</span></b></font></td></tr>\n";

  $threads=$DB_site->query("SELECT threadid,thread.title FROM thread LEFT JOIN forum USING (forumid) WHERE $forumcheck postusername='".vb_escape($username)."' ORDER BY thread.lastpost DESC");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("<a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","deletethread[$thread[threadid]]",1);
  }

  echo "
  <tr class='tblhead'><td colspan=\"2\"><hr></td></tr>\n";
  echo "<tr class='tblhead'><td><font size='1'><b><span class='tblhead'>Post</span></b></font></td><td><font size='1'><b><span class='tblhead'>Delete?</span></b></font></td></tr>\n";

  $threads=$DB_site->query("SELECT post.postid,thread.threadid,thread.title FROM post,thread LEFT JOIN forum USING (forumid) WHERE thread.threadid=post.threadid AND $forumcheck post.userid=$userid ORDER BY post.threadid DESC, post.dateline DESC");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("<a href=\"../showthread.php?s=$session[sessionhash]&amp;postid=$thread[postid]#post$thread[postid]\" target=_blank>$thread[title]</a> (postid $thread[postid])","deletepost[$thread[postid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start Prune by user selected #######################
if ($_POST['action']=="dopruneuser") {
  echo "<p>Deleting...</p>";

  if (is_array($deletethread)) {
    while (list($key,$val)=each($deletethread)) {
      if ($val==1) {
        deletethread($key,0);
      }
    }
  }

  if (is_array($deletepost)) {
    while (list($key,$val)=each($deletepost)) {
      if ($val==1) {
        deletepost($key,0);
      }
    }
  }

  echo "<p>Threads and posts deleted successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start Move #######################
if ($action=="move") {

  echo "<p>Note: This will only move the threads -- it will not copy them or leave a redirect!</p>";

  doformheader("thread","movedate");
  maketableheader("Move by date");
  makeinputcode("Move threads with last post older than x days:","daysmove","0");
  //makeforumchoosercode("Source Forum","forumid",-1,"All forums");
  makeforumchooser("forumid",-1,-1,"","----- all -----","Source Forum:");
  //makeforumchoosercode("Destination Forum","destforumid");
  makeforumchooser("destforumid",-1,-1,"","","Destination Forum:",0);
  makelabelcode("Note: this will not move posts from sub forums!");

  doformfooter("Move");

  doformheader("thread","moveuser");
  maketableheader("Move by username");
  makeinputcode("Username","username");
  //makeforumchoosercode("Source Forum","forumid",-1,"All forums");
  makeforumchooser("forumid",-1,-1,"","----- all -----","Source Forum:");
 //makeforumchoosercode("Destination Forum","destforumid");
  makeforumchooser("destforumid",-1,-1,"","","Destination Forum:",0);
  makelabelcode("Note: this will not move posts from sub forums!");

  doformfooter();

}

// ###################### Start by Move Date #######################
if ($_POST['action']=="movedate") {
  if ($daysmove=="") {
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
    echo "<p>You are about to move all threads from the $forumtitle forum older than $daysmove day(s).";

    doformheader("thread","movedate");
	 maketableheader("Move All Threads Automatically");
	 makehiddencode("forumid", "$forumid");
	 makehiddencode("daysmove", "$daysmove");
	 makehiddencode("destforumid", "$destforumid");
	 makehiddencode("confirm", "1");
	 doformfooter("Click Here to Move All Threads Automatically","",2);

	 doformheader("thread","movedatesel");
	 maketableheader("Move Threads Selectively");
	 makehiddencode("forumid", "$forumid");
	 makehiddencode("daysmove", "$daysmove");
	 makehiddencode("destforumid", "$destforumid");
	 doformfooter("Click Here to Move Threads Selectively","",2);

    exit;
  }

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $datecut=time()-($daysmove*86400);
  $DB_site->query("UPDATE thread SET forumid=$destforumid WHERE $forumcheck thread.lastpost<=$datecut");

  echo "<p>Posts moved successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start Move by date selector #######################
if ($action=="movedatesel") {

  doformheader("thread","domovedate");
  makehiddencode("destforumid",$destforumid);
  maketableheader("Move Threads Selectively");

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $datecut=time()-($daysmove*86400);
  $threads=$DB_site->query("SELECT threadid,title FROM thread WHERE $forumcheck thread.lastpost<=$datecut ORDER BY lastpost DESC");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("<a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=\"_blank\"><i>$thread[title]</i></a>","move[$thread[threadid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start Move by date selected #######################
if ($_POST['action']=="domovedate") {

  echo "<p>Moving...</p>";

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
  echo "<p>Posts moved successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start Move by user #######################
if ($_POST['action']=="moveuser") {
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

    //$users=$DB_site->query("SELECT userid,username FROM user WHERE INSTR(username,'".vb_escape($username)."')>0 ORDER BY username");
    $users=$DB_site->query("SELECT userid,username FROM user WHERE username like '%".vb_escape($username)."%' ORDER BY username");
    while ($user=$DB_site->fetch_array($users)) {

      doformheader("thread","moveuser");
	   maketableheader("Move All of \"$user[username]'s\" Posts Automatically","",0,"");
	   makehiddencode("forumid", "$forumid");
	   makehiddencode("userid", "$user[userid]");
	   makehiddencode("destforumid", "$destforumid");
	   makehiddencode("confirm", "1");
	   doformfooter("Click Here to Move All of &quot;$user[username]'s&quot; Posts Automatically","",2);

	   doformheader("thread","moveusersel");
	   maketableheader("Move \"$user[username]'s\" Posts Selectively","",0,"");
	   makehiddencode("forumid", "$forumid");
	   makehiddencode("userid", "$user[userid]");
	   makehiddencode("destforumid", "$destforumid");
	   makehiddencode("confirm", "1");
	   doformfooter("Click Here to Move &quot;$user[username]'s&quot; Posts Selectively","",2);

      echo "\n<hr>\n";
    }

    exit;
  }

  $forumcheck=iif($forumid!=-1,"forumid=$forumid AND ","");

  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid=$userid");
  $username=$usernames['username'];

  $DB_site->query("UPDATE thread SET forumid=$destforumid WHERE $forumcheck postusername='".vb_escape($username)."'");

  echo "<p>Threads moved successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// ###################### Start move by user selector #######################
if ($action=="moveusersel") {

  doformheader("thread","domoveuser");
  makehiddencode("destforumid",$destforumid);
  $usernames=$DB_site->query_first("SELECT username FROM user WHERE userid=$userid");
  $username=$usernames['username'];

  echo "<tr class='tblhead'><td><font size='1'><b><span class='tblhead'>Thread Title</span></b></font></td><td><font size='1'><b><span class='tblhead'>Move?</span></b></font></td></tr>\n";

  $forumcheck=iif($forumid!=-1,"thread.forumid=$forumid AND ","");

  $threads=$DB_site->query("SELECT threadid,title FROM thread WHERE $forumcheck postusername='".vb_escape($username)."' ORDER BY thread.lastpost DESC");
  while ($thread=$DB_site->fetch_array($threads)) {
    makeyesnocode("<a href=\"../showthread.php?s=$session[sessionhash]&amp;threadid=$thread[threadid]\" target=_blank>$thread[title]</a>","movethread[$thread[threadid]]",1);
  }

  doformfooter("Submit - only click here if you are ABSOLUTELY certain");
}

// ###################### Start move by user selected #######################
if ($_POST['action']=="domoveuser") {
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

  echo "<p>Threads moved successfully! It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update counters</a> now.</p>";
}

// **********************************************************************
// *** POLL STRIPPING SYSTEM - removes a poll from a thread *************
// **********************************************************************

// ###################### Start confirm kill poll #######################
if ($action=="removepoll") {

	$threadid=intval(trim($threadid));
	if (!$threadid) {
		echo "<p><b>No thread id specified!</b></p>";
		$action = "killpoll";
	} else {
		$thread = $DB_site->query_first("
			SELECT thread.threadid,thread.title,thread.postusername,thread.pollid,poll.question
			FROM thread
			LEFT JOIN poll USING(pollid)
			WHERE threadid='$threadid'
		");
		if (!$thread['threadid']) {
			echo "<p><b>Invalid thread id specified</b></p>";
			$action = "killpoll";
		} elseif (!$thread['pollid']) {
			echo "<p><b>No poll found in this thread!</b></p>";
			$action = "killpoll";
		} else {
			doformheader("thread","doremovepoll");
			makehiddencode("threadid",$thread['threadid']);
			makehiddencode("pollid",$thread['pollid']);
			maketableheader("Kill this poll?");
			makelabelcode("Thread Creator:","<i>$thread[postusername]</i>");
			makelabelcode("Thread Title:","<i>$thread[title]</i>");
			makelabelcode("Poll Question","<i>$thread[question]</i>");
			makeyesnocode("Delete the poll from this thread?","dodelete",0);
			doformfooter("Kill it now!",0);
		}
	}
}

// ###################### Start do kill poll #######################
if ($_POST['action']=="doremovepoll") {

	// check valid thread + poll
	$thread = $DB_site->query("SELECT threadid,pollid FROM thread WHERE threadid='".intval($threadid)."' AND pollid='".intval($pollid)."'");
	if ($DB_site->num_rows($thread) && $dodelete==1) {

		echo "<p>Deleting Votes ....\n";
		flush();
			$DB_site->query("DELETE FROM pollvote WHERE pollid='$pollid'");
		echo "votes deleted.</p>\n<p>Deleting Poll ....\n";
		flush();
			$DB_site->query("DELETE FROM poll WHERE pollid='$pollid'");
		echo "poll deleted.</p>\n<p>Updating Thread ....\n";
		flush();
			$DB_site->query("UPDATE thread SET pollid=0 WHERE threadid='$threadid'");
		echo "thread updated.</p>\n<p><b>Poll Removed From Thread.</b><br><br>".
			makelinkcode("click here to view the thread","../showthread.php?s=$session[sessionhash]&amp;threadid=$threadid",1).
			makelinkcode("strip another poll","thread.php?s=$session[sessionhash]&amp;action=killpoll")."</p>\n
		";
		flush();

	} else {
		echo "<p><b>Poll NOT deleted.</b></p>\n";
		$action = "killpoll";
	}

}

// ###################### Start kill poll #######################
if ($action=="killpoll") {

	echo "<p>This function allows you to completely remove a poll from a thread, while leaving the thread contents intact.</p>\n";

	doformheader("thread","removepoll");
	maketableheader("Poll Stripper");
	makeinputcode("Enter the thread id of the thread containing the poll you want to remove","threadid","",0,10);
	doformfooter("Continue",0);

	echo "\n\n<!-- the pun is intended ;o) -->\n\n";
}

// **********************************************************************
// *** UNSUBSCRIPTION SYSTEM - unsubscribe users from thread(s) *********
// **********************************************************************

// ############### generate id list for specified threads ####################
if ($action=="dospecificunsubscribe") {

	$ids = trim($ids);
	if ($ids=="") {
		echo "<p><b>No threads specified!</b></p>";
		$action = "unsubscribe";
	} else {
		$threadids = eregi_replace("[[:space:]]+",",",$ids);
		$action = "confirmunsubscribe";
	}

}

// ############### generate id list for mass-selected threads ####################
if ($action=="domassunsubscribe") {

	$forumid = intval($forumid);
	if ($forumid==-1) {
		unset($forumid);
	}
	$daysprune = intval(trim($daysprune));
	$datecut = time() - (86400 * $daysprune);

	//echo "<pre>forumid = '$forumid'\ndaysprune = '$daysprune'\nids = '$ids'</pre>";

	if ($forumid) {
		$sqlconds .= "\n".iif($sqlconds=="","WHERE","AND")." forumid=$forumid";
	}
	if ($daysprune) {
		$sqlconds .= "\n".iif($sqlconds=="","WHERE","AND")." lastpost<$datecut";
	}

	$query = "SELECT threadid FROM thread $sqlconds";

	//echo "<pre>$query</pre>";

	$threads = $DB_site->query($query);
	if ($DB_site->num_rows($threads)) {
		while ($thread = $DB_site->fetch_array($threads)) {
			$ids .= "$thread[threadid] ";
		}
		$threadids = str_replace(" ", ",", trim($ids));
		$action = "confirmunsubscribe";
	} else {
		echo "<p><b>No threads matched your query!</b></p>";
		$action = "unsubscribe";
	}

}

// ############### generate id list for mass-selected threads ####################
if ($action=="confirmunsubscribe") {

	//echo "<pre>[$threadids]</pre>\n";

	$sub = $DB_site->query_first("SELECT COUNT(*) AS threads FROM subscribethread WHERE threadid IN($threadids)");
	if ($sub['threads']>0) {
		doformheader("thread","killsubscription");
		makehiddencode("threadids",$threadids);
		maketableheader("Confirm Unsubscribe Threads");
		makedescription("Are you <b>sure</b> you want to unsubscribe all users from the ".number_format($sub['threads'])." thread(s) matching your search pattern?");
		doformfooter("Yes, unsubscribe now",0,2,"Oops... no!");
	} else {
		echo "<p><b>No thread subscriptions matched your conditions</b></p>";
		$action = "unsubscribe";
	}

}

// ############### do unsubscribe threads ####################
if ($_POST['action']=="killsubscription") {

	$DB_site->query("DELETE FROM subscribethread WHERE threadid IN($threadids)");

	echo "<p><b>Threads unsubscribed</b></p>";
	$action = "unsubscribe";

}

// ############### unsubscribe threads ####################
if ($action=="unsubscribe") {

	echo "<p>This system allows you to remove all user subscriptions from the thread(s) you specify.</p>\n";

	doformheader("thread","dospecificunsubscribe");
	maketableheader("Unsubscribe users from specific threads");
	maketextareacode("Enter the threadid(s) of the threads from which you want to unsubscribe users.<p><font size='1'>(separate ids with spaces - eg: 1 2 6 9)</font>","ids");
	doformfooter("Unsubscribe Threads");

	doformheader("thread","domassunsubscribe");
	maketableheader("Mass unsubscribe users from threads");
	makeinputcode("Threads older than days:","daysprune",30);
	makeforumchooser("forumid",-1,-1,"","----- all -----","Threads from forum:");
	doformfooter("Unsubscribe Threads");

}

cpfooter();
?>
