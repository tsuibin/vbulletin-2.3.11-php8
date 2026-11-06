<?php
// ###################### Start database migration #######################
//
// Used by dbsettings.php: copies the whole vBulletin database from the engine
// that is active right now into another engine, so an administrator can move
// the forum from MySQL to PostgreSQL (or the other way round) without a dump /
// restore round trip and without knowing any SQL.
//
// The copy is done in three steps:
//   1. read the source schema (information_schema of whichever engine is live)
//      and emit CREATE TABLE statements in the *target* dialect,
//   2. stream the rows table by table, converting the values that differ
//      between the engines (bytea <-> blob, serial <-> auto_increment),
//   3. compare row counts per table; only when every table matches does the
//      caller rewrite admin/config.php. A failure therefore leaves the forum
//      running on the old database.

require_once __DIR__ . '/php8compat.php';

define('DBMIG_BATCH_ROWS', 200);

function dbmig_quote($type, $name) {
	if ($type == 'pgsql') {
		return '"' . str_replace('"', '""', (string) $name) . '"';
	}
	return '`' . str_replace('`', '``', (string) $name) . '`';
}

function dbmig_pg_literal($value) {
	return "'" . str_replace("'", "''", (string) $value) . "'";
}

// ---------------------------------------------------------------- connections

// Opens a second connection next to the one the forum is already using.
// Returns array(type, link, schema, dbname) or null with $error set.
function dbmig_open($values, &$error) {
	$error = '';
	$type = ($values['dbservertype'] == 'pgsql') ? 'pgsql' : 'mysql';

	if ($type == 'pgsql') {
		if (!function_exists('pg_connect')) {
			$error = 'PHP 未启用 pgsql 扩展。';
			return null;
		}
		$port = ($values['dbport'] === '') ? '5432' : $values['dbport'];
		$conn = "host=" . $values['servername'] . " port=" . $port . " dbname=" . $values['dbname'];
		if ($values['dbusername'] !== '') {
			$conn .= " user=" . $values['dbusername'];
		}
		if ($values['dbpassword'] !== '') {
			$conn .= " password=" . $values['dbpassword'];
		}
		try {
			$link = @pg_connect($conn, PGSQL_CONNECT_FORCE_NEW);
		} catch (Throwable $e) {
			$error = 'PostgreSQL 连接失败：' . $e->getMessage();
			return null;
		}
		if (!$link) {
			$error = 'PostgreSQL 连接失败：' . $values['servername'] . ':' . $port . '/' . $values['dbname'];
			return null;
		}
		$schema = ($values['dbschema'] === '') ? 'public' : $values['dbschema'];
		@pg_query($link, "SET client_encoding TO 'UTF8'");
		@pg_query($link, "SET standard_conforming_strings TO on");
		@pg_query($link, 'SET bytea_output TO hex');
		@pg_query($link, 'SET search_path TO ' . dbmig_quote('pgsql', $schema) . ', pg_catalog');
		return array('type' => 'pgsql', 'link' => $link, 'schema' => $schema, 'dbname' => $values['dbname']);
	}

	if (!function_exists('mysqli_connect')) {
		$error = 'PHP 未启用 mysqli 扩展。';
		return null;
	}
	$port = ($values['dbport'] === '') ? 0 : (int) $values['dbport'];
	mysqli_report(MYSQLI_REPORT_OFF);
	try {
		$link = @mysqli_connect($values['servername'], $values['dbusername'], $values['dbpassword'], $values['dbname'], $port);
	} catch (Throwable $e) {
		$error = 'MySQL 连接失败：' . $e->getMessage();
		return null;
	}
	if (!$link) {
		$error = 'MySQL 连接失败：' . mysqli_connect_error();
		return null;
	}
	@mysqli_set_charset($link, 'utf8mb4');
	return array('type' => 'mysql', 'link' => $link, 'schema' => '', 'dbname' => $values['dbname']);
}

function dbmig_close($conn) {
	if (!$conn) {
		return;
	}
	if ($conn['type'] == 'pgsql') {
		@pg_close($conn['link']);
	} else {
		@mysqli_close($conn['link']);
	}
}

