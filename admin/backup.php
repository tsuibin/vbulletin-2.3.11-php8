<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

if (function_exists("set_time_limit")==1 and get_cfg_var("safe_mode")==0) {
  @set_time_limit(0);
}

if (isset($action) and ($action=="csvtable" or $action=="sqltable")) {
  $noheader=1;
}

//suppress gzipping
$nozip=1;

require("./global.php");

adminlog(iif($table!="","Table = $table",""));

// The dump writers live in backup_dump.php; see that file for the MySQL vs
// PostgreSQL branch.
require_once __DIR__ . '/backup_dump.php';


function csvdumptable($table,$separator,$quotes,$showhead) {
  global $DB_site;

  // get columns for header row
  if ($showhead) {
    $firstfield=1;
    $fields = $DB_site->query("SHOW FIELDS FROM $table");
    while ($field = $DB_site->fetch_array($fields)) {
      if (!$firstfield) {
        $contents.=$separator;
      } else {
        $firstfield=0;
      }
      $contents.=$quotes.$field['Field'].$quotes;
    }
    $DB_site->free_result($fields);
  }
  $contents.="\n";


  // get data
  $rows = $DB_site->query("SELECT * FROM $table");
  $numfields=$DB_site->num_fields($rows);
  while ($row = $DB_site->fetch_array($rows)) {

    $fieldcounter=-1;
    $firstfield=1;
    while (++$fieldcounter<$numfields) {
      if (!$firstfield) {
        $contents.=$separator;
      } else {
        $firstfield=0;
      }

      if (!isset($row[$fieldcounter])) {
        $contents .= "NULL";
      } else {
        $contents .= $quotes.vb_escape($row[$fieldcounter]).$quotes;
      }
    }

    $contents .= "\n";
  }
  $DB_site->free_result($rows);

  return $contents;
}

if ($_POST['action']=="csvtable") {
  header("Content-disposition: filename=".$table.".csv");
  header("Content-type: unknown/unknown");

  echo csvdumptable($table,$separator,$quotes,$showhead);

  exit;

}

if ($_POST['action']=="sqltable") {
	header("Content-disposition: filename=vbulletin.sql");
	header("Content-type: unknown/unknown");

	dump_header(0);

	while (list($key,$val)=each($table)) {
		if ($val==1) {
		  sqldumptable($key);
		  echo "\n\n\n";
		}
  }

  exit;

}

cpheader();

if (isset($action)==0) {
  $action="choose";
}

if ($action=="choose") {

  ?>
  <p>From here, you can back up your vBulletin database.</p>

  <?php
  // the dump format follows the configured database, so switching between
  // MySQL and PostgreSQL needs no change here
  echo "<p><b>Dump format:</b> " . (backup_is_pgsql() ? "PostgreSQL" : "MySQL") .
       " (matches the database this board is running on).</p>\n";
  ?>

  <P><b>SQL Dump of data:</b></p>

  <?php

  doformheader("backup","sqltable");
  maketableheader("Database tables to include in backup");

  $result=$DB_site->query("SHOW tables");
  while ($currow=$DB_site->fetch_array($result)) {
    makeyesnocode($currow[0],"table[$currow[0]]",1);
  }

  doformfooter("Do Backup");


  doformheader("backup","sqlfile");
  maketableheader("Save data to file on server:");
  makeinputcode("Path and filename on server","filename","./forumbackup-".date("m-d-Y",time()).".sql",0,60);
  echo "<tr class='firstalt'><td colspan='2'><p><b>PHP must have access to write in this directory</b> (usually chmod 0777)</p></td></tr>\n";
  echo "<tr class='firstalt'><td colspan='2'><p><b>Warning:</b> Do not place your backup in an Internet-accessible directory. Place it outside of your root website directory if possible!</p></td></tr>\n";
  doformfooter("Save File");

  doformheader("backup","csvtable");
  maketableheader("CSV Dump of data:");

  echo "<tr class='".getrowbg()."'>\n<td><p>Choose table:</p></td>\n<td><p>";
  echo "<select name=\"table\" size=\"1\">\n";

  $result=$DB_site->query("SHOW tables");
  while ($currow=$DB_site->fetch_array($result)) {
    echo "<option value=\"$currow[0]\">$currow[0]</option>\n";
  }

  echo "</select></p></td></tr>\n\n";

  makeinputcode("separator","separator",",");
  makeinputcode("Quotes","quotes","'");
  makeyesnocode("Show column names","showhead",1);

  doformfooter("Generate");

}

if ($_POST['action']=="sqlfile") {
  $filehandle=fopen($filename,"w");
  dump_header($filehandle);
  $result=$DB_site->query("SHOW tables");
  while ($currow=$DB_site->fetch_array($result)) {
		sqldumptable($currow[0], $filehandle);
		fwrite($filehandle, "\n\n\n");
		echo "<p>Dumping $currow[0]</p>";
  }
  fclose($filehandle);

  echo "<p>Data dumped sucessfully!</p>";

}

cpfooter();
?>
