# vBulletin 2.3.11 — PHP 8 兼容性移植（研究与存档）

> ## ⚠️ 商业软件 / 授权声明 — 使用前必读
>
> **vBulletin 是商业专有软件**，版权归 **MH Sub I, LLC**（原 Jelsoft Enterprises /
> vBulletin Solutions）所有，按商业授权销售，官方网站 `https://www.vbulletin.com/`。
>
> 本仓库包含 **vBulletin 2.3.11** 的原始源码及本人在其之上所做的兼容性修改。
> **本仓库不附带、不授予、也不替代任何 vBulletin 使用授权；不提供客户号、许可证、
> 授权绕过或任何破解内容。**
>
> - 任何人在编译、部署或使用本仓库内容之前，**必须自行向 vBulletin 官方购买有效授权**。
> - 若你未持有有效授权，请立即停止使用并删除本仓库的本地副本。
> - 仓库中的源码仅作为**个人技术研究与历史存档**，记录「如何让 2003 年的 PHP 4
>   论坛在 PHP 8 上运行」；它不构成对源码的再分发授权。
> - 原始发行包中的 `INSTALL.txt`、`LICENSE.txt`、`README.txt`、`demos/`、
>   `extras/documentation` 未包含在本仓库（见 `.gitignore`），请以你购买的官方
>   发行包中的许可文件为准。
> - "vBulletin" 及相关标识为其权利人的商标，本仓库不主张任何权利。
>
> **English summary:** vBulletin is proprietary commercial software. This repository
> is a personal research/archive of PHP 8 compatibility patches applied on top of
> vBulletin 2.3.11. It grants **no license**. You must purchase a valid vBulletin
> license from the vendor before any use. No cracks, license keys or bypasses are
> included.

---

## 1. 项目性质与来源

| 项 | 内容 |
|---|---|
| 上游 | vBulletin **2.3.11**（2003 年前后，PHP 4 时代） |
| 原始来源 | GitHub `tsuibin/vb24php5`，最后提交 `a5fce59`（2016-05-05） |
| 基线版本证据 | `admin/install4php5.php:16` 的 `$version = "2.3.11"`、`admin/vbulletin.style` 首行 `2.3.11`、`admin/global.php` 的 `$codeversionnumber` |
| 规模 | 91 个 PHP 文件、约 3.8 万行、256 个文件、2.7 MB |
| 架构 | 过程式单体；唯一类 `DB_Sql_vb`；模板存数据库并 `eval()` 渲染（810 处 `eval`、828 处 `gettemplate`）；1781 处 SQL 调用 |
| 本仓库目标 | 让这份代码能在 **PHP 8.x + 现代 MySQL/MariaDB** 上跑起来，并如实记录所有改动与遗留风险 |

这不是官方版本、不是分支发行版、不是"去授权版"。所有改动均为运行时兼容性修补，
未增删业务功能，未改动任何授权校验逻辑。

## 2. 基线目录

```
根目录（36 个 php）   面向用户的独立入口：index / forumdisplay / showthread / member / private / search / register ...
admin/               后台 CP、21 个 upgrade*.php、安装脚本、425 KB 的 vbulletin.style（默认模板导出）
mod/                 版主 CP
images/              155 个界面素材
admin/config.php     数据库连接配置（本仓库内为占位值）
admin/php8compat.php 【新增】PHP 8 兼容层
```

`member2.php` / `private2.php` 是原版 2.3「PM 增强」的拆分文件，不是移植产物。

## 3. 修改总览

| 轮次 | 目标 | 主要规模 |
|---|---|---|
| A | PHP 8 可运行 | 新增兼容层；自动改写 472 处长数组、2967 处裸数组键、275 处短标签；`mysql_*` → `mysqli_*` |
| B | SQL 转义加固 | 735 处 `addslashes` → 字符集安全的 `vb_escape`；42 个数值请求参数在引导层强转 |
| C | 登录态下的 TypeError | 规范化 `timezoneoffset`；`floor(phpversion())` 系列加显式数值转换 |
| D | 中文 / UTF-8 支持 | 全站显式 `UTF-8` 声明（HTTP 头 + meta）；连接固定 `utf8mb4`；表转 `utf8mb4` |
| E | 中文发帖乱码（发布链路） | `censortext()` 两处按字节 `chr(173)`/`chr(160)` 替换改为匹配完整 UTF-8 序列 |
| F | MySQL 8 JOIN 优先级 | 6 处 `FROM a,b LEFT JOIN c ON (...a.col...)` 改为显式 `JOIN ... ON` |
| G | PHP 7 移除的 `/e` 修饰符 | `bbcodeparse2()` 6 条 + `unhtmlspecialchars()` 1 条改用 `preg_replace_callback`；新增 UTF-8 版 `vb_chr()` |
| H | 界面文案 i18n | 新增 `lang_en.php` / `lang_zh.php` / `lang.php`，cookie 切换中英，模板源码级翻译（用户内容不受影响） |
| I | 加载计时 | 页面底部输出页面总耗时与模板加载耗时，中英双语 |
| — | 验证 | PHP 8.5.12 + MariaDB 13.0.1 真库端到端回归 |

以下按文件逐项说明。

## 4. 逐项修改明细

### 4.1 新增 `admin/php8compat.php`（兼容层，约 205 行）

被 89 个 PHP 文件以 `require_once __DIR__ . '/.../php8compat.php';` 在文件最顶部加载
（`admin/config.php` 除外，它由安装器重写）。内容：

- **已移除函数的 polyfill**：`each()`（基于数组内部指针，保持
  `while (list($k,$v)=each($arr))` 的精确语义）、`ereg` / `eregi` /
  `ereg_replace` / `eregi_replace` / `split` / `spliti` / `sql_regcase`
  （POSIX ERE → PCRE，自动挑选未出现在模式中的分隔符；73 个实际模式全部验证可编译，
  `\1` 反向引用行为一致）。
- **magic quotes**：`get_magic_quotes_gpc()` / `get_magic_quotes_runtime()` /
  `set_magic_quotes_runtime()` 返回「关闭」值的空实现。
- **ext/mysql 残余**：`mysql_escape_string()` 委托 `vb_escape()`，`mysql_close()` 空实现。
- **错误处理器**：PHP 8 把「未定义变量 / 未定义数组键 / 字符串偏移 / Array to string
  conversion / `each()` 循环末尾」从 E_NOTICE 提升为 E_WARNING，而原代码使用
  `error_reporting(7)`（`E_ERROR|E_WARNING|E_PARSE`）刻意隐藏 notice。兼容层只吞掉
  这批被提升的告警，其余告警照常交给 PHP。
- **260 个裸数组键常量**：PHP 8 移除了「未定义常量回退为自身名字」的行为；而 vB 把
  PHP 代码存在数据库（模板、`setting.optioncode`）并在运行时 `eval()`，源码改写覆盖不到。
  兼容层对采集到的 260 个小写标识符执行 `define($k, $k)`，恢复 PHP 7 语义。
- **`vb_cast_request_ids()`**：见 4.7。
- **`vb_escape()`**：见 4.7。

### 4.2 全树自动改写（PHP tokenizer 驱动）

用 `token_get_all()` 逐 token 重建源码，保证除目标改动外字节不变：

| 改写 | 数量 | 说明 |
|---|---|---|
| `$HTTP_GET_VARS` 等 → `$_GET` / `$_POST` / `$_COOKIE` / `$_SERVER` / `$_ENV` / `$_FILES` | 472 | 长数组在 PHP 5.4 移除 |
| 代码上下文 `$arr[bareword]` → `$arr['bareword']` | **2967** | PHP 8 下裸常量是致命 `Error` |
| `<? echo` → `<?php echo` | 275 | 短标签默认关闭 |

**字符串插值中的 1625 处 `$arr[key]` 保持不变**（PHP 8 双引号插值仍合法，改写反而会
造成解析错误）。这一步由 token 状态机区分代码 / 字符串 / heredoc，避免误伤。

### 4.3 `admin/db_mysql.php`：`mysql_*` → `mysqli_*`

ext/mysql 在 PHP 7.0 移除。重写 `DB_Sql_vb` 类但**完全保留公开接口**
（`query` / `query_first` / `fetch_array` / `num_rows` / `num_fields` / `data_seek` /
`insert_id` / `free_result` / `close` / `halt` / `escape`），因此 1781 处调用零改动。

- 参数顺序反转：`mysqli_query($link, $sql)`、`mysqli_select_db($link, $db)`。
- `mysqli_fetch_array()` 默认 `MYSQLI_BOTH`，保留原 `mysql_fetch_array` 的数字+关联键语义。
- `mysqli_report(MYSQLI_REPORT_OFF)`：PHP 8.1 起 mysqli 默认抛 `mysqli_sql_exception`，
  必须关闭以恢复「返回 false + `halt()` 处理」的旧行为。
