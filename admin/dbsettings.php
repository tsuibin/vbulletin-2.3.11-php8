<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

// ###################### Start database settings page #######################
//
// Lets the administrator pick the database engine (mysql / pgsql) and the
// connection details without editing admin/config.php by hand. The choice has
// to live in a file, not in the database: reading a setting out of the
// database would already require knowing which database to talk to.
//
// Saving rewrites the data-related assignments in admin/config.php and keeps
// a timestamped copy of the old file next to it.

$configfile = __DIR__ . '/config.php';

function dbsettings_keys() {
	return array('dbservertype', 'servername', 'dbport', 'dbschema', 'dbusername', 'dbpassword', 'dbname', 'usepconnect');
}

function dbsettings_escape_value($value) {
	return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value);
}

function dbsettings_unescape_value($value) {
	return str_replace(array("\\\\", "\\'"), array('\\', "'"), $value);
}

// "$name = 'value';" as well as the older unquoted "$name = 5433;" -- both
// shapes appear in config.php files depending on when they were written.
function dbsettings_var_pattern($key) {
	return '/\$' . preg_quote($key, '/') . '\s*=\s*(?:\'(?:\\\\.|[^\'\\\\])*\'|[^\'"]*)\s*;/';
}

function dbsettings_find_var($text, $key) {
	if (!preg_match(dbsettings_var_pattern($key), $text, $match)) {
		return null;
	}
	$statement = $match[0];
	if (preg_match('/(\'(?:\\\\.|[^\'\\\\])*\')/', $statement, $quoted)) {
		return dbsettings_unescape_value(substr($quoted[1], 1, -1));
	}
	$raw = preg_replace('/^\$' . preg_quote($key, '/') . '\s*=\s*/', '', $statement);
	$raw = preg_replace('/\s*;$/', '', $raw);
	return trim($raw, " \t\"");
}

// Current values, read out of the config file itself so the form always shows
// what the next request will actually use.
function dbsettings_read_config($file) {
	$values = array();
	$text = @file_get_contents($file);
	if ($text === false) {
		return null;
	}
	foreach (dbsettings_keys() as $key) {
		$found = dbsettings_find_var($text, $key);
		$values[$key] = ($found === null) ? '' : $found;
	}
	return $values;
}

// Rewrites each "$name = 'value';" line in place, appends missing ones before
// the closing tag, validates the result as PHP before it replaces the file.
function dbsettings_write_config($file, $values) {
	$text = @file_get_contents($file);
	if ($text === false) {
		return "无法读取 " . $file . "，请检查文件权限。";
	}

	foreach ($values as $key => $value) {
		$line = '$' . $key . " = '" . dbsettings_escape_value($value) . "';";
		$pattern = dbsettings_var_pattern($key);
		if (preg_match($pattern, $text)) {
			$text = preg_replace_callback($pattern, function () use ($line) { return $line; }, $text, 1);
			continue;
		}
		$close = strrpos($text, '?>');
		if ($close === false) {
			$text .= "\n" . $line . "\n";
		} else {
			$text = substr($text, 0, $close) . $line . "\n" . substr($text, $close);
		}
	}

	try {
		token_get_all($text, TOKEN_PARSE);
	} catch (ParseError $e) {
		return "生成的配置不是合法的 PHP（" . $e->getMessage() . "），文件未改动。";
	}

	$backup = $file . '.bak.' . date('Ymd-His');
	if (!@copy($file, $backup)) {
		return "无法备份原配置到 " . $backup . "，文件未改动。";
	}
	if (@file_put_contents($file, $text) === false) {
		@copy($backup, $file);
		return "无法写入 " . $file . "，已恢复原文件。";
	}
	return "";
}

