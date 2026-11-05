<?php
require_once __DIR__ . '/php8compat.php';

// db class for PostgreSQL
// this class is used in all scripts
// do NOT fiddle unless you know what you are doing
//
// vBulletin 2.x was written against MySQL 3.x/4.x: the whole board talks SQL
// through this one class, so supporting PostgreSQL means implementing the same
// fifteen methods on top of the pgsql extension and translating the handful of
// MySQL-only constructs the queries rely on (IF(), UNIX_TIMESTAMP(),
// FROM_UNIXTIME(), "LIMIT n,m", REPLACE INTO, "INSERT ... VALUES (NULL, ...)").
// Everything else -- the board code, templates, language layer -- is untouched.

class DB_Sql_vb {
  public $database = "";

  public $link_id  = 0;
  public $query_id = 0;
  public $record   = array();

  public $errdesc    = "";
  public $errno   = 0;
  public $reporterror = 1;

  public $server   = "localhost";
  public $port     = 5432;
  public $user     = "postgres";
  public $password = "";

  // Search path of the vBulletin schema. Set from $dbschema in config.php.
  public $schema   = "public";

  public $appname  = "vBulletin";
  public $appshortname = "vBulletin (cp)";

  // Primary keys of the tables the board writes with REPLACE INTO. MySQL's
  // REPLACE is delete-then-insert; PostgreSQL spells the same thing
  // INSERT ... ON CONFLICT (pk) DO UPDATE. Keep this list in step with the
  // schema if more REPLACE INTO statements are ever added.
  public $primary_keys = array(
    'searchindex' => array('wordid', 'postid'),
    'adminutil'   => array('title'),
  );

  // Cache of information_schema lookups: table => array(position => array(name, serial))
  public $column_cache = array();

  // Words that PostgreSQL reserves and that the board uses as identifiers.
  // In this schema only the "user" table collides: PostgreSQL rejects
  // "CREATE TABLE user" and reads a bare USER as the current_user function,
  // so every identifier occurrence has to be quoted.
  public $reserved_idents = array('user');

  // ---------------------------------------------------------------- connection

  private function is_linked()
  {
    return is_resource($this->link_id) || (class_exists('Pgsql\Connection') && $this->link_id instanceof \Pgsql\Connection);
  }

  private function is_result($res)
  {
    return is_resource($res) || (class_exists('Pgsql\Result') && $res instanceof \Pgsql\Result);
  }

  function connect() {
    global $usepconnect;

    if ( $this->is_linked() ) {
      return;
    }

    $conninfo = "host=" . $this->server . " port=" . $this->port .
                " dbname=" . $this->database .
                " user=" . $this->user .
                " password=" . $this->password;

    if ($usepconnect == 1) {
      $this->link_id = @pg_pconnect($conninfo, PGSQL_CONNECT_FORCE_NEW);
    } else {
      $this->link_id = @pg_connect($conninfo, PGSQL_CONNECT_FORCE_NEW);
    }
    if (!$this->is_linked()) {
      $this->halt("Link-ID == false, connect failed");
      return false;
    }

    // vBulletin 2 is a single-byte application but the port ships UTF-8
    // language files; make the server agree instead of letting it pick.
    @pg_query($this->link_id, "SET client_encoding = 'UTF8'");
    // Anything the board escapes comes from pg_escape_string(), which doubles
    // quotes rather than adding backslashes -- standard_conforming_strings must
    // stay on, otherwise '\\'' sequences would be re-interpreted.
    @pg_query($this->link_id, "SET standard_conforming_strings = on");
    // Unquoted identifiers fold to lower case on PostgreSQL, which is exactly
    // what the MySQL schema (all lower case table/column names) expects.
    if ($this->schema != "") {
      @pg_query($this->link_id, "SET search_path TO " . $this->quote_ident($this->schema) . ",pg_catalog");
    }
  }

  function geterrdesc() {
    $this->error = $this->is_linked() ? pg_last_error($this->link_id) : '';
    return $this->error;
  }

  function geterrno() {
    // PostgreSQL has SQLSTATE codes, not numeric errnos. Map the two the board
    // cares about, keep 0 for "no error".
    $this->errno = 0;
    if ($this->is_linked()) {
      $msg = pg_last_error($this->link_id);
      if ($msg != '') {
        $this->errno = 1;
      }
    }
    return $this->errno;
  }