function dbmig_exec($conn, $sql) {
	if ($conn['type'] == 'pgsql') {
		$res = @pg_query($conn['link'], $sql);
		if ($res === false) {
			return pg_last_error($conn['link']);
		}
		pg_free_result($res);
		return '';
	}
	$res = @mysqli_query($conn['link'], $sql);
	if ($res === false) {
		return mysqli_error($conn['link']);
	}
	if ($res instanceof mysqli_result) {
		mysqli_free_result($res);
	}
	return '';
}

// ------------------------------------------------------------- introspection

function dbmig_tables($conn) {
	$tables = array();
	if ($conn['type'] == 'pgsql') {
		$res = @pg_query($conn['link'], 'SELECT tablename FROM pg_tables WHERE schemaname = ' . dbmig_pg_literal($conn['schema']) . ' ORDER BY tablename');
		while ($row = pg_fetch_row($res)) {
			$tables[] = $row[0];
		}
		pg_free_result($res);
		return $tables;
	}
	$res = @mysqli_query($conn['link'], 'SHOW TABLES');
	while ($row = mysqli_fetch_row($res)) {
		$tables[] = $row[0];
	}
	mysqli_free_result($res);
	return $tables;
}

function dbmig_count($conn, $table) {
	if ($conn['type'] == 'pgsql') {
		$res = @pg_query($conn['link'], 'SELECT count(*) FROM ' . dbmig_quote('pgsql', $table));
		$row = $res ? pg_fetch_row($res) : false;
		$count = $row ? (int) $row[0] : -1;
		if ($res) {
			pg_free_result($res);
		}
		return $count;
	}
	$res = @mysqli_query($conn['link'], 'SELECT COUNT(*) FROM ' . dbmig_quote('mysql', $table));
	$row = $res ? mysqli_fetch_row($res) : false;
	$count = $row ? (int) $row[0] : -1;
	if ($res) {
		mysqli_free_result($res);
	}
	return $count;
}

// Canonical column descriptions. The engines disagree on names and on the
// storage class, so every column is reduced to a small set of kinds that both
// sides can render.
function dbmig_parse_default_pg($raw) {
	if ($raw === null) {
		return array(false, null);
	}
	$raw = trim($raw);
	if (preg_match('/^nextval\(/i', $raw)) {
		return array(false, null); // handled as auto_increment
	}
	if (preg_match('/^NULL::/i', $raw)) {
		return array(false, null);
	}
	// "'0'::character varying" / "''::text" / "123" / "now()"
	$cast = strrpos($raw, '::');
	$body = ($cast === false) ? $raw : substr($raw, 0, $cast);
	if (preg_match('/^\'(.*)\'$/s', $body, $m)) {
		return array(true, str_replace("''", "'", $m[1]));
	}
	if (preg_match('/^(-?\d+(?:\.\d+)?)$/', $body)) {
		return array(true, $body);
	}
	return array(false, null);
}

