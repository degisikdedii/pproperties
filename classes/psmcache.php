<?php
/**
 * This source file is subject to the commercial software
 * license agreement available through the world-wide-web at this URL:
 * https://psandmore.com/licenses/sla
 * If you are unable to obtain the license, please send an email to
 * support@psandmore.com and we will send you a copy immediately.
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration
class PSMCache
{
    private static $local = array();

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function isStored($key)
    {
        return array_key_exists($key, self::$local);
    }

    /**
     * @param string $key
     * @param string $value
     */
    public static function store($key, $value)
    {
        self::$local[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function retrieve($key)
    {
        return (array_key_exists($key, self::$local) ? self::$local[$key] : null);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function once($key)
    {
        $key = 'PSMCache::once:' . $key;
        if (self::isStored($key)) {
            return false;
        }
        self::store($key, true);
        return true;
    }

    /**
     * @param string $key
     */
    public static function clean($key)
    {
        if (strpos($key, '*') !== false) {
            $regexp = str_replace('\\*', '.*', preg_quote($key, '#'));
            foreach (array_keys(self::$local) as $key) {
                if (preg_match('#^' . $regexp . '$#', $key)) {
                    unset(self::$local[$key]);
                }
            }
        } else {
            unset(self::$local[$key]);
        }
    }
}