  // Charset-aware quoting of a value for inclusion in a SQL string literal.
  function escape($value) {
    if ($this->is_linked()) {
      return pg_escape_string($this->link_id, (string) $value);
    }
    return addslashes((string) $value);
  }

  function select_db($database="") {
    if ($database != "") {
      $this->database = $database;
    }
    // PostgreSQL selects a database at connection time; switching means
    // reconnecting. The board only calls this right after connect().
    if ($this->is_linked()) {
      $this->link_id = 0;
    }
  }

  // ---------------------------------------------------------------- public API

  function query($query_string) {
    global $query_count, $showqueries;

    if ($showqueries) {
      echo "Query: $query_string\n";
    }

    if (!($this->is_linked())) {
      $this->halt("Invalid SQL: " . $query_string);
      return false;
    }

    // Keep the un-translated text for the error page: the board's messages are
    // read by admins who know the original query.
    $original = $query_string;
    $query_string = $this->translate($query_string);

    $this->query_id = @pg_query($this->link_id, $query_string);
    if (!($this->is_result($this->query_id))) {
      $this->halt("Invalid SQL: " . $original . "\n\n(translated to PostgreSQL: " . $query_string . ")");
      return false;
    }

    $query_count++;
    return $this->query_id;
  }

  function fetch_array($query_id=-1,$query_string="") {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    if ( $this->is_result($this->query_id) ) {
      // PGSQL_BOTH is the default: the board uses both $row[0] and
      // $row['column'] forms.
      $this->record = pg_fetch_array($this->query_id, NULL, PGSQL_BOTH);
    } else {
      if ( !empty($query_string) ) {
        $this->halt("Invalid query id (".$this->query_id.") on this query: $query_string");
      } else {
        $this->halt("Invalid query id ".$this->query_id." specified");
      }
      $this->record = false;
    }
    return $this->record;
  }

  function free_result($query_id=-1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    if ($this->is_result($this->query_id)) {
      pg_free_result($this->query_id);
    }
    return true;
  }

  function query_first($query_string) {
    $query_id = $this->query($query_string);
    $returnarray = $this->fetch_array($query_id, $query_string);
    $this->free_result($query_id);
    return $returnarray;
  }

  function data_seek($pos,$query_id=-1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    if ($this->is_result($this->query_id)) {
      return pg_result_seek($this->query_id, $pos);
    }
    return false;
  }

  function num_rows($query_id=-1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    if ($this->is_result($this->query_id)) {
      return pg_num_rows($this->query_id);
    }
    return 0;
  }

  function num_fields($query_id=-1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    if ($this->is_result($this->query_id)) {
      return pg_num_fields($this->query_id);
    }
    return 0;
  }

  function insert_id() {
    // PostgreSQL has no connection-level "last insert id". Every table that
    // needs one has a serial/identity column, and lastval() returns the value
    // most recently drawn from a sequence in this session.
    if (!($this->is_linked())) {
      return 0;
    }
    $res = @pg_query($this->link_id, "SELECT lastval()");
    if (!($this->is_result($res))) {
      return 0;
    }
    $row = pg_fetch_row($res);
    pg_free_result($res);
    return isset($row[0]) ? (int) $row[0] : 0;
  }

  function close() {
    if ($this->is_linked()) {
      pg_close($this->link_id);
      $this->link_id = 0;
    }
    return true;
  }

