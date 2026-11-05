<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);
// db class for mysql
// this class is used in all scripts
// do NOT fiddle unless you know what you are doing
//
// ext/mysql was removed in PHP 7.0, so this class now talks to MySQL through
// mysqli. The public interface (query / query_first / fetch_array / ...) is
// unchanged, so the rest of the board does not need to know.

class DB_Sql_vb {
  public $database = "";

  public $link_id  = 0;
  public $query_id = 0;
  public $record   = array();

  public $errdesc    = "";
  public $errno   = 0;
  public $reporterror = 1;

  public $server   = "localhost";
  public $user     = "root";
  public $password = "";
  // Declared so that a $dbport in config.php can be assigned without creating
  // a dynamic property (deprecated in PHP 8.2). Unused by the MySQL driver:
  // the port goes with the host as "host:port" or through the socket.
  public $port     = 0;
  // Same for $dbschema (PostgreSQL only, but assigned unconditionally when set).
  public $schema   = "";

  public $appname  = "vBulletin";
  public $appshortname = "vBulletin (cp)";

  function connect() {
    global $usepconnect;
    // connect to db server

    // PHP 8.1 made mysqli throw mysqli_sql_exception by default; vBulletin
    // expects a false return value and handles errors itself via halt().
    mysqli_report(MYSQLI_REPORT_OFF);

    if ( 0 == $this->link_id ) {
      $host = ($usepconnect == 1) ? 'p:' . $this->server : $this->server;
      $this->link_id = @mysqli_connect($host, $this->user, $this->password);
      if (!$this->link_id) {
        $this->halt("Link-ID == false, connect failed");
        return false; // halt() only exits when reporterror is on
      }
      if ($this->database!="") {
        if(!mysqli_select_db($this->link_id, $this->database)) {
          $this->halt("cannot use database ".$this->database);
        }
      }
      // vBulletin 2.x was written for MySQL 4.x. Modern MySQL/MariaDB enable
      // STRICT_TRANS_TABLES + NO_ZERO_DATE by default, which reject the zero
      // dates and partially-specified INSERTs the board relies on. Restore
      // the old behaviour for this connection only.
      @mysqli_query($this->link_id, "SET SESSION sql_mode=''");

      // Pin the connection to utf8mb4. The original board is a single-byte
      // (windows-1252) app; without this the server may negotiate utf8mb3 and
      // silently replace 4-byte characters (emoji, some CJK) with U+FFFD.
      @mysqli_set_charset($this->link_id, 'utf8mb4');
    }
  }

  function geterrdesc() {
    $this->error = ($this->link_id instanceof mysqli) ? mysqli_error($this->link_id) : '';
    return $this->error;
  }

  function geterrno() {
    $this->errno = ($this->link_id instanceof mysqli) ? mysqli_errno($this->link_id) : 0;
    return $this->errno;
  }

  // Charset-aware quoting of a value for inclusion in a SQL string literal.
  function escape($value) {
    if ($this->link_id instanceof mysqli) {
      return mysqli_real_escape_string($this->link_id, (string) $value);
    }
    return vb_escape((string) $value);
  }

  function select_db($database="") {
    // select database
    if ($database!="") {
      $this->database=$database;
    }

    if (!($this->link_id instanceof mysqli) || !mysqli_select_db($this->link_id, $this->database)) {
      $this->halt("cannot use database ".$this->database);
    }

  }

  function query($query_string) {
    global $query_count,$showqueries,$explain,$querytime;
    // do query

    if ($showqueries) {
      echo "Query: $query_string\n";

      global $pagestarttime;
      $pageendtime=microtime();
      $starttime=explode(" ",$pagestarttime);
      $endtime=explode(" ",$pageendtime);

      $beforetime=$endtime[0]-$starttime[0]+$endtime[1]-$starttime[1];

      echo "Time before: $beforetime\n";
    }

    if (!($this->link_id instanceof mysqli)) {
      $this->halt("Invalid SQL: ".$query_string);
      return false;
    }
    $this->query_id = mysqli_query($this->link_id, $query_string);
    if (!$this->query_id) {
      $this->halt("Invalid SQL: ".$query_string);
    }

    $query_count++;

    if ($showqueries) {
      $pageendtime=microtime();
      $starttime=explode(" ",$pagestarttime);
      $endtime=explode(" ",$pageendtime);

      $aftertime=$endtime[0]-$starttime[0]+$endtime[1]-$starttime[1];
      $querytime+=$aftertime-$beforetime;

      echo "Time after:  $aftertime\n";

      if ($explain and substr(trim(strtoupper($query_string)),0,6)=="SELECT") {
        $explain_id = mysqli_query($this->link_id, "EXPLAIN $query_string");
        echo "</pre>\n";
        echo "
        <table width=100% border=1 cellpadding=2 cellspacing=1>
        <tr>
          <td><b>table</b></td>
          <td><b>type</b></td>
          <td><b>possible_keys</b></td>
          <td><b>key</b></td>
          <td><b>key_len</b></td>
          <td><b>ref</b></td>
          <td><b>rows</b></td>
          <td><b>Extra</b></td>
        </tr>\n";
        while($array=mysqli_fetch_array($explain_id)) {
          echo "
          <tr>
            <td>$array[table]&nbsp;</td>
            <td>$array[type]&nbsp;</td>
            <td>$array[possible_keys]&nbsp;</td>
            <td>$array[key]&nbsp;</td>
            <td>$array[key_len]&nbsp;</td>
            <td>$array[ref]&nbsp;</td>
            <td>$array[rows]&nbsp;</td>
            <td>$array[Extra]&nbsp;</td>
          </tr>\n";
        }
        echo "</table>\n<BR><hr>\n";
        echo "\n<pre>";
      } else {
        echo "\n<hr>\n\n";
      }
    }

    return $this->query_id;
  }