- 非 SELECT 语句返回 `true`：`fetch_array` / `num_rows` / `free_result` 等加了
  `instanceof mysqli_result` 保护，避免 TypeError。
- 连接失败时 `halt()` 若 `reporterror=0` 不退出，加了 `return false` 防止后续
  `mysqli_select_db(false, ...)` 抛 TypeError。
- `connect()` 成功后执行 `SET SESSION sql_mode=''`：MySQL 8 / MariaDB 默认
  `STRICT_TRANS_TABLES + NO_ZERO_DATE`，会拒绝 vB 2.x 的零日期默认值和省略列的 INSERT
  （实测报 1364）。
- 新增 `escape()` 方法。

### 4.4 三个引导层与 magic quotes

`global.php`、`admin/global.php`、`mod/global.php`：

- 删除 `$GLOBALS = stripslashesarray($GLOBALS);` —— **PHP 8.5 直接禁止整体赋值 `$GLOBALS`**，
  原为 magic quotes 反转逻辑，magic quotes 已在 PHP 5.4 移除，整段连同
  `stripslashesarray()` 一起删除。
- `set_magic_quotes_runtime(0)` 由兼容层空实现承接。
- 清掉上游移植遗留的调试代码：`d()` 函数、`define(d, ...)` 裸常量、`echo d`、
  `echo '__LINE__'.'__FUNCTION__'`、`var_dump($_SESSION)`、`admin/index.php` 的 `d();` 调用；
  安装脚本里的 `echo $step`、`var_dump($_POST)`、`echo "$bbtitle"...`。

### 4.5 其它 PHP 8 破坏点（逐处手工修复）

- `implode($array, $glue)` 反序参数 3 处 → 改为 `implode($glue, $array)`（PHP 8.0 移除反序写法）。
- `count(null)` 1 处 → 加 `is_array()` 保护。
- `calendar.php` 的 `${month.$MonthNum.name}` → `${'month'.$MonthNum.'name'}`（裸常量）。
- `admin/functions.php` 的 `PHPVERSION` → `PHP_VERSION`（未定义常量）。
- `admin/bbimport.php` 的 `readdir(false)`：给 `opendir` 结果加保护（PHP 8 抛 TypeError）。
- `admin/stats.php` 的 `strftime("%W", …)` → `date("W", …)`。
- 安装脚本 `TYPE=HEAP` → `ENGINE=MEMORY`（MySQL 5.5+ 移除 `TYPE=`）。
- 安装脚本 17 处自链接 `install.php` → `install4php5.php`（文件已改名）。
- `floor(phpversion())` / `floor($phpversionnum)` / `floor($phpversion)` 共 6 处 →
  显式 `(float)` 转换。`phpversion()` 返回 `"8.5.12"`，PHP 8 下 `floor("8.5.12")` 抛
  TypeError；这些位置在 `bbcodeparse2()`、`unhtmlspecialchars()`、`phphighlite()`、
  安装器里，属于可达路径。
- 8 处 `(double)` → `(float)`（等价写法，消除 PHP 8.5 弃用提示）。

### 4.6 安装器 / 升级脚本

`admin/install4php5.php` 与 `admin/installed.php`（两者内容相同）：改用 `$_GET['step']`
和 `$_POST[...]` 直接取表单值；其余 21 个 `upgrade*.php` 保持原样但已随全树改写。
所有独立入口都加载兼容层，因此不再依赖 `register_globals` / `register_long_arrays`。

### 4.7 SQL 转义加固（不改调用方式）

> 本次**未引入参数绑定**，1781 处 `query()` 调用签名一处未动。原因：约 1088 处 SQL
> 含变量，其中不少是动态表名/列名、变长 `IN (...)` 列表、安装器生成的 DDL，无法
> 参数化；全量绑定等于重写数据访问层。

- **735 处** SQL 上下文的 `addslashes(...)` → `vb_escape(...)`：
  使用 `mysqli_real_escape_string()`（按连接真实字符集转义），连接建立前回退
  `addslashes()`。`addslashes()` 在多字节字符集下可被绕过。
- **保留 12 处** `addslashes()`：它们是 PHP 字符串 / eval 转义而非 SQL
  （`gettemplate()`、`generateoptions()`、安装器生成 options 模板、`global.php` 的
  URL 处理、`admin/template.php` 模板导出、头像/图标/表情的文件名数组）。
  换成 `real_escape_string` 会多转义 `\n` / `\Z`，反而污染模板。
- `mysql_escape_string()` 兼容 shim 改为委托 `vb_escape()`。
- `vb_cast_request_ids()` 在三个 `global.php` 的 `extract()` 之后调用，把 42 个数值型
  请求参数（`forumid` / `threadid` / `perpage` / `startat` / …）强制 `intval()`。
  原因是 `WHERE forumid=$forumid`、`LIMIT $startat,$perpage` 这类**无引号插值**转义无效；
  仅处理「已设置且为标量」的变量，保留 `isset()` 语义，不影响 SQL 片段字符串。

### 4.8 登录态下的时区 TypeError 修复

`user.timezoneoffset` 是 VARCHAR，`admin` 默认值为 `''`。PHP 8 下 `'' != 0` 变为
**true**（PHP 7 为 false），于是进入 `global.php` 的时区分支，`abs('')` 抛 TypeError；
`vbdate()` 里的 `0 - ''` 同样抛 `Unsupported operand types`。带登录态访问任意页面都会挂
（匿名访问不触发，早期验证因此漏掉）。

- `admin/sessions.php`：在所有 `$bbuserinfo` 赋值路径结束后，单点规范化
  `$bbuserinfo['timezoneoffset'] = (float) ...`，覆盖 `abs()` / `vbdate()` /
  `calendar.php` / `member.php` 全部下游。
- `admin/functions.php::vbdate()`：偏移量与 `$timeoffset` 都显式转 float 再算术。

### 4.9 中文 / UTF-8 支持

vBulletin 2.3.11 是 `windows-1252`（单字节）时代的应用，模板里**没有任何 charset
声明**，后台 CP 与升级脚本则写死 `charset=windows-1252`。中文内容因此会乱码：

- 页面只靠 PHP `default_charset` 自动附加的 HTTP 头，浏览器可能按单字节编码猜测；
- 数据库表若为 `utf8mb3`（MySQL/MariaDB 的 `utf8` 别名，最多 3 字节），4 字节字符
  （emoji、部分 CJK 扩展）写入时被替换成 `U+FFFD`（`EF BF BD`），**且不可恢复**。

修复（源码层，两个站点树同步）：

- `global.php`：`@header("Content-type: text/html; charset=UTF-8")` 明确响应头；并在
  `$headinclude` 前置 `<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">`，
  双保险避免浏览器猜编码。
- `admin/db_mysql.php::connect()`：`mysqli_set_charset($link, 'utf8mb4')` 固定连接字符集。
- 全树 22 处 `charset=windows-1252` → `charset=UTF-8`（后台 CP 的 `cpheader()` 与
  21 个 `upgrade*.php`）。
