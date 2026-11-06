<html>
<!--
<?php
require_once __DIR__ . '/php8compat.php';

$step=$_GET['step'];
// determine if php is running
if (1==0) {
  echo "-->You are not running PHP - Please contact your system administrator.<!--";
} else {
  echo "--".">";
}

$onvservers=0; // set this to 1 if you're on Vservers and get disconnected after running an ALTER TABLE command

$version = "2.3.11";

error_reporting(7);

if (function_exists("set_time_limit")==1 and get_cfg_var("safe_mode")==0) {
  @set_time_limit(1200);
}

set_magic_quotes_runtime(0);

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

function iif($condition,$truevalue,$falsevalue) {
  if ($condition) {
    return $truevalue;
  } else {
    return $falsevalue;
  }
}

function doqueries() {
  global $DB_site,$query,$explain,$onvservers,$step;

  while (list($key,$val)=each($query)) {
    echo "<p>$explain[$key]</p>\n";
    echo "<"."!-- ".htmlspecialchars($val)." --".">\n\n";
    flush();
    if ($onvservers==1 and substr($val, 0, 5)=="ALTER") {
      $DB_site->reporterror=0;
    }
    $DB_site->query($val);
    if ($onvservers==1 and substr($val, 0, 5)=="ALTER") {
      $DB_site->link_id=0;
      @mysql_close();

      sleep(1);
      $DB_site->connect();

      if ($step!=4) {
        $DB_site->reporterror=1;
      }
    }
  }

  unset ($query);
  unset ($explain);
}

if (!isset($action)) {
?>
<HTML><HEAD>
<link rel="stylesheet" href="../cp.css">
<title>vBulletin <?php echo $version; ?> Install script</title>
<script language="JavaScript">
<!--
function areyousure() {
	if (confirm("You are about to clear ALL data from your database,\nincluding non-vBulletin data.\n\nAre you SURE?")) {
		if (confirm("You have chosen to clear your ENTIRE MySQL database.\nvBulletin and Jelsoft Enterprises Ltd. can hold no responsibility\nfor any loss of data incurred as a result of performing this action.\n\nDo you agree to these terms?")) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}
--<?php ?>>
</script>
</HEAD>
<BODY>
<table width="100%" bgcolor="#3F3849" cellpadding="2" cellspacing="0" border="0"><tr><td>
<table width="100%" bgcolor="#524A5A" cellpadding="3" cellspacing="0" border="0"><tr>
<td><a href="http://vbulletin.com/forum" target="_blank"><img src="cp_logo.gif" width="160" height="49" border="0" alt="Click here to visit the vBulletin support forums"></a></td>
<td width="100%" align="center">
<p><font size="2" color="#F7DE00"><b>vBulletin <?php echo $version; ?> Install Script</b></font></p>
<p><font size="1" color="#F7DE00"><b>(Note: Please be patient as some parts of this may take some time.)</b></font></p>
</td></tr></table></td></tr></table>
<br>
<?php
flush();
}

if ($step=="") {
  $step=1;
}

if ($step==1) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 1
------

+ Introduction
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/
echo "<p>Welcome to vBulletin Version $version. Running this script will do a clean install of vBulletin onto your server.</p>";

/* // these were causing problems. Gotta do more testing

echo "<p>Checking your server against vBulletin's requirements ...</p>\n<p>\n";

$phpversion = phpversion();
echo "PHP version: $phpversion ... ".iif($phpversion>="3.0.9", "Passed!", "<b>Failed!</b>")."<br>\n";
echo "MySQL support in PHP ... ".iif(function_exists("mysql_connect"), "Passed!", "<b>Failed!</b>")."<br>\n";
echo "PCRE support in PHP ... ".iif(function_exists("preg_replace"), "Passed!", "<b>Failed!</b>")."<br>\n";
echo "magic_quotes_sybase disabled ... ".iif(!get_cfg_var("magic_quotes_sybase"), "Passed!", "<b>Failed!</b>")."<br>\n";
$track_vars_check = get_cfg_var("track_vars");
if ($phpversion>="4.0.3") {
  $track_vars_check = 1;
}
echo "track_vars enabled ... ".iif($track_vars_check, "Passed!", "<b>Failed!</b>")."<br>\n";
$reg_globals_check = get_cfg_var("register_globals");
if (floor((float) $phpversion)==3) {
  $reg_globals_check = 1; // 3.0.9 doesn't have register_globals
}
echo "register_globals enabled ... ".iif($reg_globals_check, "Passed!", "<b>Failed!</b>")."<br>\n";

echo "</p>\n<p>Even if one of the above checks \"failed,\" you may be able to proceed without problems. However, it is recommended that you adjust your settings so that all tests pass.</p>\n";

*/

echo "<p><a href=\"install4php5.php?step=".($step+1)."\"><b>Click here to continue with the next step --&gt;</b></a></p>\n";
}  // end step 1

if ($step==2) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 2
------

+ check config.php
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/

$canwrite=@fopen("./config.php","a");
@fclose($canwrite);
$canread=@fopen("./config.php","r");
@fclose($canread);
$fileexists=file_exists("./config.php");
	echo "setp 2";
if ($canwrite==0 and !$fileexists) {
	// file does not exist and cannot write new file
	echo "<p>Cannot find config.php file and cannot create new one automatically.</p>";
	echo "<p>Make sure that you have uploaded it and that it is in the admin directory. It should look something like this:</p>";
?>
<pre>
&lt;?php

/////////////////////////////////////////////////////////////
// Please note that if you get any errors when connecting, //
// that you will need to email your host as we cannot tell //
// you what your specific values are supposed to be        //
/////////////////////////////////////////////////////////////

// type of database running
// (only mysql is supported at the moment)
$dbservertype="mysql";

// hostname or ip of server
$servername="localhost";

// username and password to log onto db server
$dbusername="root";
$dbpassword="";

// name of database
$dbname="forum";

// technical email address - any error messages will be emailed here
$technicalemail = "dbmaster@your-email-address-here.com";

// use persistant connections to the database
// 0 = don't use
// 1 = use
$usepconnect = 1;

?&gt;</pre>
<?php
	echo "<p>Make sure that when you upload the config.php file that there is no spaces before or after the <? ?></p>";

	echo "<p>Once you have uploaded the new config.php file, reload this page.</p>";

  exit;
}

if ($canwrite==0 and $fileexists) {
	// test out config
	include("./config.php");

	echo "<p>Please confirm the details below:</p>\n";
	echo "<p><b>Database server type:</b> $dbservertype</p>\n";
	echo "<p><b>Database server hostname / IP address:</b> $servername</p>\n";
	echo "<p><b>Database username:</b> $dbusername</p>\n";
	echo "<p><b>Database password:</b> $dbpassword</p>\n";
	echo "<p><b>Database name:</b> $dbname</p>\n";
	echo "<p><b>Technical email:</b> <a href=\"mailto:$technicalemail\">$technicalemail</a></p>\n";
	echo "<p>Only continue to the next step if those details are correct. If they are not, please edit your config.php file and reupload it. The next step will test database connectivity.</p>";
  if ($technicalemail=="dbmaster@your-email-address-here.com") {
    echo "<p>Please update your 'Technical email' in your config.php before continuing.</p>";
  } else {
    echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Next step --&gt;</a></p>\n";
  }
}

if ($canwrite!=0 and $fileexists) {
	// test out config
	include("./config.php");

  echo "<form action=\"install4php5.php\" method=\"post\"><input type=hidden name=step value=writeconfig>";
	echo "<p>Please confirm the details below:</p>\n";
	echo "<p><b>Database server type:</b> <input name=\"dbservertype\" value=\"$dbservertype\"></p>\n";
	echo "<p><b>Database server hostname / IP address:</b> <input name=\"servername\" value=\"$servername\"></p>\n";
	echo "<p><b>Database username:</b> <input name=\"dbusername\" value=\"$dbusername\"></p>\n";
	echo "<p><b>Database password:</b> <input name=\"dbpassword\" value=\"$dbpassword\"></p>\n";
	echo "<p><b>Database name:</b> <input name=\"dbname\" value=\"$dbname\"></p>\n";
	echo "<p><b>Technical email:</b> <input name=\"technicalemail\" value=\"$technicalemail\"></a></p>\n";
	echo "<p><input type=submit value=\"Update config.php file\"></form></p>";
  if ($technicalemail!="dbmaster@your-email-address-here.com") {
    echo "<p><form action=\"install4php5.php\" method=get><input type=hidden name=step value=".($step+1)."><input type=submit value=\"Continue without updating values\"></form></p>\n";
  }
}

if ($canwrite!=0 and !$fileexists) {
  echo "<form action=\"install4php5.php\" method=\"post\"><input type=hidden name=step value=writeconfig>";
	echo "<p>Please confirm the details below:</p>\n";
	echo "<p><b>Database server type:</b> <input name=\"dbservertype\" value=\"mysql\"></p>\n";
	echo "<p><b>Database server hostname / IP address:</b> <input name=\"servername\" value=\"localhost\"></p>\n";
	echo "<p><b>Database username:</b> <input name=\"dbusername\" value=\"root\"></p>\n";
	echo "<p><b>Database password:</b> <input name=\"dbpassword\" value=\"\"></p>\n";
	echo "<p><b>Database name:</b> <input name=\"dbname\" value=\"forum\"></p>\n";
	echo "<p><b>Technical email:</b> <input name=\"technicalemail\" value=\"dbmaster@your-email-address-here.com\"></a></p>\n";
	echo "<p><input type=submit value=\"Update config.php file\"></form></p>";
}

}  // end step 2

if ($step=="writeconfig") {

$dbservertype = strtolower($dbservertype);
  //write config file

  if ($technicalemail=="dbmaster@your-email-address-here.com") {
    echo "<P>Please enter a new email address in the technical email field.</p>";
    exit;
  }
  $configfile="<"."?php

/////////////////////////////////////////////////////////////
// Please note that if you get any errors when connecting, //
// that you will need to email your host as we cannot tell //
// you what your specific values are supposed to be        //
/////////////////////////////////////////////////////////////

// type of database running
// (only mysql is supported at the moment)
\$dbservertype='$dbservertype';

// hostname or ip of server
\$servername='$servername';

// username and password to log onto db server
\$dbusername='$dbusername';
\$dbpassword='$dbpassword';

// name of database
\$dbname='$dbname';

// technical email address - any error messages will be emailed here
\$technicalemail='$technicalemail';

// use persistant connections to the database
// 0 = don't use
// 1 = use
\$usepconnect=1;

?".">";

	if (file_exists($path)!=0) {
		unlink($path);
	}
	$filenum=fopen("./config.php","w");
	fwrite($filenum,$configfile);
	fclose($filenum);

  $step=3;
}

if ($step>=3) {
  // step 3 and after, we are ok loading this file
  include("./config.php");
}

if ($step==3) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 3
------

+ attempt database connectivity
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/

echo "<P>Attempting to attach to database...</p>";

// connect to db
// load db class
$dbclassname="./db_$dbservertype.php";
include($dbclassname);

$DB_site=new DB_Sql_vb;

// initialise vars
$DB_site->appname="vBulletin Installer";
$DB_site->appshortname="vBulletin (inst)";
$DB_site->database=$dbname;
$DB_site->server=$servername;
$DB_site->user=$dbusername;
$DB_site->password=$dbpassword;

// allow this script to catch errors
$DB_site->reporterror=0;

$DB_site->connect();
// end init db

$errno=$DB_site->errno;

if ($DB_site->link_id!=0) {

	if ($errno!=0) {
		if ($errno==1049) {
			echo "<p>You have specified a non existent database. Trying to create one now...</p>";
			$DB_site->query("CREATE DATABASE $dbname");
			echo "<p>Trying to connect again...</p>";
			$DB_site->select_db($dbname);

			$errno=$DB_site->geterrno();

			if ($errno==0) {
				echo "<p>Connect succeeded!</p>";
				echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Click here to continue -></a></p>";
			} else {
				echo "<p>Connect failed again! Please ensure that the database and server is correctly configured and try again.</p>";
				echo "<p>Click <a href=\"http://www.vbulletin.com/\"here</a> to go to the vBulletin website</p>";
				exit;
			}
		} else {

			echo "<p>Connect failed: unexpected error from the database.</p>";
			echo "<p>Error number: ".$DB_site->errno."</p>";
			echo "<p>Error description: ".$DB_site->errdesc."</p>";
			echo "<p>Please ensure that the database and server is correctly configured and try again.</p>";
			echo "<p>Click <a href=\"http://www.vbulletin.com/\"here</a> to go to the vBulletin website</p>";
			exit;

		}
	} else {
		// succeeded! yay!
		echo "<p>Connection succeeded! The database already exists.</p>";
		echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Click here to continue --&gt;</a></p>";
		// reset database??
		echo "<p>&nbsp;</p><p>&nbsp;</p><form action=\"install4php5.php\" method=\"get\" onSubmit=\"return areyousure();\">
		<input type=\"hidden\" name=\"step\" value=\"".($step+1)."\"><input type=\"hidden\" name=\"reset\" value=\"1\">
		<table cellpadding=\"10\" cellspacing=\"0\" border=\"0\" bgcolor=\"red\" align=\"center\" width=\"75%\"><tr><td>
		<table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" bgcolor=\"#CCCCCC\"><tr><td align=\"center\">
		If you would like to continue <b>and</b> to erase <b>ALL</b> tables from your database, click the button below.
		This will unconditionally erase the entire contents of your MySQL database,
		<b>INCLUDING</b> any non-vBulletin data!
		<p><input type=\"submit\" value=\"EMPTY YOUR DATABASE\" style=\"background-color:red;color:white;font-weight:bold;font-size:12px\"></p>
		</td></tr></table></td></tr></table></form>";

	}
} else {
  echo "<p>The database has failed to connect because you do not have permission to connect to the server. Please go back to the last step and ensure that you have entered all your login details correctly.</p>";
	echo "<p>Click <a href=\"http://www.vbulletin.com/\"here</a> to go to the vBulletin website</p>";
  exit;
}

}  // end step 3

