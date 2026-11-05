<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog();

cpheader();

if (isset($action)==0) {
  $action="options";
}

if ($action=="options") {

/*
changed settings:

bbactive
bbclosedreason
faxnumber
enablesearches
enableemail
timeoffset
dateformat
timeformat
registereddateformat
addheaders
logip
*/

  if (isset($settinggroupid)) {
    $sqlwhere=" WHERE settinggroupid=$settinggroupid ";
  } else {
    $sqlwhere=" WHERE displayorder<>0 ";
  }

  echo "<p>Settings:</p>\n";
  echo "<table border='0'><tr valign='top'><td><ul>\n";

  $settinggroups=$DB_site->query("SELECT settinggroupid,title,displayorder FROM settinggroup $sqlwhere ORDER BY displayorder");
  $numgroups = $DB_site->num_rows($settinggroups);
  $percolumn = ceil($numgroups/2);
  while ($settinggroup=$DB_site->fetch_array($settinggroups)) {
  	if ($settingcounter++ == $percolumn) { echo "</ul></td><td><ul>"; }
	echo makelinkcode($settinggroup['title'],"#settinggroup$settinggroup[settinggroupid]")."<br>\n";
  }

  echo "</ul></td></tr></table>\n";

  doformheader("options","dooptions",0,0);

  $DB_site->data_seek(0,$settinggroups);
  while ($settinggroup=$DB_site->fetch_array($settinggroups)) {

    echo "<table cellpadding='0' cellspacing='0' border='0' width='100%' class='tblborder'><tr><td>\n";
	echo "<table cellpadding='4' cellspacing='1' border='0' width='100%'>\n";
  	maketableheader($settinggroup['title'],"settinggroup$settinggroup[settinggroupid]");

    $settings=$DB_site->query("SELECT settingid,title,varname,value,description,optioncode,displayorder FROM setting WHERE settinggroupid=$settinggroup[settinggroupid] ORDER BY displayorder");
    while ($setting=$DB_site->fetch_array($settings)) {

      $setting['description'] = vbsetting_desc($setting['varname'], $setting['description']);
      echo "<tr class='".getrowbg()."'>\n<td width=\"65%\"><p><b>$setting[title]</b><br><font size='1'>$setting[description]</font></p></td>\n<td width=\"35%\">";

      if ($setting['optioncode']=="") {
        echo "<input type=\"text\" size=\"35\" name=\"setting[$setting[settingid]]\" value=\"".htmlspecialchars($setting['value'])."\">";
      } elseif ($setting['optioncode']=="yesno") {
        echo "Yes<input type=\"radio\" name=\"setting[$setting[settingid]]\"  ".iif($setting['value']==1,"checked","")." value=\"1\"> No <input type=\"radio\" name=\"setting[$setting[settingid]]\" ".iif($setting['value']==0,"checked","")." value=\"0\">";
	  } elseif ($setting['optioncode']=="textarea") {
	    echo "<textarea name=\"setting[$setting[settingid]]\" rows=\"6\" cols=\"50\">".htmlspecialchars($setting['value'])."</textarea>";
      } else {
        eval ("echo \"$setting[optioncode]\";");
      }

      echo "</td>\n</tr>\n";
    }
	echo "</table></td></tr></table><br><br>\n";
  }
  echo "<table cellpadding='1' cellspacing='0' border='0' width='100%' class='tblborder'><tr><td>\n";
  echo "<table cellpadding='4' cellspacing='0' border='0' width='100%'>\n";
  $tableadded=1;
  doformfooter("Save Changes");


}

// ###################### Start set options #######################
if ($_POST['action']=="dooptions") {

  while (list($key,$val)=each($setting)) {
    $DB_site->query("UPDATE setting SET value='".vb_escape($val)."' WHERE settingid='$key'");
  }

  $optionstemplate=generateoptions();
  $DB_site->query("UPDATE template SET template='$optionstemplate' WHERE title='options'");

  echo "Thanks for updating the options.";

}

cpfooter();
?>