- **已有数据库需自行转换**（源码改动不会改已存在的数据）：
  `ALTER TABLE <t> CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
  对所有表执行，并 `ALTER DATABASE ... CHARACTER SET utf8mb4`。转换前请先备份。
  **转换前必须先把两个二进制列改成 BLOB**（`attachment.filedata`、`customavatar.avatardata`），
  否则图片会被替换成 `?` 而损坏，详见 4.15。
  已转成 `U+FFFD` 的历史字符无法自动还原，需重新编辑对应帖子。

验证：连接字符集 `utf8mb4`；新增 emoji（`F0 9F 98 80`）与中文（`E6 B5 8B E8 AF 95`）
写入/读出字节完全一致，不再产生 `U+FFFD`；页面同时带 HTTP 头与 meta 的 UTF-8 声明。

### 4.10 中文发帖乱码：`censortext()` 按字节替换（真正的发布链路根因）

上面 4.9 只解决了「显示 / 存储」层；实际发帖时中文仍会乱码，根因在
`admin/functions.php::censortext()`（发主题、回帖、私信都会调用）：

```php
$text = str_replace(chr(173), '_', $text);   // 0xAD  soft hyphen
$text = str_replace(chr(160), '_', $text);   // 0xA0  NBSP
```

这是单字节（windows-1252）时代的写法：直接替换**裸字节** 0xAD / 0xA0。但在 UTF-8 下，
很多汉字的末字节恰好是 0xAD 或 0xA0 —— 例如 **「中」= `E4 B8 AD`**。于是「中」的
第三个字节被换成 `_`（0x5F），产生非法 UTF-8 序列 `E4 B8 5F`；随后：

- 标题经过 `htmlspecialchars($subject)`，PHP 8 默认的 `ENT_SUBSTITUTE` 把非法序列
  替换成 `U+FFFD`（`EF BF BD`）；
- 正文不经过 `htmlspecialchars()`，非法字节写入 MySQL 时被转成 `?`（0x3F）。

这解释了为什么「我再测试一下」完全正常（不含 0xAD/0xA0 字节），而含「中」的
标题/正文变成 `?_?` 或 `U+FFFD`。修复：改为匹配**完整的 UTF-8 两字节序列**：

```php
$text = str_replace("\xC2\xAD", '_', $text);   // U+00AD
$text = str_replace("\xC2\xA0", '_', $text);   // U+00A0
```

单测：`中文测试帖😀_中文` 经 `censortext()` 后字节完全一致、仍为合法 UTF-8，
且 soft hyphen / NBSP 依旧被替换为 `_`。端到端 HTTP 发帖（标题
`中文发帖测试_😀_中`）验证：库中为
`E4B8AD E69687 ... F09F9880 5F E4B8AD`，无 `U+FFFD`、中文与 emoji 均完整；
预览（Preview）路径同样无乱码且不写库。

> 注：早期验证曾用 SQL `CONCAT` 直接追加字符，绕过了 `censortext()` 等 PHP 处理链，
> 因此没暴露这个问题。以真实表单 POST 为准。

### 4.11 MySQL 8 的 JOIN 优先级：`Unknown column ... in 'ON'`

vB 2.3.11 大量使用「逗号隐式连接 + `LEFT JOIN`」混写，例如 `sendnotification()`：

```sql
SELECT user.*, style.templatesetid
FROM subscribethread,user,usergroup
LEFT JOIN style ON (IF(user.styleid=0, 1, user.styleid)=style.styleid)
WHERE ...
```

旧 MySQL（3.x/4.x）把整串逗号表当作 `LEFT JOIN` 的左表；**MySQL 5.0+ / 8 / MariaDB
按 SQL 标准让 `JOIN` 的优先级高于逗号运算符**，上式被解析为
`subscribethread, user, (usergroup LEFT JOIN style ON ...)`，于是 `ON` 里的
`user.styleid` 属于尚未参与该 JOIN 的表 → `ERROR 1054 (42S22): Unknown column
'user.styleid' in 'ON'`。该查询由 `newreply.php` 回复时的 `sendnotification()` 调用，
所以一回复就报错。

修复：全部改为**显式 JOIN**，并把 `WHERE` 里的表间连接条件移到 `ON`：

```sql
FROM subscribethread
JOIN user ON subscribethread.userid = user.userid
JOIN usergroup ON usergroup.usergroupid = user.usergroupid
LEFT JOIN style ON (IF(user.styleid=0, 1, user.styleid)=style.styleid)
WHERE ...
```

共修 6 处：

| 文件 | 触发路径 |
|---|---|
| `admin/functions.php` `sendnotification()` | 回复 / 版主操作后的订阅邮件通知（用户报告的报错） |
| `usercp.php` | 用户 CP 私信列表（实测报 `privatemessage.iconid`） |
| `private.php` | 查看私信（开启头像时） |
| `admin/misc.php` | 后台重建帖数 / 附件计数 |
| `search.php`（2 处） | 搜索结果按主题 / 按帖子显示 |

`search.php` 里可能出现的 `,searchindex` 相应改为 `CROSS JOIN searchindex`，
保证仍在 JOIN 链内、后续 `LEFT JOIN` 的 `ON` 能引用前面的表。
`admin/thread.php` 两处 `FROM post,thread LEFT JOIN forum USING (forumid)` 因
`USING` 不写表前缀、不会触发「未知列」，保持原样（结果集语义等价）。

验证：6 条改写后的 SQL 全部在 MariaDB 13 上解析执行成功；真实 HTTP 回复发帖成功
（无 `Unknown column` / `mysql error`，中文正文完整）；`usercp.php` 不再报错；
多种搜索词、3 种登录态 × 15 页面回归 0 错误。

### 4.12 PHP 7 移除的 `/e` 修饰符（发帖转跳页的 `Unknown modifier 'e'`）

`admin/functions.php` 有两处依赖 `preg_replace` 的 `/e` 修饰符（PHP 7.0 已移除）：

1. `unhtmlspecialchars()`：用 `/esiU` 的 `preg_replace` 把数字 HTML 实体转成字符。
   发帖 / 回帖成功后的转跳页会调用它来还原用户名等字段，于是页面直接打印
   `Warning: preg_replace(): Unknown modifier 'e'`，并且返回值变成 `null`。
2. `bbcodeparse2()`：PHP4+ 分支的 6 条 BBCode 规则用 `/esiU`，替换串是 PHP 表达式
   （`createlists(...)` / `checkurl(...)` / `stripbrsfromcode(...)` / `phphighlite(...)`）。
   一旦出现 `[url]` / `[list]` / `[code]` / `[php]`，整条 `preg_replace` 失败并返回
   `null`，正文会被清空。

修复：

- 6 条规则去掉 `/e`，改为 `preg_replace_callback` + 显式回调数组
  `$vb_bbcode_callbacks`（索引 0–5 对应原来的 6 个表达式），其余规则仍走普通
  `preg_replace`；整体按原顺序逐条应用。
- `unhtmlspecialchars()` 改为 `preg_replace_callback('/(&#)([0-9]*)(;)/iU', ...)`。
- 新增 `vb_chr()`：优先 `mb_chr($cp, 'UTF-8')`，无 mbstring 时手工做 UTF-8 编码。
  原来的 `chr()` 只产生单字节，无法表示 `&#20013;`（U+4E2D 中）。

验证：`unhtmlspecialchars('&#20013;&#25991;')` → `中文`；
`[url]` / `[url=...]` / `[list]` / `[code]` / `[php]` / `[b]` / `[email]` 均正确渲染；
真实 HTTP 回帖的转跳页与主题页 0 条 `Unknown modifier` / `Warning`；
3 种登录态 × 17 页面回归 0 错误。

### 4.13 界面文案抽取与中英切换（i18n）

vBulletin 2.3 没有语言层：界面文字散落在 488 个数据库模板和 PHP 代码里。本版新增一套**不改模板、不改表结构**的语言层。

| 文件 | 作用 |
|---|---|
| `admin/lang_en.php` | 由工具自动抽取的**全量清单**（897 条，键=英文原文，值=英文） |
| `admin/lang_zh.php` | 中文词表（966 条）：897 条模板文案全部有译文，另有 PHP 侧短语与日期词 |
| `admin/lang.php` | 加载器：cookie 选择、`vbphrase()`、模板翻译、`?lang=` 切换 |
| `tools/extract_lang.php` | 重新扫描全部模板，生成 `lang_en.php`（新增文案后可再跑） |

**工作方式**

- 语言来自 cookie `bblanguage`（`en` / `zh`），默认 `en`；也可在 `admin/config.php` 设 `$defaultlanguage`。
- `?lang=zh` / `?lang=en` 设置一年期 cookie 并 302 回原地址（保留其余查询参数）；页脚有「中文 | English」切换条。
- `gettemplate()` 在 **模板源码** 上做翻译：只替换整段文本节点和 `value` / `alt` / `title` / `placeholder` 属性；`<script>`、`<style>`、`<textarea>`、注释被保护。
  **因为翻译发生在 eval 之前，帖子、私信、签名等用户内容永远不会被改写**。
- 含 `$变量` 的模板（如 `All times are GMT. The time now is $timenow.`）会按 `$var` 切成静态片段逐段翻译。
- PHP 侧文案用 `vbphrase('English')`；`vbdate()` 输出的月份/星期名也会翻译。

**兼容 vB 2.3 原有逻辑**

- 不修改任何数据库模板，`vbulletin.style` 也不变，升级/重装不受影响。
- en 语言返回空映射，渲染路径与原来完全一致。
- 只新增 4 个文件 + 3 处钩子（`functions.php` 加载、`gettemplate()` 翻译、`global.php` 切换）。

**覆盖率**：模板侧抽取的 **897 条已全部翻译**（导航、版块/主题、发帖表单、分页、时区/日期、账号、搜索、日历、FAQ、常见错误与跳转提示）；只有 11 条字体名/代码示例/空白保留原样。语言包共 1000+ 条，另含后台 CP 的导航、登录与常用按钮（见下）。新增文案只需重跑 `tools/extract_lang.php` 并在 `lang_zh.php` 补一行。

**后台 CP**：`admin/global.php` 同样处理 `?lang=`（保留 `s=` 等参数），导航栏底部和登录页都有「中文 | English」切换。CP 页面是纯 PHP 输出、不走 `gettemplate()`，因此非英文时用 `ob_start()` + `register_shutdown_function` 对整页做一次文本节点替换，`action=nav` / `action=home` 这类不调用 `cpfooter()` 的 frame 也能翻译。**英文模式不启用缓冲，行为与原版完全一致。** 导航、登录、常用按钮/表头来自 `lang_zh.php`；**设置页帮助文本**（`setting.description`，155 条 / 约 24 KB）单独放在 `admin/lang_zh_settings.php`，按 `setting.varname` 映射，由 `vbsetting_desc()` 在 `admin/options.php` 渲染时替换。编辑页 `admin/setting.php` 刻意不做替换，避免把中文写回数据库。其余设置页的长描述可按同样方式继续补充。

