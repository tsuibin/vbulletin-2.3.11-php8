<?php
require_once __DIR__ . '/php8compat.php';
require_once __DIR__ . '/dbsettings_migrate.php';
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

// Connection details are remembered per engine, so picking "PostgreSQL" in the
// form brings back the host/user/password/database that were used last time
// instead of leaving the MySQL values in the fields.
function dbsettings_profile_keys() {
	return array('servername', 'dbport', 'dbschema', 'dbusername', 'dbpassword', 'dbname', 'usepconnect');
}

function dbsettings_profiles_php($profiles) {
	$text = "\$dbprofiles = array(\n";
	foreach (array('mysql', 'pgsql') as $type) {
		if (!isset($profiles[$type])) {
			continue;
		}
		$text .= "\t'" . $type . "' => array(\n";
		foreach (dbsettings_profile_keys() as $key) {
			$value = isset($profiles[$type][$key]) ? $profiles[$type][$key] : '';
			$text .= "\t\t'" . $key . "' => '" . dbsettings_escape_value($value) . "',\n";
		}
		$text .= "\t),\n";
	}
	$text .= ");";
	return $text;
}

// Reads the "$dbprofiles = array(...);" block back out of config.php.
function dbsettings_read_profiles($text) {
	if (!preg_match('/\$dbprofiles\s*=\s*array\([\s\S]*?\n\);/', $text, $match)) {
		return array();
	}
	$literal = substr($match[0], strpos($match[0], 'array('));
	$profiles = array();
	try {
		$evaluated = eval('return ' . $literal . ';');
	} catch (Throwable $e) {
		return array();
	}
	if (!is_array($evaluated)) {
		return array();
	}
	foreach ($evaluated as $type => $values) {
		if (!is_array($values) || !in_array($type, array('mysql', 'pgsql'))) {
			continue;
		}
		$clean = array();
		foreach (dbsettings_profile_keys() as $key) {
			$clean[$key] = isset($values[$key]) ? (string) $values[$key] : '';
		}
		$profiles[$type] = $clean;
	}
	return $profiles;
}

