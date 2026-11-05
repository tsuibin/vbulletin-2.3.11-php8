<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);
if (isset($_GET['explain']) OR isset($_POST['explain'])) {
	
  $showqueries = 1;
  $explain = 1;
}
if (isset($_GET['showqueries']) OR isset($_POST['showqueries']) or isset($showqueries)) {
  $showqueries = 1;
  $pagestarttime = microtime();
} else {
  $pagestarttime = 0;
}

$incp=1;

// allow script to work with registerglobals off
if ( function_exists('ini_get') ) {
	$onoff = ini_get('register_globals');
} else {
	$onoff = get_cfg_var('register_globals');
}
if ($onoff != 1) {
	@extract($_SERVER, EXTR_SKIP);
	@extract($_COOKIE, EXTR_SKIP);
	@extract($_FILES, EXTR_SKIP);
	@extract($_POST, EXTR_SKIP);
	@extract($_GET, EXTR_SKIP);
	@extract($_ENV, EXTR_SKIP);
}

// Force numeric request parameters to integers before any SQL is built.
vb_cast_request_ids();

// Magic quotes were removed in PHP 5.4; on PHP 8 request data is never escaped.

// version numbers:
$codeversionnumber="2.3.11";
$codeinfo="";

// initialise variables
unset($forumcache);
unset($threadcache);
unset($postcache);
unset($urlSearchArray);
unset($urlReplaceArray);
unset($emailSearchArray);
unset($emailReplaceArray);
unset($iforumcache);
unset($ipermcache);
unset($iaccesscache);
unset($usergroupdef);
unset($noperms);
unset($usergroupcache);
unset($vars);
unset($usercache);
unset($forumarraycache);
unset($permscache);
unset($foruminfo);

if ($_GET['HTTP_POST_VARS']['action'] == $_POST['action']) {
  unset($_POST['action']);
}
$_POST['action'] = trim($_POST['action']);

if ($_GET['HTTP_COOKIE_VARS']['bbadminon'] == $_COOKIE['bbadminon']) {
  unset($_POST['action']);
}

// ###################### Start init #######################

unset($dbservertype);
unset($debug);
//load config
require("./config.php");
if ($debug != 1) {
	unset($showqueries);
	unset($explain);
}

// init db **********************
// load db class
$dbservertype = strtolower($dbservertype);
$dbclassname="./db_$dbservertype.php";
require($dbclassname);

$DB_site=new DB_Sql_vb;

$DB_site->appname="vBulletin Control Panel";
$DB_site->appshortname="vBulletin (cp)";
$DB_site->database=$dbname;
$DB_site->server=$servername;
$DB_site->user=$dbusername;
$DB_site->password=$dbpassword;

$DB_site->connect();

$dbpassword="";
$DB_site->password="";
// end init db

// load options
$optionstemp=$DB_site->query_first("SELECT template FROM template WHERE title='options'");
eval($optionstemp['template']);
$versionnumber=$templateversion;

// ###################### Start headers #######################
/*
$noheader=1;
if ($addheaders and !$noheader) {
  // default headers
  header("HTTP/1.0 200 OK");
  header("HTTP/1.1 200 OK");
  header("Content-type: text/html");
}
*/

if ($nocacheheaders and !$noheader) {
  // no caching
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
  header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
  header("Pragma: no-cache");                                   // HTTP/1.0
}

// ###################### Start functions #######################
require("./functions.php");
require("./adminfunctions.php");

// ###################### Start sessions #######################
/*if (!isset($bbadminon) and !$bbadminon) {
  $sessionhash="";
  $bbuserinfo[userid]="";
  $bbuserinfo[password]="";
}*/

require("./sessions.php");

/*
if (isset($loginusername)) {
  if ($user=$DB_site->query_first("SELECT userid,password FROM user WHERE username='".vb_escape($loginusername)."'")) {
    if ($user[password]==$password) {
      $userid=$user[userid];
      $bbuserinfo=getuserinfo($userid);
      $DB_site->query("UPDATE session SET userid='$userid' WHERE sessionhash='$session[dbsessionhash]'");
    }
  }
}
*/