// Tries the submitted settings with a brand new connection, so a broken set
// never gets saved blind.
function dbsettings_test_connection($values, &$details) {
	$details = array();
	$type = ($values['dbservertype'] == 'pgsql') ? 'pgsql' : 'mysql';

	if ($values['dbname'] == '') {
		$details[] = '数据库名不能为空。';
		return false;
	}

	if ($type == 'pgsql') {
		if (!function_exists('pg_connect')) {
			$details[] = 'PHP 未启用 pgsql 扩展（--with-pgsql），无法连接 PostgreSQL。';
			return false;
		}
		$port = ($values['dbport'] === '') ? '5432' : $values['dbport'];
		$conn = "host=" . $values['servername'] . " port=" . $port . " dbname=" . $values['dbname'];
		if ($values['dbusername'] !== '') {
			$conn .= " user=" . $values['dbusername'];
		}
		if ($values['dbpassword'] !== '') {
			$conn .= " password=" . $values['dbpassword'];
		}
		$link = false;
		try {
			$link = @pg_connect($conn, PGSQL_CONNECT_FORCE_NEW);
		} catch (Throwable $e) {
			$details[] = 'PostgreSQL 连接失败：' . $e->getMessage();
			return false;
		}
		if (!$link) {
			$details[] = 'PostgreSQL 连接失败：' . $values['servername'] . ':' . $port . '/' . $values['dbname'];
			$details[] = '请检查主机、端口、库名、用户名/密码，以及 pg_hba.conf 是否允许该连接。';
			return false;
		}
		$schema = ($values['dbschema'] === '') ? 'public' : $values['dbschema'];
		$quoted = "'" . str_replace("'", "''", $schema) . "'";
		$res = @pg_query($link, "SELECT tablename FROM pg_tables WHERE schemaname = " . $quoted);
		$tables = array();
		if ($res) {
			while ($row = pg_fetch_row($res)) {
				$tables[] = $row[0];
			}
			pg_free_result($res);
		}
		$details[] = '已连接 PostgreSQL ' . @pg_version($link) . "（schema: " . $schema . "）";
		$details[] = '该 schema 下现有 ' . count($tables) . ' 张表。';
		$missing = array();
		foreach (array('user', 'setting', 'forum') as $needed) {
			if (!in_array($needed, $tables)) {
				$missing[] = $needed;
			}
		}
		if ($missing) {
			$details[] = '缺少 vBulletin 核心表：' . implode(', ', $missing) . '（这个库里还没装过 vB，或 schema 填错了）。';
		}
		pg_close($link);
		return true;
	}

	if (!function_exists('mysqli_connect')) {
		$details[] = 'PHP 未启用 mysqli 扩展，无法连接 MySQL/MariaDB。';
		return false;
	}
	$port = ($values['dbport'] === '') ? 0 : (int) $values['dbport'];
	// PHP 8.1+ turns a failed mysqli_connect() into an exception by default;
	// the driver files switch the reporting off, this page does the same.
	mysqli_report(MYSQLI_REPORT_OFF);
	$link = false;
	try {
		$link = @mysqli_connect($values['servername'], $values['dbusername'], $values['dbpassword'], $values['dbname'], $port);
	} catch (Throwable $e) {
		$details[] = 'MySQL 连接失败：' . $e->getMessage();
		return false;
	}
	if (!$link) {
		$details[] = 'MySQL 连接失败：' . $values['servername'] . ($port ? ':' . $port : '') . '/' . $values['dbname'];
		$details[] = 'mysqli: ' . mysqli_connect_error();
		return false;
	}
	@mysqli_set_charset($link, 'utf8mb4');
	$details[] = '已连接 MySQL/MariaDB ' . mysqli_get_server_info($link) . '。';
	$res = @mysqli_query($link, "SHOW TABLES");
	$count = $res ? mysqli_num_rows($res) : 0;
	$details[] = '该库下现有 ' . $count . ' 张表。';
	if ($res) {
		mysqli_free_result($res);
	}
	$hasuser = @mysqli_query($link, "SHOW TABLES LIKE 'user'");
	$hassetting = @mysqli_query($link, "SHOW TABLES LIKE 'setting'");
	$missing = array();
	if (!$hasuser || mysqli_num_rows($hasuser) == 0) {
		$missing[] = 'user';
	}
	if (!$hassetting || mysqli_num_rows($hassetting) == 0) {
		$missing[] = 'setting';
	}
	if ($missing) {
		$details[] = '缺少 vBulletin 核心表：' . implode(', ', $missing) . '（这个库里还没装过 vB）。';
	}
	mysqli_close($link);
	return true;
}

function dbsettings_post_values() {
	$values = array();
	foreach (dbsettings_keys() as $key) {
		$values[$key] = isset($_POST['db'][$key]) ? trim((string) $_POST['db'][$key]) : '';
	}
	$values['dbservertype'] = ($values['dbservertype'] == 'pgsql') ? 'pgsql' : 'mysql';
	$values['usepconnect'] = ($values['usepconnect'] == '1') ? '1' : '0';
	return $values;
}

function dbsettings_row($label, $html, $note = "") {
	echo "<tr class=\"firstalt\"><td width=\"35%\" nowrap>" . $label . "</td><td>" . $html;
	if ($note != "") {
		echo "<br><font size=\"1\">" . $note . "</font>";
	}
	echo "</td></tr>\n";
}

$current = dbsettings_read_config($configfile);
if ($current === null) {
	cpheader("<title>Database Settings</title>");
	echo "<p>Cannot read <b>" . htmlspecialchars($configfile) . "</b>. Check the file permissions.</p>";
	cpfooter();
	exit;
}

if (!isset($action)) {
	$action = "modify";
}

$values = $current;
$messages = array();
$errors = array();

if ($action == "save" || $action == "test") {
	$values = dbsettings_post_values();
	$ok = dbsettings_test_connection($values, $details);
	if ($ok) {
		$messages = $details;
	} else {
		$errors = $details;
	}

	if ($action == "save" && $ok) {
		$error = dbsettings_write_config($configfile, $values);
		if ($error == "") {
			$messages[] = "已写入 admin/config.php（旧文件备份为 " . basename($configfile) . ".bak." . date('Ymd-His') . "）。";
			$messages[] = "新的连接设置在下一个请求生效：刷新本页即会使用新配置。";
			$current = dbsettings_read_config($configfile);
		} else {
			$errors[] = $error;
		}
	} elseif ($action == "save" && !$ok) {
		$errors[] = "连接测试没通过，配置未保存。";
	}
}