function dbmig_columns($conn, $table) {
	$cols = array();
	if ($conn['type'] == 'pgsql') {
		$sql = 'SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable, column_default ' .
		       'FROM information_schema.columns WHERE table_schema = ' . dbmig_pg_literal($conn['schema']) .
		       ' AND table_name = ' . dbmig_pg_literal($table) . ' ORDER BY ordinal_position';
		$res = @pg_query($conn['link'], $sql);
		while ($row = pg_fetch_assoc($res)) {
			$col = array(
				'name' => $row['column_name'],
				'kind' => 'text',
				'length' => (int) $row['character_maximum_length'],
				'precision' => (int) $row['numeric_precision'],
				'scale' => (int) $row['numeric_scale'],
				'nullable' => ($row['is_nullable'] == 'YES'),
				'autoincrement' => false,
				'has_default' => false,
				'default' => null,
			);
			switch ($row['data_type']) {
				case 'integer': $col['kind'] = 'int'; break;
				case 'bigint': $col['kind'] = 'bigint'; break;
				case 'smallint': $col['kind'] = 'smallint'; break;
				case 'character varying': $col['kind'] = 'varchar'; break;
				case 'character': $col['kind'] = 'varchar'; break;
				case 'text': $col['kind'] = 'text'; break;
				case 'bytea': $col['kind'] = 'blob'; break;
				case 'numeric': $col['kind'] = 'decimal'; break;
				case 'double precision': $col['kind'] = 'float'; break;
				case 'real': $col['kind'] = 'float'; break;
				case 'boolean': $col['kind'] = 'smallint'; break;
				case 'oid': $col['kind'] = 'int'; break;
				case 'date': $col['kind'] = 'temporal'; $col['length'] = 10; break;
				case 'timestamp without time zone':
				case 'timestamp with time zone': $col['kind'] = 'temporal'; $col['length'] = 19; break;
				case 'time without time zone': $col['kind'] = 'temporal'; $col['length'] = 8; break;
			}
			if (preg_match('/^nextval\(/i', (string) $row['column_default'])) {
				$col['autoincrement'] = true;
			} else {
				list($has, $value) = dbmig_parse_default_pg($row['column_default']);
				$col['has_default'] = $has;
				$col['default'] = $value;
			}
			$cols[] = $col;
		}
		pg_free_result($res);
		return $cols;
	}

	$sql = 'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA ' .
	       'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ' . dbmig_pg_literal($conn['dbname']) .
	       ' AND TABLE_NAME = ' . dbmig_pg_literal($table) . ' ORDER BY ORDINAL_POSITION';
	$res = @mysqli_query($conn['link'], $sql);
	while ($row = mysqli_fetch_assoc($res)) {
		$col = array(
			'name' => $row['COLUMN_NAME'],
			'kind' => 'text',
			'length' => (int) $row['CHARACTER_MAXIMUM_LENGTH'],
			'precision' => (int) $row['NUMERIC_PRECISION'],
			'scale' => (int) $row['NUMERIC_SCALE'],
			'nullable' => ($row['IS_NULLABLE'] == 'YES'),
			'autoincrement' => (stripos($row['EXTRA'], 'auto_increment') !== false),
			'has_default' => ($row['COLUMN_DEFAULT'] !== null),
			'default' => ($row['COLUMN_DEFAULT'] === null) ? null : (string) $row['COLUMN_DEFAULT'],
		);
		switch (strtolower($row['DATA_TYPE'])) {
			case 'tinyint':
			case 'smallint':
			case 'year': $col['kind'] = 'smallint'; break;
			case 'mediumint':
			case 'int':
			case 'integer': $col['kind'] = 'int'; break;
			case 'bigint': $col['kind'] = 'bigint'; break;
			case 'varchar':
			case 'char': $col['kind'] = 'varchar'; break;
			case 'tinytext':
			case 'text':
			case 'mediumtext':
			case 'longtext': $col['kind'] = 'text'; break;
			case 'tinyblob':
			case 'blob':
			case 'mediumblob':
			case 'longblob': $col['kind'] = 'blob'; break;
			case 'datetime':
			case 'timestamp': $col['kind'] = 'temporal'; $col['length'] = 19; break;
			case 'date': $col['kind'] = 'temporal'; $col['length'] = 10; break;
			case 'time': $col['kind'] = 'temporal'; $col['length'] = 8; break;
			case 'decimal':
			case 'numeric': $col['kind'] = 'decimal'; break;
			case 'float':
			case 'double': $col['kind'] = 'float'; break;
			case 'enum':
			case 'set':
				$col['kind'] = 'varchar';
				$col['length'] = 0;
				if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $row['COLUMN_TYPE'], $m)) {
					foreach ($m[1] as $value) {
						$col['length'] = max($col['length'], strlen(stripcslashes($value)));
					}
				}
				$col['length'] = max(1, $col['length']);
				break;
			case 'bit':
				$col['kind'] = 'smallint';
				$col['has_default'] = false;
				$col['default'] = null;
				break;
		}
		$cols[] = $col;
	}
	mysqli_free_result($res);
	return $cols;
}