验证：`?lang=zh` 切换后 cookie 生效、页面出现中文；切回 `?lang=en` 完全恢复英文；
12 个页面 × 2 种语言均 0 PHP 警告；`php -l` 全树 0 失败；英文页面的中文仅来自用户数据与切换条。

### 4.14 页面与模板加载时间

页面底部新增一行 10px 灰色计时信息（居中，位于 `</body>` 之前）：

```
Page load: 0.0138 s | Template load: 0.0019 s
页面加载：0.0209 秒 ｜ 模板加载：0.0037 秒
```

- **页面加载**：从 `admin/php8compat.php` 第一行（所有入口最先加载的文件）记 `$vb_pagestart`，
  到 `dovars()` 输出前计算总耗时。
- **模板加载**：在 `cachetemplates()`（批量取模板）和 `gettemplate()`（单模板查询 + 语言翻译）
  内累计到 `$vb_templatetime`。
- 输出点：`dovars()` 在 gzip 与 `Content-Length` 之前注入，保证压缩后长度正确；后台 CP 由 `cpfooter()` 输出。
- 文案走 `vbphrase()`，中英自动切换；无模板/数据库改动。

### 4.15 附件/头像二进制列（utf8mb4 转换的坑）

`attachment.filedata` 与 `customavatar.avatardata` 在 vB 2.3 里是 `mediumtext`。MySQL 4 时代用 latin1，
二进制文件按字节存进 text 列没有问题；但 `CONVERT TO CHARACTER SET utf8mb4` 后，列会按 UTF-8 校验，
文件中的非法字节被逐个替换成 `?`（`0x3F`）。

实测：一个 77,404 字节的 PNG，**33,804 字节被替换**，首字节 `89` 变成 `3F`，签名从
`89504E470D0A1A0A` 变成 `3F504E470D0A1A0A`，浏览器因此提示「图片存在错误」。

修复：

```sql
ALTER TABLE attachment   MODIFY filedata   LONGBLOB NOT NULL;
ALTER TABLE customavatar MODIFY avatardata LONGBLOB NOT NULL;
```

安装器已同步为 `longblob`。BLOB 列不参与字符集转换，后续再 `CONVERT TO CHARACTER SET` 也不会破坏它。

注意：**已经损坏的附件无法恢复**，需要在帖子里重新上传。修复后做过逐字节验证：
源文件、库中 `filedata`、`attachment.php` 下载三者 md5 一致，`file` 识别为 `PNG image data, 64 x 64`。

**默认内联显示图片**：vB 2.3 默认把附件图片渲染成下载链接。要默认显示，需要两处同时为 1：

- `setting.viewattachedimages = 1`（附件图片内联）
- `forum.allowimages = 1`（版块允许 `[img]`）

并且要同步到**编译后的 options 模板**（`template.title='options'` 里的 `$viewattachedimages = "1";`）——
只改 `setting` 表运行时不生效。安装器已默认开启这两项。

存量站点（不是用本版安装器新建的库）需要自己同步，否则只有新装库才生效：

```sql
UPDATE setting SET value='1' WHERE varname IN ('viewattachedimages');
UPDATE forum SET allowimages='1' WHERE forumid IN (1,2);
```

### 4.16 i18n 层与运维入口的加固

这一轮修的是语言层自身的边界问题，不涉及业务逻辑。

**`tools/extract_lang.php` 改为 CLI-only**
这个文件原本放在仓库根目录的 `tools/` 下，无鉴权、硬编码 `root`/空密码，任何人对它发一次
HTTP 请求就会连库并**覆盖写 `admin/lang_en.php`**。现在：

- 开头 `if (PHP_SAPI !== 'cli')` 直接返回 404 并退出；
- 连接参数改为 `require admin/config.php`，与站点共用同一份配置，工具里不再有第二份凭据；
- 顺手修了剥离 `<script>` 块的正则（原文件里 `<script\b[^>]*>` 被漏成 `]*>`，导致脚本内
  字符串被当成界面文案抽取，`lang_en.php` 里因此混入过 `<script language="JavaScript"`）。

部署时 `tools/` 仍应和安装脚本一样禁止 Web 访问（见第 5 节）。

**后台 CP 的整页翻译**
后台页面是纯 PHP `echo`，没有模板，所以 i18n 靠 `ob_start()` 缓冲 + `register_shutdown_function`
在输出末尾整体翻译。三处修正：

- 翻译时传 `vblang_template($html, false)`，**不再翻译 `value` / `alt` / `title` / `placeholder`
  属性**。后台全是表单，翻译控件 value 会让提交回库的数据变成中文；
- 非英文语言下强制 `$nozip = 1`。`cpheader()`/`cpfooter()` 自己会开一层 ob 做 gzip，套在这层
  缓冲外面时，压缩后的字节会被当成页面交给翻译器，输出损坏；
- shutdown 里先 `while (ob_get_level() > 1) ob_end_flush()` 把嵌套缓冲收拢，再取内容，避免
  只清掉一层导致内容丢失。

**切换条位置**
原来 `$footer = $vblangbar . $footer;` 把 `div` 塞在 footer 模板（自带 table 结构）之前，容易
顶出错位横条。改为 `$footer .= $vblangbar;` 并加 `clear:both`，落在 footer 之后。

**`?lang=` 的跳转归一化**
`parse_url($_SERVER['REQUEST_URI'])` 取出的 path 在 `REQUEST_URI` 以 `//` 开头时是 `//host`，
直接进 `Location:` 会被浏览器当作协议相对地址跳到站外。新增 `vb_lang_localpath()`，统一
`'/' . ltrim($path, '/')` 后再拼接，`vb_lang_url()` 与 `vb_lang_switch()` 都用它。

**`vblang_date()` 改为单次正则**
原来对 36 个月份/星期词各跑一次 `preg_replace`，帖子列表页一页要执行上千次；现在按最长优先
合成一条 `\b(...)\b` 正则，一次 `preg_replace_callback` 完成。改回调还有一个作用：译文里若含
`$1` 或反斜杠，不会被当成反向引用解析。

**语言包文案细节**

- 月份统一为阿拉伯数字（`January` → `1月` … `December` → `12月`）。原来全称用中文数字、缩写
  用数字，`May` 既是全称又是缩写只能取一个，同一页面会混排；
- 统计/列表列头名词化：`Views` → `查看数`、`Replies` → `回复数`、`Threads:` → `主题数：`、
  `Posts:` → `帖子数：`。

**设置项的标题与组名**
上一轮只翻译了设置项的帮助文本（`setting.description`），列表里每个设置项的粗体标题、
以及左侧的 31 个设置组名仍是英文，整页是中英混排。这些文案来自安装器而不是模板，
`tools/extract_lang.php` 抽不到，所以单独放在 `admin/lang_zh_titles.php`：

```php
return array(
    'setting' => array('bbactive' => '论坛是否开放', ...),  // key = setting.varname
    'group'   => array('General Settings' => '常规设置', ...), // key = settinggroup.title
);
```

由 `vbsetting_title()` / `vbsettinggroup_title()` 在 `admin/options.php`（分组目录、分组表头、
设置行标题）与 `admin/setting.php`（设置管理列表）里取用。

两项实现上的选择：

- **按 `varname` 而不是按英文原文做键**。同一个英文标题会出现在不同设置上（例如头像和附件
  都有 `Maximum File Size`），按原文做键只能给一个译文；
- **不并入 `vb_lang_map()`**。一旦并进去，前台模板里任何恰好叫 `URL`、`Refresh` 的文本也会被
  替换成后台语境下的译文（`URL` → `论坛 URL`），语义就跑偏了。

覆盖情况：171 个设置项标题、31 个设置组名全部有译文（脚本按安装器的 `INSERT` 逐条断言，
缺一条就报错退出）。

### 4.17 PostgreSQL 数据库支持

vB2 的所有 SQL 都走 `admin/db_mysql.php` 里的那一个类（15 个方法），所以加 PG 支持不需要动
业务代码：新增 **`admin/db_pgsql.php`**，用同名类 `DB_Sql_vb` 实现同一套接口，把 MySQL 方言
在这一层翻译掉。

**启用方式**（`admin/config.php`）：

```php
$dbservertype = 'pgsql';
$dbport       = 5432;          // 留空则用默认端口
$dbschema     = 'public';      // 表所在 schema，留空即 public
```

`global.php` / `admin/global.php` / 安装器都会把 `$dbport`、`$dbschema` 传给 DB 对象；不填则
沿用原来的 MySQL 行为，无需改动现有部署。

**接口层**：`connect / geterrdesc / geterrno / escape / select_db / query / fetch_array /
free_result / query_first / data_seek / num_rows / num_fields / insert_id / close / halt`
与 MySQL 版逐一对应。几点与 MySQL 不同的实现：

