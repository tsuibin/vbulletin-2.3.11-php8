<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

if ($action=="move" or $action=="prune" or $action=="useroptions") {
  $noheader=1;
}

require("./global.php");

if ($action=="useroptions") {

  $userid = verifyid("user",$userid);

  $permissions=getpermissions();
  if ($permissions['cancontrolpanel']) {
    header("Location: admin/index.php?s=$session[sessionhash]&loc=".urlencode("user.php?s=$session[sessionhash]&action=edit&userid=$userid"));
  } elseif ($permissions['ismoderator'] or $ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canviewprofile=1")) {
    header("Location: mod/index.php?s=$session[sessionhash]&loc=".urlencode("user.php?s=$session[sessionhash]&action=viewuser&userid=$userid"));
  } else {
    show_nopermission();
  }

  exit;
}

if ($action=="move") {

  $forumid = verifyid("forum",$forumid);

  $permissions=getpermissions();
  if ($permissions['cancontrolpanel']) {
    header("Location: admin/index.php?s=$session[sessionhash]&loc=".urlencode("thread.php?s=$session[sessionhash]&action=move"));
  } elseif ($permissions['ismoderator'] or $ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassmove=1")) {
    header("Location: mod/index.php?s=$session[sessionhash]&loc=".urlencode("thread.php?s=$session[sessionhash]&action=move"));
  } else {
    show_nopermission();
  }

  exit;
}

if ($action=="prune") {

  $forumid = verifyid("forum",$forumid);

  $permissions=getpermissions();
  if ($permissions['cancontrolpanel']) {
    header("Location: admin/index.php?s=$session[sessionhash]&loc=".urlencode("thread.php?s=$session[sessionhash]&action=prune"));
  } elseif ($permissions['ismoderator'] or $ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmassprune=1")) {
    header("Location: mod/index.php?s=$session[sessionhash]&loc=".urlencode("thread.php?s=$session[sessionhash]&action=prune"));
  } else {
    show_nopermission();
  }

  exit;
}

if ($action=="modposts") {

  $permissions=getpermissions();
  if ($permissions['ismoderator'] or $ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmoderateposts=1")) {
    header("Location: mod/index.php?s=$session[sessionhash]&loc=".urlencode("moderate.php?s=$session[sessionhash]&action=posts"));
  } else {
    show_nopermission();
  }

}

if ($action=="modattach") {

  $permissions=getpermissions();
  if ($permissions['ismoderator'] or $ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canmoderateattachments=1")) {
    header("Location: mod/index.php?s=$session[sessionhash]&loc=".urlencode("moderate.php?s=$session[sessionhash]&action=attachments"));
  } else {
    show_nopermission();
  }

}

//setup redirects for other options in moderators cp

?>