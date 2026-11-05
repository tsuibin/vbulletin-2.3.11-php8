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

// data dump functions
function sqldumptable($table, $fp=0) {
  global $DB_site;

  $tabledump = "DROP TABLE IF EXISTS $table;\n";
  $tabledump .= "CREATE TABLE $table (\n";

  $firstfield=1;

  // get columns and spec
  $fields = $DB_site->query("SHOW FIELDS FROM $table");
  while ($field = $DB_site->fetch_array($fields)) {
    if (!$firstfield) {
      $tabledump .= ",\n";
    } else {
      $firstfield=0;
    }
    $tabledump .= "   $field[Field] $field[Type]";
    if (!empty($field["Default"])) {
      // get default value
      $tabledump .= " DEFAULT '$field[Default]'";
    }
		if ($field['Null'] != "YES") {
      // can field be null
      $tabledump .= " NOT NULL";
    }
    if ($field['Extra'] != "") {
      // any extra info?
      $tabledump .= " $field[Extra]";
    }
  }
  $DB_site->free_result($fields);

  // get keys list
  $keys = $DB_site->query("SHOW KEYS FROM $table");
  while ($key = $DB_site->fetch_array($keys)) {
    $kname=$key['Key_name'];
    if ($kname != "PRIMARY" and $key['Non_unique'] == 0) {
      $kname="UNIQUE|$kname";
    }
    if(!is_array($index[$kname])) {
      $index[$kname] = array();
    }
    $index[$kname][] = $key['Column_name'];
  }
  $DB_site->free_result($keys);

  // get each key info
  while(list($kname, $columns) = @each($index)){
    $tabledump .= ",\n";
    $colnames=implode(",", $columns);

    if($kname == "PRIMARY"){
      // do primary key
      $tabledump .= "   PRIMARY KEY ($colnames)";
    } else {
      // do standard key
      if (substr($kname,0,6) == "UNIQUE") {
        // key is unique
        $kname=substr($kname,7);
      }

      $tabledump .= "   KEY $kname ($colnames)";

    }
  }

  $tabledump .= "\n);\n\n";
  if ($fp) {
    fwrite($fp,$tabledump);
  } else {
    echo $tabledump;
  }

  // get data
  $rows = $DB_site->query("SELECT * FROM $table");
  $numfields=$DB_site->num_fields($rows);
  while ($row = $DB_site->fetch_array($rows)) {
    $tabledump = "INSERT INTO $table VALUES(";

    $fieldcounter=-1;
    $firstfield=1;
    // get each field's data
    while (++$fieldcounter<$numfields) {
      if (!$firstfield) {
        $tabledump.=", ";
      } else {
        $firstfield=0;
      }

      if (!isset($row[$fieldcounter])) {
        $tabledump .= "NULL";
      } else {
        $tabledump .= "'".mysql_escape_string($row[$fieldcounter])."'";
      }
    }

    $tabledump .= ");\n";
    
    if ($fp) {
      fwrite($fp,$tabledump);
    } else {
      echo $tabledump;
    }
  }
  $DB_site->free_result($rows);

  //return $tabledump;
}

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

	$result=$DB_site->query("SHOW tables");
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