cpheader("<title>Database Settings</title>");

if ($messages) {
	echo "<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"tblborder\" align=\"center\" width=\"90%\">\n";
	echo "<tr><td class=\"firstalt\"><b>结果</b><ul>";
	foreach ($messages as $line) {
		echo "<li>" . htmlspecialchars($line) . "</li>";
	}
	echo "</ul></td></tr></table><br>\n";
}
if ($errors) {
	echo "<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"tblborder\" align=\"center\" width=\"90%\">\n";
	echo "<tr><td class=\"secondalt\"><b>错误</b><ul>";
	foreach ($errors as $line) {
		echo "<li>" . htmlspecialchars($line) . "</li>";
	}
	echo "</ul></td></tr></table><br>\n";
}

echo "<table cellpadding=\"1\" cellspacing=\"0\" border=\"0\" class=\"tblborder\" align=\"center\" width=\"90%\"><tr><td>\n";
echo "<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" width=\"100%\">\n";
maketableheader("Database Settings");
?>
<form action="dbsettings.php" method="post">
<input type="hidden" name="s" value="<?php echo $session['sessionhash']; ?>">
<?php
$selected = function ($value, $mine) {
	return ($value == $mine) ? " selected=\"selected\"" : "";
};
dbsettings_row("Database type",
	"<select name=\"db[dbservertype]\">" .
	"<option value=\"mysql\"" . $selected('mysql', $values['dbservertype']) . ">MySQL / MariaDB</option>" .
	"<option value=\"pgsql\"" . $selected('pgsql', $values['dbservertype']) . ">PostgreSQL</option>" .
	"</select>",
	"The forum can only use one engine at a time，两种引擎的库内容不能互相读取。");
dbsettings_row("Server", "<input type=\"text\" name=\"db[servername]\" size=\"40\" value=\"" . htmlspecialchars($values['servername']) . "\">",
	"MySQL 一般是 localhost 或 127.0.0.1；PostgreSQL 可以用主机名，也可以填 socket 目录（如 /tmp）。");
dbsettings_row("Port", "<input type=\"text\" name=\"db[dbport]\" size=\"10\" value=\"" . htmlspecialchars($values['dbport']) . "\">",
	"留空则用默认端口：MySQL 3306，PostgreSQL 5432。");
dbsettings_row("Schema", "<input type=\"text\" name=\"db[dbschema]\" size=\"20\" value=\"" . htmlspecialchars($values['dbschema']) . "\">",
	"仅 PostgreSQL 使用；留空按 public 处理。MySQL 忽略此项。");
dbsettings_row("Username", "<input type=\"text\" name=\"db[dbusername]\" size=\"30\" value=\"" . htmlspecialchars($values['dbusername']) . "\">");
dbsettings_row("Password", "<input type=\"password\" name=\"db[dbpassword]\" size=\"30\" value=\"" . htmlspecialchars($values['dbpassword']) . "\">");
dbsettings_row("Database name", "<input type=\"text\" name=\"db[dbname]\" size=\"30\" value=\"" . htmlspecialchars($values['dbname']) . "\">");
dbsettings_row("Persistent connections",
	"<input type=\"checkbox\" name=\"db[usepconnect]\" value=\"1\"" . ($values['usepconnect'] == '1' ? " checked" : "") . ">",
	"旧版 MySQL 的 pconnect；PostgreSQL 侧忽略。");
?>
<tr class="firstalt"><td>&nbsp;</td><td>
<button type="submit" name="action" value="test">Test connection</button>
<button type="submit" name="action" value="save">Save settings</button>
</td></tr>
</table>
</td></tr></table>
</form>
<br>
<table cellpadding="4" cellspacing="1" border="0" class="tblborder" align="center" width="90%">
<tr><td class="firstalt">
<b>当前生效的配置</b><br>
<font size="1">
type: <?php echo htmlspecialchars($current['dbservertype']); ?> &nbsp;|&nbsp;
server: <?php echo htmlspecialchars($current['servername']); ?> &nbsp;|&nbsp;
port: <?php echo ($current['dbport'] === '' ? '(default)' : htmlspecialchars($current['dbport'])); ?> &nbsp;|&nbsp;
database: <?php echo htmlspecialchars($current['dbname']); ?><?php
if ($current['dbservertype'] == 'pgsql') {
	echo " &nbsp;|&nbsp; schema: " . htmlspecialchars($current['dbschema'] === '' ? 'public' : $current['dbschema']);
}
?>
</font>
</td></tr>
<tr><td class="firstalt">
<font size="1">
切换引擎的步骤：先在目标库里准备好 vBulletin 数据（用安装器新建，或用备份页导出的 dump 导入），
再在这里改引擎并保存。保存后论坛首页若打不开，说明目标库缺表或账号权限不足——
把 <b>admin/config.php.bak.*</b> 复制回 <b>admin/config.php</b> 即可回退。
</font>
</td></tr>
</table>
<?php
cpfooter();
?>