- `escape()` 用 `pg_escape_string()`，连接上固定 `standard_conforming_strings=on`；
  `php8compat.php` 的 `vb_escape()` 改为调用 `$DB_site->escape()`，不再硬编码 `mysqli`，
  两个驱动共用一条转义路径。
- `insert_id()` 用 `lastval()`。前提是自增列建成了 `serial` / `bigserial`（见下面的 DDL 转换），
  且 INSERT 没有显式指定该列的值。
- `halt()` 的报错页把 `mysql error` 换成 `pgsql error`，并同时打印原始 SQL 与翻译后的 SQL，
  便于定位是哪条改写出的问题。

**SQL 方言翻译**（`translate()`，只作用于代码部分，字符串字面量不动）：

| MySQL 写法 | 出现次数 | 翻译为 |
|---|---|---|
| `LIMIT offset,count` | 会员列表、搜索、帖子列表等 | `LIMIT count OFFSET offset` |
| `IF(cond,a,b)` | 14 | `(CASE WHEN cond THEN a ELSE b END)`，支持嵌套 |
| `ISNULL(x)` | 2 | `(x IS NULL)` |
| `INSTR(s,sub)` | 51（后台用户/主题管理、`member.php`、`search.php` 等） | `STRPOS(s,sub)`，同参数、同 1 起始下标 |
| `UNIX_TIMESTAMP([date])` | 43 | `EXTRACT(EPOCH FROM ...)::bigint`，无参用 `now()` |
| `FROM_UNIXTIME(ts[,fmt])` | 20 | `to_timestamp(ts)`，带格式则走 `to_char` + 格式码映射 |
| `REPLACE INTO` | 3 | `INSERT ... ON CONFLICT (...) DO UPDATE`，冲突列**运行时**查 `information_schema`（见下） |
| `INSERT ... VALUES (NULL, ...)` | 安装器 + 建表后插入 | 自增列的 `NULL` 改成 `DEFAULT`（PG 不接受显式 NULL） |
| `LIKE` | 模板/搜索 | `ILIKE`（MySQL 那套 ci 排序规则是大小写不敏感的） |
| `FROM a,b,c LEFT JOIN d ON (…a.列…)` | `functions.php:2544` 订阅通知等 | 逗号列表改写成 `CROSS JOIN`（见下） |
| 字符串里的 `\'` / `\\` | 安装器、`addslashes` 产物 | 反转义后按 PG 规则双写单引号（`'Who\'s'` → `'Who''s'`） |
| `SHOW TABLES` / `SHOW FIELDS` / `SHOW KEYS` | 安装器、`admin/backup.php` | 查 `pg_tables` / `information_schema`，列名按 MySQL 的名字起别名 |
| `LOW_PRIORITY` / `HIGH_PRIORITY` / `DELAYED` | 主题浏览量、附件下载数、会话活跃时间等关机查询 | 直接删除（见 4.17.3） |
| 表名 `user` | 全站 | `"user"`（`USER` 在 PG 里是保留字，且裸写会被当成 `current_user`） |

两处改写的来由值得单独记一笔，它们都是**真机跑出来的**，不是预想的：

- **逗号 FROM + JOIN**：`SELECT … FROM subscribethread,user,usergroup LEFT JOIN style ON
  (IF(user.styleid=0,1,user.styleid)=style.styleid)`。MySQL 允许 `ON` 里引用逗号列表中的表，
  PG 把 `c LEFT JOIN d` 解析成 FROM 列表的第三项，于是 `ON` 看不见 `user`，报
  `invalid reference to FROM-clause entry`。`rewrite_from_joins()` 把 FROM 段的顶层逗号
  改成 `CROSS JOIN` 后语义与 MySQL 一致（两者本来也不构成外连接条件）。
- **`REPLACE INTO` 的冲突列**：原来写死一张 `$primary_keys` 表，`searchindex` 在 MySQL 里
  **根本没有主键**（只有三列 `DEFAULT '0' NOT NULL`），MySQL 的 REPLACE 于是退化成普通
  INSERT；写死主键会让 `ON CONFLICT` 报 `there is no unique or exclusion constraint`。
  现在 `conflict_target()` 查 `information_schema.table_constraints`，取 PRIMARY KEY，
  没有就取第一个 UNIQUE，都没有就退化成纯 `INSERT`。

**建表（DDL）转换**：安装器的 schema 是 MySQL `CREATE TABLE` 字符串，PG 跑不了。
`db_pgsql.php` 提供 `convert_ddl()`，安装器的 `doqueries()` 检测到该方法后按「一条 MySQL 语句 →
若干条 PG 语句」执行：

- `int(10) unsigned` → `bigint`（MySQL unsigned int 上限 4294967295，超出 PG `integer`）；
  `smallint/tinyint` → `smallint`，unsigned 的 `smallint` → `integer`
- `text/mediumtext/longtext` → `text`，`blob` 系列 → `bytea`，`enum(...)` → `varchar(64)`
- **`date` / `datetime` → `varchar(19)`**：vB2 全程把日期当字符串用（生日存空串、写
  `'0000-00-00'`、用 `LIKE '%-11-05'` 匹配），MySQL 的 date 列容忍这些，PG 不容忍
  （`operator does not exist: date ~~* unknown`）。全库只有 `user.birthday` 与
  `calendar_events.eventdate` 两列，保留字面值比改业务代码划算，ISO 字符串的大小比较
  也与日期比较同序。
- `auto_increment` → `serial` / `bigserial`，`NOT NULL` 去掉（serial 隐含非空）
- 数值列的 `DEFAULT '0'` 去掉引号；真 date/timestamp 列的 `DEFAULT '0000-00-00'` → 可空
- **`NOT NULL` 且无默认值的列补 `DEFAULT 0` / `DEFAULT ''`**：MySQL 历史非严格模式会替
  省略的列补空值，PG 直接报 not-null 违规（`INSERT INTO bbcode VALUES ('…')` 就踩在这里）
- `KEY name (cols)` / `UNIQUE KEY` / 无名字的 `INDEX (cols)` 拆成独立的 `CREATE INDEX`，
  **索引名加表名前缀**（PG 的索引名是全 schema 唯一，`CREATE INDEX userid ON post` 与
  `ON thread` 会撞名），前缀长度 `col(10)` 去掉（PG 不支持）

安装器本身不用改流程：`$dbservertype` 由表单填写，填 `pgsql` 即走新驱动；数据库连不上时的
「自动建库」分支保留 MySQL 的 `errno==1049` 判断，PG 侧请先手工 `createdb`。

**真机验证**：PG 支持已在源码编译的 PostgreSQL **18.6** + PHP **8.5.0**（`pgsql`/`pdo_pgsql`/
`mysqli`/`gd`）上跑通全流程，不是只在静态检查层面通过：

- 98 个 PHP 文件 `php -l` 全通过；安装器 `install4php5.php` 从 step 1 走到 step 10
  「You have now completed the install」，建表 **40 张 / 64 条语句 / 0 失败**，
  灌入模板 487、设置 171、设置组 31、替换集 43、样式 1，并建出管理员账号。
- 前台 `index.php`、`forumdisplay.php`、`showthread.php`、`memberlist.php`、`calendar.php`、
  `online.php`、`search.php`、`register.php`、`faq.php`、`misc.php?action=bbcode` 全部 200、
  无 SQL 错误；侧栏「最新主题」、版块列表、帖子正文、在线列表均正常出数。
- 19 项功能用例（自增 `NULL`、`insert_id`、`UNIX_TIMESTAMP`、`LIMIT 0,5`、`LIKE 'ALI%'`、
  `REPLACE INTO` 两次 upsert、`REPLACE ... SELECT`、三种 `SHOW`、`query_first`、`data_seek`）
  在空库上全绿。

**仍然存在的缺口**：

1. **本仓库的源码是裁剪过的**，没有 `login.php`，模板里也没有 `login` 模板，所以
   *登录、发帖、回帖、后台管理* 这些需要身份的操作无法在本仓库上端到端验证；PG 驱动本身
   没有挡住这些路径的 SQL，缺的是文件。
2. **`GROUP BY` 严格模式**：PG 要求 SELECT 里所有非聚合列都出现在 `GROUP BY`，MySQL 不要求。
   已跑过的页面没有触发，但论坛统计、日历、版块聚合这类查询没有逐条排查。
3. **数字列与非数字字符串比较**：`WHERE userid=''` 这类已在驱动层修掉（`rewrite_empty_numeric()`，
   见 4.17.2），但把数字列和 `'abc'` 这种非空串比较仍会报 `invalid input syntax`。代码里没见到
   这种写法，未逐个排查。
4. 全文检索 / `searchindex` 的分词逻辑未按 PG 调整（`REPLACE INTO` 在该表上按无主键处理，
   退化成了普通 INSERT，与 MySQL 行为一致）。
