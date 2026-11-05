<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "sendtofriend,email_sendtofriend,redirect_sentemail";

require("./global.php");

if (!isset($action) or $action=="") {
  $action="showsend";
}

if (!$enableemail) {
  eval("standarderror(\"".gettemplate("error_emaildisabled")."\");");
  exit;
}

$threadid = verifyid("thread",$threadid);
$threadinfo=getthreadinfo($threadid);
$permissions=getpermissions($threadinfo['forumid']);
if (!$permissions['canview'] or !$permissions['canemail']) {
  show_nopermission();
}

updateuserforum($threadinfo['forumid']);

if ($action=="showsend") {

  if ($wordwrap!=0) {
    $threadinfo['title']=dowordwrap($threadinfo['title']);
  }

  eval("dooutput(\"".gettemplate("sendtofriend")."\");");

}

if ($_POST['action']=="sendfriend") {

  if ($sendtoname=="" or $sendtoemail=="" or $emailsubject=="" or $emailmessage=="") {
    eval("standarderror(\"".gettemplate("error_requiredfields")."\");");
    exit;
  }

  $username = $_POST['username'];
  $password = $_POST['password'];
  $emailsubject = preg_replace('#[\n\t\r,]#s', ' ', $emailsubject);

  if (isset($username)) {
    if (!trim($username)) {
      eval("standarderror(\"".gettemplate("error_nousername")."\");");
    }
    if ($userinfo=$DB_site->query_first("SELECT user.*,userfield.* FROM user,userfield WHERE username='".vb_escape(htmlspecialchars($username))."' AND user.userid=userfield.userid")) {
      if (!$password) {
        eval("standarderror(\"".gettemplate("error_usernametaken")."\");");
      } elseif (md5($password)!=$userinfo['password']) {
        eval("standarderror(\"".gettemplate("error_wrongpassword")."\");");
      } else {
        $bbuserinfo = $userinfo;
        $postusername = $bbuserinfo['username'];

        if ($user['cookieuser']==1) {
           vbsetcookie("bbuserid",$user['userid'], true, true);
           vbsetcookie("bbpassword",$user['password'], true, true);
        }
        $DB_site->query("UPDATE session SET userid='$bbuserinfo[userid]' WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");
      }
    } else {
      $postusername = htmlspecialchars($username);
    }
  } else {
    $postusername = $bbuserinfo['username'];
  }

  eval("\$message = \"".gettemplate("email_sendtofriend",1,0)."\";");

  vbmail($sendtoemail, $emailsubject, $message);

  eval("standardredirect(\"".gettemplate("redirect_sentemail")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=".intval($threadid)."\");");
}

?>