  function halt($msg) {
    $this->errdesc = $this->geterrdesc();
    $this->errno   = $this->geterrno();
    global $technicalemail, $bbuserinfo, $scriptpath, $_SERVER;

    if ($this->reporterror == 1) {
      $message = "Database error in " . $this->appname . " $GLOBALS[templateversion]:\n\n$msg\n";
      $message .= "pgsql error: " . $this->errdesc . "\n\n";
      $message .= "pgsql error number: " . $this->errno . "\n\n";
      $message .= "Date: " . date("l dS of F Y h:i:s A") . "\n";
      $message .= "Script: $GLOBALS[bburl]" . (($scriptpath) ? $scriptpath : $_SERVER['REQUEST_URI']) . "\n";
      $message .= "Referer: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') . "\n";

      if ($technicalemail) {
        @mail($technicalemail, $this->appshortname . " Database error!", $message, "From: $technicalemail");
      }

      echo "<html><head><title>$GLOBALS[bbtitle] Database Error</title><style>P,BODY{FONT-FAMILY:tahoma,arial,sans-serif;FONT-SIZE:11px;}</style><body>\n\n<!-- $message -->\n\n";
      echo "</table></td></tr></table></form>\n<blockquote><p>&nbsp;</p><p><b>There seems to have been a slight problem with the $GLOBALS[bbtitle] database.</b><br>\n";
      echo "Please try again by pressing the <a href=\"javascript:window.location=window.location;\">refresh</a> button in your browser.</p>";
      echo "An E-Mail has been dispatched to our <a href=\"mailto:$technicalemail\">Technical Staff</a>, who you can also contact if the problem persists.</p>";
      echo "<p>We apologise for any inconvenience.</p>";

      if (isset($bbuserinfo['usergroupid']) && $bbuserinfo['usergroupid'] == 6) {
        echo "<form><textarea rows=\"12\" cols=\"60\">" . htmlspecialchars($message) . "</textarea></form>";
      }
      echo "</blockquote></body></head></html>";
      exit;
    }
  }

  // ----------------------------------------------------------- SQL translation

  // Entry point: turn a MySQL statement into one PostgreSQL accepts.
  function translate($sql) {
    $sql = trim($sql);
    if ($sql == "") {
      return $sql;
    }

    // SHOW TABLE / SHOW FIELDS / SHOW KEYS are used by the installer and by
    // admin/backup.php; answer them from the catalog with MySQL's column names.
    if (preg_match('/^SHOW\s+TABLES\s*$/i', $sql)) {
      return $this->show_tables_sql();
    }
    if (preg_match('/^SHOW\s+FIELDS\s+FROM\s+([A-Za-z_][\w$]*)\s*$/i', $sql, $m)) {
      return $this->show_fields_sql($m[1]);
    }
    if (preg_match('/^SHOW\s+KEYS\s+FROM\s+([A-Za-z_][\w$]*)\s*$/i', $sql, $m)) {
      return $this->show_keys_sql($m[1]);
    }

    $sql = $this->rewrite_limit($sql);
    $sql = $this->rewrite_if($sql);
    $sql = $this->rewrite_isnull($sql);
    $sql = $this->rewrite_unix_timestamp($sql);
    $sql = $this->rewrite_from_unixtime($sql);
    $sql = $this->rewrite_replace_into($sql);
    $sql = $this->rewrite_insert_nulls($sql);
    $sql = $this->rewrite_like($sql);
    // Quoting goes last: the rewrites above parse table names with plain \w+
    // patterns, which double quotes would break.
    $sql = $this->quote_reserved($sql);

    return $sql;
  }

  // Bare occurrences of the reserved identifiers used by this schema get
  // double quotes: "FROM user" -> "FROM ""user""", "user.username" ->
  // ""user"".username". Literals and already-quoted identifiers are left alone.
  private function quote_reserved($sql) {
    if (!$this->reserved_idents) {
      return $sql;
    }
    $pattern = '/\b(' . implode('|', array_map('preg_quote', $this->reserved_idents)) . ')\b/i';
    return $this->replace_outside_quotes($sql, $pattern, array($this, 'quote_ident_match'));
  }

  private function quote_ident_match($m) {
    return '"' . strtolower($m[1]) . '"';
  }

  // "LIMIT offset,count" -> "LIMIT count OFFSET offset"
  private function rewrite_limit($sql) {
    return preg_replace('/\bLIMIT\s+(\d+)\s*,\s*(\d+)/i', 'LIMIT $2 OFFSET $1', $sql);
  }

  // MySQL LIKE is case insensitive for the collations vB2 installations used;
  // PostgreSQL LIKE is case sensitive. ILIKE restores the old behaviour.
  private function rewrite_like($sql) {
    return $this->replace_outside_quotes($sql, '/\b(NOT\s+)?LIKE\b/i', array($this, 'like_to_ilike'));
  }