function dbsettings_with_profiles($text, $profiles) {
	$block = dbsettings_profiles_php($profiles);
	if (preg_match('/\$dbprofiles\s*=\s*array\([\s\S]*?\n\);/', $text)) {
		return preg_replace_callback('/\$dbprofiles\s*=\s*array\([\s\S]*?\n\);/', function () use ($block) {
			return $block;
		}, $text, 1);
	}
	$close = strrpos($text, '?>');
	if ($close === false) {
		return $text . "\n" . $block . "\n";
	}
	return substr($text, 0, $close) . $block . "\n" . substr($text, $close);
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
function dbsettings_write_config($file, $values, $profiles = null) {
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

	if (is_array($profiles)) {
		$text = dbsettings_with_profiles($text, $profiles);
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

// pg_version($link) returns an array in modern PHP, a string only without a
// connection — this keeps the page working either way.
function dbsettings_pg_server_version($link) {
	$parameter = @pg_parameter_status($link, 'server_version');
	if ($parameter !== false && $parameter !== '') {
		return $parameter;
	}
	$version = @pg_version($link);
	if (is_array($version)) {
		return isset($version['server']) ? $version['server'] : 'unknown';
	}
	return ($version === false || $version === '') ? 'unknown' : $version;
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
		$details[] = '已连接 PostgreSQL ' . dbsettings_pg_server_version($link) . "（schema: " . $schema . "）";
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

function dbsettings_profile_of($values) {
	$profile = array();
	foreach (dbsettings_profile_keys() as $key) {
		$profile[$key] = isset($values[$key]) ? (string) $values[$key] : '';
	}
	return $profile;
}

function dbsettings_same_database($left, $right) {
	if ($left['dbservertype'] != $right['dbservertype']) {
		return false;
	}
	$leftschema = ($left['dbservertype'] == 'pgsql' && $left['dbschema'] !== '') ? $left['dbschema'] : 'public';
	$rightschema = ($right['dbservertype'] == 'pgsql' && $right['dbschema'] !== '') ? $right['dbschema'] : 'public';
	return ($left['dbname'] == $right['dbname'])
		&& ($left['servername'] == $right['servername'])
		&& ($left['dbport'] == $right['dbport'])
		&& ($leftschema == $rightschema);
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
$profiles = dbsettings_read_profiles(@file_get_contents($configfile));

if ($action == "load" || $action == "save" || $action == "test" || $action == "migrate") {
	$values = dbsettings_post_values();

	if ($action == "load") {
		$type = $values['dbservertype'];
		if (isset($profiles[$type])) {
			$values = array_merge($values, $profiles[$type]);
			$values['dbservertype'] = $type;
			$messages[] = "已载入 " . ($type == 'pgsql' ? 'PostgreSQL' : 'MySQL/MariaDB') . " 上次保存的连接信息。";
		} else {
			$messages[] = ($type == 'pgsql' ? 'PostgreSQL' : 'MySQL/MariaDB') .
				" 还没有保存过的连接信息，表单保留当前内容；填好后先 Test connection。";
		}
	}

	if ($action == "save" || $action == "test" || $action == "migrate") {
		$ok = dbsettings_test_connection($values, $details);
		if ($ok) {
			$messages = array_merge($messages, $details);
		} else {
			$errors = array_merge($errors, $details);
		}
	}

	if ($action == "save" && $ok) {
		$profiles[$values['dbservertype']] = dbsettings_profile_of($values);
		$error = dbsettings_write_config($configfile, $values, $profiles);
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

	if ($action == "migrate") {
		$recreate = (isset($_POST['dbmig_recreate']) && $_POST['dbmig_recreate'] == '1');
		if (!$ok) {
			$errors[] = "目标库连不上，没有开始迁移，配置未改动。";
		} elseif (dbsettings_same_database($current, $values)) {
			$errors[] = "目标和当前使用的是同一个数据库，无需迁移。";
		} else {
			$messages[] = "开始把数据从 " . ($current['dbservertype'] == 'pgsql' ? 'PostgreSQL' : 'MySQL') . "（" .
				$current['dbname'] . "）复制到 " . ($values['dbservertype'] == 'pgsql' ? 'PostgreSQL' : 'MySQL') . "（" .
				$values['dbname'] . "）……";
			$migrated = dbmig_run($current, $values, $recreate, $miglog, $migerrors);
			if ($migrated) {
				foreach ($miglog as $line) {
					$messages[] = $line;
				}
				$previous = $current;
				$profiles[$current['dbservertype']] = dbsettings_profile_of($current);
				$profiles[$values['dbservertype']] = dbsettings_profile_of($values);
				$error = dbsettings_write_config($configfile, $values, $profiles);
				if ($error == "") {
					$messages[] = "已把论坛切到 " . ($values['dbservertype'] == 'pgsql' ? 'PostgreSQL' : 'MySQL') .
						"（" . $values['dbname'] . "），config.php 备份为 " . basename($configfile) . ".bak." . date('Ymd-His') . "。";
					if ($previous['dbservertype'] == $values['dbservertype']) {
						$messages[] = "原数据库 " . $previous['dbname'] . " 没有被删除，仍在服务器上；要退回时把连接信息改回去即可。";
					} else {
						$messages[] = "原库没有删除，随时可以切回来：在 Database type 里选回 " .
							($previous['dbservertype'] == 'pgsql' ? 'PostgreSQL' : 'MySQL') . "，连接信息会自动载入。";
					}
					$current = dbsettings_read_config($configfile);
				} else {
					$errors[] = $error;
					$errors[] = "数据已经复制完成，但配置没写进去——请手动把 admin/config.php 改成目标库，或修复权限后重试。";
				}
			} else {
				foreach ($migerrors as $line) {
					$errors[] = $line;
				}
				$errors[] = "迁移没有完成，论坛仍在使用原来的数据库，配置未改动。";
			}
		}
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
<input type="hidden" name="action" id="dbaction" value="modify">
<?php
$selected = function ($value, $mine) {
	return ($value == $mine) ? " selected=\"selected\"" : "";
};
$profiletypes = array();
foreach (array('mysql', 'pgsql') as $type) {
	$profiletypes[] = ($type == 'pgsql' ? 'PostgreSQL' : 'MySQL') . (isset($profiles[$type]) ? '(已保存)' : '');
}
dbsettings_row("Database type",
	"<select name=\"db[dbservertype]\" onchange=\"document.getElementById('dbaction').value='load'; this.form.submit();\">" .
	"<option value=\"mysql\"" . $selected('mysql', $values['dbservertype']) . ">MySQL / MariaDB</option>" .
	"<option value=\"pgsql\"" . $selected('pgsql', $values['dbservertype']) . ">PostgreSQL</option>" .
	"</select>",
	"换引擎会立刻载入该引擎上次保存的连接信息（" . implode(' / ', $profiletypes) . "）。");
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
dbsettings_row("Recreate tables in the target database",
	"<input type=\"checkbox\" name=\"dbmig_recreate\" value=\"1\"" . (isset($_POST['dbmig_recreate']) && $_POST['dbmig_recreate'] == '1' ? " checked" : "") . ">",
	"只用于迁移：目标库已经有同名的 vBulletin 表时，勾上会先删除它们再复制。目标库是空库则不用勾。");
?>
<tr class="firstalt"><td>&nbsp;</td><td>
<button type="submit" onclick="document.getElementById('dbaction').value='test'">Test connection</button>
<button type="submit" onclick="document.getElementById('dbaction').value='save'">Save settings</button>
<button type="submit" onclick="if (confirm('将把当前数据库的全部数据复制到上面的目标库，并把论坛切过去。原库不会被删除。继续？')) { document.getElementById('dbaction').value='migrate'; return true; } return false;">Copy data to this database and switch</button>
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
