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

**后台 CP**：`admin/global.php` 同样处理 `?lang=`（保留 `s=` 等参数），导航栏底部和登录页都有「中文 | English」切换。CP 页面是纯 PHP 输出、不走 `gettemplate()`，因此非英文时用 `ob_start()` + `register_shutdown_function` 对整页做一次文本节点替换，`action=nav` / `action=home` 这类不调用 `cpfooter()` 的 frame 也能翻译。**英文模式不启用缓冲，行为与原版完全一致。** 目前 CP 词表覆盖导航、登录、常用按钮/表头，各页面的长描述仍为英文，可继续在 `lang_zh.php` 补条目。

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

## 5. 运行要求

| 组件 | 要求 |
|---|---|
| PHP | 8.x（实测 **8.5.12**）；需启用 `mysqli`，`gd` 可选（验证码/头像） |
| 数据库 | MySQL / MariaDB（实测 **MariaDB 13.0.1**） |
| 不再需要 | `register_globals`、`register_long_arrays`、magic quotes、`short_open_tag`、ext/mysql |
| 字符集 | **UTF-8 / `utf8mb4`**：表、连接、HTTP 头、HTML meta 四者需一致。新建库请用 `CREATE DATABASE ... CHARACTER SET utf8mb4`；存量库需 `ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4`（见 4.9） |
| Web 服务 | 任意能执行 PHP 的服务器；`admin/` 下 21 个 `upgrade*.php` 与安装脚本**必须禁止 Web 访问** |

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