  private function like_to_ilike($m) {
    return (isset($m[1]) && $m[1] != "") ? "NOT ILIKE" : "ILIKE";
  }

  // IF(cond, then, else) -> (CASE WHEN cond THEN then ELSE else END)
  private function rewrite_if($sql) {
    return $this->replace_function($sql, 'IF', array($this, 'if_to_case'));
  }

  private function if_to_case($args) {
    if (count($args) != 3) {
      return null; // not the MySQL IF(); leave it alone
    }
    $cond = $this->rewrite_if($args[0]);
    $then = $this->rewrite_if($args[1]);
    $else = $this->rewrite_if($args[2]);
    return "(CASE WHEN " . $cond . " THEN " . $then . " ELSE " . $else . " END)";
  }

  // ISNULL(x) -> (x IS NULL)
  private function rewrite_isnull($sql) {
    return $this->replace_function($sql, 'ISNULL', array($this, 'isnull_to_predicate'));
  }

  private function isnull_to_predicate($args) {
    if (count($args) != 1) {
      return null;
    }
    return "(" . $args[0] . " IS NULL)";
  }

  // UNIX_TIMESTAMP() / UNIX_TIMESTAMP('2003-01-01') -> epoch seconds
  private function rewrite_unix_timestamp($sql) {
    return $this->replace_function($sql, 'UNIX_TIMESTAMP', array($this, 'unix_timestamp_to_epoch'));
  }

  private function unix_timestamp_to_epoch($args) {
    $arg = trim(implode(',', $args));
    if ($arg == "") {
      return "(EXTRACT(EPOCH FROM now())::bigint)";
    }
    return "(EXTRACT(EPOCH FROM (" . $arg . ")::timestamp)::bigint)";
  }

  // FROM_UNIXTIME(x) -> to_timestamp(x)
  private function rewrite_from_unixtime($sql) {
    return $this->replace_function($sql, 'FROM_UNIXTIME', array($this, 'from_unixtime'));
  }

  private function from_unixtime($args) {
    if (count($args) < 1 || trim($args[0]) == "") {
      return null;
    }
    $ts = trim($args[0]);
    if (count($args) > 1) {
      // FROM_UNIXTIME(ts, fmt): MySQL format codes differ from to_char's.
      return "to_char(to_timestamp(" . $ts . "), " . $this->mysql_date_format($args[1]) . ")";
    }
    return "to_timestamp(" . $ts . ")";
  }

  // Minimal %Y-%m-%d %H:%i:%s -> YYYY-MM-DD HH24:MI:SS mapping.
  private function mysql_date_format($fmt) {
    $map = array('%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%c' => 'FMMM', '%d' => 'DD', '%e' => 'FMDD',
                 '%H' => 'HH24', '%h' => 'HH12', '%i' => 'MI', '%s' => 'SS', '%p' => 'AM', '%W' => 'FMDay',
                 '%a' => 'Dy', '%M' => 'FMMonth', '%b' => 'Mon', '%j' => 'DDD', '%T' => 'HH24:MI:SS');
    $body = trim($fmt, " \t\n\r\0\x0B'\"");
    return "'" . strtr($body, $map) . "'";
  }

  // REPLACE INTO t (a,b,c) VALUES ... -> INSERT INTO t (a,b,c) VALUES ...
  //                                       ON CONFLICT (pk) DO UPDATE SET ...
  private function rewrite_replace_into($sql) {
    if (!preg_match('/^REPLACE\s+INTO\s+([A-Za-z_][\w$]*)\s*(\(([^)]*)\))?\s+(.*)$/is', $sql, $m)) {
      return $sql;
    }
    $table = $m[1];
    $rest  = $m[4];
    $cols  = array();
    if ($m[3] != "") {
      foreach ($this->split_top_level($m[3]) as $c) {
        $cols[] = trim($c);
      }
    }

    $pk = isset($this->primary_keys[$table]) ? $this->primary_keys[$table] : array();
    if ($pk) {
      $conflict = " ON CONFLICT (" . implode(',', $pk) . ")";
      $sets = array();
      foreach ($cols as $c) {
        if (in_array($c, $pk)) {
          continue;
        }
        $sets[] = $c . " = EXCLUDED." . $c;
      }
      if ($sets) {
        $conflict .= " DO UPDATE SET " . implode(', ', $sets);
      } else {
        $conflict .= " DO NOTHING";
      }
    } else {
      // No key known: at least keep the statement valid and idempotent.
      $conflict = " ON CONFLICT DO NOTHING";
    }

    $head = "INSERT INTO " . $table . ($m[3] != "" ? " (" . implode(',', $cols) . ")" : "") . " " . $rest;
    return $head . $conflict;
  }