function dbmig_indexes($conn, $table) {
	$indexes = array();
	if ($conn['type'] == 'pgsql') {
		$sql = 'SELECT i.relname AS name, ix.indisunique AS uniq, ix.indisprimary AS pri, pg_get_indexdef(ix.indexrelid) AS def ' .
		       'FROM pg_index ix JOIN pg_class i ON i.oid = ix.indexrelid ' .
		       'JOIN pg_class t ON t.oid = ix.indrelid JOIN pg_namespace n ON n.oid = t.relnamespace ' .
		       'WHERE n.nspname = ' . dbmig_pg_literal($conn['schema']) . ' AND t.relname = ' . dbmig_pg_literal($table);
		$res = @pg_query($conn['link'], $sql);
		while ($row = pg_fetch_assoc($res)) {
			$def = $row['def'];
			$cols = '';
			if (preg_match('/USING\s+\w+\s*\((.*)\)/is', $def, $m)) {
				$cols = $m[1];
			}
			$indexes[] = array(
				'name' => $row['name'],
				'columns' => $cols,
				'unique' => ($row['uniq'] == 't'),
				'primary' => ($row['pri'] == 't'),
			);
		}
		pg_free_result($res);
		return $indexes;
	}

	$res = @mysqli_query($conn['link'], 'SHOW INDEX FROM ' . dbmig_quote('mysql', $table));
	$by_name = array();
	while ($row = mysqli_fetch_assoc($res)) {
		$name = $row['Key_name'];
		$seq = (int) $row['Seq_in_index'];
		if (!isset($by_name[$name])) {
			$by_name[$name] = array(
				'name' => $name,
				'columns' => array(),
				'unique' => ((int) $row['Non_unique'] == 0),
				'primary' => ($name == 'PRIMARY'),
			);
		}
		$by_name[$name]['columns'][$seq] = $row['Column_name'];
	}
	foreach ($by_name as $name => $entry) {
		ksort($entry['columns']);
		$entry['columns'] = implode(', ', $entry['columns']);
		$indexes[] = $entry;
	}
	mysqli_free_result($res);
	return $indexes;
}

// ------------------------------------------------------------------- DDL out

function dbmig_column_type($target, $col) {
	if ($target == 'pgsql') {
		switch ($col['kind']) {
			case 'bigint':
				return $col['autoincrement'] ? 'bigserial' : 'bigint';
			case 'smallint':
				return $col['autoincrement'] ? 'smallserial' : 'smallint';
			case 'int':
				return $col['autoincrement'] ? 'serial' : 'integer';
			case 'decimal':
				return 'numeric(' . max(1, $col['precision']) . ',' . max(0, $col['scale']) . ')';
			case 'varchar':
				return 'character varying(' . max(1, $col['length']) . ')';
			case 'text':
				return 'text';
			case 'blob':
				return 'bytea';
			case 'temporal':
				return 'character varying(' . max(1, $col['length']) . ')';
			case 'float':
				return 'double precision';
		}
		return 'text';
	}

	switch ($col['kind']) {
		case 'bigint': return 'bigint(20)';
		case 'smallint': return 'smallint(6)';
		case 'int': return 'int(11)';
		case 'varchar': return 'varchar(' . max(1, $col['length']) . ')';
		case 'text': return 'mediumtext';
		case 'blob': return 'longblob';
		case 'temporal':
			if ($col['length'] == 10) {
				return 'date';
			}
			if ($col['length'] == 8) {
				return 'time';
			}
			return 'datetime';
		case 'float': return 'double';
		case 'decimal': return 'decimal(' . max(1, $col['precision']) . ',' . max(0, $col['scale']) . ')';
	}
	return 'mediumtext';
}

