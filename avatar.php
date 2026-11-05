<?php
require_once __DIR__ . '/admin/php8compat.php';

$noheader=1;

//require("./global.php");

set_magic_quotes_runtime(0);

error_reporting(7);

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

if (!empty($userid)) {
  $userid = intval($userid);
} else if (!empty($_POST['userid'])) {
  $userid = intval($_POST['userid']);
} else if (!empty($_GET['userid'])) {
  $userid = intval($_GET['userid']);
} else {
  $userid = 0;
}

if ($avatarinfo=$DB_site->query_first("SELECT avatardata,dateline,filename FROM customavatar WHERE userid=".intval($userid))) {
  header("Cache-control: max-age=31536000");
  header("Expires: " . gmdate("D, d M Y H:i:s",time()+31536000) . " GMT");
  header("Content-disposition: inline; filename=\"$avatarinfo[filename]\"");
  header("Content-Length: ".strlen($avatarinfo['avatardata']));
  header("Last-Modified: " . gmdate("D, d M Y H:i:s",$avatarinfo['dateline']) . " GMT");

  $extension = substr(strrchr(strtolower($avatarinfo['filename']), '.'),1);
  if($extension == 'jpg') {
    header('Content-type: image/jpeg');
  } elseif ($extension == 'png') {
    header('Content-type: image/png');
  } else {
    header('Content-type: image/gif');
  }
  echo $avatarinfo['avatardata'];
} else {
  header("Content-type: image/gif");
  readfile("./images/clear.gif");
}

?>