  // "INSERT INTO t VALUES (NULL, ...)" -- MySQL reads a NULL in an
  // auto_increment column as "next value", PostgreSQL rejects it. Swap those
  // NULLs for DEFAULT, which is what MySQL actually did.
  private function rewrite_insert_nulls($sql) {
    if (!preg_match('/^\s*INSERT\s+INTO\s+([A-Za-z_][\w$]*)\s*(\(([^)]*)\))?\s+VALUES\s+(.*)$/is', $sql, $m)) {
      return $sql;
    }
    $table = $m[1];
    $cols = array();
    if ($m[3] != "") {
      foreach ($this->split_top_level($m[3]) as $c) {
        $cols[] = trim($c);
      }
    } else {
      $cols = $this->column_names($table);
    }
    if (!$cols) {
      return $sql;
    }

    $values = $m[4];
    // Everything after VALUES is a list of (...) tuples for this statement
    // shape; rewrite each tuple in place.
    $out = "";
    $pos = 0;
    $len = strlen($values);
    while ($pos < $len) {
      $open = strpos($values, '(', $pos);
      if ($open === false) {
        $out .= substr($values, $pos);
        break;
      }
      $close = $this->matching_paren($values, $open);
      if ($close === false) {
        $out .= substr($values, $pos);
        break;
      }
      $tuple = substr($values, $open + 1, $close - $open - 1);
      $parts = $this->split_top_level($tuple);
      if (count($parts) == count($cols)) {
        foreach ($parts as $i => $v) {
          if (trim($v) === 'NULL' && $this->is_serial_column($table, $cols[$i])) {
            $parts[$i] = 'DEFAULT';
          }
        }
      }
      $out .= substr($values, $pos, $open - $pos) . "(" . implode(',', $parts) . ")";
      $pos = $close + 1;
    }
    return "INSERT INTO " . $table . ($m[3] != "" ? " (" . implode(',', $cols) . ")" : "") . " VALUES " . $out;
  }

  // --------------------------------------------------- catalog-backed SHOW shims

  private function show_tables_sql() {
    $db = $this->database;
    return "SELECT tablename AS \"Tables_in_" . $db . "\" FROM pg_tables WHERE schemaname = " .
           $this->quote_literal($this->schema) . " ORDER BY tablename";
  }

  private function show_fields_sql($table) {
    return "SELECT c.column_name AS \"Field\", " .
           "CASE c.data_type WHEN 'character varying' THEN 'varchar' WHEN 'double precision' THEN 'double' " .
           "ELSE c.data_type END AS \"Type\", " .
           "CASE c.is_nullable WHEN 'YES' THEN 'YES' ELSE 'NO' END AS \"Null\", " .
           "CASE WHEN EXISTS (SELECT 1 FROM information_schema.table_constraints tc " .
           "JOIN information_schema.key_column_usage k ON tc.constraint_name = k.constraint_name " .
           "WHERE tc.constraint_type = 'PRIMARY KEY' AND tc.table_schema = c.table_schema " .
           "AND tc.table_name = c.table_name AND k.column_name = c.column_name) THEN 'PRI' ELSE '' END AS \"Key\", " .
           "COALESCE(c.column_default, '') AS \"Default\", " .
           "'' AS \"Extra\" " .
           "FROM information_schema.columns c WHERE c.table_schema = " . $this->quote_literal($this->schema) .
           " AND c.table_name = " . $this->quote_literal($table) . " ORDER BY c.ordinal_position";
  }

