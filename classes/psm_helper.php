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
class PSMHelper
{
    public static function ppSetupExtraModulesDir($relative = false)
    {
        return ($relative ? 'modules/' : _PS_MODULE_DIR_) . 'pproperties/setup/extra/modules';
    }

    public static function ppSetupExtraModulesVars($module)
    {
        $vars = array();
        $vars['module'] = $module;
        $vars['root'] = self::ppSetupExtraModulesDir();
        $vars['base'] = $vars['root'] . '/' . $module->name;
        $vars['dirname'] = $vars['base'] . '/' . $module->version;
        $vars['ppsetup'] = $vars['dirname'] . '/ppsetup.php';
        return $vars;
    }

    public static function ppSetupInstance($module, $name = '', $file = '')
    {
        if (is_array($module)) {
            $vars = $module;
            $name = '';
            $file = $module['ppsetup'];
            $module = $module['module'];
        }
        if ($name == '' && $module->name != 'pproperties') {
            $name = $module->name;
        }
        if ($file == '') {
            $file = _PS_MODULE_DIR_ . Tools::strtolower($name != '' ? $name : $module->name) . '/ppsetup.php';
        }
        $file = str_replace('\\', '/', $file);
        $cache_key = 'psm_helper::ppSetupInstance:' . $module->name . ':' . $name . '!' . $file;
        if (!Cache::isStored($cache_key)) {
            $classname = 'PPSetup' . ($name != '' ? Tools::toCamelCase($name, true) : '');
            if (isset($vars) && !file_exists($file)) {
                self::ppropertiesIntegrationCreate($vars);
            }
            if (is_file($file)) {
                if ($name != '' && $name != 'pproperties') {
                    require_once(_PS_MODULE_DIR_ . 'pproperties/ppsetup.php');
                }
                require_once($file);
                $result = new $classname($module);
                $result->fullpath = str_replace(array('/', '\\'), '/', $file);
                $result->vars = (isset($vars) ? $vars : null);
            } else {
                $result = false;
            }
            Cache::store($cache_key, $result);
        }
        return Cache::retrieve($cache_key);
    }

    public static function ppropertiesIntegration($vars, $install, &$errors = null)
    {
        Tools::deleteDirectory($vars['base']);
        if ($install) {
            if (file_exists($vars['base'])) {
                $error = PSM::translate('error_delete_directory', array(PSM::normalizePath($vars['base'], 'relative')));
                if ($errors !== null) {
                    $errors[] = $error;
                }
                return array('error_delete_directory' => $error);
            }
            self::ppropertiesIntegrationCreate($vars);
            PSM::protectDirectory($vars['base']);
            if (!is_file($vars['ppsetup'])) {
                $error = PSM::translate('error_create_file', array(PSM::normalizePath($vars['ppsetup'], 'relative')));
                if ($errors !== null) {
                    $errors[] = $error;
                }
                return array('error_create_file' => $error);
            }
        }
        return true;
    }

    private static function ppropertiesIntegrationCreate($vars)
    {
        if (!file_exists($vars['dirname'])) {
            mkdir($vars['dirname'], 0755, true);
        }
        Tools::copy(_PS_MODULE_DIR_ . $vars['module']->name . '/ppsetup.php', $vars['ppsetup']);
    }

    public static function psmsetupReplaceStringsRegexPregReplaceCallback($pattern, $content, &$replacement, &$cnt, &$middle, &$end, &$md5)
    {
        return preg_replace_callback(
            $pattern,
            function ($matches) use (&$pattern, &$replacement, &$cnt, &$middle, &$end, &$md5) {
                $mark = rand(1000, 9999) . ':';
                $start = sprintf(PSMSetup30::REGEX_START_SIGNATURE, $mark, $md5) . "\n";
                $match = reset($matches);
                $replace = preg_replace($pattern, $replacement, $match);
                $cnt++;
                return $start . $replace . $middle . $mark . bin2hex($match) . $end;
            },
            $content
        );
    }
}
