<?php
require_once __DIR__ . '/admin/php8compat.php';
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

@error_reporting(7);

$incp = 0;

// initialise variables
unset($emailReplaceArray);
unset($emailSearchArray);
unset($forumarraycache);
unset($forumcache);
unset($foruminfo);
unset($iaccesscache);
unset($iforumcache);
unset($ipermcache);
unset($noperms);
unset($permscache);
unset($postcache);
unset($threadcache);
unset($urlReplaceArray);
unset($urlSearchArray);
unset($usercache);
unset($usergroupcache);
unset($usergroupdef);
unset($vars);

function xss_clean ($var) {
	$var = preg_replace( '/javascript/i', 'java script', $var );
	$var = str_replace( '"', '&quot;', $var );
	$var = str_replace( '<', '&lt;', $var );
	return str_replace( '>', '&gt;', $var );
}

// get useful vars
$ipaddress=$REMOTE_ADDR;
$scriptpath = xss_clean( $REQUEST_URI );
if ($scriptpath=='') {
  if ($PATH_INFO) {
    $scriptpath = $PATH_INFO;
  } else {
    $scriptpath = $PHP_SELF;
  }

  if ($QUERY_STRING) {
    $scriptpath .= '?'.addslashes( xss_clean( $QUERY_STRING ) );
  }
}

if ( !isset($url) ) {
  $url = $_SERVER['HTTP_REFERER'];
} else {
  if ($url==$_SERVER['HTTP_REFERER']) {
    $url='index.php';
  }
}

if ($url==$scriptpath or $url=='') {
  $url='index.php';
}
$url = str_replace( '$', '', addslashes( xss_clean( $url ) ) );

if (isset($_POST['url'])) {
	$_POST['url'] = $url;
}
if (isset($_GET['url'])) {
	$_GET['url'] = $url;
}

if ($_GET['HTTP_POST_VARS']['action'] == $_POST['action']) {
  unset($_POST['action']);
}
$_POST['action'] = trim($_POST['action']);

if ( isset( $goto ) ) {
	$goto = xss_clean( $goto );
}

// ###################### Start init #######################

unset($dbservertype);
unset($debug);
//load config
require('./admin/config.php');
if ($debug != 1) {
	unset($showqueries);
	unset($explain);
}

// init db **********************
// load db class
$dbservertype = strtolower($dbservertype);
$dbclassname="./admin/db_$dbservertype.php";
require($dbclassname);

$DB_site=new DB_Sql_vb;

$DB_site->appname='vBulletin';
$DB_site->appshortname='vBulletin (forum)';
$DB_site->database=$dbname;
$DB_site->server=$servername;
$DB_site->user=$dbusername;
$DB_site->password=$dbpassword;

$DB_site->connect();

$dbpassword="";
$DB_site->password="";
// end init db

if ( isset($showqueries) ) {
  echo "<pre>";
}