function dbmig_default_sql($target, $col) {
	$type = dbmig_column_type($target, $col);
	$parts = array();
	$parts[] = $type;

	if ($col['autoincrement']) {
		if ($target == 'mysql') {
			$parts[] = 'NOT NULL';
			$parts[] = 'AUTO_INCREMENT';
		}
		// serial types already imply NOT NULL + nextval
		return implode(' ', $parts);
	}

	$parts[] = $col['nullable'] ? 'NULL' : 'NOT NULL';

	$default = null;
	if ($col['has_default']) {
		$default = $col['default'];
	} elseif (!$col['nullable']) {
		// Same fallback the installer uses: vBulletin inserts rows without
		// every column, NOT NULL without a default would reject them.
		if (in_array($col['kind'], array('int', 'bigint', 'smallint', 'float', 'decimal'))) {
			$default = '0';
		} elseif (in_array($col['kind'], array('varchar', 'text', 'temporal'))) {
			$default = '';
		}
	}

	if ($default === null) {
		return implode(' ', $parts);
	}
	if ($target == 'pgsql') {
		$parts[] = 'DEFAULT ' . dbmig_pg_literal($default);
	} else {
		if (in_array($col['kind'], array('text', 'blob'))) {
			return implode(' ', $parts); // TEXT/BLOB cannot carry a default here
		}
		$parts[] = "DEFAULT '" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $default) . "'";
	}
	return implode(' ', $parts);
}

function dbmig_create_table_sql($target, $table, $cols, $indexes) {
	$defs = array();
	$columns = array();
	foreach ($cols as $col) {
		$defs[] = "\t" . dbmig_quote($target, $col['name']) . ' ' . dbmig_default_sql($target, $col);
		$columns[] = dbmig_quote($target, $col['name']);
	}
	foreach ($indexes as $index) {
		if (!$index['primary'] || trim($index['columns']) === '') {
			continue;
		}
		$defs[] = "\tPRIMARY KEY (" . $index['columns'] . ')';
	}
	$sql = 'CREATE TABLE ' . dbmig_quote($target, $table) . " (\n" . implode(",\n", $defs) . "\n)";
	if ($target == 'mysql') {
		$sql .= ' ENGINE=MyISAM DEFAULT CHARSET=utf8mb4';
	}
	return $sql;
}

function dbmig_index_sql($target, $table, $indexes) {
	$sql = array();
	foreach ($indexes as $index) {
		if ($index['primary'] || trim($index['columns']) === '') {
			continue;
		}
		$name = $index['name'];
		if ($target == 'pgsql') {
			// PostgreSQL index names are unique per schema, not per table.
			if (stripos($name, $table . '_') !== 0 && stripos($name, $table . '__') !== 0) {
				$name = $table . '_' . $name;
			}
			$sql[] = 'CREATE ' . ($index['unique'] ? 'UNIQUE ' : '') . 'INDEX ' . dbmig_quote('pgsql', $name) .
			         ' ON ' . dbmig_quote('pgsql', $table) . ' (' . $index['columns'] . ')';
		} else {
			$sql[] = 'ALTER TABLE ' . dbmig_quote('mysql', $table) . ' ADD ' . ($index['unique'] ? 'UNIQUE ' : '') .
			         'INDEX ' . dbmig_quote('mysql', $name) . ' (' . $index['columns'] . ')';
		}
	}
	return $sql;
}

// ------------------------------------------------------------------ row copy

function dbmig_fetch($conn, $table, $offset, $limit) {
	$sql = 'SELECT * FROM ' . dbmig_quote($conn['type'], $table) . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
	$rows = array();
	if ($conn['type'] == 'pgsql') {
		$res = @pg_query($conn['link'], $sql);
		if ($res === false) {
			return false;
		}
		while ($row = pg_fetch_assoc($res)) {
			$rows[] = $row;
		}
		pg_free_result($res);
		return $rows;
	}
	$res = @mysqli_query($conn['link'], $sql);
	if ($res === false) {
		return false;
	}
	while ($row = mysqli_fetch_assoc($res)) {
		$rows[] = $row;
	}
	mysqli_free_result($res);
	return $rows;
}