if ($step>=4) {

  // connect to db
  // load db class
  $dbclassname="./db_$dbservertype.php";
  include($dbclassname);

  if ($onvservers) {
    $usepconnect = 0;
  }

  $DB_site=new DB_Sql_vb;

  // initialise vars
  $DB_site->appname="vBulletin Installer";
  $DB_site->appshortname="vBulletin (inst)";
  $DB_site->database=$dbname;
  $DB_site->server=$servername;
  $DB_site->user=$dbusername;
  $DB_site->password=$dbpassword;

  // allow this script to catch errors
//  $DB_site->reporterror=0;

  $DB_site->connect();
  // end init db
}

if ($step==4) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 4
------

+ reset db
+ set up tables
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/

if ($reset==1) {
	echo "<h1 align=\"center\"><font color=\"Red\">RESET DATABASE?</font></h1>";
	echo "<p align=\"center\">By choosing YES to this action, your ENTIRE database will be cleared.</p>";
	echo "<p align=\"center\"><b>DO NOT</b> choose YES if your database contains<br>any data other than vBulletin data, as this will be<br><b>IRREVERSIBLY DELETED</b>.</p>";
	echo "<p align=\"center\">This is your final chance to prevent your data being deleted!</p>";
	echo "<p align=\"center\"><a href=\"install4php5.php?step=4&amp;resetdatabase=yes\">[ <b>YES</b>, EMPTY THE DATABASE OF <b>ALL</b> DATA ]</a></p>";
	echo "<p align=\"center\"><a href=\"install4php5.php?step=4\">[ <b>NO</b>, DO NOT EMPTY THE DATABASE ]</a></p>";
	echo "<p align=\"center\"><font size=\"1\">vBulletin and Jelsoft Enterprises Ltd. can hold no responsibility for any<br>loss of data incurred as a result of performing this action.</font></p>";
	exit;
}
if ($resetdatabase=="yes") {
	echo "<p>Resetting database...";
	$result=$DB_site->query("SHOW tables");
	while ($currow=$DB_site->fetch_array($result)) {
		$DB_site->query("DROP TABLE IF EXISTS $currow[0]");
	}
	echo "succeeded</p>";
}

$DB_site->reporterror=1;
$query[]="CREATE TABLE access (
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   forumid smallint(5) unsigned DEFAULT '0' NOT NULL,
   accessmask smallint(5) unsigned DEFAULT '0' NOT NULL
)";
$explain[]="Creating table access";
$query[]="ALTER TABLE access ADD UNIQUE (userid,forumid)";
$explain[]="Altering access table";

$query[]="CREATE TABLE adminlog (
   adminlogid int(10) unsigned NOT NULL auto_increment,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   script char(20) NOT NULL,
   action char(20) NOT NULL,
   extrainfo char(200) NOT NULL,
   ipaddress VARCHAR(15) NOT NULL,
   PRIMARY KEY (adminlogid)
)";
$explain[]="Creating table admin log";

$query[]="CREATE TABLE adminutil (
	title VARCHAR (10) not null,
	text MEDIUMTEXT not null,
	PRIMARY KEY (title)
)";

$explain[] = "Creating table admin util";

$query[]="CREATE TABLE announcement (
   announcementid smallint(5) unsigned NOT NULL auto_increment,
   title varchar(250) NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   startdate int(10) unsigned DEFAULT '0' NOT NULL,
   enddate int(10) unsigned DEFAULT '0' NOT NULL,
   pagetext mediumtext NOT NULL,
   forumid smallint(6) DEFAULT '0' NOT NULL,
   PRIMARY KEY (announcementid),
   KEY (forumid)
)";
$explain[]="Creating table announcement";

$query[]="CREATE TABLE attachment (
   attachmentid INT unsigned NOT NULL auto_increment,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   filename varchar(100) NOT NULL,
   filedata longblob NOT NULL,
   visible smallint(5) unsigned DEFAULT '0' NOT NULL,
   counter smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (attachmentid)
)";
$explain[]="Creating table attachment";

$query[]="CREATE TABLE avatar (
   avatarid smallint(5) unsigned NOT NULL auto_increment,
   title char(100) NOT NULL,
   minimumposts smallint(6) DEFAULT '0' NOT NULL,
   avatarpath char(100) NOT NULL,
   PRIMARY KEY (avatarid)
)";
$explain[]="Creating table avatar";

$query[]="CREATE TABLE bbcode (
   bbcodeid smallint(5) unsigned NOT NULL auto_increment,
   bbcodetag varchar(200) NOT NULL,
   bbcodereplacement varchar(200) NOT NULL,
   bbcodeexample varchar(200) NOT NULL,
   bbcodeexplanation mediumtext NOT NULL,
   twoparams smallint(6) DEFAULT '0' NOT NULL,
   PRIMARY KEY (bbcodeid)
)";
$explain[]="Creating table bbcode";

$query[]="CREATE TABLE calendar_events (
   eventid int(10) unsigned NOT NULL auto_increment,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   event mediumtext NOT NULL,
   eventdate date DEFAULT '0000-00-00' NOT NULL,
   public smallint(5) unsigned DEFAULT '0' NOT NULL,
   subject varchar(254) NOT NULL,
   allowsmilies smallint(6) DEFAULT '1' NOT NULL,
   PRIMARY KEY (eventid),
   KEY userid (userid)
)";
$explain[]="Creating table calendar_events";

$query[]="CREATE TABLE customavatar (
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   avatardata longblob NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   filename CHAR(100) NOT NULL,
   PRIMARY KEY (userid)
)";
$explain[]="Creating table custom avatar";

$query[]="CREATE TABLE forum (
   forumid smallint(5) unsigned NOT NULL auto_increment,
   styleid smallint(5) unsigned DEFAULT '0' NOT NULL,
   title char(100) NOT NULL,
   description char(250) NOT NULL,
   active smallint(6) DEFAULT '0' NOT NULL,
   displayorder smallint(6) DEFAULT '0' NOT NULL,
   replycount int(10) unsigned DEFAULT '0' NOT NULL,
   lastpost int(11) DEFAULT '0' NOT NULL,
   lastposter char(50) NOT NULL,
   threadcount mediumint(8) unsigned DEFAULT '0' NOT NULL,
   allowposting tinyint(4) DEFAULT '0' NOT NULL,
   cancontainthreads smallint(6) DEFAULT '0' NOT NULL,
   daysprune smallint(5) unsigned DEFAULT '0' NOT NULL,
   newpostemail char(250) NOT NULL,
   newthreademail char(250) NOT NULL,
   moderatenew smallint(6) DEFAULT '0' NOT NULL,
   moderateattach smallint(6) DEFAULT '0' NOT NULL,
   allowbbcode smallint(6) DEFAULT '0' NOT NULL,
   allowimages smallint(6) DEFAULT '0' NOT NULL,
   allowhtml smallint(6) DEFAULT '0' NOT NULL,
   allowsmilies smallint(6) DEFAULT '0' NOT NULL,
   allowicons smallint(6) DEFAULT '0' NOT NULL,
   parentid smallint(6) DEFAULT '0' NOT NULL,
   parentlist char(250) NOT NULL,
   allowratings smallint(6) DEFAULT '0' NOT NULL,
   countposts smallint(6) DEFAULT '1' NOT NULL,
   styleoverride smallint(5) DEFAULT '0' NOT NULL,
   PRIMARY KEY (forumid)
)";
$explain[]="Creating table forum";

$query[]="CREATE TABLE forumpermission (
   forumpermissionid smallint(5) unsigned NOT NULL auto_increment,
   forumid smallint(5) unsigned DEFAULT '0' NOT NULL,
   usergroupid smallint(5) unsigned DEFAULT '0' NOT NULL,
   canview smallint(6) DEFAULT '0' NOT NULL,
   cansearch smallint(6) DEFAULT '0' NOT NULL,
   canemail smallint(6) DEFAULT '0' NOT NULL,
   canpostnew smallint(6) DEFAULT '0' NOT NULL,
   canmove smallint(6) DEFAULT '0' NOT NULL,
   canopenclose smallint(6) DEFAULT '0' NOT NULL,
   candeletethread smallint(6) DEFAULT '0' NOT NULL,
   canreplyown smallint(6) DEFAULT '0' NOT NULL,
   canreplyothers smallint(6) DEFAULT '0' NOT NULL,
   canviewothers smallint(6) DEFAULT '0' NOT NULL,
   caneditpost smallint(6) DEFAULT '0' NOT NULL,
   candeletepost smallint(6) DEFAULT '0' NOT NULL,
   canpostattachment smallint(6) DEFAULT '0' NOT NULL,
   canpostpoll smallint(6) DEFAULT '0' NOT NULL,
   canvote smallint(6) DEFAULT '0' NOT NULL,
   cangetattachment SMALLINT DEFAULT '1' not null,
   PRIMARY KEY (forumpermissionid),
   KEY ugid_fid (usergroupid, forumid)
)";
$explain[]="Creating table forum permission";

$query[]="CREATE TABLE icon (
   iconid smallint(5) unsigned NOT NULL auto_increment,
   title char(100) NOT NULL,
   iconpath char(100) NOT NULL,
   PRIMARY KEY (iconid)
)";
$explain[]="Creating table icon";

$query[]="CREATE TABLE moderator (
   moderatorid smallint(5) unsigned NOT NULL auto_increment,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   forumid smallint(6) DEFAULT '0' NOT NULL,
   newthreademail smallint(6) DEFAULT '0' NOT NULL,
   newpostemail smallint(6) DEFAULT '0' NOT NULL,
   caneditposts smallint(6) DEFAULT '0' NOT NULL,
   candeleteposts smallint(6) DEFAULT '0' NOT NULL,
   canviewips smallint(6) DEFAULT '0' NOT NULL,
   canmanagethreads smallint(6) DEFAULT '0' NOT NULL,
   canopenclose smallint(6) DEFAULT '0' NOT NULL,
   caneditthreads smallint(6) DEFAULT '0' NOT NULL,
   caneditstyles smallint(6) DEFAULT '0' NOT NULL,
   canbanusers smallint(6) DEFAULT '0' NOT NULL,
   canviewprofile smallint(6) DEFAULT '0' NOT NULL,
   canannounce smallint(6) DEFAULT '0' NOT NULL,
   canmassmove smallint(6) DEFAULT '0' NOT NULL,
   canmassprune smallint(6) DEFAULT '0' NOT NULL,
   canmoderateposts smallint(6) DEFAULT '0' NOT NULL,
   canmoderateattachments smallint(6) DEFAULT '0' NOT NULL,
   PRIMARY KEY (moderatorid),
   KEY userid (userid,forumid)
)";
$explain[]="Creating table moderator";

$query[]="CREATE TABLE poll (
   pollid int(10) unsigned NOT NULL auto_increment,
   question varchar(100) NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   options text NOT NULL,
   votes text NOT NULL,
   active smallint(6) DEFAULT '1' NOT NULL,
   numberoptions smallint(5) unsigned DEFAULT '0' NOT NULL,
   timeout smallint(5) unsigned DEFAULT '0' NOT NULL,
   multiple SMALLINT UNSIGNED DEFAULT '0' not null,
   voters SMALLINT UNSIGNED DEFAULT '0' not null,
   PRIMARY KEY (pollid)
)";
$explain[]="Creating table poll";

$query[]="CREATE TABLE pollvote (
   pollvoteid int(10) unsigned NOT NULL auto_increment,
   pollid int(10) unsigned DEFAULT '0' NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   votedate int(10) unsigned DEFAULT '0' NOT NULL,
   voteoption int(10) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (pollvoteid),
   KEY userid (userid, pollid)
)";
$explain[]="Creating table pollvote";