5. 排序与大小写：`ORDER BY` 在 MySQL 默认 ci、PG 按字节序，中文/英文混排顺序会不一致。

#### 4.17.1 备份导出（`admin/backup.php`）

导出格式**跟随当前配置的数据库自动切换**，管理员不需要做任何选择：MySQL 上仍是原来的
MySQL dump，PostgreSQL 上输出可直接 `psql -f` 导入的 PG dump。两套写入器都在
`admin/backup_dump.php`，页面侧只调用 `sqldumptable()`，由 `backup_is_pgsql()` 分派。

PG 侧的 schema 不从 `SHOW FIELDS` 拼（那个 shim 只给得出 PG 类型名），而是查 `pg_catalog`：

- 列按 `attnum` 顺序取 `format_type`、`attnotnull`、`pg_get_expr(默认值)`，
  `nextval(...)` 默认值的列还原成 `serial` / `bigserial`，表重建时序列一起回来
- `PRIMARY KEY` / `UNIQUE` 用 `pg_get_constraintdef` 原样写进 `CREATE TABLE`
- 其余索引用 `pg_get_indexdef` 单独输出 `CREATE INDEX`（约束已带的索引跳过）
- 数据用 `INSERT INTO ... (列清单) VALUES (...)`，列清单显式写死，列顺序变化也能导回；
  `bytea` 走 `decode('hex','hex')`，避免反斜杠交给字符串字面量
- 每个 serial 列在表末尾补 `setval(seq, MAX(col))`，还原后自增不会从 1 重来
- 文件头带 `SET client_encoding = 'UTF8'` 与 `SET standard_conforming_strings = on`

真机验证（PostgreSQL 18.6）：页面「SQL Dump」导出 40 张表、614 KB，`psql` 导入空库
**0 报错**、表数 40、模板/设置/设置组/替换集/用户行数与源库逐一相同，还原库 `index.php`
正常出页（200，内容与源库一致）；自增序列续接正确。「保存到服务器文件」与 CSV 导出同样跑通。

#### 4.17.2 数字列与空串比较（`rewrite_empty_numeric()`）

vB2 里大量 `WHERE userid=''` 的写法（会话查不到人时 `$bbuserinfo[userid]` 就是空串，
`usercp.php`、`member.php` 都会拼出这种条件）。MySQL 会把 `''` 当 0 比较，所以条件等价于
`userid=0`；PostgreSQL 直接报 `invalid input syntax for type bigint: ""` 把整页打挂。

驱动在 `translate()` 里改写这种比较，**只对确实是数字类型的列生效**：

- 列类型来自 `information_schema.columns`（`column_info()` 已经按表缓存，每页只查一次）
- 表/别名从语句的 `FROM`、`JOIN`、`UPDATE` 里解析；同一列在多张表里类型不一致时不动
- `col = ''` → `col = 0`，`<> ''` / `!= ''` → `<> 0`，`> ''` → `> 0`，`>= ''` → `>= 0`，
  `< ''` → `< 0`，`<= ''` → `<= 0`，`<=> ''` → `= 0`（与 MySQL 的隐式转换等价）
- 文本列**不改**：`username=''` 仍然匹配空字符串（驱动靠类型判断区分，不是靠列名猜）
- 匹配只在代码位置生效，字符串字面量里的 `=` 不会被误改

真机验证（PostgreSQL 18.6）：改前 `usercp.php?s=` 直接返回 Database error
（`invalid input syntax for type bigint: ""`）；改后 `usercp.php`、`member.php`、
`memberlist.php`、`private.php`、`search.php`、`online.php`、`calendar.php`、
`index.php`、`forumdisplay.php`、`showthread.php`、`register.php` 全部 200 且无 SQL 报错，
文本列 `=''` 的语义与原来一致。

#### 4.17.3 语句修饰符（`rewrite_priority_modifiers()`）

vB2 把大量计数写成 MySQL 专有的**语句修饰符**形式，典型是关机查询：

```sql
UPDATE LOW_PRIORITY thread SET views=views+1 WHERE threadid='3'      -- showthread.php
UPDATE LOW_PRIORITY attachment SET counter=counter+1 WHERE ...       -- attachment.php
UPDATE LOW_PRIORITY session SET lastactivity=... WHERE sessionhash='...'  -- admin/sessions.php
```

这些修饰符必须紧跟在动词后面（`UPDATE LOW_PRIORITY t`、`INSERT DELAYED INTO t`）。PG 没有
对应的关键字，会把 `LOW_PRIORITY` 当成表名，报
`relation "low_priority" does not exist`。因为它们是**关机查询**（`$shutdownqueries`，
在页面渲染完之后才执行），报错不体现在页面上——页面照常 200，只是**浏览量、附件下载数、
在线状态时间这些计数一直不增长**，日志里每次访问留一条 ERROR。

修法是在 `translate()` 最前面删掉这个修饰符：`LOW_PRIORITY` / `HIGH_PRIORITY` 对
`INSERT` / `REPLACE` / `UPDATE` / `DELETE` / `SELECT` 都合法；`DELAYED` 只对
`INSERT` / `REPLACE` 处理，避免把名叫 `delayed` 的列误删。删掉不改变语义：这些关键字
只是让 MySQL 把写操作推迟/降优先级，结果集完全一样。

真机验证（PostgreSQL 18.6）：改前每次 `showthread.php` / `attachment.php` 都留下
`relation "low_priority" does not exist`；改后访问同一主题，`thread.views` 7 → 8、
`attachment.counter` 3 → 4，日志不再出现该报错。

#### 4.17.4 后台切换数据库（`admin/dbsettings.php`）

后台 **Import & Maintenance → Database Settings** 可以选 MySQL 还是 PostgreSQL，不用再手改
`admin/config.php`。选型必须落在文件里而不是库里——要读库里的设置，得先知道该连哪个库。

字段：数据库类型、主机、端口、schema（仅 PG）、用户名、密码、库名、持久连接。页面显示的
当前值直接解析自 `admin/config.php` 本身，所以看到的一定是下一个请求会用的值。

保存流程是**先测后写**：

- `Test connection` 用提交的参数新建一条连接：PG 侧报版本、schema 和该 schema 的表数，
  MySQL 侧报版本和表数；两边都会检查 `user` / `setting`（MySQL 再查 `forum`）这些核心表在不在
- 连不通就只显示错误，**不写配置**（PHP 8.1+ 的 mysqli 默认抛异常，页面里用
  `mysqli_report(MYSQLI_REPORT_OFF)` 关掉，错误以文本呈现而不是 Fatal error）
- `Save settings` 才改写 `admin/config.php`：只替换这 8 个 `$db*` / `$servername` 赋值，其余内容
  （`$technicalemail`、`$canviewadminlog` 等）原样保留；旧写法 `$dbport = 5433;`（不带引号）也能识别，
  统一重写成规范形式
- 改写前先 `copy` 成 `config.php.bak.YYYYMMDD-HHMMSS`；写之前用
  `token_get_all($text, TOKEN_PARSE)` 校验生成结果是合法 PHP，不合法则放弃并保留原文件
- 保存后下一个请求即生效，不必重启（开了 opcache 的 FPM 建议 reload 一次）

代价与回退：新配置连不上时前台会打不开，把最近的 `config.php.bak.*` 覆盖回 `config.php` 即可，
页面上也写了这句。只点 `Save settings` 不会迁移数据——目标库得先有 vB 的表；要让页面自己把
数据搬过去，用 4.17.7 的 `Copy data to this database and switch`。

真机验证（PG 18.6 站点 + PHP 8.5）：页面正确列出当前 PG 配置；`Test connection` 对
`host=/tmp port=5433 dbname=vb2311 schema=public` 报“已连接 PostgreSQL 18.6、40 张表”；
库名写错报连接失败；`Save settings` 成功改写并留备份，改写后 `php -l` 通过、站点 200；
改到 `dbservertype=mysql`（本机无 MySQL）时给出 `MySQL 连接失败：Connection refused`，
配置未被写入，站点仍 200。

#### 4.17.5 二进制附件与 `escape()`（`is_binary()`）

发帖带附件时 `newreply.php` 会崩在 `pg_escape_string()`：

```
Fatal error: Uncaught ValueError: pg_escape_string(): Argument #2 ($string)
Escaping string failed in admin/db_pgsql.php
```

原因是 `acceptupload()` 把上传的图片原样交给 `vb_escape()` → `escape()`，而
`pg_escape_string()` 只接受合法的 UTF-8 文本；PNG 的字节（`\x89`、`\0`）一进去 PHP 8 直接抛
`ValueError`（PHP 5/7 只是 warning，所以老版本没暴露）。

`escape()` 现在先判类型，两条路分开走：

