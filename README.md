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

## 5. 运行要求

| 组件 | 要求 |
|---|---|
| PHP | 8.x（实测 **8.5.12**）；需启用 `mysqli`，`gd` 可选（验证码/头像） |
| 数据库 | MySQL / MariaDB（实测 **MariaDB 13.0.1**） |
| 不再需要 | `register_globals`、`register_long_arrays`、magic quotes、`short_open_tag`、ext/mysql |
| 字符集 | 应用为 `windows-1252`；连接字符集由 `mysqli_real_escape_string()` 自动适配 |
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

## 8. 已知遗留问题（重要，勿用于生产）

1. **仍是字符串拼接 SQL**，不是参数绑定；动态标识符与 IN 列表仍靠拼接（数值已强转、
   字符串已 `real_escape_string`）。
2. **810 处 `eval()` 执行数据库里的模板**，`phpinclude` 模板可执行任意 PHP —— 拿到
   后台模板编辑权即等于任意代码执行。
3. 口令为 **md5 无盐**，且 `bbpassword` cookie 直接存 md5 值；会话 hash 为
   `md5(uniqid(microtime()))`。
4. `xss_clean()` 只替换 `javascript` / `"` / `<` / `>`，防护很弱。
5. **`/e` 修饰符**（PHP 7 已移除）在 `admin/functions.php` 的 `bbcodeparse2()`
   （约第 910 行）与 `unhtmlspecialchars()`（约第 2212 行）的 PHP4+ 分支仍可达：
   会报 `preg_replace(): Unknown modifier 'e'` 并返回空，影响 `[url]` / `[list]` /
   `[code]` / `[php]` BBCode 解析与 HTML 实体解码。修它需要把表达式数组重写为
   `preg_replace_callback`，属 BBCode 引擎级改动，**本仓库尚未修复**。
6. `admin/config.php` 为占位凭据（`root` / `passwd`），部署时须自行修改。
7. 安装脚本与 21 个 `upgrade*.php` 无鉴权，必须移出 web root 或在服务器层拦截。

## 9. 再次声明

本仓库是**技术研究的存档**，不是可分发的产品，也不代表 vBulletin 官方。
所有商标与源码版权归原权利人。**使用本仓库内容前，请先购买有效的 vBulletin 授权。**