  private function show_keys_sql($table) {
    return "SELECT k.constraint_name AS \"Key_name\", k.column_name AS \"Column_name\", " .
           "CASE tc.constraint_type WHEN 'PRIMARY KEY' THEN 'PRIMARY' WHEN 'UNIQUE' THEN 'UNIQUE' ELSE '' END AS \"Non_unique\", " .
           "k.ordinal_position AS \"Seq_in_index\" " .
           "FROM information_schema.table_constraints tc " .
           "JOIN information_schema.key_column_usage k ON tc.constraint_name = k.constraint_name " .
           "AND tc.table_schema = k.table_schema AND tc.table_name = k.table_name " .
           "WHERE tc.table_schema = " . $this->quote_literal($this->schema) .
           " AND tc.table_name = " . $this->quote_literal($table) . " ORDER BY k.constraint_name, k.ordinal_position";
  }

  // ------------------------------------------------------------- DDL conversion

  // The installer builds its schema as MySQL CREATE TABLE strings. PostgreSQL
  // cannot run those, and one MySQL table may need several PostgreSQL
  // statements (table + indexes), so this returns a list.
  function convert_ddl($sql) {
    if (!preg_match('/^\s*CREATE\s+TABLE\s+([A-Za-z_][\w$]*)\s*\((.+)\)\s*(TYPE\s*=\s*\w+|ENGINE\s*=\s*\w+)?\s*$/is', trim($sql), $m)) {
      return array($this->translate($sql));
    }
    $table = $m[1];
    $table_sql = $this->quote_reserved($table);
    $body  = $m[2];
    $defs  = $this->split_top_level($body);

    $columns = array();
    $indexes = array();
    foreach ($defs as $def) {
      $def = trim($def);
      if ($def == "") {
        continue;
      }
      if (preg_match('/^PRIMARY\s+KEY\s*\((.+)\)$/is', $def, $pk)) {
        $columns[] = "PRIMARY KEY (" . $this->ident_list($pk[1]) . ")";
        continue;
      }
      if (preg_match('/^(UNIQUE\s+)?(KEY|INDEX)\s+([A-Za-z_][\w$]*)?\s*\((.+)\)$/is', $def, $idx)) {
        $unique = ($idx[1] != "") ? "UNIQUE " : "";
        $name = ($idx[3] != "") ? $idx[3] : $table . "_idx" . (count($indexes) + 1);
        $indexes[] = "CREATE " . $unique . "INDEX " . $name . " ON " . $table_sql . " (" . $this->ident_list($idx[4]) . ")";
        continue;
      }
      $columns[] = $this->convert_column($def);
    }

    if (!$columns) {
      return array($this->translate($sql));
    }
    $stmt = "CREATE TABLE " . $table_sql . " (\n  " . implode(",\n  ", $columns) . "\n)";
    $out = array($stmt);
    foreach ($indexes as $idx) {
      $out[] = $idx;
    }
    return $out;
  }