- **二进制**（含 NUL 字节，或 `mb_check_encoding(..., 'UTF-8')` 为假）→ 返回
  `\\x<bin2hex>`：`rewrite_string_literals()` 会把 `\\` 还原成 `\`，语句里最终是
  `'\x89504e47...'`，正好是 PostgreSQL 的 bytea 十六进制字面量
- **文本** → 仍是 `pg_escape_string()`，但把结果里的反斜杠再翻倍一次。因为连接里
  `standard_conforming_strings=on`，`pg_escape_string()` 只翻倍单引号、不动反斜杠，
  而 `rewrite_string_literals()` 会把裸 `\x` 当成 MySQL 转义吃掉一个字符——翻倍后
  它正好吃到那一对 `\\`、还原出一个 `\`

真机验证（PG 18.6）：`vb_escape()` 处理 1483 字节的 PNG → 写入 `attachment.filedata`
（bytea）→ `octet_length` 1483、读回逐字节相同；`WHERE filedata='<同值>'`（acceptupload
的重复检测）能命中自己；`attachment.php` 取出后 `file` 认作 `PNG image data, 48 x 48`、
`cmp` 与源文件一致。文本侧 `中文'quote"反斜杠\结尾`、emoji、`%` 与 `_` 五种样例进出等长等值。

#### 4.17.6 `DISTINCT` 与 `ORDER BY` 的列不匹配（`rewrite_distinct_order()`）

`search.php`（`showposts=0` 的“只显示主题”模式）会生成这种语句：

```sql
SELECT DISTINCT thread.threadid FROM thread JOIN forum ON thread.forumid=forum.forumid
 WHERE thread.visible=1
 ORDER BY forum.title DESC, thread.lastpost DESC LIMIT 0,10
```

MySQL 允许 `ORDER BY` 用没出现在 select 列表里的列；PostgreSQL 直接拒绝：
`for SELECT DISTINCT, ORDER BY expressions must appear in select list`。按 `title` / `forum` 排序时都能复现。

修法是**把原语句包进一层子查询**，结果列不变：

```sql
SELECT "threadid" FROM (
  SELECT DISTINCT thread.threadid, forum.title AS _vb_order1, thread.lastpost AS _vb_order2
   FROM thread JOIN forum … WHERE thread.visible=1
) AS "_vb_distinct" ORDER BY "_vb_order1" DESC, "_vb_order2" DESC LIMIT 0,10
```

- 内层：保留 `DISTINCT`，把缺失的排序表达式补进 select 列表并起名 `_vb_orderN`
- 外层：按原始输出列名投影回去，`ORDER BY` 改用 `_vb_orderN`，`LIMIT` 留在外层
- 补进去的排序列与去重列是同一行的依赖字段（`thread.lastpost` 依赖 `threadid`、`forum.title` 依赖 `forumid`），去重后的行数不变
- 只要 `ORDER BY` 的每个表达式都能在 select 列表里找到（比较时忽略大小写与空白），就**原样放行**，不套子查询
- 解析不了 select 项（比如 `COUNT(*)` 这类无法用列名再投影的表达式）时也原样放行，宁可让 PG 报错也不乱改

真机验证（PG 18.6）：用修复前的 `db_pgsql.php` 访问
`search.php?action=showresults&searchid=1&sortby=title` 与 `sortby=forum`，两次都返回
`for SELECT DISTINCT, ORDER BY expressions must appear in select list`；换上新驱动后
`sortby=title|forum|replies|lastpost` 四种排序全部 200 且无 SQL 报错；帖子模式（`showposts=1`）
的 `title|forum|lastpost|replies|views|poster` 六种排序同样全部正常。造了 3 个主题、4 条帖子做过
对照：`ORDER BY thread.lastpost DESC` 出 `902,903,901`，`ORDER BY thread.title ASC` 出
`902,901,903`，`ORDER BY forum.title DESC` 把另一个版块的主题排在最前，去重后仍是 3 行。

#### 4.17.7 后台一键换引擎（连接档案 + 跨引擎数据迁移）

`admin/dbsettings.php` 现在不只是“填一串连接参数再保存”，它把两个引擎当成两个可切换的
目标来管理，页面在 `Import & Maintenance` 下（Back-up Database 之后）。

**按引擎记住连接信息。** 连接参数存进 `admin/config.php` 的 `$dbprofiles` 里，一个引擎一份：

```php
$dbprofiles = array(
	'pgsql' => array('servername' => '/tmp', 'dbport' => '5433', 'dbschema' => 'public',
	                 'dbusername' => 'user', 'dbpassword' => '', 'dbname' => 'vb2311', 'usepconnect' => '0'),
	'mysql' => array('servername' => '127.0.0.1', 'dbport' => '3306', 'dbschema' => '',
	                 'dbusername' => 'vbuser', 'dbpassword' => 'vbpass', 'dbname' => 'vb2311_mysql', 'usepconnect' => '0'),
);
```

在 Database type 下拉框里换引擎会立刻提交一次 `action=load`，把该引擎上次保存的
主机/端口/schema/用户名/密码/库名填回表单——不用记、也不用重填。档案和当前生效的
8 个赋值写在同一个文件里，所以切换是原子的：不会出现“档案已改、生效配置没改”的半截状态；
写盘前仍然先 `token_get_all($text, TOKEN_PARSE)` 校验、并留一份 `config.php.bak.<时间>`。

**数据迁移（新文件 `admin/dbsettings_migrate.php`）。** 按钮是
`Copy data to this database and switch`，一次点击把当前数据库整库复制到表单里填的目标库，
成功后才改配置：

1. **读源库结构**：`information_schema.columns` + `pg_indexes`/`SHOW INDEX`，归一成
   int / bigint / smallint / varchar / text / blob / temporal / decimal / float 八类
2. **按目标方言出 DDL**：PostgreSQL 侧 `serial`、`character varying(n)`、`bytea`、
   `numeric(p,s)`；MySQL 侧 `bigint(20) … AUTO_INCREMENT`、`mediumtext`、`longblob`、
   `ENGINE=MyISAM DEFAULT CHARSET=utf8mb4`。PostgreSQL 的索引名按 schema 唯一，所以名字
   会自动带上表名前缀；主键写进 `CREATE TABLE`，其余索引在数据搬完后建
3. **NOT NULL 补默认值**：沿用安装器的约定（数值补 `0`、字符补 `''`），否则 vB 那些
   “不给该列赋值”的 INSERT 会被拒绝
4. **搬数据**：每 200 行一批 `INSERT ... VALUES (...),(...)`，带显式列清单。跨引擎的值差异
   在这一步处理——PostgreSQL 的 `bytea`（hex 输出）解码成二进制再写成 MySQL 字符串、
   反过来 MySQL 的 `longblob` 原样写成 `'\x<hex>'` 字面量；`mysqli_real_escape_string`
   在字节层面安全，附件/头像不会坏
5. **续接自增**：PostgreSQL 逐表 `setval(pg_get_serial_sequence(...), MAX(col))`，
   MySQL 逐表 `ALTER TABLE ... AUTO_INCREMENT = MAX+1`，迁移后新帖不会撞 id
6. **逐表核对行数**：任何一张表对不上就报错并**不写配置**，论坛继续跑在原库上
7. **写配置**：把目标连接写成生效配置、同时把两台机器的连接信息都存进 `$dbprofiles`。
   原库不删、不锁，随时在 Dropdown 里选回原引擎保存即可切回

几个安全判定：目标库已有同名表 → 拒绝（除非勾 `Recreate tables in the target database`，
勾了会先 `DROP TABLE ... CASCADE`）；目标库连不上 → 只报错不写配置；目标与当前是同一个
库 → 直接拒绝；库名不存在 → 报“连接失败”，配置保持原样。

真机验证（PostgreSQL 18.6 + MariaDB 10.6.23 + PHP 8.5）：

- **PG → PG**（`vb2311` → 空库 `vb2311_pg2`）：40 张表、20 张有数据的表逐表行数一致，
  自增序列停在 `MAX(postid)=900004` / `MAX(threadid)=903`；站点 index/forumdisplay/
  showthread/usercp/memberlist/calendar/online/private/register/后台 全部 200
- **PG → MySQL**：40 张表建好、行数全部一致，`attachment.filedata` 242 字节 PNG 在
  MySQL 里 `HEX()` 与源文件逐字节相同（`longblob`）；站点换到 MySQL 后同样 12 个页面全 200，
  后台也能登录
- **MySQL → PG**（回到新建的 `vb2311_back`）：行数一致、附件字节仍一致、
  `user.birthday` 仍是 `character varying(19)`；`attachment.php?attachmentid=2001`
  下载回来 `image/png`、242 字节、`cmp` 与原始文件一致
- **护栏**：目标库已有表且未勾 recreate → 明确拒绝并列出入侵的表名；勾 recreation →
  先删 40 张旧表再复制、校验通过；目标库不存在 → “连接失败：/tmp:5433/nosuchdb”、
  `dbname` 仍是原值；同一库 → 拒绝
