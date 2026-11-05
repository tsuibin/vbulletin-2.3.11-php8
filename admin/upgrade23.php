<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

$oldversion = "2.2.9";
$newversion = "2.3.0";
$thisscript = "upgrade23.php";
$scriptnumber = 23;

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

function gotonext($extra="") {
	global $step,$thisscript;
	$nextstep = $step+1;
	echo "<p><a href=\"$thisscript?step=$nextstep\">Continue with the upgrade --&gt;</a> $extra</p>\n";
}

function createupgradelist($startat,$stopat,$comma="") {
	if ($startat<=$stopat) {
	  return $comma."upgrade$startat.php".createupgradelist($startat+1,$stopat,", ");
	}
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

if (!$step) {
  $step = 1;
}

// ******************* STEP 1 *******************
if ($step==1) {

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

  ?>
  <p>This script will upgrade you from vBulletin <?php echo $oldversion ?> to vBulletin <?php echo $newversion ?>. If you are upgrading from a version other than <?php echo $oldversion ?>, please run previous upgrade scripts first.</p>

  <ul>
  <li>Installing a new version: run <i>install.php</i><br><br>
  <li>Upgrading from vBulletin 2.0.3 or before: please visit <a href="http://www.vbulletin.com/members/upgrade.html">http://www.vbulletin.com/members/upgrade.html</a>
  <li>Upgrading from vBulletin 2.2.0: run <i><?php echo createupgradelist(14,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.1: run <i><?php echo createupgradelist(15,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.2: run <i><?php echo createupgradelist(16,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.3: run <i><?php echo createupgradelist(17,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.4: run <i><?php echo createupgradelist(18,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.5: run <i><?php echo createupgradelist(19,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.6: run <i><?php echo createupgradelist(20,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.7: run <i><?php echo createupgradelist(21,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.8: run <i><?php echo createupgradelist(22,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.2.9: run <i><?php echo createupgradelist(23,$scriptnumber); ?></i>
  <li>Upgrading from vBulletin 2.3.0 Release Candidate 1, 2 or 3: run <i>upgrade23.php <b>again</b></i></li>
  </ul>

  <?php

  gotonext();
}

// ******************* STEP 2 *******************
if ($step==2)
{
	echo "<p>Updating a setting description...</p>";
	$DB_site->query("
		UPDATE setting SET
			title = 'Show \'edited by\' for admins / super moderators?',
			description = 'If you want the [edited by xxx] message to appear when an administrator or super moderator edits a message, select \'Yes\' here.'
		WHERE varname = 'showeditedbyadmin'
	");

	echo "<p>Updating a setting description...</p>";
	$DB_site->query("
		UPDATE setting SET
			title = 'Allow Duplicate Attachments?',
			description = 'Setting this to NO will cause the post to refer to the previous existence of the attachment instead of adding another copy of it to the database. It only checks for attachments posted by the user that is making the post.<br /><br /><b>Note:</b> if you have a large number of attachments in your database we recommend that you set this option to \'No\'.'
		WHERE varname = 'allowduplicates'
	");

	echo "<p>Adding admin option for Image Verification system...";
	if ($DB_site->query_first("SELECT settingid FROM setting WHERE varname = 'regimagecheck'"))
	{
		echo ' (field 1 already exists - continuing)';
	}
	else
	{
		$DB_site->query("INSERT INTO setting (settingid,settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (NULL,6,'Image Verification','regimagecheck','1','If enabled, this option will display a random image to new users at registration time. The user will have to copy the contents of the image in order to verify that an automated system is not processing registrations. Requires GD to be enabled on your server.','yesno','10')");
	}
	echo '</p>';

	echo "<p>Adding admin option for GD selection...";
	if ($DB_site->query_first("SELECT settingid FROM setting WHERE varname = 'gdversion'"))
	{
		echo ' (field 2 already exists - continuing)';
	}
	else
	{
		$DB_site->query("INSERT INTO setting (settingid,settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (NULL,2,'GD Version','gdversion','0','Version of GD installed on your server. If GD is enabled, you can find the version listed in your phpinfo().','<select name=\\\\\"setting[\$setting[settingid]]\\\\\">
		<option value=\\\\\"0\\\\\" \".iif(\$setting[value]==0,\"selected\",\"\").\">None</option>
		<option value=\\\\\"1\\\\\" \".iif(\$setting[value]==1,\"selected\",\"\").\">GD 1.6.x/1.8.x</option>
		<option value=\\\\\"2\\\\\" \".iif(\$setting[value]==2,\"selected\",\"\").\">GD 2+</option>
		</select>','100')");
	}
	echo '</p>';

	// do stuff that might cause an error if we've run this script before with reporterror=0

	// **********************
	$DB_site->reporterror = 0;
	// **********************

	echo "<p>Creating Image Verification table...</p>";
	$DB_site->query("CREATE TABLE regimage (
		regimagehash CHAR( 32 ) NOT NULL ,
		imagestamp CHAR( 6 ) NOT NULL ,
		dateline INT UNSIGNED NOT NULL ,
		INDEX (regimagehash, dateline))
	");

	echo "<p>Adding new column to useractivation...</p>";
	$DB_site->query("ALTER TABLE useractivation ADD usergroupid SMALLINT UNSIGNED NOT NULL");
	echo "<p>Setting default usergroupid...</p>";
	$DB_site->query("UPDATE useractivation SET usergroupid=2");
	
	// **********************
	$DB_site->reporterror = 1;
	// **********************

	echo "<p>Updating styleid to use forum default...</p>";
	$DB_site->query("UPDATE user SET styleid=0 WHERE styleid=1");

	echo "<p>Done</p>";

	gotonext();
}

// ******************* STEP 3 *******************
if ($step==3) {

  echo "<b>Importing new templates ....</b>";
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

  echo "Done!<p>\n";

  gotonext();
}

// ******************* STEP 4 *******************
if ($step==4) {
  // update version
  echo "Updating version number .... ";
  $DB_site->query("UPDATE setting SET value='$newversion' WHERE varname='templateversion'");

  $template="";
  $settings=$DB_site->query("SELECT varname,value FROM setting");
  while ($setting=$DB_site->fetch_array($settings)) {
  	$template.="\$$setting[varname] = \"".vb_escape(str_replace("\"","\\\"",$setting['value']))."\";\n";
  }

  $DB_site->query("UPDATE template SET template='$template' WHERE title='options'");
  echo "Done!\n";

  echo "<p>Upgrade to $newversion completed successfully! <br>Please delete the following files if you uploaded them: install.php, and all files that begin with 'upgrade'";

  echo "<p>Thank you for using vBulletin.";
}

?>
</body>
</html>