  // One column definition line: "userid int(10) unsigned DEFAULT '0' NOT NULL auto_increment"
  private function convert_column($def) {
    if (!preg_match('/^([A-Za-z_][\w$]*)\s+(.+)$/is', $def, $m)) {
      return $def;
    }
    $name = $m[1];
    $rest = $m[2];

    $serial = false;
    if (preg_match('/\bauto_increment\b/i', $rest)) {
      $serial = true;
      $rest = preg_replace('/\s*\bauto_increment\b/i', '', $rest);
    }

    // Type + modifiers
    if (!preg_match('/^([A-Za-z]+)\s*\(([^)]*)\)(\s+unsigned)?/i', $rest, $t)) {
      if (!preg_match('/^([A-Za-z]+)(\s+unsigned)?/i', $rest, $t)) {
        return $name . " " . $rest;
      }
      $t[2] = "";
      $t[3] = isset($t[2]) ? $t[2] : "";
    }
    $type = strtolower($t[1]);
    $size = isset($t[2]) ? $t[2] : "";
    $unsigned = (isset($t[3]) && $t[3] != "") || preg_match('/\bunsigned\b/i', $rest);
    $tail = ltrim(substr($rest, strlen($t[0])));

    switch ($type) {
      case 'tinyint':
      case 'smallint':
      case 'mediumint':
        // MySQL's narrow integers have no PostgreSQL equivalent worth keeping
        // (smallint is enough for every enum-like column in this schema, and
        // unsigned values need the range).
        $pgtype = ($unsigned || $type == 'mediumint') ? 'integer' : 'smallint';
        break;
      case 'int':
      case 'integer':
        $pgtype = $unsigned ? 'bigint' : 'integer';
        break;
      case 'bigint':
        $pgtype = 'bigint';
        break;
      case 'char':
      case 'varchar':
        $pgtype = $type . "(" . $size . ")";
        break;
      case 'text':
      case 'tinytext':
      case 'mediumtext':
      case 'longtext':
        $pgtype = 'text';
        break;
      case 'blob':
      case 'tinyblob':
      case 'mediumblob':
      case 'longblob':
        $pgtype = 'bytea';
        break;
      case 'enum':
        // PostgreSQL has no enum type in DDL shorthand; varchar keeps the
        // installer simple and matches MySQL's implicit string handling.
        $pgtype = 'varchar(64)';
        break;
      case 'double':
        $pgtype = 'double precision';
        break;
      case 'float':
        $pgtype = 'real';
        break;
      case 'decimal':
      case 'numeric':
        $pgtype = "numeric(" . $size . ")";
        break;
      case 'datetime':
      case 'timestamp':
        $pgtype = 'timestamp';
        break;
      default:
        $pgtype = $type . ($size != "" ? "(" . $size . ")" : "");
    }

    if ($serial) {
      // serial implies NOT NULL + a sequence default; PostgreSQL's integer
      // sequence covers the range MySQL's auto_increment used.
      $pgtype = ($pgtype == 'bigint') ? 'bigserial' : 'serial';
      $tail = preg_replace('/\bNOT\s+NULL\b/i', '', $tail);
    }

    // DEFAULT '0' on a numeric column: PostgreSQL wants the literal unquoted.
    if (preg_match("/\bDEFAULT\s+'(-?[0-9]+)'/i", $tail, $d)) {
      if (in_array($pgtype, array('integer', 'bigint', 'smallint', 'serial', 'bigserial', 'numeric', 'double precision', 'real'))) {
        $tail = str_replace($d[0], "DEFAULT " . $d[1], $tail);
      }
    }
    // MySQL zero dates are legal, PostgreSQL rejects them; the board stores
    // dates as integers anyway, so NULL is the safe equivalent.
    $tail = preg_replace("/\bDEFAULT\s+'0000-00-00( 00:00:00)?'/i", "DEFAULT NULL", $tail);

    $tail = preg_replace('/\s+/', ' ', trim($tail));
    return $name . " " . $pgtype . ($tail != "" ? " " . $tail : "");
  }

  // ----------------------------------------------------------- catalog helpers

  private function column_names($table) {
    $info = $this->column_info($table);
    if (!$info) {
      return array();
    }
    ksort($info);
    $names = array();
    foreach ($info as $col) {
      $names[] = $col['name'];
    }
    return $names;
  }

  private function is_serial_column($table, $column) {
    $info = $this->column_info($table);
    if (!isset($info[$column])) {
      return false;
    }
    return $info[$column]['serial'];
  }

  // position => array(name, serial), cached per request.
  private function column_info($table) {
    if (isset($this->column_cache[$table])) {
      return $this->column_cache[$table];
    }
    $this->column_cache[$table] = array();
    if (!($this->is_linked())) {
      return array();
    }
    $sql = "SELECT column_name, column_default, ordinal_position FROM information_schema.columns " .
           "WHERE table_schema = " . $this->quote_literal($this->schema) .
           " AND table_name = " . $this->quote_literal($table) . " ORDER BY ordinal_position";
    $res = @pg_query($this->link_id, $sql);
    if (!$this->is_result($res)) {
      return array();
    }
    $info = array();
    while ($row = pg_fetch_row($res)) {
      $serial = (strpos((string) $row[1], 'nextval(') === 0);
      $info[$row[0]] = array('name' => $row[0], 'serial' => $serial);
    }
    pg_free_result($res);
    $this->column_cache[$table] = $info;
    return $info;
  }

  // --------------------------------------------------------------- SQL utilities

  private function quote_ident($name) {
    return '"' . str_replace('"', '""', $name) . '"';
  }

  private function quote_literal($value) {
    return "'" . str_replace("'", "''", $value) . "'";
  }

