<?php
// SQL dump writers shared by admin/backup.php. Kept in their own file so the
// dump can be exercised without booting the control panel.

// data dump functions
//
// The dump follows the database the board is actually running on: a MySQL
// connection produces the original MySQL dump, a PostgreSQL connection produces
// a PostgreSQL dump (schema rebuilt from the catalog, because "SHOW FIELDS"
// only carries PostgreSQL type names). Nothing has to be chosen by the admin.

function backup_is_pgsql() {
  global $dbservertype;
  return isset($dbservertype) && strtolower((string)$dbservertype) == "pgsql";
}

function backup_quote_ident($name) {
  return '"' . str_replace('"', '""', $name) . '"';
}

function backup_quote_string($value) {
  return "'" . str_replace("'", "''", $value) . "'";
}

// PostgreSQL literal escaping for the dump: with standard_conforming_strings on
// (the default) backslashes are ordinary characters, so only the quote needs
// doubling. Done here rather than through the driver so the file is importable
// with plain psql, independently of what escape() does.
function pg_quote_value($value) {
  return "'" . str_replace("'", "''", (string)$value) . "'";
}

function dumpwrite($fp, $text) {
  if ($fp) {
    fwrite($fp, $text);
  } else {
    echo $text;
  }
}

// PostgreSQL needs the session pinned to the encoding the catalogue uses and to
// the modern string-literal rules, or a dump written with doubled quotes would
// be misread on import.
function dump_header($fp) {
  if (!backup_is_pgsql()) {
    return;
  }
  dumpwrite($fp, "-- vBulletin backup, PostgreSQL format\n");
  dumpwrite($fp, "SET client_encoding = 'UTF8';\n");
  dumpwrite($fp, "SET standard_conforming_strings = on;\n\n");
}

function sqldumptable($table, $fp=0) {
  if (backup_is_pgsql()) {
    pg_dump_table($table, $fp);
    return;
  }
  sql_dump_table_mysql($table, $fp);
}

function sql_dump_table_mysql($table, $fp=0) {
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
  $index = array();
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
  foreach ($index as $kname => $columns) {
    $tabledump .= ",\n";
    $colnames=implode(",", $columns);

    if($kname == "PRIMARY"){
      // do primary key
      $tabledump .= "   PRIMARY KEY ($colnames)";
    } else {
      // do standard key
      $unique = false;
      if (substr($kname,0,6) == "UNIQUE") {
        // key is unique
        $kname=substr($kname,7);
        $unique = true;
      }

      $tabledump .= "   " . ($unique ? "UNIQUE KEY" : "KEY") . " $kname ($colnames)";

    }
  }

  $tabledump .= "\n);\n\n";
  dumpwrite($fp, $tabledump);

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
        $tabledump .= "'".$DB_site->escape($row[$fieldcounter])."'";
      }
    }

    $tabledump .= ");\n";

    dumpwrite($fp, $tabledump);
  }
  $DB_site->free_result($rows);
}