$query[]="CREATE TABLE post (
   postid int(10) unsigned NOT NULL auto_increment,
   threadid int(10) unsigned DEFAULT '0' NOT NULL,
   username varchar(50) NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   title varchar(100) NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   attachmentid smallint(5) unsigned DEFAULT '0' NOT NULL,
   pagetext mediumtext NOT NULL,
   allowsmilie smallint(6) DEFAULT '0' NOT NULL,
   showsignature smallint(6) DEFAULT '0' NOT NULL,
   ipaddress varchar(16) NOT NULL,
   iconid smallint(5) unsigned DEFAULT '0' NOT NULL,
   visible smallint(6) DEFAULT '0' NOT NULL,
   edituserid int(10) unsigned DEFAULT '0' NOT NULL,
   editdate int(10) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (postid),
   KEY iconid (iconid),
   KEY userid (userid),
   KEY threadid (threadid, userid)
)";
$explain[]="Creating table post";

$query[]="CREATE TABLE privatemessage (
   privatemessageid int(10) unsigned NOT NULL auto_increment,
   folderid smallint(6) DEFAULT '0' NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   touserid int(10) unsigned DEFAULT '0' NOT NULL,
   fromuserid int(10) unsigned DEFAULT '0' NOT NULL,
   title varchar(250) NOT NULL,
   message mediumtext NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   showsignature smallint(6) DEFAULT '0' NOT NULL,
   iconid smallint(5) unsigned DEFAULT '0' NOT NULL,
   messageread smallint(6) DEFAULT '0' NOT NULL,
   readtime INT (10) UNSIGNED DEFAULT '0' not null,
   receipt SMALLINT (6) UNSIGNED DEFAULT '0' not null,
   deleteprompt SMALLINT (6) UNSIGNED DEFAULT '0' not null,
   multiplerecipients SMALLINT (6) UNSIGNED DEFAULT '0' not null,
   PRIMARY KEY (privatemessageid),
   KEY userid (userid)
)";
$explain[]="Creating table private message";

$query[]="CREATE TABLE profilefield (
   profilefieldid smallint(5) unsigned NOT NULL auto_increment,
   title char(50) NOT NULL,
   description char(250) NOT NULL,
   required smallint(6) DEFAULT '0' NOT NULL,
   hidden smallint(6) DEFAULT '0' NOT NULL,
   maxlength smallint(6) DEFAULT '250' NOT NULL,
   size smallint(6) DEFAULT '25' NOT NULL,
   displayorder SMALLINT(6) NOT NULL,
   editable SMALLINT DEFAULT '1' NOT NULL,
   PRIMARY KEY (profilefieldid)
)";
$explain[]="Creating table profile field";

$query[]="CREATE TABLE replacement (
   replacementid smallint(5) unsigned NOT NULL auto_increment,
   replacementsetid smallint(6) DEFAULT '0' NOT NULL,
   findword text NOT NULL,
   replaceword text NOT NULL,
   PRIMARY KEY (replacementid),
   KEY (replacementsetid)
)";
$explain[]="Creating table replacement";

$query[]="CREATE TABLE replacementset (
   replacementsetid smallint(5) unsigned NOT NULL auto_increment,
   title char(250) NOT NULL,
   PRIMARY KEY (replacementsetid)
)";
$explain[]="Creating table replacement set";

$query[]="CREATE TABLE search (
   searchid int(10) unsigned NOT NULL auto_increment,
   query mediumtext NOT NULL,
   postids mediumtext NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   querystring varchar(200) NOT NULL,
   showposts smallint(6) DEFAULT '0' NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   ipaddress varchar(20) NOT NULL,
   PRIMARY KEY (searchid),
   KEY (querystring),
   KEY (userid)
)";
$explain[]="Creating table search";

$query[]="CREATE TABLE searchindex (
   wordid int(10) unsigned DEFAULT '0' NOT NULL,
   postid int(10) unsigned DEFAULT '0' NOT NULL,
   intitle smallint(5) unsigned DEFAULT '0' NOT NULL
)";
$explain[]="Creating table search index";

$query[]="ALTER TABLE searchindex ADD UNIQUE (wordid,postid)";
$explain[]="Altering search index table";

$query[]="CREATE TABLE session (
   sessionhash char(32) NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   host char(50) NOT NULL,
   useragent char(100) NOT NULL,
   lastactivity int(10) unsigned DEFAULT '0' NOT NULL,
   location CHAR (255) not null,
   styleid smallint(5) unsigned DEFAULT '0' NOT NULL,
   althash CHAR (32) NOT NULL,
   PRIMARY KEY (sessionhash)
)";
$explain[]="Creating table session";

$query[]="CREATE TABLE setting (
   settingid smallint(5) unsigned NOT NULL auto_increment,
   settinggroupid smallint(5) unsigned DEFAULT '0' NOT NULL,
   title varchar(100) NOT NULL,
   varname varchar(100) NOT NULL,
   value mediumtext NOT NULL,
   description mediumtext NOT NULL,
   optioncode mediumtext NOT NULL,
   displayorder smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (settingid)
)";
$explain[]="Creating table setting";

$query[]="CREATE TABLE settinggroup (
   settinggroupid smallint(5) unsigned NOT NULL auto_increment,
   title char(100) NOT NULL,
   displayorder smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (settinggroupid)
)";
$explain[]="Creating table setting group";

$query[]="CREATE TABLE smilie (
   smilieid smallint(5) unsigned NOT NULL auto_increment,
   title char(100) NOT NULL,
   smilietext char(10) NOT NULL,
   smiliepath char(100) NOT NULL,
   PRIMARY KEY (smilieid)
)";
$explain[]="Creating table smilie";

$query[]="CREATE TABLE style (
   styleid smallint(5) unsigned NOT NULL auto_increment,
   replacementsetid smallint(5) unsigned DEFAULT '0' NOT NULL,
   templatesetid smallint(5) unsigned DEFAULT '0' NOT NULL,
   title char(250) NOT NULL,
   userselect smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (styleid)
)";
$explain[]="Creating table style";

$query[]="INSERT INTO style VALUES (1, 1, 1, 'Default', 1)";
$explain[]="Inserting data into style table";

$query[]="CREATE TABLE subscribeforum (
   subscribeforumid int(10) unsigned NOT NULL auto_increment,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   forumid smallint(5) unsigned DEFAULT '0' NOT NULL,
   emailupdate smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (subscribeforumid),
   KEY (userid)
)";
$explain[]="Creating table subscribed forum";

$query[]="CREATE TABLE subscribethread (
   subscribethreadid int(10) unsigned NOT NULL auto_increment,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   threadid int(10) unsigned DEFAULT '0' NOT NULL,
   emailupdate smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (subscribethreadid),
   KEY (threadid)
)";
$explain[]="Creating table subscribed thread";

$query[]="CREATE TABLE template (
   templateid smallint(5) unsigned NOT NULL auto_increment,
   templatesetid smallint(6) DEFAULT '0' NOT NULL,
   title varchar(100) NOT NULL,
   template mediumtext NOT NULL,
   PRIMARY KEY (templateid),
   KEY title (title(30),templatesetid)
)";
$explain[]="Creating table template";

$query[]="CREATE TABLE templateset (
   templatesetid smallint(5) unsigned NOT NULL auto_increment,
   title char(250) NOT NULL,
   PRIMARY KEY (templatesetid)
)";
$explain[]="Creating table template set";

$query[]="CREATE TABLE thread (
   threadid int(10) unsigned NOT NULL auto_increment,
   title varchar(100) NOT NULL,
   lastpost int(10) unsigned DEFAULT '0' NOT NULL,
   forumid smallint(5) unsigned DEFAULT '0' NOT NULL,
   pollid int(10) unsigned DEFAULT '0' NOT NULL,
   open tinyint(4) DEFAULT '0' NOT NULL,
   replycount int(10) unsigned DEFAULT '0' NOT NULL,
   postusername varchar(50) NOT NULL,
   postuserid int(10) unsigned DEFAULT '0' NOT NULL,
   lastposter varchar(50) NOT NULL,
   dateline int(10) unsigned DEFAULT '0' NOT NULL,
   views INT(10) UNSIGNED DEFAULT '0' NOT NULL,
   iconid smallint(5) unsigned DEFAULT '0' NOT NULL,
   notes varchar(250) NOT NULL,
   visible smallint(6) DEFAULT '0' NOT NULL,
   sticky smallint(6) DEFAULT '0' NOT NULL,
   votenum smallint(5) unsigned DEFAULT '0' NOT NULL,
   votetotal smallint(5) unsigned DEFAULT '0' NOT NULL,
   attach smallint(5) unsigned DEFAULT '0' NOT NULL,
   PRIMARY KEY (threadid),
   KEY iconid (iconid),
   KEY forumid (forumid, visible, sticky, lastpost)
)";
$explain[]="Creating table thread";

$query[]="CREATE TABLE threadrate (
   threadrateid int(10) unsigned NOT NULL auto_increment,
   threadid int(10) unsigned DEFAULT '0' NOT NULL,
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   vote smallint(6) DEFAULT '0' NOT NULL,
   ipaddress varchar(20) NOT NULL,
   PRIMARY KEY (threadrateid),
   KEY threadid (threadid)
)";
$explain[]="Creating table thread rate";

$query[]="CREATE TABLE user (
   userid int(10) unsigned NOT NULL auto_increment,
   usergroupid smallint(5) unsigned DEFAULT '0' NOT NULL,
   username varchar(50) NOT NULL,
   password varchar(50) NOT NULL,
   email varchar(50) NOT NULL,
   styleid smallint(5) unsigned DEFAULT '0' NOT NULL,
   parentemail varchar(50) NOT NULL,
   coppauser smallint(6) DEFAULT '0' NOT NULL,
   homepage varchar(100) NOT NULL,
   icq varchar(20) NOT NULL,
   aim varchar(20) NOT NULL,
   yahoo varchar(20) NOT NULL,
   signature mediumtext NOT NULL,
   adminemail smallint(6) DEFAULT '0' NOT NULL,
   showemail smallint(6) DEFAULT '0' NOT NULL,
   invisible smallint(6) DEFAULT '0' NOT NULL,
   usertitle varchar(250) NOT NULL,
   customtitle smallint(6) DEFAULT '0' NOT NULL,
   joindate int(10) unsigned DEFAULT '0' NOT NULL,
   cookieuser smallint(6) DEFAULT '0' NOT NULL,
   daysprune smallint(6) DEFAULT '0' NOT NULL,
   lastvisit int(10) unsigned DEFAULT '0' NOT NULL,
   lastactivity int(10) unsigned DEFAULT '0' NOT NULL,
   lastpost int(10) unsigned DEFAULT '0' NOT NULL,
   posts smallint(5) unsigned DEFAULT '0' NOT NULL,
   timezoneoffset varchar(4) NOT NULL,
   emailnotification smallint(6) DEFAULT '0' NOT NULL,
   buddylist mediumtext NOT NULL,
   ignorelist mediumtext NOT NULL,
   pmfolders mediumtext NOT NULL,
   receivepm smallint(6) DEFAULT '0' NOT NULL,
   emailonpm smallint(6) DEFAULT '0' NOT NULL,
   pmpopup smallint(6) DEFAULT '0' NOT NULL,
   avatarid smallint(6) DEFAULT '0' NOT NULL,
   options smallint(6) DEFAULT '15' NOT NULL,
   birthday date DEFAULT '0000-00-00' NOT NULL,
   maxposts smallint(6) DEFAULT '-1' NOT NULL,
   startofweek smallint(6) DEFAULT '1' NOT NULL,
   ipaddress varchar(20) NOT NULL,
   referrerid int(10) unsigned DEFAULT '0' NOT NULL,
   nosessionhash smallint(6) DEFAULT '0' NOT NULL,
   inforum SMALLINT UNSIGNED DEFAULT '0' not null,
   PRIMARY KEY (userid),
   KEY usergroupid (usergroupid),
   KEY username (username),
   INDEX (inforum)
)";
$explain[]="Creating table user";

$query[]="CREATE TABLE userfield (
   userid int(10) unsigned DEFAULT '0' NOT NULL,
   field1 char(250) NOT NULL,
   field2 char(250) NOT NULL,
   field3 char(250) NOT NULL,
   field4 char(250) NOT NULL,
   PRIMARY KEY (userid)
)";
$explain[]="Creating table userfield";

