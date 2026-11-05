<?php
/**
 * vBulletin 2.3 i18n layer.
 *
 * UI strings live in lang_en.php (full manifest) and lang_zh.php (translations).
 * Translation happens on the *template source* inside gettemplate(), so user
 * content (posts, PMs, signatures) can never be rewritten by accident.
 * Language is selected by the "bblanguage" cookie; ?lang=zh|en switches it.
 */

if (!function_exists('vbphrase')) {

    $GLOBALS['vb_supported_languages'] = array('en', 'zh');

    function vb_lang_default()
    {
        if (isset($GLOBALS['defaultlanguage'])
            && in_array($GLOBALS['defaultlanguage'], $GLOBALS['vb_supported_languages'], true)) {
            return $GLOBALS['defaultlanguage'];
        }
        return 'en';
    }

    function vb_lang_current()
    {
        if (isset($_COOKIE['bblanguage'])
            && in_array($_COOKIE['bblanguage'], $GLOBALS['vb_supported_languages'], true)) {
            return $_COOKIE['bblanguage'];
        }
        return vb_lang_default();
    }

    function vb_lang_map($lang = null)
    {
        static $cache = array();
        if ($lang === null) {
            $lang = vb_lang_current();
        }
        if (isset($cache[$lang])) {
            return $cache[$lang];
        }
        $map = array();
        if ($lang !== 'en') {
            $file = __DIR__ . '/lang_' . $lang . '.php';
            if (is_file($file)) {
                $loaded = include $file;
                if (is_array($loaded)) {
                    $map = $loaded;
                }
            }
        }
        $cache[$lang] = $map;
        return $map;
    }

    /** Translate one UI string (PHP-side messages). */
    function vbphrase($text)
    {
        $map = vb_lang_map();
        return isset($map[$text]) ? $map[$text] : $text;
    }

    /** Translate the text nodes / label attributes of a template source. */
    function vblang_template($html)
    {
        if (!is_string($html)) {
            return $html;
        }
        $map = vb_lang_map();
        if (empty($map) || strpos($html, '<') === false) {
            return $html;
        }

        static $cache = array();
        $ck = md5($html);
        if (isset($cache[$ck])) {
            return $cache[$ck];
        }

        // Protect script / style / textarea / comments.
        $stash = array();
        $html = preg_replace_callback(
            '/<!--.*?-->|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<textarea\b[^>]*>.*?<\/textarea>/is',
            function ($m) use (&$stash) {
                $k = "\x02VBL" . count($stash) . "\x03";
                $stash[$k] = $m[0];
                return $k;
            },
            $html
        );

        // Text nodes (odd indexes are tags).
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $seg) {
            if ($i % 2 == 1) {
                continue;
            }
            $t = trim($seg);
            if ($t === '') {
                continue;
            }
            $key = preg_replace('/\s+/', ' ', $t);
            if (isset($map[$key])) {
                preg_match('/^\s*/', $seg, $lead);
                preg_match('/\s*$/', $seg, $trail);
                $parts[$i] = $lead[0] . $map[$key] . $trail[0];
                continue;
            }
            if (strpos($seg, '$') === false) {
                continue;
            }
            // Text mixed with $variables: translate the static fragments.
            $chunks = preg_split('/(\$[A-Za-z_][A-Za-z0-9_]*(?:\[[^\]]*\])?)/', $seg, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($chunks as $ci => $chunk) {
                if ($ci % 2 == 1) {
                    continue;
                }
                $tc = trim($chunk);
                if ($tc === '') {
                    continue;
                }
                $kc = preg_replace('/\s+/', ' ', $tc);
                if (isset($map[$kc])) {
                    preg_match('/^\s*/', $chunk, $l2);
                    preg_match('/\s*$/', $chunk, $t2);
                    $chunks[$ci] = $l2[0] . $map[$kc] . $t2[0];
                }
            }
            $parts[$i] = implode('', $chunks);
        }
        $html = implode('', $parts);

        // Label attributes on buttons / inputs.
        $html = preg_replace_callback(
            '/\b(value|alt|title|placeholder)(\s*=\s*")([^"]*)(")/i',
            function ($m) use ($map) {
                $key = preg_replace('/\s+/', ' ', trim($m[3]));
                if (isset($map[$key])) {
                    return $m[1] . $m[2] . $map[$key] . $m[4];
                }
                return $m[0];
            },
            $html
        );

        if ($stash) {
            $html = strtr($html, $stash);
        }
        $cache[$ck] = $html;
        return $html;
    }

    /** Translate month / weekday names in formatted dates. */
    function vblang_date($text)
    {
        $map = vb_lang_map();
        if (empty($map)) {
            return $text;
        }
        static $words = array(
            'January','February','March','April','May','June','July','August','September','October','November','December',
            'Jan','Feb','Mar','Apr','Jun','Jul','Aug','Sep','Oct','Nov','Dec',
            'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday',
            'Mon','Tue','Wed','Thu','Fri','Sat','Sun'
        );
        foreach ($words as $w) {
            if (isset($map[$w])) {
                $text = preg_replace('/\b' . preg_quote($w, '/') . '\b/', $map[$w], $text);
            }
        }
        return $text;
    }

    /** Translate a setting's help text, keyed by setting.varname. */
    function vbsetting_desc($varname, $description)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            if (vb_lang_current() === 'zh') {
                $file = __DIR__ . '/lang_zh_settings.php';
                if (is_file($file)) {
                    $loaded = include $file;
                    if (is_array($loaded)) {
                        $map = $loaded;
                    }
                }
            }
        }
        return isset($map[$varname]) ? $map[$varname] : $description;
    }

    /** Build a ?lang=xx URL that keeps the current path and query. */
    function vb_lang_url($lang)
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $p = parse_url($uri);
        $q = array();
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
        }
        $q['lang'] = $lang;
        return (isset($p['path']) ? $p['path'] : '/') . '?' . http_build_query($q);
    }

    /** Handle ?lang=zh|en: set the cookie and redirect to the clean URL. */
    function vb_lang_switch()
    {
        if (!isset($_GET['lang']) || headers_sent()) {
            return;
        }
        $lang = (string) $_GET['lang'];
        if (!in_array($lang, $GLOBALS['vb_supported_languages'], true)) {
            return;
        }
        setcookie('bblanguage', $lang, time() + 31536000, '/');
        $_COOKIE['bblanguage'] = $lang;

        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $p = parse_url($uri);
        $q = array();
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
        }
        unset($q['lang']);
        $qs = http_build_query($q);
        $loc = (isset($p['path']) ? $p['path'] : '/') . ($qs ? '?' . $qs : '');
        header('Location: ' . $loc);
        exit;
    }
}

