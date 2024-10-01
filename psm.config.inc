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

if (!defined('PSM_DIR') && version_compare(phpversion(), '7.1', '>=')) {
    define('PSM_DIR', dirname(__FILE__) . '/');
    define('PSM_CLASSES_DIR', PSM_DIR . 'classes/');
    define('PSM_CLASSES_CORE_DIR', PSM_CLASSES_DIR . 'core/');
    spl_autoload_register(function ($class) {
        static $_VERSION_ = null;
        static $classes = null;
        static $core = null;
        if ($_VERSION_ === null) {
            $_VERSION_ = substr(_PS_VERSION_, 0, strrpos(_PS_VERSION_, '.'));
            $classes = array(
                'PrestaShop\\Module\\Pproperties\\PSMExtension' => PSM_DIR . 'src/PSMExtension.php',
                'PP' => PSM_CLASSES_DIR . 'pp.php',
                'PSM' => PSM_CLASSES_DIR . 'psm.php',
                'PSMCache' => PSM_CLASSES_DIR . 'psmcache.php',
                'PSMSetup30' => PSM_CLASSES_DIR . 'psmsetup30.php',
                'PSMHelper' => PSM_CLASSES_DIR . 'psm_helper.php',
                'PSMHelperIntegrate' => PSM_CLASSES_DIR . 'psm_helper_integrate.php',
            );
            $core = array(
                'CartBase' => 'classes/CartBase.php',
                'PrestaShop\\PrestaShop\\Core\\Cart\\CartRowBase' => 'classes/CartRowBase.php',
                'ProductBase' => 'classes/ProductBase.php',
                'CartControllerBase' => 'controllers/front/CartControllerBase.php',
                'AdminCartsControllerBase' => 'controllers/admin/AdminCartsControllerBase.php',
                'AdminOrdersControllerBase' => 'controllers/admin/AdminOrdersControllerBase.php',
            );
        }
        if (array_key_exists($class, $classes)) {
            include_once $classes[$class];
            return true;
        } else {
            if (array_key_exists($class, $core)) {
                $filename = PSM_CLASSES_CORE_DIR . _PS_VERSION_ . '/' . $core[$class];
                if (!is_file($filename)) {
                    $filename = PSM_CLASSES_CORE_DIR . $_VERSION_ . '/' . $core[$class];
                    if (!is_file($filename)) {
                        $filename = PSM_CLASSES_CORE_DIR . $core[$class];
                    }
                }
                include_once $filename;
                return true;
            }
        }
        return false;
    });

    function psm_strlen($string)
    {
        return strlen($string);
    }

    function psm_ser($value)
    {
        return serialize($value);
    }

    function psm_unser($str, array $options = [])
    {
        return unserialize($str, $options);
    }
}
