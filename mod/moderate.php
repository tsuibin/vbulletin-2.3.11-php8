<?php
require_once __DIR__ . '/../admin/php8compat.php';
error_reporting(7);

require("./global.php");

cpheader();

if ($action=="posts") {

  doformheader("moderate","doposts");
  maketableheader("Threads awaiting validation");

  $sql="";

  $perms=getpermissions();
  if ($perms['ismoderator']) {
    $sql=" OR 1=1";
  } else {
    $forums=$DB_site->query("SELECT forumid FROM forum");

    while ($forum=$DB_site->fetch_array($forums)) {
      if (ismoderator($forum['forumid'],"canmoderateposts")) {
        $sql.=" OR thread.forumid=$forum[forumid]";
      }
    }
  }

  $threads=$DB_site->query("SELECT threadid,thread.title as title, thread.notes as notes,thread.forumid as forumid, forum.title as forumtitle FROM thread LEFT JOIN forum ON (thread.forumid=forum.forumid) WHERE (1=0 $sql) AND visible=0 ORDER BY thread.lastpost");
  while ($thread=$DB_site->fetch_array($threads)) {

    $post=$DB_site->query_first("SELECT postid,pagetext,dateline,userid FROM post WHERE threadid='$thread[threadid]' ORDER BY dateline");
    $user=$DB_site->query_first("SELECT userid,username FROM user WHERE userid='$post[userid]'");

	makelabelcode("<b>Posted by:</b>","<a href=\"user.php?s=$session[sessionhash]&amp;action=viewuser&amp;userid=$user[userid]\">$user[username]</a>");
	makelabelcode("<b>Located in:</b>","<a href=\"../forumdisplay.php?s=$session[sessionhash]&amp;forumid=$thread[forumid]\" target=\"_blank\">$thread[forumtitle]</a>");
    makeinputcode("Thread:","title[$thread[threadid]]",$thread['title'], 0);
    maketextareacode("Message:","threadpagetext[$thread[threadid]]",$post['pagetext'],4,50);
    makeyesnocode("Validate:","validatethread[$thread[threadid]]",1);
    makeyesnocode("Delete:","deletethread[$thread[threadid]]",0);
    makeinputcode("Thread notes:","notes[$thread[threadid]]",$thread['notes'],50);
    maketableheader("&nbsp;","",0);
    $done=1;
  }
  restarttable();

  maketableheader("Posts awaiting validation");

  $posts=$DB_site->query("SELECT postid,pagetext,post.dateline,userid,thread.title as title,thread.forumid as forumid,forum.title as forumtitle FROM post,thread,forum WHERE thread.threadid=post.threadid AND thread.forumid=forum.forumid AND post.visible=0 AND (1=0 $sql) ORDER BY dateline");
  while ($post=$DB_site->fetch_array($posts)) {

    $user=$DB_site->query_first("SELECT userid,username FROM user WHERE userid='$post[userid]'");
	makelabelcode("<b>Posted by:</b>","<a href=\"user.php?s=$session[sessionhash]&amp;action=viewuser&amp;userid=$user[userid]\">$user[username]</a>");
	makelabelcode("<b>Located in:</b>","<a href=\"../forumdisplay.php?s=$session[sessionhash]&amp;forumid=$post[forumid]\" target=\"_blank\">$post[forumtitle]</a>");
    makeinputcode("Thread:","title[$thread[threadid]]",$post['title']);
    maketextareacode("Message:","postpagetext[$post[postid]]",$post['pagetext'],4,40);
    makeyesnocode("Validate:","validatepost[$post[postid]]",1);
    makeyesnocode("Delete:","deletepost[$post[postid]]",0);
    maketableheader("&nbsp;","",0);

    $done=1;
  }
  restarttable();

  if ($done) {
    doformfooter();
  } else {
    maketableheader("Nothing to moderate!");
  }

}

