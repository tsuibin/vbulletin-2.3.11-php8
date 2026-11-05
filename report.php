<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);
// templates changed: reportbadpost, error_noreason, email_reportbadpost, emailsubject_reportbadpost, redirect_reportthanks,postbix

$templatesused = "reportbadpost,email_reportbadpost,emailsubject_reportbadpost,redirect_reportthanks";

require("./global.php");

if ($bbuserinfo['userid']==0) {
  show_nopermission();
}

if (!$enableemail) {
	eval("standarderror(\"".gettemplate("error_emaildisabled")."\");");
	exit;
}

if (!isset($action) or $action=="") {
  $action="report";
}

if ($action=="report") {
  $postid=verifyid("post",$postid);

  eval("dooutput(\"".gettemplate("reportbadpost")."\");");

}

if ($_POST['action']=="sendemail") {
  $postid=verifyid("post",$postid);

  if (trim($reason)=="") {
    eval("standarderror(\"".gettemplate("error_noreason")."\");");
    exit;
  }

  // get mods for forum
  $post=getpostinfo($postid);
  $thread=getthreadinfo($post['threadid']);
  //$forumlist=getforumlist($thread[forumid],"forumid");
  //$moderators=$DB_site->query("SELECT user.email FROM moderator,user WHERE user.userid=moderator.userid AND $forumlist");

  $foruminfo=getforuminfo($thread['forumid']);
  $moderators=$DB_site->query("SELECT DISTINCT user.email FROM moderator,user WHERE user.userid=moderator.userid AND moderator.forumid IN ($foruminfo[parentlist])");

  $thread['title'] = unhtmlspecialchars($thread['title']);
  $post['title'] = unhtmlspecialchars($post['title']);

  if ($DB_site->num_rows($moderators)==0) {
    // get admins if no mods
    $moderators=$DB_site->query("SELECT user.email FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND (usergroup.cancontrolpanel=1 OR usergroup.ismoderator=1)");
  }

  while ($moderator=$DB_site->fetch_array($moderators)) {
    eval("\$message = \"".gettemplate("email_reportbadpost",1,0)."\";");
    eval("\$subject = \"".gettemplate("emailsubject_reportbadpost",1,0)."\";");

    vbmail($moderator['email'], $subject, $message);
  }

  $url = str_replace("\"", "", $url);
  eval("standardredirect(\"".gettemplate("redirect_reportthanks")."\",\"\$url\");");
}
?>