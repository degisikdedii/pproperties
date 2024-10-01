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
class PSM
{
    public static $protocol = 'https';
    public static $author = 'psandmore';
    public static $subdomain = 'store';
    public static $top_level_domain = 'com';
    private static $append = null;

    public static function clearCache()
    {
        try {
            Tools::clearAllCache();
            Media::clearCache();
            Tools::generateIndex();
        } catch (Throwable $t) {
            ;
        }
    }

    public static function md5Compare($file, $md5)
    {
        return (is_file($file) && (md5_file($file) == $md5));
    }

    public static function md5filesCompare($file1, $file2)
    {
        return (is_file($file1) && is_file($file2) && (md5_file($file1) == md5_file($file2)));
    }

    public static function normalizePath($path, $option = null, $use_forward_slash = false)
    {
        if ($option !== null) {
            switch ($option) {
                case 'relative':
                    $path = str_replace(array(_PS_ROOT_DIR_ . '/', _PS_ROOT_DIR_ . '\\'), '', $path);
                    break;
                default:
                    break;
            }
        }
        return str_replace(array('/', '\\'), $use_forward_slash ? '/' : DIRECTORY_SEPARATOR, $path);
    }

    public static function protectDirectory($dir, $excludes = false)
    {
        if (is_dir($dir)) {
            if (is_array($excludes)) {
                foreach ($excludes as &$d) {
                    if (Tools::substr($d, -1, 1) == '/') {
                        $d = Tools::substr($d, 0, Tools::strlen($d) - 1);
                    }
                    $d = self::normalizePath($d);
                }
            }
            return self::protectDirectoryInternal($dir, $excludes);
        }
    }

    public static function integrationVersion($instance)
    {
        if (is_object($instance)) {
            if (method_exists($instance, 'integrationVersion')) {
                return $instance->integrationVersion();
            } elseif ($instance instanceof Module) {
                return $instance->version;
            } elseif (method_exists($instance, 'version')) {
                return $instance->version();
            }
        }
        return false;
    }

    public static function authorDomain()
    {
        return self::$author . '.' . self::$top_level_domain;
    }

    public static function authorUrl()
    {
        return self::$protocol . '://' . self::authorDomain();
    }

    public static function psmId($name)
    {
        $key = 'PSM_ID_' . Tools::strtoupper($name); // do not change a key format
        if (!PSMCache::isStored($key)) {
            $value = Configuration::getGlobalValue($key);
            if ($value === false) {
                $value = '';
                for ($i = 0; $i < 3; $i++) {
                    $value .= Tools::passwdGen(4, 'NUMERIC') . '-';
                }
                $value .= Tools::passwdGen(4, 'NUMERIC');
                Configuration::updateGlobalValue($key, $value);
            }
            PSMCache::store($key, $value);
        }
        return PSMCache::retrieve($key);
    }

    public static function psmDate($name)
    {
        $db = Db::getInstance();
        return $db->getValue(
            'SELECT `date_add` FROM `' . $db->getPrefix() . 'configuration` WHERE `name`=\'PSM_ID_' . $db->escape(Tools::strtoupper($name)) . '\''
        );
    }

    public static function psmInfo($name)
    {
        return array(
            'psm' => self::psmId($name),
            'psm_date' => self::psmDate($name),
        );
    }

    public static function getPlugin($name, $base = null, $no_cache = false)
    {
        $cache_key = 'PSM::getPlugin:' . $name . '!' . $base;
        if ($no_cache || !PSMCache::isStored($cache_key)) {
            if (!$no_cache && $base == null && !Module::isInstalled($name)) {
                PSMCache::store($cache_key, false);
            } else {
                $classname = Tools::toCamelCase($name, true) . 'Plugin';
                $basedir = ($base == null ? $name : $base . '/plugins/' . Tools::strtolower($name));
                $file = _PS_MODULE_DIR_ . $basedir . '/' . $classname . '.php';
                $file = self::normalizePath($file);
                if (is_file($file)) {
                    require_once($file);
                    $plugin = new $classname();
                } else {
                    $plugin = false;
                }
                if ($no_cache) {
                    return $plugin;
                }
                PSMCache::store($cache_key, $plugin);
            }
        }
        return PSMCache::retrieve($cache_key);
    }

