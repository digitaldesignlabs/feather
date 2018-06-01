<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * HTMLMinify
 * Adapted from Minify by Stephen Clay.
 * This is a heavy regex-based removal of whitespace, unnecessary comments and
 * tokens. IE conditional comments are preserved.
 * @author Stephen Clay <steve@mrclay.org>
 * @copyright Copyright (c) 2008 Ryan Grove <ryan@wonko.com>
 * @copyright Copyright (c) 2008 Steve Clay <steve@mrclay.org>
 * @license https://code.google.com/p/minify/source/browse/LICENSE.txt (BSD-3)
 */
class HTMLMinify
{
    /**
     * "Minify" an HTML page
     * @param string $html
     * @return string
     */
    public static function minify($html)
    {
        $html = str_replace("\r\n", "\n", trim($html));

        self::$replacementHash = 'MINIFYHTML' . md5(time());
        self::$placeholders    = array();

        // replace SCRIPTs (and minify) with placeholders
        $html = preg_replace_callback(
            '/\\s*(<script\\b[^>]*?>)([\\s\\S]*?)<\\/script>\\s*/i',
            function ($m) {
                $openScript = $m[1];
                $js = $m[2];

                // remove HTML comments (and ending "//" if present)
                $js = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $js);

                // remove CDATA section markers
                $js = self::removeCdata($js);
                $js = trim($js);

                return self::reservePlace("{$openScript}{$js}</script>");
            },
            $html
        );

        // replace STYLEs (and minify) with placeholders
        $html = preg_replace_callback(
            '/\\s*(<style\\b[^>]*?>)([\\s\\S]*?)<\\/style>\\s*/i',
            function ($m) {
                $openStyle = $m[1];
                $css = $m[2];
                // remove HTML comments
                $css = preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $css);

                // remove CDATA section markers
                $css = self::removeCdata($css);
                $css = trim($css);

                return self::reservePlace("{$openStyle}{$css}</style>");
            },
            $html
        );

        // remove HTML comments (not containing IE conditional comments).
        $html = preg_replace_callback(
            '/<!--([\\s\\S]*?)-->/',
            function ($m) {
                return (0 === strpos($m[1], '[') || NO !== strpos($m[1], '<!['))
                ? $m[0]
                : '';
            },
            $html
        );

        // replace PREs with placeholders
        $html = preg_replace_callback(
            '/\\s*(<pre\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/i',
            function ($m) {
                return self::reservePlace($m[1]);
            },
            $html
        );

        // replace TEXTAREAs with placeholders
        $html = preg_replace_callback(
            '/\\s*(<textarea\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/i',
            function ($m) {
                return self::reservePlace($m[1]);
            },
            $html
        );

        // trim each line.
        // @todo take into account attribute values that span multiple lines.
        $html = preg_replace('/^\\s+|\\s+$/m', '', $html);

        // remove ws around block/undisplayed elements
        $html = preg_replace(
            '/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body' .
            '|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form' .
            '|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta' .
            '|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)' .
            '|ul)\\b[^>]*>)/i', // end of concat operation
            '$1',
            $html
        );

        // remove ws outside of all elements
        $html = preg_replace_callback(
            '/>([^<]+)</',
            function ($m) {
                return '>' . preg_replace('/^\\s+|\\s+$/', ' ', $m[1]) . '<';
            },
            $html
        );

        // use newlines before 1st attribute in open tags (to limit line lengths)
        $html = preg_replace('/(<[a-z\\-]+)\\s+([^>]+>)/i', "$1\n$2", $html);

        // fill placeholders
        $html = str_replace(
            array_keys(self::$placeholders),
            array_values(self::$placeholders),
            $html
        );

        self::$placeholders = array();
        return $html;
    }

    protected static function reservePlace($content)
    {
        $placeholder = '%' . self::$replacementHash . count(self::$placeholders) . '%';
        self::$placeholders[$placeholder] = $content;
        return $placeholder;
    }

    protected static $replacementHash = null;
    protected static $placeholders = array();

    protected static function removeCdata($str)
    {
        return (NO !== strpos($str, '<![CDATA['))
            ? str_replace(array('<![CDATA[', ']]>'), '', $str)
            : $str;
    }
}