  function fetch_array($query_id=-1,$query_string="") {
    // retrieve row
    if ($query_id!=-1) {
      $this->query_id=$query_id;
    }
    if ( $this->query_id instanceof mysqli_result ) {
      // MYSQLI_BOTH is the default: the board uses both $row[0] and
      // $row['column'] forms.
      $this->record = mysqli_fetch_array($this->query_id);
    } else {
      if ( !empty($query_string) ) {
        $this->halt("Invalid query id (".$this->query_id.") on this query: $query_string");
      } else {
        $this->halt("Invalid query id ".$this->query_id." specified");
      }
    }

    return $this->record;
  }

  function free_result($query_id=-1) {
    // retrieve row
    if ($query_id!=-1) {
      $this->query_id=$query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return mysqli_free_result($this->query_id);
    }
    return true;
  }

  function query_first($query_string) {
    // does a query and returns first row
    $query_id = $this->query($query_string);
    $returnarray=$this->fetch_array($query_id, $query_string);
    $this->free_result($query_id);
    return $returnarray;
  }

  function data_seek($pos,$query_id=-1) {
    // goes to row $pos
    if ($query_id!=-1) {
      $this->query_id=$query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return mysqli_data_seek($this->query_id, $pos);
    }
    return false;
  }

  function num_rows($query_id=-1) {
    // returns number of rows in query
    if ($query_id!=-1) {
      $this->query_id=$query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return mysqli_num_rows($this->query_id);
    }
    return 0;
  }

  function num_fields($query_id=-1) {
    // returns number of fields in query
    if ($query_id!=-1) {
      $this->query_id=$query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return mysqli_num_fields($this->query_id);
    }
    return 0;
  }

  function insert_id() {
    // returns last auto_increment field number assigned

    return ($this->link_id instanceof mysqli) ? mysqli_insert_id($this->link_id) : 0;

  }

  function close() {
  	// closes connection to the database

	return ($this->link_id instanceof mysqli) ? mysqli_close($this->link_id) : true;
  }

  function halt($msg) {
    $this->errdesc = $this->geterrdesc();
    $this->errno   = $this->geterrno();
    // prints warning message when there is an error
    global $technicalemail, $bbuserinfo, $scriptpath, $_SERVER;

    if ($this->reporterror==1) {
      $message="Database error in " . $this->appname . " $GLOBALS[templateversion]:\n\n$msg\n";
      $message.="mysql error: " . $this->errdesc . "\n\n";
      $message.="mysql error number: " . $this->errno . "\n\n";
      $message.="Date: ".date("l dS of F Y h:i:s A")."\n";
      $message.="Script: $GLOBALS[bburl]" . (($scriptpath) ? $scriptpath : $_SERVER['REQUEST_URI']) . "\n";
      $message.="Referer: ".$_SERVER['HTTP_REFERER']."\n";

      if ($technicalemail) {
        @mail ($technicalemail,$this->appshortname. " Database error!",$message,"From: $technicalemail");
      }

      echo "<html><head><title>$GLOBALS[bbtitle] Database Error</title><style>P,BODY{FONT-FAMILY:tahoma,arial,sans-serif;FONT-SIZE:11px;}</style><body>\n\n<!-- $message -->\n\n";

      echo "</table></td></tr></table></form>\n<blockquote><p>&nbsp;</p><p><b>There seems to have been a slight problem with the $GLOBALS[bbtitle] database.</b><br>\n";
      echo "Please try again by pressing the <a href=\"javascript:window.location=window.location;\">refresh</a> button in your browser.</p>";
      echo "An E-Mail has been dispatched to our <a href=\"mailto:$technicalemail\">Technical Staff</a>, who you can also contact if the problem persists.</p>";
      echo "<p>We apologise for any inconvenience.</p>";

      if ($bbuserinfo['usergroupid']==6) {
        echo "<form><textarea rows=\"12\" cols=\"60\">".htmlspecialchars($message)."</textarea></form>";
      }

	  echo "</blockquote></body></head></html>";
      exit;
    }
  }
}
?>
