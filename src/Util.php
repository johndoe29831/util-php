<?php

namespace Jd29;

final class Util
{
    public static function hello()
    {
        echo "Hello World!";
    }

    /**
     * python startsWith
     * @see https://qiita.com/satoshi-nishinaka/items/f15ccbcf8b8f91c1e2dd
     *
     * @param [type] $haystack
     * @param [type] $needle
     * @param boolean $case
     * @return void
     */
    static public function startsWith($haystack, $needle, $case = true)
    {
        if ($case) {
            return (strcmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
        }
        return (strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
    }

    /**
     * python endsWith
     * @see https://qiita.com/satoshi-nishinaka/items/f15ccbcf8b8f91c1e2dd
     *
     * @param [type] $haystack
     * @param [type] $needle
     * @param boolean $case
     * @return void
     */
    static public function endsWith($haystack, $needle, $case = true)
    {
        if ($case) {
            return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
        }
        return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
    }

    /**
     * 日時が正しいか検証
     *
     * @param [type] $date
     * @param string $format
     * @return void
     */
    static function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}