$query[]="CREATE TABLE usergroup (
   usergroupid smallint(5) unsigned NOT NULL auto_increment,
   title char(100) NOT NULL,
   usertitle char(100) NOT NULL,
   cancontrolpanel smallint(6) DEFAULT '0' NOT NULL,
   canmodifyprofile smallint(6) DEFAULT '0' NOT NULL,
   canviewmembers smallint(6) DEFAULT '0' NOT NULL,
   canview smallint(6) DEFAULT '0' NOT NULL,
   cansearch smallint(6) DEFAULT '0' NOT NULL,
   canemail smallint(6) DEFAULT '0' NOT NULL,
   canpostnew smallint(6) DEFAULT '0' NOT NULL,
   canmove smallint(6) DEFAULT '0' NOT NULL,
   canopenclose smallint(6) DEFAULT '0' NOT NULL,
   candeletethread smallint(6) DEFAULT '0' NOT NULL,
   canreplyown smallint(6) DEFAULT '0' NOT NULL,
   canreplyothers smallint(6) DEFAULT '0' NOT NULL,
   canviewothers smallint(6) DEFAULT '0' NOT NULL,
   caneditpost smallint(6) DEFAULT '0' NOT NULL,
   candeletepost smallint(6) DEFAULT '0' NOT NULL,
   canusepm smallint(6) DEFAULT '0' NOT NULL,
   canpostpoll smallint(6) DEFAULT '0' NOT NULL,
   canvote smallint(6) DEFAULT '0' NOT NULL,
   canpostattachment smallint(6) DEFAULT '0' NOT NULL,
   canpublicevent smallint(6) DEFAULT '0' NOT NULL,
   canpublicedit smallint(6) DEFAULT '0' NOT NULL,
   canthreadrate smallint(6) DEFAULT '1' NOT NULL,
   maxbuddypm SMALLINT (6) UNSIGNED DEFAULT '5' NOT NULL,
   maxforwardpm SMALLINT (6) UNSIGNED DEFAULT '5' NOT NULL,
   cantrackpm SMALLINT (6) DEFAULT '1' NOT NULL,
   candenypmreceipts SMALLINT (6) DEFAULT '1' NOT NULL,
   canwhosonline SMALLINT (6) DEFAULT '1' NOT NULL,
   canwhosonlineip SMALLINT (6) DEFAULT '0' NOT NULL,
   ismoderator smallint(6) DEFAULT '0' NOT NULL,
   showgroup SMALLINT UNSIGNED DEFAULT '0' not null,
   cangetattachment SMALLINT DEFAULT '1' not null,
	 PRIMARY KEY (usergroupid),
   INDEX(showgroup)
)";
$explain[]="Creating table usergroup";

$query[]="CREATE TABLE usertitle (
   usertitleid smallint(5) unsigned NOT NULL auto_increment,
   minposts smallint(5) unsigned DEFAULT '0' NOT NULL,
   title char(250) NOT NULL,
   PRIMARY KEY (usertitleid)
)";
$explain[]="Creating table user title";

$query[]="CREATE TABLE word (
   wordid int(10) unsigned NOT NULL auto_increment,
   title char(50) NOT NULL,
   PRIMARY KEY (wordid)
)";
$explain[]="Creating table word";

$query[]="ALTER TABLE word ADD UNIQUE (title)";
$explain[]="Altering word table";

$query[]="CREATE TABLE useractivation (
  useractivationid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT UNSIGNED not null,
  dateline INT UNSIGNED not null,
  activationid CHAR(20) not null,
  type SMALLINT UNSIGNED not null,
  usergroupid SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (useractivationid),
  INDEX (userid,type)
)";
$explain[]="Creating activation numbers' table";

$query[]="CREATE TABLE regimage (
		regimagehash CHAR( 32 ) NOT NULL ,
		imagestamp CHAR( 6 ) NOT NULL ,
		dateline INT UNSIGNED NOT NULL ,
		INDEX (regimagehash, dateline))
	";
$explain[]="Creating Registration Image Table";

doqueries();

if ($DB_site->errno!=0) {
	echo "<p>The script reported errors in the installation of the tables. Only continue if you are sure that they are not serious.</p>";
	echo "<p>The errors were:</p>";
	echo "<p>Error number: ".$DB_site->errno."</p>";
	echo "<p>Error description: ".$DB_site->errdesc."</p>";
} else {
	echo "<p>Tables set up successfully.</p>";
}

echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Next step --&gt;</a></p>\n";
}  // end step 4

if ($step==5) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 5
------

+ add default data
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/

// do bb codes
$query[]="INSERT INTO bbcode VALUES (NULL,'b','<b>\\\\4</b>','[b]Bold[/b]','The [b] tag allows you to write text bold',0)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'i','<i>\\\\4</i>','[i]Italics[/i]','The [i] tag allows you to write text in italics',0)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'email','<a href=\"mailto:\\\\4\">\\\\4</a>','[email]support@vbulletin.com[/email]','The [email] tag allows you to include email addresses.',0)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'email','<a href=\"mailto:\\\\5\">\\\\7</a>','[email=support@vbulletin.com]vBulletin Support[/email]','This email tag allows you to include your own text in an email field.',1)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'size','<font size=\"\\\\5\">\\\\7</font>','[size=+1]Size 1[/size]','The [size] tag allows you to control the size of the font.',1)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'quote','<blockquote><smallfont>quote:</smallfont><hr>\\\\4<hr></blockquote>','[quote]This is a quote[/quote]','The quote tag is used to denote a quote from another post',0)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'u','<u>\\\\4</u>','[u]Underline[/u]','The [u] tag allows you to underline text.',0)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'color','<font color=\"\\\\5\">\\\\7</font>','[color=red]Red![/color]','The [color] tag allows you to specify the font color.',1)";
$explain[]="Adding bbcode data";
$query[]="INSERT INTO bbcode VALUES (NULL,'font','<font face=\"\\\\5\">\\\\7</font>','[font=courier]Test[/font]','You can change the font of your text.',1)";

$query[]="INSERT INTO adminutil (title) VALUES ('ids')";
$explain[]="Adding Admin Util Data";

$query[]="INSERT INTO forum VALUES (1, 0, 'Main Category', 'Main Category Description', '1', '1', '0', '0', '', '0', '0', '0', '0', '', '', '0', '0', '0', '0', '0', '0', '0', '-1', '1,-1', '0', '1', '0')";
$explain[]="Adding demo category";
$query[]="INSERT INTO forum VALUES (2, 0, 'Main Forum', 'Main Forum Description', '1', '1', '0', '0', '', '0', '1', '1', '30', '', '', '0', '0', '1', '1', '0', '1', '1', '1', '2,1,-1', '1', '1', '0')";
$explain[]="Adding demo forum";

$query[]="INSERT INTO icon VALUES (NULL,'Post','images/icons/icon1.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Arrow','images/icons/icon2.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Lightbulb','images/icons/icon3.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Exclamation','images/icons/icon4.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Question','images/icons/icon5.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Cool','images/icons/icon6.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Smile','images/icons/icon7.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Angry','images/icons/icon8.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Unhappy','images/icons/icon9.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Talking','images/icons/icon10.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Red face','images/icons/icon11.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Wink','images/icons/icon12.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Thumbs down','images/icons/icon13.gif')";
$explain[]="Adding icon data";
$query[]="INSERT INTO icon VALUES (NULL,'Thumbs up','images/icons/icon14.gif')";
$explain[]="Adding icon data";

$query[]="INSERT INTO smilie VALUES (NULL,'smile',':)','images/smilies/smile.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'frown',':(','images/smilies/frown.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'embarrasment',':o','images/smilies/redface.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'big grin',':D','images/smilies/biggrin.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'wink',';)','images/smilies/wink.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'stick out tongue',':p','images/smilies/tongue.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'cool',':cool:','images/smilies/cool.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'roll eyes (sarcastic)',':rolleyes:','images/smilies/rolleyes.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'mad',':mad:','images/smilies/mad.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'eek!',':eek:','images/smilies/eek.gif')";
$explain[]="Adding smilie data";
$query[]="INSERT INTO smilie VALUES (NULL,'confused',':confused:','images/smilies/confused.gif')";
$explain[]="Adding smilie data";

$query[]="INSERT INTO usergroup VALUES ( '1', 'Unregistered / Not Logged In', 'Guest', '0', '1', '1', '1', '1', '1', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '1')";
$explain[]="Adding usergroup data";
$query[]="INSERT INTO usergroup VALUES ( '2', 'Registered', '', '0', '1', '1', '1', '1', '1', '1', '0', '0', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '0', '0', '1', '5', '5', '1', '1', '1', '0', '0', '0', '1')";
$explain[]="Adding usergroup data";
$query[]="INSERT INTO usergroup VALUES ( '3', 'Users Awaiting Email Confirmation', '', '0', '1', '1', '1', '1', '1', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '1')";
$explain[]="Adding usergroup data";
$query[]="INSERT INTO usergroup VALUES ( '4', '(COPPA) Users Awaiting Moderation', '', '0', '1', '1', '1', '1', '1', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '1')";
$explain[]="Adding usergroup data";
$query[]="INSERT INTO usergroup VALUES ( '5', 'Super Moderators', 'Super Moderator', '0', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '0', '0', '0', '0', '1', '1', '1', '1', '1')";
$explain[]="Adding usergroup data";
$query[]="INSERT INTO usergroup VALUES ( '6', 'Administrators', 'Administrator', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '5', '5', '1', '1', '1', '1', '1', '1', '1')";
$explain[]="Adding usergroup data";
$query[]="INSERT INTO usergroup VALUES ( '7', 'Moderators', 'Moderator', '0', '1', '1', '1', '1', '1', '1', '0', '0', '0', '1', '1', '1', '1', '1', '1', '1', '1', '1', '0', '0', '1', '5', '5', '1', '0', '1', '1', '0', '0', '1')";
$explain[]="Adding usergroup data";


$query[]="INSERT INTO usertitle VALUES (NULL,0,'Junior Member')";
$explain[]="Adding user title data";
$query[]="INSERT INTO usertitle VALUES (NULL,30,'Member')";
$explain[]="Adding user title data";
$query[]="INSERT INTO usertitle VALUES (NULL,100,'Senior Member')";
$explain[]="Adding user title data";

$query[]="INSERT INTO profilefield VALUES
	(1,'Biography','A few details about yourself',0,0,250,25,1,1),
	(2,'Location','Where you live',0,0,250,25,2,1),
	(3,'Interests','Your hobbies, etc',0,0,250,25,3,1),
	(4,'Occupation','Your job',0,0,250,25,4,1)";
$explain[]="Inserting definition for biography field";

$query[]="INSERT INTO templateset VALUES ( '1', 'Default')";
$explain[]="Adding default template set";
$query[]="INSERT INTO replacementset VALUES ( '1', 'Default')";
$explain[]="Adding default replacement set";

doqueries();

$DB_site->reporterror=0;
$DB_site->query("ALTER TABLE session ENGINE=MEMORY");
$DB_site->reporterror=1;

echo "<p>All tables successfully populated. The next step will set up the templates.";

echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Next step --&gt;</a></p>\n";
}  // end step 5

if ($step==6) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 6
------

+ install templates
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/


$path="./vbulletin.style";

if(file_exists($path)==0) {
	$styletext="";
} else {
	$filesize=filesize($path);

	$filenum=fopen($path,"r");

	$styletext=fread($filenum,$filesize);

	fclose($filenum);
}

if ($styletext=="") {
  echo "<p>Please ensure that the vbulletin.style file exists in the current directory and then reload this current page.</p>";
  exit;
}

$DB_site->query("DELETE FROM template WHERE templatesetid=-1 AND title<>'options'");
$DB_site->query("DELETE FROM replacement WHERE replacementsetid=-1");

$stylebits=explode("|||",$styletext);

list($devnul,$styleversion)=each($stylebits);

list($devnul,$style['title'])=each($stylebits);
list($devnul,$replacementset['title'])=each($stylebits);
list($devnul,$templateset['title'])=each($stylebits);

// check to see if we are installing a master template set or just a custom style set

// installing a master!!
$style['styleid']=-1;
$style['userselect']=0;
$style['replacementsetid']=-1;
$style['templatesetid']=-1;

// get number of replacements and templates
list($devnull,$numreplacements)=each($stylebits);
list($devnull,$numtemplates)=each($stylebits);

$counter=0;
while ($counter++<$numreplacements) {
	list($devnull,$findword)=each($stylebits);
	list($devnull,$replaceword)=each($stylebits);
	if (trim($findword)!="") {
		$DB_site->query("INSERT INTO replacement (replacementsetid,findword,replaceword) VALUES ($style[replacementsetid],'".vb_escape($findword)."','".vb_escape($replaceword)."')");
	}
}

$counter=0;
while ($counter++<$numtemplates) {
	list($devnull,$title)=each($stylebits);
	list($devnull,$template)=each($stylebits);
	if (trim($title)!="") {
		$DB_site->query("INSERT INTO template (templatesetid,title,template) VALUES ($style[templatesetid],'".vb_escape($title)."','".vb_escape($template)."')");
	}
}

// Add special template that holds max logged in users information.
$DB_site->query("INSERT INTO template (templatesetid, title) VALUES ('-2','maxloggedin')");
// Add special template that holds the birthday information.
$DB_site->query("INSERT INTO template (templatesetid, title) VALUES ('-2','birthdays')");