- **档案**：`action=load` 切到 MySQL 时表单回填 `127.0.0.1` / `vbuser` / `vb2311_mysql`，
  切回 PostgreSQL 时回填 `/tmp` / `5433` / `vb2311_back`，页面上两个引擎都标着 `(已保存)`

#### 4.17.8 去掉后台的外链“版本检查”（`version.gif` / `version.js`）

后台每个页面都会向 vBulletin 官方发两种请求：

- `admin/adminfunctions.php` 的 `doformiddle()` 返回一个 1×1 统计像素
  `http://www.vbulletin.com/version/version.gif?v=2311&id=VBFBF97DF0`，被拼进控制面板顶栏
- `admin/index.php` 的 `action=head` / `action=home` 用 `fsockopen('version.vbulletin.com', 80)`
  （**超时 3 秒**）探活，通了再插
  `http://version.vbulletin.com/version.js` 与 `versioncheck.js`，用来显示“有新版本可用”

这两处都是 2000 年代的上网统计/更新提示。放到内网或离线环境会有三个问题：

1. 浏览器对明文 `http://` 的升级（HSTS）会在网络面板里留下 `307 Internal Redirect`，
   脚本和图片拿不到，控制面板上的版本号/“最新版本”显示成 `N/A`
2. 每开一个后台页面先卡最多 3 秒等 `fsockopen` 超时
3. 会把论坛标识（`id=VBFBF97DF0` 与 `SIMPLE_VERSION`）发给第三方

现在：`doformiddle()` 直接返回空串（保留函数签名，`admin/index.php` 与 `mod/index.php` 里
`$df=doformiddle(...)` 的调用照旧安全）；`action=head` 顶栏改为本地文案
`Control Panel (Version 2.3.11)` + `vBulletin 2.3.11 · PHP 8 port（MySQL / PostgreSQL）`；
`action=home` 的 `fsockopen` 探活、两个远程脚本、“有新版本”横幅一并删除，
页脚改为静态版本号。后台不再有任何指向 `version.vbulletin.com` 的请求。

真机验证（PHP 8.5 + PG 18.6）：`admin/index.php`（框架页）、`?action=head`、`?action=home`
三页渲染结果里 `version.vbulletin.com` / `version.gif` / `vb_version` / `isNewerVersion`
命中数均为 0；顶栏正确显示 `Control Panel (Version 2.3.11)`；三页响应 0.06–0.12 秒
（原先每个 head 帧最多多等 3 秒）；`modifyoptions`、`dbsettings`、`users`、`backup`
四个后台页面仍全部 200 且无 SQL 报错。浏览器里如果还看到旧请求，是缓存里的旧 HTML，
强制刷新一次即可。

## 5. 运行要求

| 组件 | 要求 |
|---|---|
| PHP | 8.x（实测 **8.5.12**）；需启用 `mysqli`，`gd` 可选（验证码/头像） |
| 数据库 | MySQL / MariaDB（实测 **MariaDB 13.0.1**）；也可选 PostgreSQL（`$dbservertype='pgsql'`，见 4.17，**已在 PostgreSQL 18.6 上跑通安装器与前台页面**） |
| 不再需要 | `register_globals`、`register_long_arrays`、magic quotes、`short_open_tag`、ext/mysql |
| 字符集 | **UTF-8 / `utf8mb4`**：表、连接、HTTP 头、HTML meta 四者需一致。新建库请用 `CREATE DATABASE ... CHARACTER SET utf8mb4`；存量库需 `ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4`（见 4.9） |
| Web 服务 | 任意能执行 PHP 的服务器；`admin/` 下 21 个 `upgrade*.php`、安装脚本与 `tools/` 目录**必须禁止 Web 访问** |

## 6. 本地测试环境（示例）

曾在本机以「用户级、无 root」方式搭建，结构如下（**不属于本仓库内容**）：

```
~/vb24-env/
├── site/          站点根（本仓库副本，admin/config.php 写本地测试库连接）
├── mysql-data/    MariaDB 持久数据目录
├── installers/    安装脚本（已移出 web root）
├── router.php     对 install*.php / upgrade*.php URL 返回 404
├── php.ini        mysqli + gd
├── install.php    CLI 重装驱动（step 4..10）
└── start.sh / stop.sh
```

Web 服务可用 PHP 内置服务器（多 worker）：

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t site router.php
```

安装器通过 CLI 驱动执行 step 4–10，完成后完整数据为 42 张表 / 487 模板 /
171 项设置 / 2 个论坛 / 1 个管理员。**装完务必删除安装脚本或用 router 拦截**——
它们无鉴权且能重建数据库。

## 7. 验证结果

| 检查 | 结果 |
|---|---|
| `php -l` 全部 91 个 PHP 文件 | 0 失败、0 Deprecated |
| tokenizer 静态扫描 | 代码上下文裸数组键 0、未定义常量 0、反序 `implode` 0 |
| 全新安装 step 4–10（真库） | 7/7 通过，42 表 / 487 模板 / 171 设置 |
| 首页渲染 | 真实数据库模板输出约 10 KB HTML，stderr 0 |
| 58 个页面 × 8 组参数（真库） | 0 失败、0 告警 |
| 3 种登录态（匿名 / `''` / `'+8'` 时区）× 15 页面 | 全部 200、0 PHP 错误 |
| 后台登录 | 错误密码被拒且不下发认证 cookie；正确密码进 3-frame 控制台 |
| SQL 转义行为 | 输出与改动前逐字节一致（`vb_escape` 与 `addslashes` 在本字符集下等价），注入载荷被正确中和 |
| UTF-8 中文 | 连接 `utf8mb4`；emoji + 中文写入/读出字节一致，无新增 `U+FFFD`；页面带 HTTP 头与 meta 双声明 |
| 中文发帖（真实 POST） | `中文发帖测试_😀_中` 标题/正文完整入库，无 `U+FFFD`、无 `?`；预览路径同样正常 |
| 回复 / 搜索 / 私信 | 真实 HTTP 回复成功、无 `Unknown column`；6 条改写 SQL 解析通过；多种搜索词无报错 |
| BBCode / 实体解码 | `[url]`/`[list]`/`[code]`/`[php]`/`[b]`/`[email]` 与 `&#20013;` 均正确；发帖转跳页 0 警告 |
| 中英切换 | cookie 生效，12 页面 × 2 语言 0 警告；897 条模板文案全量翻译；en 渲染与原文一致，用户内容不被翻译 |
| 后台 CP 切换 | `/admin/?s=x&lang=zh` 302 保留参数并写 cookie；nav/home/forum/user 均出现中文，en 模式不变 |
| 设置页帮助 | 155 条 `setting.description` 全部中文（`options.php` 4665 个汉字）；编辑页仍为英文，避免写回污染 |
| 加载计时 | 前台 11 页面 × 中英、后台 CP 均输出页面/模板耗时；模板耗时 < 页面耗时 |
| 附件二进制 | `filedata`/`avatardata` 改为 BLOB；上传→入库→下载 md5 与源文件一致 |
| 图片内联 | `viewattachedimages=1` + `forum.allowimages=1`；帖子渲染为 `<img src="attachment.php…">`，不再需点击 |
| 回归 | 3 种登录态 × 15 页面 0 错误；两树 `php -l` 全通过 |

## 8. 已知遗留问题（重要，勿用于生产）

1. **仍是字符串拼接 SQL**，不是参数绑定；动态标识符与 IN 列表仍靠拼接（数值已强转、
   字符串已 `real_escape_string`）。
2. **810 处 `eval()` 执行数据库里的模板**，`phpinclude` 模板可执行任意 PHP —— 拿到
   后台模板编辑权即等于任意代码执行。
3. 口令为 **md5 无盐**，且 `bbpassword` cookie 直接存 md5 值；会话 hash 为
   `md5(uniqid(microtime()))`。
4. `xss_clean()` 只替换 `javascript` / `"` / `<` / `>`，防护很弱。
5. `admin/functions.php` 的 `/e` 修饰符已在 4.12 中改为 `preg_replace_callback`；
   但 `bbcodeparse2()` 仍是「一串正则顺序替换」的旧实现，嵌套 / 异常 BBCode 的
   边界行为未做系统性验证。
6. `admin/config.php` 为占位凭据（`root` / `passwd`），部署时须自行修改。
7. 安装脚本与 21 个 `upgrade*.php` 无鉴权，必须移出 web root 或在服务器层拦截。
8. 应用内部大量 `strlen()` / `substr()` / `wordwrap()` 按**字节**处理字符串，对 UTF-8
   多字节文本仍可能截断或错误换行。
   另外，历史数据中已变成 `U+FFFD` / `?` 的字符无法自动还原（见 4.9 / 4.10），
   需要重新编辑对应帖子；`censortext()` 修复后新发的内容不会再出现此问题。

## 9. 再次声明

本仓库是**技术研究的存档**，不是可分发的产品，也不代表 vBulletin 官方。
所有商标与源码版权归原权利人。**使用本仓库内容前，请先购买有效的 vBulletin 授权。**