function dbmig_is_binary($value) {
	return (strpos($value, "\0") !== false) || (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8'));
}

function dbmig_literal($conn, $col, $value) {
	if ($value === null) {
		return 'NULL';
	}
	$value = (string) $value;
	if ($conn['type'] == 'pgsql') {
		if ($col['kind'] == 'blob' || dbmig_is_binary($value)) {
			return "'\\x" . bin2hex($value) . "'";
		}
		return "'" . str_replace("'", "''", $value) . "'";
	}
	// mysqli_real_escape_string handles quotes, NUL bytes and backslashes and
	// is byte safe, which matters for attachments.
	return "'" . mysqli_real_escape_string($conn['link'], $value) . "'";
}

function dbmig_normalize_row($conn, $cols, $row) {
	foreach ($cols as $i => $col) {
		$name = $col['name'];
		if (!isset($row[$name])) {
			$row[$name] = null;
			continue;
		}
		$value = $row[$name];
		if ($value === null) {
			continue;
		}
		// PostgreSQL hands bytea back as the hex text form "\x8950...".
		if ($conn['type'] == 'pgsql' && $col['kind'] == 'blob' && preg_match('/^\\\\x[0-9a-f]*$/i', $value)) {
			$row[$name] = $value === '\\x' ? '' : pack('H*', substr($value, 2));
		} elseif ($conn['type'] == 'pgsql' && $col['kind'] == 'blob' && function_exists('pg_unescape_bytea')) {
			$row[$name] = pg_unescape_bytea($value);
		}
	}
	return $row;
}

function dbmig_copy_table($source, $target, $table, $cols, &$copied) {
	$copied = 0;
	$offset = 0;
	$names = array();
	foreach ($cols as $col) {
		$names[] = dbmig_quote($target['type'], $col['name']);
	}
	$name_list = implode(', ', $names);

	while (true) {
		$rows = dbmig_fetch($source, $table, $offset, DBMIG_BATCH_ROWS);
		if ($rows === false) {
			return '读取源表 ' . $table . ' 失败。';
		}
		if (!$rows) {
			break;
		}
		$values = array();
		foreach ($rows as $row) {
			$row = dbmig_normalize_row($source, $cols, $row);
			$lits = array();
			foreach ($cols as $col) {
				$lits[] = dbmig_literal($target, $col, isset($row[$col['name']]) ? $row[$col['name']] : null);
			}
			$values[] = '(' . implode(', ', $lits) . ')';
		}
		$sql = 'INSERT INTO ' . dbmig_quote($target['type'], $table) . ' (' . $name_list . ') VALUES ' . implode(', ', $values);
		$error = dbmig_exec($target, $sql);
		if ($error != '') {
			return '写入表 ' . $table . ' 失败：' . $error;
		}
		$copied += count($rows);
		$offset += count($rows);
		if (count($rows) < DBMIG_BATCH_ROWS) {
			break;
		}
	}
	return '';
}

// After a copy the sequences still sit at their initial value, so the next
// insert would collide with an existing id.
function dbmig_reset_sequences($conn, $table, $cols) {
	foreach ($cols as $col) {
		if (!$col['autoincrement']) {
			continue;
		}
		if ($conn['type'] == 'pgsql') {
			$sql = 'SELECT setval(pg_get_serial_sequence(' . dbmig_pg_literal(dbmig_quote('pgsql', $table)) . ', ' .
			       dbmig_pg_literal($col['name']) . '), GREATEST((SELECT COALESCE(MAX(' . dbmig_quote('pgsql', $col['name']) .
			       '), 0) FROM ' . dbmig_quote('pgsql', $table) . '), 1), true)';
			$res = @pg_query($conn['link'], $sql);
			if ($res === false) {
				return '重置自增序列失败（' . $table . '.' . $col['name'] . '）：' . pg_last_error($conn['link']);
			}
			pg_free_result($res);
			continue;
		}
		$max = 0;
		$res = @mysqli_query($conn['link'], 'SELECT MAX(' . dbmig_quote('mysql', $col['name']) . ') FROM ' . dbmig_quote('mysql', $table));
		if ($res) {
			$row = mysqli_fetch_row($res);
			$max = (int) $row[0];
			mysqli_free_result($res);
		}
		$error = dbmig_exec($conn, 'ALTER TABLE ' . dbmig_quote('mysql', $table) . ' AUTO_INCREMENT = ' . ($max + 1));
		if ($error != '') {
			return '重置自增计数失败（' . $table . '）：' . $error;
		}
	}
	return '';
}

// ------------------------------------------------------------------ driver

// Copies every table from $source_values into $target_values.
// $recreate drops vBulletin tables that already exist in the target.
function dbmig_run($source_values, $target_values, $recreate, &$log, &$errors) {
	$log = array();
	$errors = array();

	$source = dbmig_open($source_values, $error);
	if (!$source) {
		$errors[] = '源库（当前生效的配置）连接失败：' . $error;
		return false;
	}
	$target = dbmig_open($target_values, $error);
	if (!$target) {
		dbmig_close($source);
		$errors[] = '目标库连接失败：' . $error;
		return false;
	}

	$source_tables = dbmig_tables($source);
	if (!$source_tables) {
		dbmig_close($source);
		dbmig_close($target);
		$errors[] = '源库里没有任何表，无法迁移。';
		return false;
	}

	// Refuse to write into a database that already holds vBulletin tables,
	// unless the administrator explicitly asked for them to be replaced.
	$existing = dbmig_tables($target);
	$overlap = array_intersect($source_tables, $existing);
	if ($overlap && !$recreate) {
		dbmig_close($source);
		dbmig_close($target);
		$errors[] = '目标库里已经有 ' . count($overlap) . ' 张同名表（' . implode(', ', array_slice($overlap, 0, 5)) .
		            ' …）。请换一个空库，或勾选 "Recreate tables in the target database" 让本页先删掉它们。';
		return false;
	}
	if ($overlap) {
		foreach ($overlap as $table) {
			$error = dbmig_exec($target, 'DROP TABLE ' . dbmig_quote($target['type'], $table) . ' CASCADE');
			if ($error != '') {
				$errors[] = '删除目标库旧表 ' . $table . ' 失败：' . $error;
				dbmig_close($source);
				dbmig_close($target);
				return false;
			}
		}
		$log[] = '已删除目标库里 ' . count($overlap) . ' 张旧的 vBulletin 表。';
	}

	$schema = array();
	foreach ($source_tables as $table) {
		$cols = dbmig_columns($source, $table);
		if (!$cols) {
			$errors[] = '读取表结构失败：' . $table;
			dbmig_close($source);
			dbmig_close($target);
			return false;
		}
		$indexes = dbmig_indexes($source, $table);
		$sql = dbmig_create_table_sql($target['type'], $table, $cols, $indexes);
		$error = dbmig_exec($target, $sql);
		if ($error != '') {
			$errors[] = '建表 ' . $table . ' 失败：' . $error;
			dbmig_close($source);
			dbmig_close($target);
			return false;
		}
		$schema[$table] = array('columns' => $cols, 'indexes' => $indexes);
	}
	$log[] = '已在目标库建好 ' . count($schema) . ' 张表。';

	foreach ($schema as $table => $info) {
		$error = dbmig_copy_table($source, $target, $table, $info['columns'], $copied);
		if ($error != '') {
			$errors[] = $error;
			dbmig_close($source);
			dbmig_close($target);
			return false;
		}
		$error = dbmig_reset_sequences($target, $table, $info['columns']);
		if ($error != '') {
			$errors[] = $error;
			dbmig_close($source);
			dbmig_close($target);
			return false;
		}
		foreach (dbmig_index_sql($target['type'], $table, $info['indexes']) as $index_sql) {
			$error = dbmig_exec($target, $index_sql);
			if ($error != '') {
				$errors[] = '建索引失败（' . $table . '）：' . $error;
				dbmig_close($source);
				dbmig_close($target);
				return false;
			}
		}
		if ($copied > 0) {
			$log[] = $table . ': ' . $copied . ' 行';
		}
	}

	// Row counts are the cheapest useful proof that nothing was truncated.
	$mismatch = array();
	foreach ($schema as $table => $info) {
		$from = dbmig_count($source, $table);
		$to = dbmig_count($target, $table);
		if ($from != $to) {
			$mismatch[] = $table . '（源 ' . $from . ' 行 / 目标 ' . $to . ' 行）';
		}
	}
	dbmig_close($source);
	dbmig_close($target);

	if ($mismatch) {
		$errors[] = '这些表的行数对不上：' . implode('，', $mismatch);
		return false;
	}

	$total = 0;
	foreach ($schema as $table => $info) {
		$total++;
	}
	$log[] = '校验通过：' . $total . ' 张表的行数与源库完全一致。';
	return true;
}