echo "<p>Style imported correctly!</p>";

echo "<p>The next step will allow you to set up the options for this board.</p>";

echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Next step --&gt;</a></p>\n";
} // end step 6

if ($step==7) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 7
------

+ set options
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/

if ($PATH_INFO) {
	$scriptpath = $PATH_INFO;
} else {
	$scriptpath = $PHP_SELF;
}
$bburl = "http://$SERVER_NAME".substr($scriptpath,0,strpos($scriptpath,"/admin/"));
$homeurl = "http://$SERVER_NAME/";
$webmaster = "webmaster@$SERVER_NAME";

?><form action="install4php5.php?step=8" method="post">
<input type="hidden" name="step" value="8">

<table border=0>

<tr>
<td><b>BB Title</b></td>
<td><input type="text" size="35" name="bbtitle" value=" Forums"></td>
</tr>
<tr><td colspan=2>Title of board. Appears in the title of every page.<br></td></tr>

<tr>
<td><b>Home Title</b></td>
<td><input type="text" size="35" name="hometitle" value=""></td>
</tr>
<tr><td colspan=2>Name of your homepage. Appears at the bottom of every page.<br></td></tr>

<tr>
<td><b>BB URL</b></td>
<td><input type="text" size="35" name="bburl" value="<?php echo $bburl ?>"></td>
</tr>
<tr><td colspan=2>URL (with no final "/") of the BB.<br></td></tr>

<tr>
<td><b>Home URL</b></td>
<td><input type="text" size="35" name="homeurl" value="<?php echo $homeurl; ?>"></td>
</tr>
<tr><td colspan=2>URL of your home page. Appears at the bottom of every page.<br></td></tr>

<tr>
<td><b>Webmaster email address</b></td>
<td><input type="text" size="35" name="webmasteremail" value="<?php echo $webmaster; ?>"></td>
</tr>
<tr><td colspan=2>Email address of the webmaster.<br></td></tr>

</table>
<input type=submit value="Submit Options and Continue to next step">
</form>
<?php

} // end step 7

if ($step==8) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 8
------

+ set up options
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/
$bbtitle = $_POST['bbtitle'];
$hometitle = $_POST['hometitle'];
$bburl = $_POST['bburl'];
$homeurl = $_POST['homeurl'];

if ($bbtitle=="" or $hometitle=="" or $bburl=="http://www.yourhost.com/forums" or $homeurl=="http://www.yourhost.com/" or $webmasteremail=="webmaster@your-email-address-here.com") {
  echo "<p>Please enter new values in all the fields on the previous page. Press the back button and correct the error and then try again.</p>";
  exit;
}

if (get_magic_quotes_gpc()) {
  $bbtitle=stripslashes($bbtitle);
  $hometitle=stripslashes($hometitle);
  
}

// do settings
$DB_site->query("INSERT INTO settinggroup VALUES (1,'Turn Your vBulletin on and off',1)");
$DB_site->query("INSERT INTO settinggroup VALUES (2,'General Settings',2)");
$DB_site->query("INSERT INTO settinggroup VALUES (3,'Contact Details',3)");
$DB_site->query("INSERT INTO settinggroup VALUES (4,'Posting Code allowances (vB code / HTML / etc)',4)");
$DB_site->query("INSERT INTO settinggroup VALUES (5,'Forums Home Page Options',5)");
$DB_site->query("INSERT INTO settinggroup VALUES (6,'User and registration options',6)");
$DB_site->query("INSERT INTO settinggroup VALUES (7,'Memberlist options',7)");
$DB_site->query("INSERT INTO settinggroup VALUES (8,'Thread display options',8)");
$DB_site->query("INSERT INTO settinggroup VALUES (9,'Forum Display Options',9)");
$DB_site->query("INSERT INTO settinggroup VALUES (10,'Search Options',10)");
$DB_site->query("INSERT INTO settinggroup VALUES (11,'Email Options',11)");
$DB_site->query("INSERT INTO settinggroup VALUES (12,'Date / Time options',12)");
$DB_site->query("INSERT INTO settinggroup VALUES (14,'Edit Options',14)");
$DB_site->query("INSERT INTO settinggroup VALUES (15,'IP Logging Options',15)");
$DB_site->query("INSERT INTO settinggroup VALUES (16,'Floodcheck Options',16)");
$DB_site->query("INSERT INTO settinggroup VALUES (17,'Banning Options',17)");
$DB_site->query("INSERT INTO settinggroup VALUES (18,'Language Options',18)");
$DB_site->query("INSERT INTO settinggroup VALUES (19,'Private Messaging Options',19)");
$DB_site->query("INSERT INTO settinggroup VALUES (13,'Censorship Options',13)");
$DB_site->query("INSERT INTO settinggroup VALUES (20,'HTTP Headers and output',20)");
$DB_site->query("INSERT INTO settinggroup VALUES (21,'Version Info',0)");
$DB_site->query("INSERT INTO settinggroup VALUES (22,'Spell Check',21)");
$DB_site->query("INSERT INTO settinggroup VALUES (23,'Templates',22)");
$DB_site->query("INSERT INTO settinggroup VALUES (24,'Load limiting options',24)");
$DB_site->query("INSERT INTO settinggroup VALUES (25,'Polls',25)");
$DB_site->query("INSERT INTO settinggroup VALUES (26,'Avatars',26)");
$DB_site->query("INSERT INTO settinggroup VALUES (27,'Attachments',27)");
$DB_site->query("INSERT INTO settinggroup VALUES (28,'Custom User Titles',28)");
$DB_site->query("INSERT INTO settinggroup VALUES (29,'Calendar',29)");
$DB_site->query("INSERT INTO settinggroup VALUES (30,'Upload Options',30)");
$DB_site->query("INSERT INTO settinggroup VALUES (31,'Who\'s Online', 31)");

echo "<p>Added settinggroup data</p>\n";