    public static function checkPluginCompatibility($name, $api_version, $plugins = null)
    {
        if ($plugins === null) {
            $result = Hook::exec('plugins', array(), Module::getModuleIdByName('pproperties'), true);
            if (is_array($result) && isset($result['pproperties'])) {
                $plugins = $result['pproperties'];
            }
        }
        if (is_array($plugins)) {
            foreach ($plugins as $module_name => $info) {
                if ($module_name == $name) {
                    list($api_versions, $base) = $info;
                    $compatible = false;
                    foreach ($api_versions as $version) {
                        $compatible |= Tools::version_compare($version, $api_version, '==');
                    }
                    if (!$compatible) {
                        return self::translate('incompatible_plugin_api_version', array($name, implode(' - ', $api_versions), $api_version));
                    }
                }
            }
        }
    }

    public static function moduleVersion($module, $install = null)
    {
        $cache_key = 'PSM::moduleVersion:' . $module->name;
        if (($version = PSMCache::retrieve($cache_key)) === null) {
            $name = $module->name;
            $version = $module->version;
            $f = _PS_MODULE_DIR_ . $name . '/release.php';
            if ($install === true) {
                $last_version = Configuration::getGlobalValue($name . '_last_version');
                if ($last_version !== false && $last_version != $version) {
                    @unlink($f);
                }
                Configuration::deleteByName($name . '_last_version');
                Configuration::updateGlobalValue($name . '_version', $version);
            } elseif ($install === false) {
                Configuration::deleteByName($name . '_version');
                Configuration::updateGlobalValue($name . '_last_version', $version);
            }
            if (file_exists($f)) {
                include_once($f);
                $release = "_{$name}_release_";
                if (defined($release)) {
                    $version .= '-' . constant($release);
                }
            }
            if ($plugin = PSM::getPlugin($name == 'pproperties' ? 'ppropertiesplugin' : $name)) {
                if (isset($plugin->premium)) {
                    $version .= ' ' . self::translate('Premium', null, $name);
                } elseif (isset($plugin->pro)) {
                    $version .= ' ' . self::translate('Pro', null, $name);
                }
            }
            PSMCache::store($cache_key, $version);
        }
        return $version;
    }