// PostgreSQL dump: the catalog knows more than "SHOW FIELDS" exposes -- it has
// the real type names, the sequence behind every serial column, the constraint
// definitions and the index definitions -- so the schema is rebuilt from
// pg_catalog and imports straight back with psql.
function pg_dump_table($table, $fp=0) {
  global $DB_site, $dbschema;

  $schema = (isset($dbschema) && $dbschema != "") ? $dbschema : "public";
  $qualified = backup_quote_ident($schema) . "." . backup_quote_ident($table);
  $schema_sql = backup_quote_string($schema);
  $table_sql  = backup_quote_string($table);

  // table oid, so no quoting rules have to be guessed below
  $res = $DB_site->query("SELECT c.oid AS oid FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace " .
                         "WHERE c.relname = $table_sql AND n.nspname = $schema_sql AND c.relkind IN ('r','p')");
  $row = $DB_site->fetch_array($res);
  $DB_site->free_result($res);
  if (!$row) {
    dumpwrite($fp, "DROP TABLE IF EXISTS $qualified;\n");
    return;
  }
  $oid = (int)$row['oid'];

  // columns, in attnum order -- the same order a bare INSERT would use
  $columns = array();
  $res = $DB_site->query(
    "SELECT a.attname AS name, format_type(a.atttypid, a.atttypmod) AS type, a.attnotnull AS notnull, " .
    "       pg_get_expr(d.adbin, d.adrelid) AS def " .
    "FROM pg_attribute a LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum " .
    "WHERE a.attrelid = $oid AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum");
  while ($col = $DB_site->fetch_array($res)) {
    $seq = "";
    if ($col['def'] != "" && preg_match("/^nextval\\('([^']+)'::regclass\\)/", $col['def'], $m)) {
      $seq = $m[1];
    }
    $columns[] = array(
      'name'   => $col['name'],
      'type'   => $col['type'],
      'notnull'=> ($col['notnull'] == "t" || $col['notnull'] === 1 || $col['notnull'] === true),
      'def'    => $col['def'],
      'seq'    => $seq,
    );
  }
  $DB_site->free_result($res);

  if (!$columns) {
    dumpwrite($fp, "DROP TABLE IF EXISTS $qualified;\n");
    return;
  }

  $lines = array();
  $serial_columns = array();
  foreach ($columns as $col) {
    $type = $col['type'];
    if ($col['seq'] != "") {
      // a serial column owns a sequence with a nextval() default; re-create it
      // as serial so the sequence comes back with the table
      $type = ($type == "bigint") ? "bigserial" : "serial";
      $serial_columns[] = $col;
    }
    $line = "   " . backup_quote_ident($col['name']) . " " . $type;
    if ($col['notnull'] && $col['seq'] == "") {
      $line .= " NOT NULL";
    }
    if ($col['seq'] == "" && $col['def'] != "") {
      $line .= " DEFAULT " . $col['def'];
    }
    $lines[] = $line;
  }

  // primary / unique constraints
  $constraint_indexes = array();
  $res = $DB_site->query("SELECT conname AS name, pg_get_constraintdef(oid) AS def " .
                         "FROM pg_constraint WHERE conrelid = $oid AND contype IN ('p','u') " .
                         "ORDER BY contype DESC, conname");
  while ($con = $DB_site->fetch_array($res)) {
    $lines[] = "   " . $con['def'];
  }
  $DB_site->free_result($res);

  dumpwrite($fp, "DROP TABLE IF EXISTS $qualified CASCADE;\n");
  dumpwrite($fp, "CREATE TABLE $qualified (\n" . implode(",\n", $lines) . "\n);\n\n");

  // indexes that are not backed by a constraint
  $constraint_indexes = array();
  $res = $DB_site->query("SELECT i.relname AS name FROM pg_constraint c JOIN pg_class i ON i.oid = c.conindid " .
                         "WHERE c.conrelid = $oid AND c.contype IN ('p','u')");
  while ($c = $DB_site->fetch_array($res)) {
    $constraint_indexes[] = $c['name'];
  }
  $DB_site->free_result($res);

  $res = $DB_site->query("SELECT c.relname AS name, pg_get_indexdef(i.indexrelid) AS def " .
                         "FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid " .
                         "WHERE i.indrelid = $oid AND NOT i.indisprimary ORDER BY c.relname");
  while ($idx = $DB_site->fetch_array($res)) {
    if (in_array($idx['name'], $constraint_indexes)) {
      continue; // already emitted as PRIMARY KEY / UNIQUE
    }
    dumpwrite($fp, $idx['def'] . ";\n");
  }
  $DB_site->free_result($res);
  dumpwrite($fp, "\n");

  // data, with an explicit column list so the dump survives a column reorder
  $collist = array();
  foreach ($columns as $col) {
    $collist[] = backup_quote_ident($col['name']);
  }
  $rows = $DB_site->query("SELECT * FROM $qualified");
  while ($row = $DB_site->fetch_array($rows)) {
    $values = array();
    foreach ($columns as $col) {
      $value = isset($row[$col['name']]) ? $row[$col['name']] : null;
      if ($value === null) {
        $values[] = "NULL";
      } elseif ($col['type'] == "bytea") {
        // The driver hands bytea back as raw bytes (see decode_bytea_row),
        // so hex it here: decode() keeps newline/backslash handling out of
        // the string literal entirely
        $values[] = "decode('" . bin2hex($value) . "', 'hex')";
      } else {
        $values[] = pg_quote_value($value);
      }
    }
    dumpwrite($fp, "INSERT INTO $qualified (" . implode(",", $collist) . ") VALUES (" . implode(", ", $values) . ");\n");
  }
  $DB_site->free_result($rows);

  // the sequences must not restart at 1 on the restored board
  foreach ($serial_columns as $col) {
    dumpwrite($fp, "SELECT setval(" . backup_quote_string($col['seq']) . ", " .
      "COALESCE((SELECT MAX(" . backup_quote_ident($col['name']) . ") FROM $qualified), 1), true);\n");
  }
  dumpwrite($fp, "\n");
}