if ( isset( $redirect ) ) {
	$redirect = htmlspecialchars(str_replace('javascript', 'java script', $redirect));
}

$getperms=$DB_site->query_first("SELECT cancontrolpanel FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND user.userid='$bbuserinfo[userid]'");
if ($getperms['cancontrolpanel']!=1) {
  $bbuserinfo['userid']=0;
}

if ($bbuserinfo['userid']!=0 and $loginusername and !$createanonsession) {
  vbsetcookie('bbadminon', 1, 0);
  $_COOKIE['bbadminon']=1;
} else {
  if ($bbuserinfo['userid']==0) {
    $_COOKIE['bbadminon']=0;
  }
}

if ($debug!=1) {
  // check for files existance. Potential security risks!
	if (file_exists("install.php")==1) {
		echo "<html><body><p>Security alert! install.php still remains in the admin directory. This poses a security risk, so please delete that file immediately. You cannot access the control panel until you do.</p></body></html>";
		exit;
	}

	if (file_exists("upgrade1.php")==1 and substr($PHP_SELF,-strlen("upgrade1.php"))!="upgrade1.php") {
		echo "<html><body><p><a href=\"upgrade1.php?s=$session[sessionhash]\">upgrade1.php</a> exists. If you have already upgraded fully, please delete it. Otherwise, run it now.</p></body></html>";
		exit;
	}
}

$checkpwd=1;
if ($_COOKIE['bbadminon']==0 and substr($PHP_SELF,-strlen("upgrade1.php"))!="upgrade1.php" and $checkpwd) {
  $bbuserinfo['userid']=0;
} else {
  if ($bbuserinfo['userid']!=0  and $loginusername and !$createanonsession) {
    setcookie("bbadminon",1,0,'/');
    $_COOKIE['bbadminon']=1;
  }
}

if ($bbuserinfo['userid']==0 and $checkpwd) {

  cpheader("<title>Forums admin</title>");
?><br><br><br>
<table cellpadding="1" cellspacing="0" border="0" class="tblborder" align="center" width="450"><tr><td>
<table cellpadding="4" cellspacing="0" border="0" width="100%">
<?php maketableheader("Please Log in:","login",0,1); ?>
<tr class="firstalt" id="submitrow"><td align="center" nowrap><p>You are either not a valid administrator or have not logged in.</p>
<form action="../admin/index.php" method="post" id="submitrow">
<input type="hidden" name="s" value="<?php echo $session['sessionhash']; ?>">
<input type="hidden" name="action" value="login">
<!-- <input type="hidden" name="explain" value="1"> -->
<input type="hidden" name="redirect" value="<?php

if ($_SERVER['REQUEST_URI']!="") {
  $url = $_SERVER['REQUEST_URI'];
} else {
  if ($PATH_INFO) {
    $url = $PATH_INFO;
  } else {
    $url = $PHP_SELF;
  }

  if ($QUERY_STRING) {
    $url .= "?$QUERY_STRING";
  }
}

  $url=ereg_replace("sessionhash=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?sessionhash=[a-z0-9]{32}","",$url);
  $url=ereg_replace("s=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?s=[a-z0-9]{32}","",$url);

echo htmlspecialchars(str_replace('javascript', 'java script', $url));

?>">
<table cellpadding="0" cellspacing="1" border="0">
<tr>
	<td><input type="text" name="loginusername"></td>
	<td><input type="password" name="loginpassword"></td>
	<td><input type="submit" value="   Log in   " accesskey="s"></td>
</tr>
<tr>
	<td><font size="1">Username</font></td>
	<td colspan="2"><font size="1">Password</font></td>
</tr>
</table>
</form>
</td></tr></table>
</td></tr></table>
<p align="center"><font size="1">vBulletin v<?php echo $templateversion ?> Administrator Control Panel</font></p>
<?php
  cpfooter();
  exit;
}

?>