// ###################### Start functions #######################
if (isset($showqueries))
{
	// start functions parse timer
	echo "Parsing functions.php\n";
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$beforetime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time before: $beforetime\n";
	if (function_exists('memory_get_usage'))
	{
		echo "Memory Before: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
}

require('./admin/functions.php');

if (isset($showqueries))
{
	// end functions parse timer
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$aftertime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time after:  $aftertime\n";
	if (function_exists('memory_get_usage'))
	{
		echo "Memory After: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
	echo "\n<hr />\n\n";
}

// ###################### Start load options #######################
$optionstemp=$DB_site->query_first("SELECT template FROM template WHERE title='options'");
eval($optionstemp['template']);
$versionnumber=$templateversion;

// ###################### Start headers #######################
if ($addheaders and !$noheader) {
  // default headers
  @header("HTTP/1.0 200 OK");
  @header("HTTP/1.1 200 OK");
  @header("Content-type: text/html");
}

if ($nocacheheaders and !$noheader) {
  // no caching
  @header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
  @header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
  @header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
  @header("Pragma: no-cache");                                   // HTTP/1.0
}


// ###################### Start sessions #######################

// start server too busy
$servertoobusy = 0;
if ($loadlimit > 0) {
  $path = '/proc/loadavg';

  if(file_exists($path)) {
    $filesize=filesize($path);
    $filenum=fopen($path,'r');
    $filestuff=@fread($filenum,6);
    fclose($filenum);
  } else {
    $filestuff = '';
  }

  $loadavg=explode(' ',$filestuff);
  if (trim($loadavg[0])>$loadlimit) {
    $servertoobusy=1;
  }
}

if (!$servertoobusy) {
  require('./admin/sessions.php');
} else {
  $session = array();
  $bbuserinfo = array();
}

// figure out the chosen style settings
unset($codestyleid);
unset($style); // prevent some weird issues

//get some ids!
if (isset($postid)) {
  $postid=verifyid('post',$postid,0);
  if ($postid!=0) {
    $getthread=$DB_site->query_first("SELECT threadid FROM post WHERE postid='$postid'");
    $threadid=$getthread['threadid'];
  }
}
if (isset($threadid) and $thread=verifyid("thread",$threadid,0,1)) {
  $threadid = $thread['threadid'];
  if ($threadid!=0) {
    $getforum=$DB_site->query_first("SELECT forum.forumid,styleid,styleoverride FROM forum,thread WHERE forum.forumid=thread.forumid AND threadid='$threadid'");
    if ($getforum['styleoverride']==1 or $bbuserinfo['styleid']<1) {
      $codestyleid=$getforum['styleid'];
    }
  }
}
if (isset($forumid) and !isset($codestyleid)) {
  $getforum=verifyid('forum',$forumid,0,1);
  if ($getforum['styleoverride']==1 or $bbuserinfo['styleid']<1) {
    $codestyleid=$getforum['styleid'];
  }
}
if (isset($pollid) and !isset($codestyleid)) {
  $getforum=$DB_site->query_first("SELECT forum.forumid,styleid,styleoverride FROM forum,thread WHERE forum.forumid=thread.forumid AND pollid='".vb_escape($pollid)."'");
  if ($getforum['styleoverride']==1 or $bbuserinfo['styleid']<1) {
    $codestyleid=$getforum['styleid'];
  }
}

// is style in the forum/thread set?
if (isset($codestyleid) and $codestyleid!=0) {
  $styleid=$codestyleid;
} else {

  // Will look in the user info for a style
  if ($bbuserinfo['styleid']!=0) { //style specified
    $styleid=$bbuserinfo['styleid'];
  } else { //no style
    $styleid=1;
  }

  if ($style=$DB_site->query_first("SELECT templatesetid,replacementsetid,userselect FROM style WHERE styleid='$styleid'")) {
    if (!$style['userselect']) {
      unset($style);
      $styleid=1;
    }
  } else {
    unset($style);
    $styleid=1;
  }
}

if (!isset($style)) {
  $style=$DB_site->query_first("SELECT templatesetid,replacementsetid,userselect FROM style WHERE styleid='".vb_escape($styleid)."' or styleid=1 ORDER BY styleid DESC");
}
//get template set and replacement set details
$templatesetid=$style['templatesetid'];
$replacementsetid=$style['replacementsetid'];

// ###################### Referrer Stuff #########################

// Referer stuff
if ($bbuserinfo['userid']==0 and $usereferrer and !$bbreferrerid and $referrerid) {
  if ($r_id = $DB_site->query_first("SELECT userid FROM user WHERE userid = '".vb_escape($referrerid)."'")) {
    vbsetcookie("bbreferrerid",$r_id['userid']);
  }
}

// ###################### Start templates #######################
//prepare default templates **********************
if ($templatesused!='') {
  $templatesused.=',';
}
$templatesused.='gobutton,timezone,username_loggedout,username_loggedin,phpinclude,headinclude,header,footer,forumjumpbit,forumjump,nav_linkoff,nav_linkon,navbar,nav_joiner';
$templatesused.=',pagenav,pagenav_curpage,pagenav_firstlink,pagenav_lastlink,pagenav_nextlink,pagenav_pagelink,pagenav_prevlink';
unset($templatecache);
cachetemplates($templatesused);

$newpmmsg=0;
$headnewpm='';
if ($checknewpm and $bbuserinfo['userid']!=0 and $bbuserinfo['pmpopup']==2) {
  if ($noshutdownfunc) {
    $DB_site->query("UPDATE user SET pmpopup=1 WHERE userid=$bbuserinfo[userid]");
  } else {
    $shutdownqueries[]="UPDATE LOW_PRIORITY user SET pmpopup=1 WHERE userid=$bbuserinfo[userid]";
  }
  $newpmmsg=1;
  eval("\$headnewpm = \"".gettemplate('head_newpm')."\";");
}

$header='';
$footer='';
$copyrightyear = date('Y');

// parse PHP include ##################
eval(gettemplate('phpinclude',0,0));

// parse css, header & footer ##################
eval("\$headinclude = \"".gettemplate('headinclude')."\";");
eval("\$header .= \"".gettemplate('header')."\";");
eval("\$footer .= \"".gettemplate('footer')."\";");

// parse other global templates

eval("\$gobutton = \"".gettemplate('gobutton')."\";");

$timediff='';
if ($bbuserinfo['timezoneoffset']!=0) {
  if (abs($bbuserinfo['timezoneoffset'])==1) {
    $timediff=" $bbuserinfo[timezoneoffset] hour";
  } else {
    $timediff=" $bbuserinfo[timezoneoffset] hours";
  }
}

$timenow=vbdate($timeformat,time());

eval("\$timezone = \"".gettemplate('timezone')."\";");

// end prepare default templates ********************

// check to see if server is too busy. this is checked at the end of session.php
if ($servertoobusy AND $bbuserinfo['usergroupid'] != 6) {
  $useforumjump = 0; // If load limiting options stop us, we aren't including sessions.php which breakes permissions
  eval("standarderror(\"".gettemplate('error_toobusy')."\");");
  exit;
}

$permissions=getpermissions();

// check that board is active - if not admin, then display error
if (!$bbactive) {
  if (!$permissions['cancontrolpanel']) {
    eval("standarderror(\"".str_replace("\'", "'", str_replace('$', '&#36;', addslashes($bbclosedreason)))."\");");
    exit;
  }
}

if ($_SERVER['SCRIPT_NAME'] and substr($_SERVER['SCRIPT_NAME'] , -strlen('.php')) == '.php') {
	$currentscript = strtolower($_SERVER['SCRIPT_NAME']);
} elseif ($_SERVER['REDIRECT_URL'] and substr($_SERVER['REDIRECT_URL'] , -strlen('.php')) == '.php') {
	$currentscript = strtolower($_SERVER['REDIRECT_URL']);
} else {
	$currentscript = strtolower($_SERVER['PHP_SELF']);
}

if (!$permissions['canview']) {
	if (substr($currentscript,-strlen('register.php'))!='register.php' and substr($currentscript,-strlen('member.php'))!='member.php' and substr($currentscript, -strlen('regimage.php'))!='regimage.php') {
		show_nopermission();
	} elseif ($action!="register" and $action!="signup" and $action!="activate" and $action!="login" and $action!="logout" and $action!="lostpw" and $action!="emailpassword" and $action!="addmember" and $action!="coppaform" and $a!="act" and $a!="ver" and $action!="resetpassword" and $a!="pwd" and !$ih) {
		show_nopermission();
	}
}

checkipban();
$logincode=makelogincode();

?>