$DB_site->query("INSERT INTO setting VALUES (NULL,1,'Bulletin Board Active','bbactive','1','From time to time, you may want to turn your bulletin board off to the public while you perform maintenance, update versions, etc. When you turn your BB off, visitors will receive a message that states that the bulletin board is temporarily unavailable. <b>Administrators will still be able to see the board</b></p>\r\n<p>Use this as a master switch for your board. You can set options for individual user groups in the User Permissions area.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,1,'Reason for turning board off','bbclosedreason','This board is closed at the moment. Please call back later.','The text that is presented when the BB is closed.</p>\r\n<p>Note: you, as an administrator, will be able to see the forums as usual, even when you have turned them off to the public.','textarea',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,2,'URL','bburl','".vb_escape($bburl)."','URL (with no final \"/\") of the BB. Do not include \"/index.php\" on the end! Your URL should look something like this: http://www.example.com/forum','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,2,'Board Title','bbtitle','".vb_escape($bbtitle)."','Title of board. Appears in the title of every page','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,2,'Homepage Name','hometitle','".vb_escape($hometitle)."','Name of your homepage. Appears at the bottom of every page.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,2,'URL of homepage','homeurl','".vb_escape($homeurl)."','URL of your home page. Appears at the bottom of every page.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,2,'Copyright Text','copyrighttext','','Copyright text to insert the footer of the page.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,2,'URL of Privacy Statement','privacyurl','','Enter the URL of your privacy statement, if one exists.','',6)");
$DB_site->query("INSERT INTO setting (settingid,settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (NULL,2,'GD Version','gdversion','0','Version of GD installed on your server. If GD is enabled, you can find the version listed in your phpinfo().','<select name=\\\\\"setting[\$setting[settingid]]\\\\\">\r\n<option value=\\\\\"0\\\\\" \".iif(\$setting[value]==0,\"selected\",\"\").\">None</option>\r\n<option value=\\\\\"1\\\\\" \".iif(\$setting[value]==1,\"selected\",\"\").\">GD 1.6.x/1.8.x</option>\r\n<option value=\\\\\"2\\\\\" \".iif(\$setting[value]==2,\"selected\",\"\").\">GD 2+</option>\r\n</select>','100')");
$DB_site->query("INSERT INTO setting VALUES (NULL,3,'Contact Us Link','contactuslink','mailto:".vb_escape($webmasteremail)."','Link for contacting the site. Can just be mailto:webmaster@whereever.com or your own form. Appears at the bottom of every page.','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,3,'Webmaster\'s email','webmasteremail','".vb_escape($webmasteremail)."','Email address of the webmaster.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,3,'Company Name','companyname','','The name of your company. Required for COPPA.','',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,3,'Address','address','','Address of your company. This is required for COPPA forms to be posted to.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,3,'Fax Number','faxnumber','','Enter the fax number for your company here. COPPA forms will be faxed to it.\r\n</p>\r\n<p>You may wish to check out <a href=\"http://www.efax.com/\" target=_blank>http://www.efax.com/</a>','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Allow vB IMG code in signatures?','allowbbimagecode','0','','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Allow vB code in signatures?','allowbbcode','1','','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Allow smilies in signatures?','allowsmilies','1','','yesno',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Allow HTML in signatures?','allowhtml','0','','yesno',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Maximum images per post/signature','maximages','10','Maximum number of images to allow in posts / signatures. Set this to 0 to have no effect.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Clickable Smilies per Row','smcolumns','3','When a user has enabled the clickable vbcode/smilies how many smilies do you want to show per row?','',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Clickable Smilies Total','smtotal','15','When a user has enabled the clickable vbcode/smilies how many smilies do you want to display on the screen before the user is prompted to click for more.','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Allow Dynamic URL for [img] tags?','allowdynimg','0','If this is set to \'no\', the [img] tag will not be displayed if the path to the image contains dynamic characters such as ? and &. This can prevent malicious use of the tag.','yesno',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,4,'Allow vBcode Buttons & Clickable Smilies?','allowvbcodebuttons','1','This global switch allows you to completely disable vBcode buttons and clickable smilies.','yesno',9)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Show Locked forums?','showlocks','0','Do you wish to have the new post indicators shown on the index page (on.gif and off.gif) be shown with locks to guests & other members who have no access to post?','yesno',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Depth of Forums','forumhomedepth','2','Depth to show forums on home page. Do not go too large on this value for performance reasons.','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Hide private forum','hideprivateforums','1','Select \'yes\' here to hide private forums from users who are not allowed to access them. Users who do have permission to access them will have to log in before they can see these forums too. This option applies to the forum home page, and the Jump To... box.','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Display logged in users on home page','displayloggedin','1','Display logged in and active members on the home page? This option displays those users that have been active in the last {your cookie timeout} seconds on the home page.','yesno',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Show forum descriptions on the homepage.','showforumdescription','1','','yesno',0)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Show today\'s birthdays on the homepage?','showbirthdays','1','','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,5,'Highlight Admin','highlightadmin','1','Enabling this will show Admin names in Bold and Italics and Moderators\' names in Bold on the logged in users and Who\'s Online.','yesno','4')");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Display email addresses','displayemails','0','Allow public viewing of email addresses.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Use email form?','secureemail','1','If \"display email addresses\" is set to yes, then how should then email address be displayed? If this is set to yes, then an online form must be filled in to send a user an email, thus hiding the destination email address. If secureemail is set to no, then the user is just given the email address.','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Allow signatures','allowsignatures','1','Allow registered users to have signatures. Don\'t forget to update these templates: newtopic newreply editpost modifyprofile register','yesno',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Notify about new members','newuseremail','','Email address to receive email when a new user signs up. Leave blank for no email.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Require unique email addresses','requireuniqueemail','1','The default option is to require unique email addresses for each registered user. This means that no two users can have the same email address. You can disable this requirement by checking the \"Unique Email Not Required\" box.','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Illegal user names','illegalusernames','','Enter names in here that you do not want people to register. If any of the names here are included within the username, the user will told that there is an error. Separate names by spaces.','',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Allow new user registrations','allowregistration','1','If you would like to temporarily (or permanently) prevent anyone new from registering, you can do so. The REGISTER button will still be seen throughout the BB, but anyone attempting to register will be told that you are not accepting new registrations at this time.','yesno',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Allow multiple registrations per user','allowmultiregs','0','Normally, vBulletin will stop users signing up for multiple names by checking for a cookie on the user\'s machine. If one exists, then the user may not sign up for additional names. If you wish to allow your users to sign up for multiple names, then select yes for this option, and they will not be blocked from registering additional usernames.','yesno',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Verify Email address in registration','verifyemail','1','The password is emailed to the new member after they submit their registration to confirm their identity and email address. If account is not activated, they will remain in the \"users awaiting activation\" usergroup.','yesno',9)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Moderate New Members','moderatenewmembers','0','Allows you to validate new members before they are classified as registered members and are allowed to post.','yesno',10)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Use COPPA Registration system','usecoppa','1','Use the COPPA registration system. This complies with the COPPA laws and requires children under the age of 13 to get parental consent before they can post. For more info about this law, see here:<br>\r\n<a href=\"http://www.ftc.gov/opa/1999/9910/childfinal.htm\">http://www.ftc.gov/opa/1999/10/childfinal.htm</a>','yesno',11)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Timeout for Cookie','cookietimeout','900','This is the time in seconds that a user must remain inactive before the unread posts are reset to read.','',12)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Allow Users To Change Styles','allowchangestyles','1','This allows users to set their preferred style set on registration or when editing their option. Setting this to \"no\" disables that option and will force them to use whatever style has been specified.','yesno',13)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Enable Access Masks?','enableaccess','0','Access masks are a simple way to manage forum permissions, however they add additional queries on most pages. If you don\'t use it, it is recommended that you disable it.','yesno',14)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Referrer', 'usereferrer', '0', 'Enable the referrer system? If yes than a user that visits your forum through a link that contains \"referrerid=XXX\" will give referral credit to the owner of the referrerid when they register (where XXX is the userid of the referrer).', 'yesno', 15)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Minimum Username Length','minuserlength','3','Enter the minimum length a user can register with.','',15)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Maximum Username Length','maxuserlength','15','Enter the maximum length a user can register with.','',16)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Allow Ignore Moderators','ignoremods','0','Allow users to add Moderators and admins to their ignore list?','yesno',17)");
$DB_site->query("INSERT INTO setting VALUES (NULL,6,'Image Verification','regimagecheck','1','If enabled, this option will display a random image to new users at registration time. The user will have to copy the contents of the image in order to verify that an automated system is not processing registrations. Requires GD to be enabled on your server.','yesno','10')");
$DB_site->query("INSERT INTO setting VALUES (NULL,7,'Enable members list','enablememberlist','1','Enable the member list addon? This allows users to view a list of registered users and (optionally) search through it. ','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,7,'Allow advanced searches','usememberlistadvsearch','1','Allow the use of the advanced search tool for the member list.','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,7,'Members per page','memberlistperpage','30','The number of records per page that will be shown by default in the members list.','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,7,'Number of top posters to show','memberlisttopposters','10','On the \'Display top x posters\' , this option allows you to specify a value for x.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,7,'Restrict Listing','memberAllGood','1','Select Yes to hide members from appearing on the member\'s list who are Awaiting Confirmation, Awaiting Coppa, and in the Not Registered/Not Logged in usergroups.','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Order of posts within a thread','postorder','0','The standard (recommended) way to display threads is chronologically from original topic to the latest reply. You can reverse this, if you prefer, to have threads displayed in reverse, from latest post to oldest post.','Newest first<input type=\\\\\"radio\\\\\" name=\\\\\"setting[\$setting[settingid]]\\\\\"  \".iif(\$setting[value]==1,\"checked\",\"\").\" value=\\\\\"1\\\\\"> Oldest first <input type=\\\\\"radio\\\\\" name=\\\\\"setting[\$setting[settingid]]\\\\\" \".iif(\$setting[value]==0,\"checked\",\"\").\" value=\\\\\"0\\\\\">',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Maximum number of posts to display on a thread page before splitting over multiple pages','maxposts','15','','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'User Settable Maximum Posts per Page','usermaxposts','5,10,20,30,40','If you would like to allow the user to set their own maximum posts per thread then give the options seperated by commas. Leave the field blank to force them to use the MAXPOSTS setting above this option.\r\n\r\nex. 10,20,30,40','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Pages to show in nav bar','pagenavpages','3','In the list of links of pages in the current thread (or current forum), this option selects how many pages either side of the current page are shown. Set this to 0 to display all pages.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Number of characters before wrapping','wordwrap','50','If you want posts to automatically insert spaces into long words to make them wrap after a certain number of characters, set the number of characters in the box above. If you do not want this to occur, enter 0.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Maximum Characters per post','postmaxchars','10000','The maximum number of characters that you want to allow per post. Set this to 0 to disable it.','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Show default icon','showdeficon','0','Show a default icon if a user doesn\'t choose a message icon or is unable to choose one based on forum settings? {imagesfolder}/icons/icon1.gif will be used.','yesno',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,8,'Stop \'Shouting\' in titles','stopshouting','1','Prevent your users \'shouting\' in their thread titles by changing all-uppercase titles to capitalization only on the first letters of some words. Disable this for some international boards with different character sets, as this may cause problems.','yesno','9')");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Threads per Page','maxthreads','25','The number of threads to display on a forum page before splitting it over multiple pages.','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Use \'hot\' icons','usehotthreads','1','Hot icons indicate topics with a lot of activity. The icons are animated to show a folder on fire. ','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Number of views to qualify as a hot thread','hotnumberviews','150','','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Number of posts to qualify as a hot thread','hotnumberposts','15','','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Use \'dot\' icons?','showdots','1','When this feature is enabled, a logged in user will see a \"dot\" (or whatever graphic you choose) on the folder icons (hot folders, new folders, etc) next to the threads that they have participated in.','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Link to individual pages of a multipage thread on the forum listing?','linktopages','1','','yesno',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Maximum links to individual pages','maxmultipage','5','When linking to multiple pages in the forum display, this allows you to set the cut off point on which long posts stop adding more page numbers and are replaced by \'more...\'','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Showing Votes','showvotes','2','How many votes will a thread need before the votes are displayed?','',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Change Votes?','votechange','0','Allow users to change their thread rating votes?','yesno',9)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Prefix for moved threads','movedthreadprefix','Moved:�','The text with which to prefix a thread that has been moved to another forum. (You may use HTML)','',10)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Prefix for Sticky threads','stickythreadprefix','Sticky:�','Prefix to append to the beginning of thread titles that have been set to \"Sticky\". These threads always appear at the top of the thread list. (You may use HTML)','',11)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Prefix for Polls','pollthreadprefix','Poll: ','Prefix to append to the beginning of Poll thread titles. (You may use HTML)','',12)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Use Forumjump?','useforumjump','1','Do you want to use the Forumjump drop-down menu on your pages?  If your mysql server is optimized and you still are having problems you can try disabling this option to save queries.','yesno',12)");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Depth of Forums', 'forumdisplaydepth', '2', 'Depth of forums to show on forum display when displaying subforums. Do not go too large on this value for performance reasons.', '', '13')");
$DB_site->query("INSERT INTO setting VALUES (NULL,9,'Show users browsing forums?','showforumusers','1','Enabling this options will show the current users browsing a particular forum on forumdisplay.php while adding one query. Should not affect performance.','yesno','14')");
$DB_site->query("INSERT INTO setting VALUES (NULL,10,'Enable searches?','enablesearches','1','Allow searching for posts within the BB. This is quite a server intensive process so you may want to disable it.</p>\r\n<p>To disable searching of all forums, delete the option from the searchintro template. ','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,10,'Number of posts per page','searchperpage','25','Number of successful search items to display per page.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,10,'Floodcheck - Minimum time between searches','searchfloodtime','0','The minimum time (in seconds) that must expire before the user can search again. Set this to 0 to disable it.','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,10,'Minimum Word Length','minsearchlength','4','Enter the minimum word length that the search engine is to index.  The smaller this number is, the larger your search index, and conversely your database is going to be.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,10,'Maximum Word Length','maxsearchlength','20','Enter the maximum word length that the search engine is to index.  The larger this number is, the larger your search index, and conversely your database is going to be.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,10,'Allow Wild Cards?','allowwildcards','1','Allow users to use a star (*) in searches to match partial words?','yesno','6')");

