<?php
/**
 * PHP 8 compatibility shims for vBulletin 2.3.11.
 *
 * vBulletin 2.x was written for PHP 4. PHP 8 removed a number of functions
 * it relies on, and promoted several former E_NOTICE conditions to E_WARNING.
 * This file restores just enough of that behaviour to let the original code
 * run. It is loaded with require_once by every PHP file in the tree.
 */

// Request timing: bootstrap start + accumulated template loading time.
if (!isset($GLOBALS['vb_pagestart'])) {
    $GLOBALS['vb_pagestart'] = microtime(true);
    $GLOBALS['vb_templatetime'] = 0.0;
}

if (!function_exists('vb_ereg_pattern')) {

    // ---------------------------------------------------------------------
    // POSIX ERE -> PCRE. Every pattern used by this codebase is a basic
    // POSIX ERE: grouping (), intervals {m,n}, POSIX classes [[:alpha:]]
    // and \-escaped literals all mean the same thing in PCRE. Pick a
    // delimiter that does not occur in the pattern, so no escaping is needed.
    // ---------------------------------------------------------------------
    function vb_ereg_pattern($pattern, $flags = '')
    {
        foreach (array('~', '#', '%', '!', '@', '`', '/', ',', ';') as $delim) {
            if (strpos($pattern, $delim) === false) {
                return $delim . $pattern . $delim . $flags;
            }
        }
        return "\x01" . $pattern . "\x01" . $flags;
    }

    // ---------------------------------------------------------------------
    // Removed in PHP 7.0: ereg, eregi, ereg_replace, eregi_replace,
    // split, spliti, sql_regcase.
    // ---------------------------------------------------------------------
    function ereg($pattern, $string, &$regs = null)
    {
        return preg_match(vb_ereg_pattern($pattern), (string) $string, $regs);
    }

    function eregi($pattern, $string, &$regs = null)
    {
        return preg_match(vb_ereg_pattern($pattern, 'i'), (string) $string, $regs);
    }

    function ereg_replace($pattern, $replacement, $string)
    {
        return preg_replace(vb_ereg_pattern($pattern), $replacement, (string) $string);
    }

    function eregi_replace($pattern, $replacement, $string)
    {
        return preg_replace(vb_ereg_pattern($pattern, 'i'), $replacement, (string) $string);
    }

    function split($pattern, $string, $limit = -1)
    {
        return preg_split(vb_ereg_pattern($pattern), (string) $string, $limit);
    }

    function spliti($pattern, $string, $limit = -1)
    {
        return preg_split(vb_ereg_pattern($pattern, 'i'), (string) $string, $limit);
    }

    function sql_regcase($string)
    {
        return preg_replace_callback('/[a-z]/i', function ($m) {
            return '[' . strtoupper($m[0]) . strtolower($m[0]) . ']';
        }, (string) $string);
    }

    // ---------------------------------------------------------------------
    // Removed in PHP 8.0. Reimplemented over the array's internal pointer so
    // the many `while (list($k, $v) = each($arr))` loops keep their exact
    // semantics (including code that walks an array with successive calls).
    // ---------------------------------------------------------------------
    function each(&$array)
    {
        if (!is_array($array)) {
            return false;
        }
        $key = key($array);
        if ($key === null) {
            return false;
        }
        $value = current($array);
        next($array);
        return array(1 => $value, 'value' => $value, 0 => $key, 'key' => $key);
    }

    // ---------------------------------------------------------------------
    // Magic quotes were removed in PHP 5.4 / PHP 7.0. Under PHP 8 they are
    // always off, so these are no-ops returning the "off" value.
    // ---------------------------------------------------------------------
    function get_magic_quotes_gpc()
    {
        return false;
    }

    function get_magic_quotes_runtime()
    {
        return false;
    }

    function set_magic_quotes_runtime($setting)
    {
        return true;
    }

    // ---------------------------------------------------------------------
    // Charset-aware SQL string escaping. addslashes() is not safe: on a
    // connection using a multi-byte charset it can be tricked into leaving
    // a quote unescaped. mysqli_real_escape_string() uses the connection's
    // actual charset. Falls back to addslashes() before the DB is connected
    // (e.g. bootstrap code that runs before config.php is loaded).
    // ---------------------------------------------------------------------
    function vb_escape($value)
    {
        global $DB_site;
        if (isset($DB_site->link_id) && $DB_site->link_id instanceof mysqli) {
            return mysqli_real_escape_string($DB_site->link_id, (string) $value);
        }
        return addslashes((string) $value);
    }

    // ---------------------------------------------------------------------
    // ext/mysql was removed in PHP 7.0. The real database class now uses
    // mysqli; these two are only used by the backup and install scripts.
    // ---------------------------------------------------------------------
    function mysql_escape_string($string)
    {
        return vb_escape($string);
    }

    function mysql_close($link = null)
    {
        if ($link instanceof mysqli) {
            return mysqli_close($link);
        }
        return true;
    }

    // ---------------------------------------------------------------------
    // Many queries interpolate request variables into SQL *without quotes*
    // (WHERE forumid=$forumid, LIMIT $startat,$perpage, ...). Escaping does
    // not help there, so force the known numeric parameters to integers once,
    // at bootstrap, before any SQL is built. Only scalars that are already
    // set are touched, so isset() semantics and SQL-fragment variables (which
    // are strings) are preserved.
    // ---------------------------------------------------------------------
    function vb_cast_request_ids()
    {
        static $names = array(
            'forumid','threadid','postid','userid','pollid','attachmentid',
            'avatarid','iconid','smilieid','styleid','templatesetid',
            'replacementsetid','settinggroupid','usergroupid','perpage',
            'pagenumber','startat','finishat','searchid','announcementid',
            'profilefieldid','privatemessageid','eventid','wordid',
            'threadrateid','adminlogid','moderatorid','replacementid',
            'bbcodeid','parentid','displayorder','highestnum','rating',
            'pollvoteid','subscribeforumid','subscribethreadid','usertitleid',
            'importforumid','importthreadid','importpostid','importuserid',
            'importpollid','importpmid','importcategoryid','importstyleid',
        );
        foreach ($names as $name) {
            if (isset($GLOBALS[$name]) && is_scalar($GLOBALS[$name])) {
                $GLOBALS[$name] = intval($GLOBALS[$name]);
            }
        }
    }

    // ---------------------------------------------------------------------
    // PHP 8 turned "undefined variable" / "undefined array key" / "array to
    // string conversion" from E_NOTICE into E_WARNING. The original code runs
    // with error_reporting(7) = E_ERROR|E_WARNING|E_PARSE, i.e. notices were
    // deliberately hidden. Swallow exactly those promoted notices (plus the
    // end-of-each() loop warning) so behaviour matches PHP 5; let every
    // other warning reach PHP's normal handler.
    // ---------------------------------------------------------------------

    // PHP 8 removed the PHP 5 behaviour where an undefined constant used as
    // an array key evaluated to its own name. vBulletin stores PHP code in
    // the database (templates, setting.optioncode) and builds eval() strings
    // at runtime, so a source-level rewrite cannot reach every occurrence of
    // $array[word]. Restore that behaviour for exactly the identifiers this
    // board uses as bareword keys. Names are lower-case identifiers only:
    // no PHP built-in constant or keyword is shadowed.
    $vb_bareword_keys = array(
    'action', 'activationid', 'active', 'adminlogid', 'aim', 'aimicon', 'announcementid', 'asc', 'attach',
    'attachment', 'attachmentextension', 'attachmentid', 'attachsum', 'avatar', 'avatardateline', 'avatarid',
    'avatarpath', 'avatarurl', 'backcolor', 'barnumber', 'bbcodeid', 'bbcodetag', 'bbtitle', 'bburl',
    'birthday', 'buddy', 'buddylist', 'calendarday', 'closed', 'coppauser', 'count', 'counter',
    'customtitle', 'datejoined', 'dateline', 'dateread', 'datesent', 'dbsessionhash', 'deleteprompt',
    'denyreceipt', 'desc', 'description', 'displayorder', 'displayusername', 'edit', 'editable', 'editdate',
    'editedby', 'edittime', 'email', 'enddate', 'eventid', 'extrainfo', 'field2', 'filename', 'findword',
    'firstnewinsert', 'folder', 'foldericon', 'folderid', 'forumid', 'forumpermissionid', 'forumslist',
    'forumtitle', 'fromuserid', 'fromusername', 'gotonew', 'graphicnumber', 'hidden', 'home', 'homepage',
    'hostaddress', 'icon', 'iconid', 'iconpath', 'icontitle', 'icq', 'icqicon', 'ids', 'ignorelist',
    'importbb', 'importcategoryid', 'importforumid', 'importpollid', 'importuserid', 'ip', 'ipaddress',
    'iplogged', 'joindate', 'key', 'key_len', 'lastactivity', 'lastactivity_date', 'lastactivity_time',
    'lastpost', 'lastpostdate', 'lastposter', 'lastpostinfo', 'lastposttime', 'lastreplydate',
    'lastreplytime', 'lastvisit', 'lastvisitdate', 'location', 'max', 'maxbuddypm', 'maxforwardpm',
    'maxlength', 'maxpost', 'message', 'messagecount', 'messageread', 'messages', 'min', 'minimumposts',
    'minpost', 'minposts', 'moderatorid', 'moderators', 'movedprefix', 'multiple', 'multiplerecipients',
    'n10', 'n100', 'n110', 'n120', 'n20', 'n30', 'n35', 'n40', 'n50', 'n60', 'n70', 'n80', 'n90',
    'newoldhot', 'notes', 'notified', 'number', 'numberoptions', 'numbervotes', 'onlinestatus', 'onoff',
    'open', 'optioncode', 'options', 'pagenav', 'pagetext', 'parentemail', 'parentid', 'parentlist',
    'password', 'path', 'percent', 'pm', 'pmfolders', 'pmlink', 'pollid', 'possible_keys', 'postdate',
    'postedby', 'posticon', 'posticonpath', 'posticontitle', 'postid', 'postids', 'postnew', 'postreply',
    'posts', 'posttime', 'posttitle', 'postuserid', 'postusername', 'privatemessageid', 'profile',
    'profilefieldid', 'query', 'querystring', 'question', 'rating', 'readtime', 'receipt', 'ref',
    'referrerid', 'replacementid', 'replacementsetid', 'replaceword', 'replies', 'replycount', 'required',
    'rows', 's', 'script', 'search', 'sendpm', 'sessionhash', 'settinggroupid', 'settingid', 'showemail',
    'showposts', 'signature', 'size', 'smilieid', 'smiliepath', 'smilietext', 'startdate', 'sticky',
    'styleid', 'styleoverride', 'table', 'templateid', 'templatesetid', 'templateversion', 'text',
    'threadcount', 'threadicon', 'threadiconpath', 'threadicontitle', 'threadid', 'threadrateid',
    'threadslist', 'threadtitle', 'time', 'timeout', 'timeread', 'timesent', 'timezoneoffset', 'title',
    'total', 'touserid', 'tousername', 'tracking', 'type', 'typeprefix', 'undelete', 'url', 'usercp',
    'useremail', 'usergroupid', 'userid', 'username', 'users', 'userselect', 'usertitle', 'usertitleid',
    'usrname', 'value', 'varname', 'views', 'visible', 'voteavg', 'votedate', 'votenum', 'voters', 'votes',
    'where', 'wol', 'wordid', 'yahoo', 'yahooicon'
    );
    foreach ($vb_bareword_keys as $vb_key) {
        if (!defined($vb_key)) define($vb_key, $vb_key);
    }

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false; // suppressed with @
        }
        if ($severity === E_WARNING || $severity === E_NOTICE) {
            if (preg_match(
                '/^(Undefined variable|Undefined array key|Undefined index|'
                . 'Undefined property|Trying to access array offset|'
                . 'Trying to get property|Attempt to read property|'
                . 'Attempt to assign property|Array to string conversion|'
                . 'Cannot use bool as array|Cannot use a scalar value as an array|'
                . 'Cannot use string offset as an array|'
                . 'Uninitialized string offset|Illegal string offset|'
                . 'Cannot access offset of type string on string)/i',
                $message
            )) {
                return true;
            }
        }
        return false;
    });
}
