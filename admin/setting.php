<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

unset($query);

adminlog(iif($settingid!=0,"setting id = $settingid",iif($settinggroupid!=0,"settinggroup id = $settinggroupid","")));

cpheader();

if (!isset($action)) {
  $action="modify";
}

// ###################### Start Add #######################
if ($action=="add") {

  echo "<p>Leaving the 'Code to generate option' box empty will just provide a default text box</p>";

  doformheader("setting","insert");
  maketableheader("Add New Setting");

  makechoosercode("Setting Category","settinggroupid","settinggroup",$settinggroupid);
  makeinputcode("Title","title");
  makeinputcode("Variable Name","varname");
  makeinputcode("Value","value");
  maketextareacode("Description","description","",5);
  maketextareacode("Code to generate option","optioncode","",5);
  makeinputcode("Display Order","displayorder");

  doformfooter("Save");
}

// ###################### Start Insert #######################
if ($_POST['action']=="insert") {

  $query[1] = "INSERT INTO setting (settingid,settinggroupid,title,varname,value,description,optioncode,displayorder) VALUES (NULL,$settinggroupid,'".vb_escape($title)."','".vb_escape($varname)."','".vb_escape($value)."','".vb_escape($description)."','".vb_escape($optioncode)."','$displayorder')";

  $DB_site->query($query[1]);

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start Edit #######################
if ($action=="edit") {

  echo "<p>Leaving the 'Code to generate option' box empty will just provide a default text box</p>";

  $setting=$DB_site->query_first("SELECT settingid,settinggroupid,title,varname,value,description,optioncode,displayorder FROM setting WHERE settingid=$settingid");

  doformheader("setting","doupdate");
  maketableheader("Edit Setting");
  makehiddencode("settingid","$settingid");

  makechoosercode("Setting Category","settinggroupid","settinggroup",$setting['settinggroupid']);
  makeinputcode("Title","title",$setting['title']);
  makeinputcode("Variable Name","varname",$setting['varname']);
  makeinputcode("Value","value",$setting['value']);
  maketextareacode("Description","description",$setting['description'],5,60);
  maketextareacode("Code to generate option","optioncode",$setting['optioncode'],5,60);
  makeinputcode("Display Order","displayorder",$setting['displayorder']);

  doformfooter("Save Changes");

}

// ###################### Start Update #######################
if ($_POST['action']=="doupdate") {

  $query[1] = "UPDATE setting SET settinggroupid=$settinggroupid,title='".vb_escape($title)."',varname='".vb_escape($varname)."',value='".vb_escape($value)."',description='".vb_escape($description)."',optioncode='".vb_escape($optioncode)."',displayorder='$displayorder' WHERE settingid='$settingid'";

  $DB_site->query($query[1]);

  echo "<p>Record updated!</p>";

  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("setting","kill");
	makehiddencode("settingid",$settingid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this setting?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $query[1] = "DELETE FROM setting WHERE settingid=$settingid";

  $DB_site->query($query[1]);

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start add settinggroup #######################
if ($action=="addgroup") {

  doformheader("setting","insertgroup");
  maketableheader("Add New Settings Group");

  makeinputcode("Title","title");
  makeinputcode("Display Order","displayorder");

  doformfooter("Save");
}

// ###################### Start insert settinggroup #######################
if ($_POST['action']=="insertgroup") {

  $query[1] = "INSERT INTO settinggroup (settinggroupid,title,displayorder) VALUES (NULL,'".vb_escape($title)."','$displayorder')";

  $DB_site->query($query[1]);

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start edit settinggroup #######################
if ($action=="editgroup") {

  $settinggroup=$DB_site->query_first("SELECT title,displayorder FROM settinggroup WHERE settinggroupid=$settinggroupid");

  doformheader("setting","doupdategroup");
  maketableheader("Edit Settings Group");
  makehiddencode("settinggroupid","$settinggroupid");

  makeinputcode("Title","title",$settinggroup['title']);
  makeinputcode("Display Order","displayorder",$settinggroup['displayorder']);

  doformfooter("Save Changes");

}

// ###################### Start do update settinggroup #######################
if ($_POST['action']=="doupdategroup") {

  $query[1] = "UPDATE settinggroup SET title='".vb_escape($title)."',displayorder='$displayorder' WHERE settinggroupid='$settinggroupid'";

  $DB_site->query($query[1]);

  echo "<p>Record updated!</p>";

  $action="modify";

}
// ###################### Start Remove settinggroup #######################

if ($action=="removegroup") {

	doformheader("setting","killgroup");
	makehiddencode("settinggroupid",$settinggroupid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this settings group? Doing so will also delete all associated settings!");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill settinggroup #######################

if ($_POST['action']=="killgroup") {

  $query[1] = "DELETE FROM settinggroup WHERE settinggroupid=$settinggroupid";
  $query[2] = "DELETE FROM setting WHERE settinggroupid=$settinggroupid";

  $DB_site->query($query[1]);
  $DB_site->query($query[2]);

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {

  if (is_array($query)) {
  	echo "<blockquote><b>Queries Executed:</b> <font size='1'>(useful for copy/pasting into upgrade scripts)</font><br><textarea rows='10' cols='100' style='color:red'>\n";
  	while(list($queryindex,$querytext)=each($query)) {
		echo "\$DB_site->query(\"".htmlspecialchars($querytext)."\");\n";
	}
	echo "</textarea></blockquote>\n";
  }

  $optionstemplate=generateoptions();
  $DB_site->query("UPDATE template SET template='$optionstemplate' WHERE title='options'");

  $settinggroups=$DB_site->query("SELECT settinggroupid,title,displayorder FROM settinggroup ORDER BY displayorder");

  echo "<ul>";

  while ($settinggroup=$DB_site->fetch_array($settinggroups)) {

    $settinggroup['title'] = vbsettinggroup_title($settinggroup['title']);
    echo "<hr><li>$settinggroup[title] ".
			makelinkcode("edit","setting.php?s=$session[sessionhash]&amp;action=editgroup&amp;settinggroupid=$settinggroup[settinggroupid]").
			makelinkcode("remove","setting.php?s=$session[sessionhash]&amp;action=removegroup&amp;settinggroupid=$settinggroup[settinggroupid]").
			makelinkcode("add setting","setting.php?s=$session[sessionhash]&amp;action=add&amp;settinggroupid=$settinggroup[settinggroupid]").
			"<ul>\n";

    $settings=$DB_site->query("SELECT settingid,title,varname FROM setting WHERE settinggroupid=$settinggroup[settinggroupid] ORDER BY displayorder");
    while ($setting=$DB_site->fetch_array($settings)) {

      echo "<li>".vbsetting_title($setting['varname'], $setting['title'])." ($setting[varname]) ".
				makelinkcode("edit","setting.php?s=$session[sessionhash]&amp;action=edit&amp;settingid=$setting[settingid]").
				makelinkcode("remove","setting.php?s=$session[sessionhash]&amp;action=remove&amp;settingid=$setting[settingid]").
				"</li>\n";

    }

    echo "</ul></li>\n";

  }

  echo "</ul><p>That's all folks</p>";
}

cpfooter();
?>