$DB_site->query("INSERT INTO setting VALUES (NULL,11,'Enable Email features?','enableemail','1','Enable email sending features: send to friend and email notification for posters and moderators. Don\'t forget to remove those links from the <i>forumdisplay, newreply, newtopic</i>, &amp; <i>editpost</i> templates<br><br>You can turn off the \'Send to Friend\' feature for invidual user groups in the <a href=\"usergroup.php?s=$session[sessionhash]&amp;action=modify\" target=\"_blank\">User Permissions area</a>.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,12,'Time Zone Offset','timeoffset','".intval(date("H",12*60*60)-gmdate("H",12*60*60))."','Time (in hours) that the server is offset from GMT. Please select the most appropriate option.','<select name=\\\\\"setting[\$setting[settingid]]\\\\\">\r\n<option value=\\\\\"-12\\\\\" \".iif(\$setting[value]==-12,\"selected\",\"\").\">(GMT -12:00 hours) Eniwetok, Kwajalein</option>\r\n<option value=\\\\\"-11\\\\\" \".iif(\$setting[value]==-11,\"selected\",\"\").\">(GMT -11:00 hours) Midway Island, Samoa</option>\r\n<option value=\\\\\"-10\\\\\" \".iif(\$setting[value]==-10,\"selected\",\"\").\">(GMT -10:00 hours) Hawaii</option>\r\n<option value=\\\\\"-9\\\\\" \".iif(\$setting[value]==-9,\"selected\",\"\").\">(GMT -9:00 hours) Alaska</option>\r\n<option value=\\\\\"-8\\\\\" \".iif(\$setting[value]==-8,\"selected\",\"\").\">(GMT -8:00 hours) Pacific Time (US & Canada)</option>\r\n<option value=\\\\\"-7\\\\\" \".iif(\$setting[value]==-7,\"selected\",\"\").\">(GMT -7:00 hours) Mountain Time (US & Canada)</option>\r\n<option value=\\\\\"-6\\\\\" \".iif(\$setting[value]==-6,\"selected\",\"\").\">(GMT -6:00 hours) Central Time (US & Canada), Mexico City</option>\r\n<option value=\\\\\"-5\\\\\" \".iif(\$setting[value]==-5,\"selected\",\"\").\">(GMT -5:00 hours) Eastern Time (US & Canada), Bogota, Lima, Quito</option>\r\n<option value=\\\\\"-4\\\\\" \".iif(\$setting[value]==-4,\"selected\",\"\").\">(GMT -4:00 hours) Atlantic Time (Canada), Caracas, La Paz</option>\r\n<option value=\\\\\"-3.5\\\\\" \".iif(\$setting[value]==-3.5,\"selected\",\"\").\">(GMT -3:30 hours) Newfoundland</option>\r\n<option value=\\\\\"-3\\\\\" \".iif(\$setting[value]==-3,\"selected\",\"\").\">(GMT -3:00 hours) Brazil, Buenos Aires, Georgetown</option>\r\n<option value=\\\\\"-2\\\\\" \".iif(\$setting[value]==-2,\"selected\",\"\").\">(GMT -2:00 hours) Mid-Atlantic</option>\r\n<option value=\\\\\"-1\\\\\" \".iif(\$setting[value]==-1,\"selected\",\"\").\">(GMT -1:00 hours) Azores, Cape Verde Islands</option>\r\n<option value=\\\\\"0\\\\\" \".iif(\$setting[value]==0,\"selected\",\"\").\">(GMT) Western Europe Time, London, Lisbon, Casablanca, Monrovia</option>\r\n<option value=\\\\\"+1\\\\\" \".iif(\$setting[value]==+1,\"selected\",\"\").\">(GMT +1:00 hours) CET(Central Europe Time), Angola, Libya</option>\r\n<option value=\\\\\"+2\\\\\" \".iif(\$setting[value]==+2,\"selected\",\"\").\">(GMT +2:00 hours) EET(Eastern Europe Time), Kaliningrad, South Africa</option>\r\n<option value=\\\\\"+3\\\\\" \".iif(\$setting[value]==+3,\"selected\",\"\").\">(GMT +3:00 hours) Baghdad, Kuwait, Riyadh, Moscow, St. Petersburg, Volgograd, Nairobi</option>\r\n<option value=\\\\\"+3.5\\\\\" \".iif(\$setting[value]==+3.5,\"selected\",\"\").\">(GMT +3:30 hours) Tehran</option>\r\n<option value=\\\\\"+4\\\\\" \".iif(\$setting[value]==+4,\"selected\",\"\").\">(GMT +4:00 hours) Abu Dhabi, Muscat, Baku, Tbilisi</option>\r\n<option value=\\\\\"+4.5\\\\\" \".iif(\$setting[value]==+4.5,\"selected\",\"\").\">(GMT +4:30 hours) Kabul</option>\r\n<option value=\\\\\"+5\\\\\" \".iif(\$setting[value]==+5,\"selected\",\"\").\">(GMT +5:00 hours) Ekaterinburg, Islamabad, Karachi, Tashkent</option>\r\n<option value=\\\\\"+5.5\\\\\" \".iif(\$setting[value]==+5.5,\"selected\",\"\").\">(GMT +5:30 hours) Bombay, Calcutta, Madras, New Delhi</option>\r\n<option value=\\\\\"+6\\\\\" \".iif(\$setting[value]==+6,\"selected\",\"\").\">(GMT +6:00 hours) Almaty, Dhaka, Colombo</option>\r\n<option value=\\\\\"+7\\\\\" \".iif(\$setting[value]==+7,\"selected\",\"\").\">(GMT +7:00 hours) Bangkok, Hanoi, Jakarta</option>\r\n<option value=\\\\\"+8\\\\\" \".iif(\$setting[value]==+8,\"selected\",\"\").\">(GMT +8:00 hours) Beijing, Perth, Singapore, Hong Kong, Chongqing, Urumqi, Taipei</option>\r\n<option value=\\\\\"+9\\\\\" \".iif(\$setting[value]==+9,\"selected\",\"\").\">(GMT +9:00 hours) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>\r\n<option value=\\\\\"+9.5\\\\\" \".iif(\$setting[value]==+9.5,\"selected\",\"\").\">(GMT +9:30 hours) Adelaide, Darwin</option>\r\n<option value=\\\\\"+10\\\\\" \".iif(\$setting[value]==+10,\"selected\",\"\").\">(GMT +10:00 hours) EAST(East Australian Standard), Guam, Papua New Guinea, Vladivostok</option>\r\n<option value=\\\\\"+11\\\\\" \".iif(\$setting[value]==+11,\"selected\",\"\").\">(GMT +11:00 hours) Magadan, Solomon Islands, New Caledonia</option>\r\n<option value=\\\\\"+12\\\\\" \".iif(\$setting[value]==+12,\"selected\",\"\").\">(GMT +12:00 hours) Auckland, Wellington, Fiji, Kamchatka, Marshall Island</option>\r\n</select>',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,12,'Format of dates','dateformat','m-d-Y','Format that the date is presented in on the pages.</p>\r\n<p>See: <a href=\"http://www.php.net/manual/function.date.php3\" target=_blank>http://www.php.net/manual/function.date.php3</a></p>\r\n<p>Examples:<br>\r\nUS Format (e.g., 04-25-98) - m-d-y<br>\r\nExpanded US Format (e.g., April 25th, 1998) - F jS, Y<br>\r\nEuropean Format (e.g., 25-04-98) - d-m-Y<br>\r\nExpanded European Format (e.g., 25th April 1998) - jS F Y','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,12,'Format of times','timeformat','h:i A','Format that the time is presented in on the pages.</p>\r\n<p>See: <a href=\"http://www.php.net/manual/function.date.php3\" target=_blank>http://www.php.net/manual/function.date.php3</a></p>\r\n<p>Examples:<br>\r\nUse AM/PM Time Format (eg, 11:15 PM) - h:i A<br>\r\nUser 24-Hour Format Time (eg, 23:15) - H:i','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,12,'Format for registration date','registereddateformat','M Y','This is used to format dates shown with users\' posts. In the left hand column of a thread display, under the username and title, there is some text showing when the user registered.</p>\r\n<p>See: <a href=\"http://www.php.net/manual/function.date.php3\" target=_blank>http://www.php.net/manual/function.date.php3</a>','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,12,'Birthday Format','calformat1','F j, Y','Format of date shown in profile when user gives their birthyear.','',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,12,'Birthday Format #2','calformat2','F j','Format of user\'s birthday shown on profile when the user does specify their birth year. DO NOT put in a code for the year.','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,13,'Enable censor?','enablecensor','0','You may have certain words censored on your BB. Words you choose to censor will be replaced by asterisks. All subjects and messages will be affected. \r\n','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,13,'Words to censor','censorwords','','Type all words you want censored in the field below. Do not use commas to separate words, just use spaces. For example, type \"dog cat boy\", rather than \"dog, cat, boy.\" If you type \"dog\", all words containing the string \"dog\" would be censored (dogma, for instance, would appear as \"***ma\"). To censor more accurately, you can require that censors occur only for exact words. You can do this by placing a censor word in curly braces, as in {dog}. Signifying \"dog\" in the curly braces would mean that dogma would appear as dogma, but dog would appear as \"***\". Thus your censor list may appear as: cat {dog} {barn} barn<br>Do not use quotation marks and make sure you use curly braces, not parentheses, when specifying exact words.<br>This field can contain a maximum 250 characters.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,13,'Character to use to replace censored words:','censorchar','*','','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,14,'Show the \'Edited by xxx on yyy\' when a post is edited?','showeditedby','1','','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,14,'Show \'edited by\' for admins?','showeditedbyadmin','0','If you want the [edited by xxx] message to appear when an admin edits a message, select yes here. This message will appear automatically for all moderators and other users, but using this option you can optionally turn it off.','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,14,'Time to wait before starting to display \'edited by...\'','noeditedbytime','2','Time limit (in minutes) to allow user to edit the post without the [edited by xxx] message appearing at the bottom of the post.','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,14,'Time limit on editing of posts','edittimelimit','0','Time limit (in minutes) to impose on editing of messages. After this time limit only moderators will be able edit or delete the message. 1 day is 1440 minutes. Set this to 0 if you do not want it to be active.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,14,'Time limit on editing of thread title','editthreadtitlelimit','5','Specify the time-limit (in minutes) within which the thread-title may be edited by the thread starter.','','5')");
$DB_site->query("INSERT INTO setting VALUES (NULL,15,'Log IP addresses?','logip','1','For security reasons, you may wish to display the IP number of the person posting a message. The default is OFF. You may log the IP to file only- in which case the IP number is not viewable on the bulletin board, but is logged, or you may have the IP numbers logged and displayed publicly on the bulletin board.','Do not log IP<input type=\\\\\"radio\\\\\" name=\\\\\"setting[\$setting[settingid]]\\\\\"  \".iif(\$setting[value]==0,\"checked\",\"\").\" value=\\\\\"0\\\\\"> Display but require administrator or moderator <input type=\\\\\"radio\\\\\" name=\\\\\"setting[\$setting[settingid]]\\\\\" \".iif(\$setting[value]==1,\"checked\",\"\").\" value=\\\\\"1\\\\\"> Display publicly <input type=\\\\\"radio\\\\\" name=\\\\\"setting[\$setting[settingid]]\\\\\" \".iif(\$setting[value]==2,\"checked\",\"\").\" value=\\\\\"2\\\\\">',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,16,'Enable checking for post flooding?','enablefloodcheck','1','You may prevent your users from flooding your board with posts by activating this feature. By enabling floodcheck, you disallow users from posting within a given time span of their last post. In other words, if you set a floodcheck time span of 60 seconds, a user may not post a note within 60 seconds of his last post. Administrators and moderators are exempt from floodcheck.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,16,'Minimum time between posts','floodchecktime','30','Set the amount of time in seconds used by flood check to prevent post flooding. Recommended: 60. Type the number of seconds only.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,17,'Enable Banning Options?','enablebanning','0','Banning allows you to stop certain IP addresses and email addresses from registering and posting to the board. ','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,17,'IPs to ban:','banip','','IP Number Ban Lists: You may ban any IP numbers from registering and posting. Type in the complete IP number (as in 243.21.31.7), or use a partial IP number (as in 243.21.31). The BB will do matches from the beginning of each IP number that you enter. Thus, If you enter a partial IP of 243.21.31, someone attempting to register who has an IP number of 243.21.31.5 will not be able to register. Similarly, if you have an IP ban on 243.21, someone registering who has an IP of 243.21.3.44 will not be able to register. Thus, be careful when you add IPs to your ban list and be as specific as possible. As with the email ban list, put a space between each IP number. The IP Ban prevents anyone with matching IP number from registering and posting.','textarea',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,17,'Email addresses to ban','banemail','','Email Ban Lists: You may ban certain email addresses from registering on your forums. To ban a specific email, type the full email address (as in, waldo@whereiswaldo.com). To ban all email addresses from certain domains, such as hotmail, simply type the domain name (as in hotmail.com)- that will prevent anyone using a hotmail address from registering. Put a space between each banned email.','textarea',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,17,'Allow user to keep banned email','allowkeepbannedemail','1','If you ban an email address and a user already uses that address, a problem will occur. Using this option, you can specify whether the user will have to enter a new email address in their profile when they next modify their email address, or whether the user can just keep the email address which you have banned.','yesno',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,18,'Text for \'on\'','ontext','ON','Text that means on. This is used to keep the code language independent. It is used with the vB code / HTML code On / Off settings for postings.','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,18,'Text for \'off\'','offtext','OFF','Text that means off. This is used to keep the code language independent. It is used with the vB code / HTML code On / Off settings for postings.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Enable Private Messaging?','enablepms','1','Enabling this will add some performance overhead on the main index and it may not be a feature you wish to have at all.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Allow vB IMG code in private messages?','privallowbbimagecode','1','','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Allow vB code in private messages?','privallowbbcode','1','','yesno',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Allow smilies in private messages?','privallowsmilies','1','','yesno',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Allow HTML in private messages?','privallowhtml','0','','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Allow message icons','privallowicons','1','Allow the use of the standard message icons for private messages.','yesno',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Maximum saved messages','pmquota','70','Maximum number of saved messages a user can have. 0 means unlimited','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Inbox name','inboxname','Inbox','The name of the inbox folder.','',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Sent Items Name','sentitemsname','Sent Items','The name of the Sent Items folder.','',9)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'IM Support - Check for new PMs','checknewpm','1','Selecting yes for this option will cause the system to check the PM database every time a user loads a page, and will display a visible prompt for it.','yesno',10)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Floodcheck - Minimum time between messages','pmfloodtime','60','Private Message Flood Checking. Select the minimum time that must pass before a user can send another private message. This is to prevent a single user \'spamming\' by sending lots of messages very quickly. Set this to 0 to disable the option.','',11)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Maximum characters per private message','pmmaxchars','1000','Maximum characters to allow in a private message. Set this to 0 for no limit.','',12)");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Word to denote cancelled message','pmcancelledword','Cancelled:','This is the word that will prefix the title of \'cancelled\' messages in the <i>message tracking</i> section. (You may use HTML)','','13')");
$DB_site->query("INSERT INTO setting VALUES (NULL,19,'Delete \'cancelled\' messages?','pmcancelkill',0,'When users \'cancel\' messages in the message tracking area, would you like to remove the message completely? WARNING: Selecting \'yes\' could confuse users who have been notified by email about the message.','yesno',17)");
$DB_site->query("INSERT INTO setting VALUES (NULL,20,'Add Standard headers','addheaders','0','This option does not work with some combinations of web server, so is off by default. However, some IIS setups may need it turned on.</p>\r\n\r\n<p>It will send the 200 OK HTTP headers if turned on.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,20,'Add No-cache headers','nocacheheaders','0','Selecting yes will cause vBulletin to add no-cache HTTP headers. These are very effective, so adding them may cause server load to increase due to increase page requests.','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,20,'GZIP Output','gzipoutput','0','Selecting yes will enable vBulletin to gzip the output of pages, thus reducing bandwidth requirements. This will be only used on clients that support it, and are HTTP 1.1 compliant. There will be a performance overhead. This feature requires PHP 4.0.1 or greater, and the ZLIB library.','yesno',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,20,'GZIP compression level','gziplevel','1','Set the level of GZIP compression that will take place on the output. 0=none; 9=max.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,20,'Cookie Domain','cookiedomain','','The domain on which you want the cookie to have effect. If you want this to affect all of yourhost.com rather than just forums.yourhost.com, enter .yourhost.com here (note the 2 dots!!!). Can be left blank.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,20,'Path to save cookies','cookiepath','/','The path that the cookie is saved to. If you run more than one board on the same domain, it will be necessary to set this to the individual directories of the forums. Otherwise, just leave it as / .','',6)");