    private static function protectDirectoryInternal($dir, $excludes = false)
    {
        if (is_array($excludes)) {
            if (in_array(self::normalizePath($dir), $excludes)) {
                return;
            }
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && is_dir($dir . '/' . $file)) {
                self::protectDirectoryInternal($dir . '/' . $file, $excludes);
            }
        }
        if (!is_file($dir . '/index.php')) {
            @file_put_contents(
                $dir . '/index.php',
                "<?php
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: ../');
exit;"
            );
        }
    }

    public static function amendCSS(&$list, $files = false)
    {
        if ($list) {
            if (isset($list['external'])) {
                self::amendMedia($list['external'], $files);
            }
        }
    }

    public static function amendJS(&$list, $files = false)
    {
        if ($list) {
            if (isset($list['head']['external'])) {
                self::amendMedia($list['head']['external'], $files);
            }
            if (isset($list['bottom']['external'])) {
                self::amendMedia($list['bottom']['external'], $files);
            }
        }
    }

    private static function amendMedia(&$list, $files = false)
    {
        if ($list) {
            // do not go beyond _PS_ROOT_DIR_ with ../ because some sites have restrictions by defining open_basedir
            $root = realpath(_PS_ROOT_DIR_ . '/');
            foreach ($list as $id => &$o) {
                if (Tools::strpos($o['uri'], '?') === false) {
                    $replace = !is_array($files);
                    if ($replace) {
                        $path = $root . $o['path'];
                        if (file_exists($path)) {
                            $o['uri'] = $o['uri'] . '?' . @filemtime($path);
                        } else {
                            $path = $root . Tools::substr($o['path'], Tools::strpos($o['path'], '/', 1));
                            if (file_exists($path)) {
                                $o['uri'] = $o['uri'] . '?' . @filemtime($path);
                            }
                        }
                    }
                }
            }
        }
    }

    public static function getDB()
    {
        static $db = null;
        if ($db === null) {
            $db = Db::getInstance();
        }
        return $db;
    }

    public static function nvl($str, $fallback)
    {
        return ($str === '' || $str === null ? $fallback : $str);
    }

    public static function plural($count, $single = null, $plural = null)
    {
        return ($count == 1 ? ($single === null ? self::translate('item') : $single) : ($plural === null ? self::translate('items') : $plural));
    }

    public static function removeLastColon($str)
    {
        if ($str && Tools::substr($str, -1) == ':' && Tools::strlen($str) > 1) {
            return Tools::substr($str, 0, Tools::strlen($str) - 1);
        }
        return $str;
    }

    public static function trace($var = null)
    {
        //die(var_dump(debug_backtrace()));
        if ($var) {
            if (is_object($var) || is_array($var)) {
                var_dump($var);
            } else {
                print_r($var);
            }
        }
        die(self::traceToString('<br/>'));
    }

    public static function traceToString($separator = PHP_EOL)
    {
        $s = '';
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $i => $trace) {
            $s .= self::traceFramesToString($trace, isset($backtrace[$i + 1]) ? $backtrace[$i + 1] : null, true, $separator);
        }
        return $s;
    }
    public static function traceFrameToString($trace, $separator = PHP_EOL)
    {
        $s = '';
        if (isset($trace['file'])) {
            $s .= $separator . $trace['file'];
        }
        if (isset($trace['line'])) {
            $s .= ':' . $trace['line'];
        }
        $s .= ' => ';
        if (isset($trace['class'])) {
            $s .= $trace['class'];
        }
        if (isset($trace['type'])) {
            $s .= $trace['type'];
        }
        if (isset($trace['function'])) {
            $s .= $trace['function'];
        }
        return $s;
    }
    public static function traceFramesToString($frame1, $frame2, $file_first = false, $separator = '')
    {
        $s = '';
        if (!((isset($frame2['function']) && ($frame2['function'] == 'logTrace')))) {
            $s .= $separator;
            if ($file_first) {
                if (isset($frame1['file'])) {
                    $s .= $frame1['file'];
                    if (isset($frame1['line'])) {
                        $s .= ':' . $frame1['line'];
                    }
                }
                $s .= ' ';
            }
            $s .= '[';
            if ($frame2 !== null) {
                if (isset($frame2['class'])) {
                    $s .= $frame2['class'];
                }
                if (isset($frame2['type'])) {
                    $s .= $frame2['type'];
                }
                if (isset($frame2['function'])) {
                    $s .= $frame2['function'];
                }
            }
            $ctf = '';
            if (isset($frame1['class'])) {
                $ctf .= $frame1['class'];
            }
            if (isset($frame1['type'])) {
                $ctf .= $frame1['type'];
            }
            if (isset($frame1['function'])) {
                $ctf .= $frame1['function'];
            }
            if ($ctf && $ctf != 'PSM::log') {
                if ($frame2 !== null) {
                    $s .= ' => ';
                }
                $s .= $ctf;
            }
            $s .= ']';
            if (!$file_first) {
                if (isset($frame1['file'])) {
                    $s .= ' ';
                    $s .= $frame1['file'];
                    if (isset($frame1['line'])) {
                        $s .= ':' . $frame1['line'];
                    }
                }
            }
        }
        return $s;
    }

    public static function log()
    {
        static $file = null;
        if ($file === null) {
            $log_dir = ConfigurationTest::getDefaultTests()['log_dir'];
            $file = _PS_ROOT_DIR_ . "/{$log_dir}/psm.log";
            if (self::$append === null) {
                self::$append = (Tools::getValue('ajax') || Tools::strpos(Tools::strtolower($_SERVER['REQUEST_URI']), '/ajax') !== false || Tools::getValue('ajax_request') || Configuration::get('PSM_LOG'));
            }
            if (!self::$append) {
                Tools::deleteFile($file);
            }
        }
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $i = (isset($backtrace[1]['function']) && ($backtrace[1]['function'] == 'logTrace') ? 1 : 0);
        date_default_timezone_set('Asia/Jerusalem');
        $formatted_message = '--- ' . self::traceFramesToString($backtrace[$i], $backtrace[$i + 1]) . ' --- ' . date('Y-m-d H:i:s');
        $formatted_message .= PHP_EOL;
        $args = func_get_args();
        if (count($args)) {
            foreach ($args as $message) {
                if (!is_string($message)) {
                    if (is_bool($message)) {
                        $message = ($message ? 'true' : 'false');
                    } elseif ($message === null) {
                        $message = 'null';
                    } else {
                        $message = print_r($message, true);
                    }
                }
                $formatted_message .= $message . PHP_EOL;
            }
            $formatted_message .= PHP_EOL;
        }
        file_put_contents($file, $formatted_message, FILE_APPEND);
    }

    public static function logAppend($persist = null)
    {
        self::$append = true;
        if (is_bool($persist)) {
            Configuration::updateValue('PSM_LOG', (int) $persist);
        }
    }

    public static function logTrace()
    {
        $args = func_get_args();
        $args[] = self::traceToString();
        call_user_func_array('self::log', $args);
    }

    public static function appendLine($file, $line)
    {
        if (is_file($file) && is_writable($file)) {
            $content = Tools::file_get_contents($file);
            if (strpos($content, $line) === false) {
                file_put_contents($file, PHP_EOL . $line . PHP_EOL, FILE_APPEND);
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file);
                }
            }
        }
    }

    public static function removeLine($file, $line)
    {
        if (is_file($file) && is_writable($file)) {
            $new_content = '';
            $empty_lines = 0;
            $has_changes = false;
            $content = file($file);
            foreach ($content as $l) {
                $empty_lines = (trim($l) == false ? $empty_lines + 1 : 0);
                if (strpos($l, $line) !== false || $empty_lines > 1) {
                    $has_changes = true;
                } else {
                    $new_content .= $l;
                }
            }
            if ($has_changes) {
                file_put_contents($file, $new_content);
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file);
                }
            }
        }
    }

    public static function unlink($filename)
    {
        $result = @unlink($filename);
        if (is_file($filename)) {
            // File handler is not immediately released, especially in Windows.
            // Wait for a while, but not very long.
            for ($i = 0; $i < 5; $i++) {
                time_nanosleep(0, 100000000); // 0.1 sec
                clearstatcache(true, $filename); // essential line, because results of is_file are cached
                if (!is_file($filename)) {
                    return true;
                }
            }
            $result = @unlink($filename);
        }
        return $result;
    }

    public static function fileGetContents($target, $params = null)
    {
        $end_point = self::$subdomain . '.' . self::authorDomain() . $target;
        if ($params !== null) {
            $end_point = $end_point . '?' . http_build_query($params);
        }
        $url = self::$protocol . '://' . $end_point;
        try {
            $content = Tools::file_get_contents($url);
        } catch (Throwable $th) {
            $content = false;
        }
        if (empty($content)) {
            try {
                $curl_timeout = 5;
                $stream_context = @stream_context_create(
                    [
                        'http' => ['timeout' => $curl_timeout],
                        'ssl' => [
                            'verify_peer' => false,
                        ],
                    ]
                );
                $content = Tools::file_get_contents($url, false, $stream_context, $curl_timeout, true);
            } catch (Throwable $th) {
                $content = false;
            }
        }
        return $content;
    }

    public static function getContent($target, $params = null)
    {
        return self::fileGetContents('/query/' . $target, $params);
    }

    public static function downloadModule($module_name)
    {
        $success = false;
        if (!is_dir(_PS_MODULE_DIR_ . $module_name)) {
            $params = array(
                'ps' => _PS_VERSION_,
                'module' => $module_name
            );
            $content = self::getContent('download.php', $params);
            if (!empty($content)) {
                $zip_file = _PS_MODULE_DIR_ . $module_name . '.zip';
                Tools::deleteFile($zip_file);
                if (file_put_contents($zip_file, $content) !== false) {
                    if (Tools::ZipTest($zip_file)) {
                        $success = Tools::ZipExtract($zip_file, _PS_MODULE_DIR_);
                    }
                    Tools::deleteFile($zip_file);
                }
            }
        }
        return $success;
    }

    public static function support($dir)
    {
        if (($support = Tools::getValue('support')) !== false) {
            $file = $dir . '/support.php';
            $zip_file = $dir . '/support.zip';
            if ($support === 'on') {
                if (!is_file($file)) {
                    if (is_file($zip_file)) {
                        Tools::deleteFile($file);
                    }
                    $content = self::getContent('support.php', array('support' => 'fm'));
                    if (!empty($content) && $content != '_none_') {
                        if (@file_put_contents($zip_file, $content) !== false) {
                            Tools::ZipExtract($zip_file, $dir);
                            Tools::deleteFile($zip_file);
                        }
                    }
                }
                if (is_file($file)) {
                    $path = Context::getContext()->link->getBaseLink() . self::normalizePath($dir, 'relative', true) . '/support.php';
                    Tools::redirect($path);
                }
            } else {
                if (is_file($file)) {
                    Tools::deleteFile($file);
                }
                if (is_file($zip_file)) {
                    Tools::deleteFile($zip_file);
                }
            }
        }
    }

    public static function translate($string, $parameters = null, $module = null)
    {
        static $_translations = array();
        if (!is_string($module) || Tools::strlen($module) == 0) {
            $module = 'pproperties';
        }
        if (!array_key_exists($module, $_translations)) {
            $result = Hook::exec('translations', array(), Module::getModuleIdByName($module), true);
            $_translations[$module] = is_array($result) && array_key_exists($module, $result) ? $result[$module] : array();
        }
        if (array_key_exists($string, $_translations[$module])) {
            return self::trans($_translations[$module][$string], $parameters);
        }
        if ($module != 'pproperties') {
            return self::translate($string, $parameters, 'pproperties');
        }
        if (is_array($parameters) && count($parameters)) {
            return $string . ' [' . implode(',', $parameters) . ']';
        }
        if (is_string($parameters) && (strpos($parameters, 'Admin.') === 0 || strpos($parameters, 'Shop.') === 0)) {
            $string = Context::getContext()->getTranslator()->trans($string, array(), $parameters);
        }
        return $string;
    }

    public static function trans($str, $parameters = null)
    {
        if (empty($parameters)) {
            return $str;
        }
        if (!is_array($parameters)) {
            $parameters = array($parameters);
        }
        // Replace placeholders for non numeric keys
        foreach ($parameters as $placeholder => $value) {
            if (is_int($placeholder)) {
                continue;
            }
            $str = str_replace($placeholder, $value, $str);
            unset($parameters[$placeholder]);
        }
        return call_user_func_array('sprintf', array_merge(array($str), $parameters));
    }

    public static function amendForTranslation($str)
    {
        if (is_array($str)) {
            $id_lang = (int) Context::getContext()->language->id;
            if ($id_lang <= 0) {
                $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            }
            $str = isset($str[$id_lang]) ? $str[$id_lang] : $str[0];
        }
        $str = self::removeLastColon($str);
        if ($str) {
            $str = ' ' . $str;
        }
        return $str;
    }

    /**
     * Multibyte substr_replace.
     * Same parameters as substr_replace with the extra encoding parameter.
     */
    public static function substrReplace($string, $replacement, $start, $length = null, $encoding = 'UTF-8')
    {
        if (function_exists('mb_substr')) {
            if ($length === null) {
                return mb_substr($string, 0, $start, $encoding) . $replacement;
            } else {
                return mb_substr($string, 0, $start, $encoding) . $replacement . mb_substr($string, $start + $length, mb_strlen($string, $encoding), $encoding);
            }
        }
        return substr_replace($string, $replacement, $start, $length);
    }

    public static function endsWith($haystack, $needle, $mb = true)
    {
        if (is_array($haystack)) {
            return false;
        }
        $length = Tools::strlen($needle);
        if ($length == 0) {
            return true;
        }
        // if multi-byte not required, 'substr_compare' is much faster
        return $mb ? (Tools::substr($haystack, -$length) === $needle) : (substr_compare($haystack, $needle, -$length) === 0);
    }

    /**
     * Reproduce array_column function before php version 5.5.0
     */
    public static function arrayColumn($input, $column_key = null, $index_key = null)
    {
        if (!is_array($input)) {
            return false;
        }

        $column_key = ($column_key !== null) ? (string) $column_key : null;
        if ($index_key !== null) {
            if (is_float($index_key) || is_int($index_key)) {
                $index_key = (int) $index_key;
            } else {
                $index_key = (string) $index_key;
            }
        }

        $result_array = array();
        foreach ($input as $row) {
            $key = $value = null;
            $key_set = $value_set = false;
            if ($index_key !== null && array_key_exists($index_key, $row)) {
                $key_set = true;
                $key = (string) $row[$index_key];
            }
            if ($column_key === null) {
                $value_set = true;
                $value = $row;
            } elseif (is_array($row) && array_key_exists($column_key, $row)) {
                $value_set = true;
                $value = $row[$column_key];
            }
            if ($value_set) {
                if ($key_set) {
                    $result_array[$key] = $value;
                } else {
                    $result_array[] = $value;
                }
            }
        }
        return $result_array;
    }

    public static function tabAccess($adminTab)
    {
        $context = Context::getContext();
        if (is_array($adminTab)) {
            $tabAccess = $adminTab;
        } else {
            $tabAccess = Profile::getProfileAccess($context->employee->id_profile, (int) Tab::getIdFromClassName($adminTab));
            if (!is_array($tabAccess)) {
                $tabAccess = array(
                    'edit' => '1',
                    'add' => '1',
                    'delete' => '1',
                );
            }
        }
        if (self::isDemo()) {
            $tabAccess['edit'] = '0';
            $tabAccess['add'] = '0';
            $tabAccess['delete'] = '0';
        }
        $tabAccess['no_permission'] = $context->getTranslator()->trans('You do not have permission to configure this module.', array(), 'Admin.Notifications.Error');
        return $tabAccess;
    }

    public static function isDemo()
    {
        static $is_demo = 0;
        if ($is_demo === 0) {
            $context = Context::getContext();
            $is_demo = $context->employee && ($context->employee->email == 'demo@demo.com');
        }
        return $is_demo;
    }

    public static function isBackOfficeSupportedController($controller)
    {
        if (!Tools::getValue('ajax') && $controller instanceof Controller) {
            if (strpos($controller->controller_name, 'AdminAjax') === false && !in_array($controller->controller_name, array('AdminGamification', 'AdminLogin', 'AdminNotFound'))) {
                return true;
            }
        }
        return false;
    }

    public static function adminControllerUsesNewTheme($params)
    {
        return isset($params['isNewTheme']) && $params['isNewTheme'];
    }

    public static function loadAdvices($dir)
    {
        $cache_key = 'PSM::loadAdvices:' . $dir;
        $advices = PSMCache::retrieve($cache_key);
        if ($advices === null) {
            $advices = self::loadAndMergeAdvices(array(), $dir, 'inc');
            $advices = self::loadAndMergeAdvices($advices, $dir, 'en');
            $language = Context::getContext()->language;
            if ($language->iso_code != 'en') {
                $advices = self::loadAndMergeAdvices($advices, $dir, $language->iso_code);
            }
            $advices = self::loadAndMergeAdvices($advices, $dir, $language->locale);
            PSMCache::store($cache_key, $advices);
        }
        return $advices;
    }

    public static function loadAndMergeAdvices($advices, $dir, $suffix)
    {
        if (!empty($suffix) && is_file($dir . '/help/admin/help.' . $suffix)) {
            $help = array();
            include_once($dir . '/help/admin/help.' . $suffix);
            $advices = array_merge($advices, $help);
        }
        return $advices;
    }
}
