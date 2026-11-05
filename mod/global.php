<?php
require_once __DIR__ . '/../admin/php8compat.php';
error_reporting(7);

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

if ($_GET['HTTP_POST_VARS']['action'] == $_POST['action']) {
  unset($_POST['action']);
}
$_POST['action'] = trim($_POST['action']);

// ###################### Start init #######################

unset($dbservertype);
unset($debug);
//load config
require('./../admin/config.php');
if ($debug != 1) {
	unset($showqueries);
	unset($explain);
}

// init db **********************
// load db class
$dbclassname="./../admin/db_$dbservertype.php";
require($dbclassname);

$DB_site=new DB_Sql_vb;

$DB_site->appname="vBulletin Mod Control Panel";
$DB_site->appshortname="vBulletin (mcp)";
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
if ($addheaders and !$noheader) {
  // default headers
  header("HTTP/1.0 200 OK");
  header("HTTP/1.1 200 OK");
  header("Content-type: text/html");
}

if ($nocacheheaders and !$noheader) {
  // no caching
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
  header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
  header("Pragma: no-cache");                                   // HTTP/1.0
}

require ("./../admin/functions.php");
require ("./../admin/adminfunctions.php");

require("./../admin/sessions.php");

if ( isset( $redirect ) ) {
	$redirect = htmlspecialchars(str_replace('javascript', 'java script', $redirect));
}

$permissions=getpermissions();

if (!ismoderator() or !$permissions['canview']) {
  cpheader("<title>Moderators control panel</title>");
?><br><br><br>
<table cellpadding="1" cellspacing="0" border="0" class="tblborder" align="center" width="450"><tr><td>
<table cellpadding="4" cellspacing="0" border="0" width="100%">
<?php maketableheader("Please Log in:","login",0,1); ?>
<tr class="firstalt" id="submitrow"><td align="center" nowrap><p>You are either not a valid moderator or have not logged in.</p>
<form action="index.php" method="post" id="submitrow">
<input type="hidden" name="s" value="<?php echo $session['sessionhash']; ?>">
<input type="hidden" name="action" value="login">
<input type="hidden" name="redirect" value="<?php

if ($_SERVER['REQUEST_URI']!="") {
  $redirecturl = $_SERVER['REQUEST_URI'];
} else {
  $redirecturl = $PHP_SELF;
}

if (strpos(" $redirecturl", "?")) {
  $redirecturl .= "&amp;s=$session[sessionhash]";
} else {
  $redirecturl .= "?s=$session[sessionhash]";
}

echo htmlspecialchars($redirecturl);

?>">
<input type="text" name="loginusername">
<input type="password" name="loginpassword">
<input type="submit" value="   Log in   " accesskey="s">
</form>
</td></tr></table>
</td></tr></table>
<p align="center"><font size="1">vBulletin v<?php echo $templateversion ?> Moderator Control Panel</font></p>

<?php
  cpfooter();
  exit;
}

// ###################### Start makemodchoosercode #######################
function makemodchoosercode($selectedid=-1,$forumid=-1,$depth="",$topname="No one",$title="Forum Parent",$displaytop=1) {
  // $selectedid: selected forum id; $forumid: forumid to begin with;
  // $depth: character to prepend deep forums; $topname: name of top level forum (ie, "My BB", "Top Level", "No one");
  // $title: label for the drop down (listed to the left of it); $displaytop: display top level forum (0=no; 1=yes)

  global $DB_site,$bgcounter;

  if ($forumid==-1) {
    echo "<tr class='".iif($bgcounter++%2==0,"firstalt","secondalt")."'>\n<td><p>$title</p></td>\n<td><p><select name=\"parentid\" size=\"1\">\n";
    if ($displaytop==1) {
      echo "<option value=\"-1\" ".iif($selectedid==$forumid,"SELECTED","").">$depth$topname</option>\n";
    }
  } elseif (ismoderator($forumid,"canmanagethreads")) {
	$foruminfo=$DB_site->query_first("SELECT forumid,title FROM forum WHERE forumid=$forumid");
	echo "<option value=\"$foruminfo[forumid]\" ".iif($selectedid==$forumid,"SELECTED","").">$depth$foruminfo[title]</option>\n";
  }

  $depth.="--";

  $forums=$DB_site->query("SELECT forumid FROM forum WHERE parentid=$forumid ORDER BY displayorder");
  while ($forum=$DB_site->fetch_array($forums)) {
    makemodchoosercode($selectedid,$forum['forumid'],$depth);
  }

  if ($forumid==-1) {
    echo "</select>\n</p></td>\n</tr>\n";
  }

}


?>