$DB_site->query("INSERT INTO setting VALUES (NULL,21,'Template version number','templateversion','$version','Don\'t touch!','',0)");
$DB_site->query("INSERT INTO setting VALUES (NULL,22,'Spell Check Language','spellchecklang','en','The language used for the spell checking features, provided by <a href=\"http://www.spellchecker.net/\" target=_blank>Spellchecker.net</a>. If you do not want to use these features, you should remove them from the template.','<select name=\\\\\"setting[\$setting[settingid]]\\\\\">\r\n<option value=\\\\\"en\\\\\" \".iif(\$setting[value]==\"en\",\"selected\",\"\").\">American English</option>\r\n<option value=\\\\\"uk\\\\\" \".iif(\$setting[value]==\"uk\",\"selected\",\"\").\">British English</option>\r\n<option value=\\\\\"fr\\\\\" \".iif(\$setting[value]==\"fr\",\"selected\",\"\").\">French</option>\r\n<option value=\\\\\"ge\\\\\" \".iif(\$setting[value]==\"ge\",\"selected\",\"\").\">German</option>\r\n<option value=\\\\\"it\\\\\" \".iif(\$setting[value]==\"it\",\"selected\",\"\").\">Italian</option>\r\n<option value=\\\\\"sp\\\\\" \".iif(\$setting[value]==\"sp\",\"selected\",\"\").\">Spanish</option>\r\n<option value=\\\\\"dk\\\\\" \".iif(\$setting[value]==\"dk\",\"selected\",\"\").\">Danish</option>\r\n<option value=\\\\\"br\\\\\" \".iif(\$setting[value]==\"br\",\"selected\",\"\").\">Brazilian Portuguese</option>\r\n<option value=\\\\\"nl\\\\\" \".iif(\$setting[value]==\"nl\",\"selected\",\"\").\">Dutch</option>\r\n<option value=\\\\\"no\\\\\" \".iif(\$setting[value]==\"no\",\"selected\",\"\").\">Norwegian</option>\r\n<option value=\\\\\"pt\\\\\" \".iif(\$setting[value]==\"pt\",\"selected\",\"\").\">Portuguese</option>\r\n<option value=\\\\\"se\\\\\" \".iif(\$setting[value]==\"se\",\"selected\",\"\").\">Swedish</option>\r\n<option value=\\\\\"fi\\\\\" \".iif(\$setting[value]==\"fi\",\"selected\",\"\").\">Finnish</option>\r\n</select>',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,23,'Add template name in comments','addtemplatename','0','Add the template name at the beginning and end of every template rendered. This is useful for debugging and analyzing the HTML code, but turn it off to save bandwidth when running in a production environment.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,24,'Simultaneous sessions limit','sessionlimit','0','Set this to the maximum number of simultaneous sessions that you want to be active at any one time. If this number is exceeded, new users are turned away until the server is less busy. Set this to 0 to disable this option.','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,24,'*NIX Load Limit','loadlimit','0','vBulletin can read the overall load of the server on certain *nix setups (including Linux). This allows vBulletin to determine the load on the server and processor, and to turn away further users if the load becomes too high. If you do not want to use this option, set it to 0. A typical level would be 5.00 for a reasonable warning level.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,25,'Maximum Options','maxpolloptions','10','Maximum number of options a user can select for the poll. Set this to 0 to allow infinitely many options.','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,25,'Update last post time','updatelastpost','0','Update the last post time for the thread (thus returning it to the top of a forum) when a vote is placed.','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Enable Avatars','avatarenabled','1','Use this option to enable/disable the overall use of avatars.<br><br>Avatars are small images displayed under usernames in thread display and user info pages.','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Minimum custom posts','avatarcustomposts','0','Minimum number of posts that a user required before they can specify a custom avatar for use.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Allow uploads','avatarallowupload','1','Allow user to upload their own custom avatar if they have enough posts?','yesno',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Allow website uploads','avatarallowwebsite','1','Allow user to upload their own custom avatar from another website if they have enough posts?','yesno',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Maximum Dimensions','avatarmaxdimension','50','Maximum width and height (in pixels) that the custom avatar image can be.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Maximum File Size','avatarmaxsize','20000','The maximum file size (in bytes) that an avatar can be.\r\n\r\n1 KB = 1024 bytes\r\n1 MB = 1048576 bytes','',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Display Height','numavatarshigh','5','How many rows of avatars do you wish to display to the user when selecting an avatar?','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,26,'Display Width','numavatarswide','5','How many columns of avatars do you wish to display to the user when selecting an avatar?','',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,27,'Maximum File Size','maxattachsize','102400','Specify the maximum size in bytes that an upload may be. Set this value to 0 to enable any sized uploads.\n\n1 KB = 1024 bytes\n1 MB = 1048576 bytes','',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,27,'Valid Extensions','attachextensions','gif jpg png txt zip bmp jpeg','Valid extensions that uploads may have. Separate each item with a space.\n\nFor each extension, you should have a file in the /images/attach/ folder called xxx.gif where xxx is the extension of the file. This allows you to have an icon for each file type.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,27,'View Images','viewattachedimages','1','Do you wish to display attached images in the threads? Select no to just generate a link to download the image.','yesno',3)");
$DB_site->query("INSERT INTO setting (settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (27,'Display Attachments as Thumbnails','attachthumbmode','1','Show a scaled-down copy of image attachments inside posts. Clicking the thumbnail opens the original image. Select No to always show attachments at their original size.','yesno',7)");
$DB_site->query("INSERT INTO setting (settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (27,'Thumbnail Maximum Width','attachthumbwidth','480','Maximum width in pixels for attachment thumbnails; larger images are scaled proportionally. 0 = no limit.','',8)");
$DB_site->query("INSERT INTO setting (settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (27,'Thumbnail Maximum Height','attachthumbheight','360','Maximum height in pixels for attachment thumbnails. 0 = no limit.','',9)");
$DB_site->query("INSERT INTO setting VALUES (NULL,27,'Maximum Image Width','maxattachwidth','0','Set this to the maximum width attached images (jpg and gif) may have. Set it to 0 to not limit the width.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,27,'Maximum Image Height','maxattachheight','0','Set this to the maximum height attached images (jpg and gif) may have. Set it to 0 to not limit the height.','',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,27,'Allow Duplicate Images','allowduplicates','1','Setting this to NO will cause the post to refer to the previous existence of the attachment instead of adding another copy of it to the database. It only checks for attachments posted by the user that is making the post.','yesno',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Enable Titles','ctEnable','1','Enable Custom User titles?','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Maximum Chars','ctMaxChars','25','Maximum length users will be allowed to set their custom title to.','',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Number of Posts','ctPosts','10','Number of posts a user must have before they can use custom titles.','',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Number of Days','ctDays','10','Number of days a user must be registered before they can use custom titles.','',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Allow Mods/Admins to Change titles','ctAdmin','1','Allow Admins/Mods to change titles whether or not they have enough posts or have been registered long enough? User titles must be enabled.','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Boolean','ctEitherOr','1','Do you want a user to have to have the number of posts you specified above AND be registered the number of days you specified above? If you select NO then if a user satisfies EITHER criteria than they can use custom titles. For Ex a User has been registered for 1 year but only has 20 posts. If you specified 100 posts and 1 year then they could use titles if you select NO.','yesno',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Censored Words','ctCensorWords','admin forum moderator vbulletin leader','Type all words you want censored in the field below. Do not use commas to separate words, just use spaces. For example, type \"dog cat boy\", rather than \"dog, cat, boy.\" If you type \"dog\", all words containing the string \"dog\" would be censored (dogma, for instance, would appear as \"***ma\"). To censor more accurately, you can require that censors occur only for exact words. You can do this by placing a censor word in curly braces, as in {dog}. Signifying \"dog\" in the curly braces would mean that dogma would appear as dogma, but dog would appear as \"***\". Thus your censor list may appear as: cat {dog} {barn} barn<br>\r\nDo not use quotation marks and make sure you use curly braces, not parentheses, when specifying exact words.','',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,28,'Exempt Mod from Censor','ctCensorMod','1','Do you want to exempt mods from the censor words? You will want to set this to yes if you censor anything that is part of a moderator\'s title like \"moderator\" as they have custom titles by default and will get censored.','yesno',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'Enable Calendar','calendarenabled','1','Disable/enable the calendar','yesno',1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'Birthdays','calbirthday','1','Use birthdays?','yesno',2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'Individual Birthdays','calshowbirthdays','1','Show the individual birthdays for each user on the calendar? Set this to NO to just show a link if a particular day has birthdays on it.','yesno',3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'html','calallowhtml','0','Allow HTML to be used in the calendar events?','yesno',4)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'bbimagecode','calbbimagecode','1','Allow [IMG] code to be used in calendar events?','yesno',5)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'smilies','calallowsmilies','1','Allow smilies to be used in calendar events?','yesno',6)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'bbcode','calallowbbcode','1','Allow VB code to be used in calendar events?','yesno',7)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'title length','caltitlelength','50','Length to cut off titles when displayed on the main calendar. 0 disables cut-off.','',8)");
$DB_site->query("INSERT INTO setting VALUES (NULL,29,'Starting Day','calStart','1','Pick the day that you would like the weeks on your calendar to start with. Users can also change this in their profile to match their locale\'s customs.','<select name=\\\\\"setting[\$setting[settingid]]\\\\\">\r\n<option value=\\\\\"1\\\\\" \".iif(\$setting[value]==1,\"selected\",\"\").\">Sunday</option>\r\n<option value=\\\\\"2\\\\\" \".iif(\$setting[value]==2,\"selected\",\"\").\">Monday</option>\r\n<option value=\\\\\"3\\\\\" \".iif(\$setting[value]==3,\"selected\",\"\").\">Tuesday</option>\r\n<option value=\\\\\"4\\\\\" \".iif(\$setting[value]==4,\"selected\",\"\").\">Wednesday</option>\r\n<option value=\\\\\"5\\\\\" \".iif(\$setting[value]==5,\"selected\",\"\").\">Thursday</option>\r\n<option value=\\\\\"6\\\\\" \".iif(\$setting[value]==6,\"selected\",\"\").\">Friday</option>\r\n<option value=\\\\\"7\\\\\" \".iif(\$setting[value]==7,\"selected\",\"\").\">Saturday</option>\r\n</select>',9)");

$DB_site->query("INSERT INTO setting VALUES (NULL,30,'Upload In Safe Mode?','safeupload','0','If your server runs PHP with <b>SAFE MODE</b> Restrictions, set this to yes.','yesno','1')");
$DB_site->query("INSERT INTO setting VALUES (NULL,30,'Safe Mode Temp Directory','tmppath','/tmp','If your server is running in safe_mode, you\'ll need to specify a directory that is CHMOD to 777 that will act as a temporary directory for uploads.  All files are removed from this directory after database insertion. <br><b>Note:</b> Do NOT include the trailing slash (\"/\") after the directory name.','','2')");
$DB_site->query("INSERT INTO setting VALUES (NULL,31, 'Enable Who\'s Online', 'WOLenable', '1', 'Selecting NO will disable Who\'s Online for everyone.', 'yesno', 1)");
$DB_site->query("INSERT INTO setting VALUES (NULL,31, 'Enable Guests', 'WOLguests', '1', 'Display Guest activity on Who\'s Online?', 'yesno', 2)");
$DB_site->query("INSERT INTO setting VALUES (NULL,31, 'Resolve IPs', 'WOLresolve', '0', 'Resolve IP addresses for those who have access to view them? This can slow down the Display of Who\'s Online.', 'yesno', 3)");
$DB_site->query("INSERT INTO setting VALUES (NULL,31, 'Refresh', 'WOLrefresh', '60', 'Time period in seconds to refresh the Who\'s Online display. 0 disables refresh.', '', 4)");

echo "<p>Added setting data</p>\n";

$settings=$DB_site->query("SELECT varname,value FROM setting");
while ($setting=$DB_site->fetch_array($settings)) {
	$template.="\$$setting[varname] = \"".addslashes(str_replace("\"","\\\"",$setting['value']))."\";\n";
}

$DB_site->query("INSERT INTO template VALUES (NULL,-1,'options','$template')");

echo "<p>Options added and set successfully. Please continue to the next step to set yourself up as an administrator.</p>";
echo "<p><a href=\"install4php5.php?step=".($step+1)."\">Next step --&gt;</a></p>\n";

} // end step 8

if ($step==9) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 9
------

+ get admin details
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/

?><p>Please fill in the form below to set yourself up as an administrator...</p>

<form action="install4php5.php?step=<?php echo ($step+1); ?>" method="post">
<input type="hidden" name="step" value="<?php echo ($step+1); ?>">

<table border=0>

<tr>
<td>User Name</td>
<td><input type="text" size="35" name="username"></td>
</tr>
<tr>
<td>Password</td>
<td><input type="text" size="35" name="password"></td>
</tr>
<tr>
<td>Email Address</td>
<td><input type="text" size="35" name="email"></td>
</tr>
</table>
<input type=submit value="Submit form and Continue to next step">
</form>
<?php

} // end step 9

if ($step==10) {
/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
STEP 10
-------

+ add admin
+ done!
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
*/
	$username = $_POST['username'];
	$password = $_POST['password'];
	$email = $_POST['email'];
	
  if (get_magic_quotes_gpc()) {
    $username=stripslashes($username);
    $password=stripslashes($password);
  }
  echo "<p>Setting you up as an administrator...</p>";

  if ($username=="" or $password=="" or $email=="") {
    echo "<p>Please complete all the fields.</p>";
  } else {
    $DB_site->query("INSERT INTO user (userid,usergroupid,username,password,email,joindate,cookieuser,daysprune,adminemail,showemail) VALUES (NULL,6,'".vb_escape($username)."','".vb_escape(md5($password))."','".vb_escape($email)."',".time().",1,-1,1,1)");
    $uid=$DB_site->insert_id();
    $DB_site->query("INSERT INTO userfield (userid,field1,field2,field3,field4) VALUES ('$uid','','','','')");

    echo "<p>Set up successfully!</p>";
    echo "<p>You have now completed the install of vBulletin. Once you have deleted this install script you can proceed to the control panel. You will not be able to access the control panel until you delete this script for security reasons.</p>";

    echo "<p>This is the file the you must delete: install4php5.php</p>";
    echo "<p>The control panel can be found <b><a href='index.php'>here</a></b></p>";
  }
}

echo "</"."body>";
echo "<"."!--";
?>

-->
</html>