  private function ident_list($list) {
    $out = array();
    foreach ($this->split_top_level($list) as $item) {
      $item = trim($item);
      // MySQL allows "col(10)" prefix lengths on indexes; PostgreSQL does not.
      $item = preg_replace('/\([0-9]+\)/', '', $item);
      if ($item != "") {
        $out[] = $item;
      }
    }
    return implode(',', $out);
  }

  // Split on commas that are not inside parentheses or string literals.
  private function split_top_level($text) {
    $out = array();
    $depth = 0;
    $quote = null;
    $esc = false;
    $start = 0;
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
      $c = $text[$i];
      if ($quote !== null) {
        if ($esc) {
          $esc = false;
        } elseif ($c == '\\') {
          $esc = true;
        } elseif ($c == $quote) {
          $quote = null;
        }
        continue;
      }
      if ($c == "'" || $c == '"') {
        $quote = $c;
        continue;
      }
      if ($c == '(') {
        $depth++;
      } elseif ($c == ')') {
        $depth--;
      } elseif ($c == ',' && $depth == 0) {
        $out[] = substr($text, $start, $i - $start);
        $start = $i + 1;
      }
    }
    $out[] = substr($text, $start);
    return $out;
  }

  private function matching_paren($text, $open) {
    $depth = 0;
    $quote = null;
    $esc = false;
    $len = strlen($text);
    for ($i = $open; $i < $len; $i++) {
      $c = $text[$i];
      if ($quote !== null) {
        if ($esc) {
          $esc = false;
        } elseif ($c == '\\') {
          $esc = true;
        } elseif ($c == $quote) {
          $quote = null;
        }
        continue;
      }
      if ($c == "'" || $c == '"') {
        $quote = $c;
        continue;
      }
      if ($c == '(') {
        $depth++;
      } elseif ($c == ')') {
        $depth--;
        if ($depth == 0) {
          return $i;
        }
      }
    }
    return false;
  }

  // Replace calls to a MySQL function with the output of $callback(args).
  // $callback returns null to mean "leave this call untouched".
  private function replace_function($sql, $name, $callback) {
    $pattern = '/(?<![\w$])' . $name . '\s*\(/i';
    $offset = 0;
    $guard = 0;
    while ($guard++ < 200) {
      if (!preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
        break;
      }
      // $m[0][0] is the "NAME(" prefix, so the paren is its last character.
      $open = $m[0][1] + strlen($m[0][0]) - 1;
      // Skip if the match is inside a string literal.
      if (!$this->at_code_position($sql, $m[0][1])) {
        $offset = $m[0][1] + 1;
        continue;
      }
      $close = $this->matching_paren($sql, $open);
      if ($close === false) {
        break;
      }
      $args = $this->split_top_level(substr($sql, $open + 1, $close - $open - 1));
      $replacement = call_user_func($callback, $args);
      if ($replacement === null) {
        $offset = $close + 1;
        continue;
      }
      $sql = substr($sql, 0, $m[0][1]) . $replacement . substr($sql, $close + 1);
      $offset = $m[0][1] + strlen($replacement);
    }
    return $sql;
  }

  // True when $pos is not inside a '...' or "..." literal.
  private function at_code_position($sql, $pos) {
    $in_single = false;
    $in_double = false;
    for ($i = 0; $i < $pos; $i++) {
      $c = $sql[$i];
      if ($in_single) {
        if ($c == '\\') {
          $i++;
        } elseif ($c == "'") {
          $in_single = false;
        }
      } elseif ($in_double) {
        if ($c == '\\') {
          $i++;
        } elseif ($c == '"') {
          $in_double = false;
        }
      } elseif ($c == "'") {
        $in_single = true;
      } elseif ($c == '"') {
        $in_double = true;
      }
    }
    return !($in_single || $in_double);
  }

  // Regex replacement applied only to code, never to quoted literals.
  // $callback receives the match array (like preg_replace_callback).
  private function replace_outside_quotes($sql, $pattern, $callback) {
    $parts = preg_split("/((?:'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'|\"[^\"\\\\]*(?:\\\\.[^\"\\\\]*)*\"))/s", $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
      if ($i % 2 == 1) {
        continue; // the captured literal itself
      }
      $parts[$i] = preg_replace_callback($pattern, $callback, $part);
    }
    return implode('', $parts);
  }
}
?>