if ($_POST['action']=="doposts") {

  if (is_array($validatethread)) {
    while (list($key,$val)=each($validatethread)) {
      if ($deletethread[$key]) {
        //delete thread
  			$getforumid=$DB_site->query_first("SELECT forumid FROM thread WHERE threadid='$key'");
        deletethread($key);
        updateforumcount($getforumid['forumid']);
        echo "<p>Deleted thread: $key</p>\n";
      } else {
				if ($val) {
					// check whether moderator of this forum
					$getforumid=$DB_site->query_first("SELECT forumid FROM thread WHERE threadid='$key'");
					if (!ismoderator($getforumid['forumid'],"canmoderateposts")) {
						continue;
					}

					// do queries
					$DB_site->query("UPDATE thread SET visible='$val',title='".vb_escape(htmlspecialchars($title[$key]))."',notes='".vb_escape(htmlspecialchars($notes[$key]))."' WHERE threadid='$key'");
					$post=$DB_site->query_first("SELECT postid FROM post WHERE threadid='$key' ORDER BY dateline");
					$DB_site->query("UPDATE post SET pagetext='".vb_escape($threadpagetext[$key])."' WHERE postid='$post[postid]'");

					$updateforum[$getforumid['forumid']]=1;

					echo "<p>Validated thread: $key</p>\n";

				}
			}
	  }
  }

  $notified = array();
  if (is_array($validatepost)) {
    while (list($key,$val)=each($validatepost)) {
      if ($deletepost[$key]) {
        $thread=$DB_site->query_first("SELECT threadid FROM post WHERE postid='$key'");
	     $getforumid=$DB_site->query_first("SELECT forumid FROM thread WHERE threadid='$thread[threadid]'");
        deletepost($key);
        updatethreadcount($thread['threadid']);
        updateforumcount($getforumid['forumid']);
        echo "<P>Deleted post: $key</p>\n";
		} else {
		  if ($val) {
          $thread=$DB_site->query_first("SELECT threadid, userid FROM post WHERE postid='$key'");
          $getforumid=$DB_site->query_first("SELECT forumid FROM thread WHERE threadid='$thread[threadid]'");
          
          if (!ismoderator($getforumid['forumid'],"canmoderateposts")) {
          	continue;
          }
          
          $DB_site->query("UPDATE post SET pagetext='".vb_escape($postpagetext[$key])."',visible=1 WHERE postid='$key'");
          
          // send notification
          if (!$notified["$thread[threadid]"]) {
            $message = $postpagetext[$key];
            sendnotification($thread['threadid'], $thread['userid'], $key);
            $notified["$thread[threadid]"] = 1;
          }
          
          // update counts
          updatethreadcount($thread['threadid']);
          
          $updateforum[$getforumid['forumid']]=1;
          echo "<p>Validated post: $key</p>\n";
		  }
      }
    }
  }

  if (is_array($updateforum)) {
    while (list($key,$val)=each($updateforum)) {
      updateforumcount($key);
    }
  }

  echo "<p>Threads and posts moderated sucessfully.</p>";

}


// moderate attachments
if ($action=="attachments") {
  $sql="";

  $perms=getpermissions();
  if ($perms['ismoderator']) {
    $sql=" OR 1=1";
  } else {
    $forums=$DB_site->query("SELECT forumid FROM forum");

    while ($forum=$DB_site->fetch_array($forums)) {
      if (ismoderator($forum['forumid'],"canmoderateattachments")) {
        $sql.=" OR thread.forumid=$forum[forumid]";
      }
    }
  }

  doformheader("moderate","doattachments");

  $attachments=$DB_site->query("SELECT attachment.filename, attachment.attachmentid,thread.forumid
                                FROM attachment,post,thread
                                WHERE attachment.visible=0 AND attachment.attachmentid=post.attachmentid AND post.threadid=thread.threadid AND (1=0 $sql)");
  while ($attachment=$DB_site->fetch_array($attachments)) {

  maketableheader("Attachment:</b> ".htmlspecialchars($attachment['filename'])."<b>","",0);

    $extension=strtolower(substr(strrchr($attachment['filename'],"."),1));
    if ($extension=="gif" or $extension=="jpg" or $extension=="jpe" or $extension=="png") {
      $imageurl = "../attachment.php?s=$session[sessionhash]&amp;attachmentid=$attachment[attachmentid]";
      makelabelcode("Image:","<img src=\"$imageurl\" border=0>");
    } else {
      makelabelcode("File name:","<a href=\"../attachment.php?s=$session[sessionhash]&amp;attachmentid=$attachment[attachmentid]\" target=\"_blank\">".htmlspecialchars($attachment['filename'])."</a>");
    }
    makeyesnocode("Validate:","validateattachment[$attachment[attachmentid]]",1);
    makeyesnocode("Delete:","deleteattachment[$attachment[attachmentid]]",0);
    makehrcode();
    $done=1;
  }

  if ($done) {
    doformfooter();
  } else {
    maketableheader("Nothing to do!");
  }

}


if ($_POST['action']=="doattachments") {

	if (is_array($validateattachment)) {
		while (list($key,$val)=each($validateattachment)) {
      if ($deleteattachment[$key]) {
        $DB_site->query("UPDATE post SET attachmentid=0 WHERE attachmentid='$key'");
        $DB_site->query("DELETE FROM attachment WHERE attachmentid='$key'");
        echo "<p>Deleted attachment: $key</p>\n";
      } else {

				if ($val) {
					$getforumid=$DB_site->query_first("SELECT post.threadid,thread.forumid
					                                   FROM post,thread
					                                   WHERE post.attachmentid=$key AND post.threadid=thread.threadid");
					if (!ismoderator($getforumid['forumid'],"canmoderateattachments")) {
						continue;
					}

					$DB_site->query("UPDATE attachment SET visible=1 WHERE attachmentid=$key");
					$DB_site->query("UPDATE thread SET attach = attach + 1 WHERE threadid = $getforumid[threadid]");

					echo "<p>Validated attachment: $val</p>\n";
				}
		  }
		}
  }
  echo "<p>All attachments moderated sucessfully!</p>";
}

cpfooter();

?>