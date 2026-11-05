<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

$oldversion = "2.2.3";
$newversion = "2.2.4";
$thisscript = "upgrade17.php";
$scriptnumber=17;

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

if (function_exists("set_time_limit")==1 and get_cfg_var("safe_mode")==0) {
  @set_time_limit(1200);
}

// ###################### Start init #######################

unset($dbservertype);

//load config
require('./config.php');

// init db **********************
// load db class
$dbservertype = strtolower($dbservertype);
$dbclassname="./db_$dbservertype.php";
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

?>
<HTML><HEAD>
<META content="text/html; charset=UTF-8" http-equiv=Content-Type>
<META content="MSHTML 5.00.3018.900" name=GENERATOR></HEAD>
<link rel="stylesheet" href="../cp.css">
<title>vBulletin <?php echo $oldversion ?> to <?php echo $newversion ?> Upgrade script</title>
</HEAD>
<BODY>
<table width="100%" bgcolor="#3F3849" cellpadding="2" cellspacing="0" border="0"><tr><td>
<table width="100%" bgcolor="#524A5A" cellpadding="3" cellspacing="0" border="0"><tr>
<td><a href="http://vbulletin.com/forum" target="_blank"><img src="cp_logo.gif" width="160" height="49" border="0" alt="Click here to visit the vBulletin support forums"></a></td>
<td width="100%" align="center">
<p><font size="2" color="#F7DE00"><b>vBulletin <?php echo $oldversion ?> to <?php echo $newversion ?> Upgrade Script</b></font></p>
<p><font size="1" color="#F7DE00"><b>(Note: Please be patient as some parts of this may take some time.)</b></font></p>
</td></tr></table></td></tr></table>
<br>
<?php

	if (!$confirm) {
		// load options
		$optionstemp=$DB_site->query_first("SELECT template FROM template WHERE title='options'");
		eval($optionstemp['template']);

		if ( $templateversion != $oldversion ) {
			?><p>Warning: you are currently running version <?php echo $templateversion; ?> and this script upgrades from version <?php echo $oldversion; ?>. If you are sure you want to continue, please click <a href="upgrade<?php echo $scriptnumber; ?>.php?confirm=1">here</a> otherwise please consult the upgrade instructions.</p></body></html>
			<?php
			die();

		}
	}

  echo "<p>All this upgrade script will do is update your version number. There were no template changes between all releases of 2.2.3 and 2.2.4.</p>\n";

  // update version
  echo "<p>Updating version number .... ";
  $DB_site->query("UPDATE setting SET value='$newversion' WHERE varname='templateversion'");

  $template="";
  $settings=$DB_site->query("SELECT varname,value FROM setting");
  while ($setting=$DB_site->fetch_array($settings)) {
  	$template.="\$$setting[varname] = \"".vb_escape(str_replace("\"","\\\"",$setting['value']))."\";\n";
  }

  $DB_site->query("UPDATE template SET template='$template' WHERE title='options'");
  echo "Done!</p>\n";

  echo "<p>Upgrade to $newversion completed successfully! <br>Please delete the following files if you uploaded them: install.php, and all files that begin with 'upgrade'";

  echo "<p>Thank you for using vBulletin.";


?>
</body>
</html>
