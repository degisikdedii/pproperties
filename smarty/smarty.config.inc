<?php
/**
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

if (!defined('HHVM_VERSION') && version_compare(phpversion(), '7.1', '>=')) {
    if (($serialize_precision = ini_get('serialize_precision')) && !empty($serialize_precision) && ($serialize_precision > 16 || $serialize_precision == -1)) {
        ini_set('serialize_precision', 16); // on some systems float precision > 16 causes strange values appear in json_encode
    }
}

function ppSmartyRegister($smarty)
{
    smartyRegisterFunction($smarty, 'modifier', 'psmtrans', 'smartyModifierPsmtrans');
    smartyRegisterFunction($smarty, 'modifier', 'icp', 'smartyModifierIcp');
    smartyRegisterFunction($smarty, 'modifier', 'pp_safeoutput', 'smartyModifierPPSafeoutput');
    smartyRegisterFunction($smarty, 'modifier', 'pp_safeoutput_lenient', 'smartyModifierPPSafeoutputLenient');
    smartyRegisterFunction($smarty, 'modifier', 'pp', 'smartyModifierPP');
    smartyRegisterFunction($smarty, 'modifier', 'psm', 'smartyModifierPSM');
    smartyRegisterFunction($smarty, 'modifier', 'pp_once', 'smartyModifierPPOnce');
    smartyRegisterFunction($smarty, 'modifier', 'pp_jscript_inline', 'smartyModifierPPJsScriptInline');
    smartyRegisterFunction($smarty, 'modifier', 'ucfirst', 'smartyModifierUcfirst');
    smartyRegisterFunction($smarty, 'modifier', 'formatQty', 'smartyModifierFormatQty');
    smartyRegisterFunction($smarty, 'modifier', 'displayQty', 'smartyModifierDisplayQty');

    smartyRegisterFunction($smarty, 'function', 'ppOnce', array('PSMCache', 'once'));
    smartyRegisterFunction($smarty, 'function', 'ppAssign', array('PP', 'smartyPPAssign'));
    smartyRegisterFunction($smarty, 'function', 'formatQty', array('PP', 'smartyFormatQty'));
    smartyRegisterFunction($smarty, 'function', 'convertQty', array('PP', 'convertQty'));
    smartyRegisterFunction($smarty, 'function', 'displayQty', array('PP', 'smartyDisplayQty'));
    smartyRegisterFunction($smarty, 'function', 'smartpriceText', 'smartyFunctionSmartpriceText');

    // We need to get the old instance of the LazyRegister because some of the functions are already defined and we need to check in the old one first.
    $original_lazy_register = SmartyLazyRegister::getInstance($smarty);
    // We restore some functions from Smarty that have been removed in PrestaShop 1.7 because new themes don't use them.
    if (!isset($smarty->registered_plugins['function']['convertPrice'])) {
        smartyRegisterFunction($smarty, 'function', 'convertPrice', array('Product', 'convertPrice'), true, $original_lazy_register);
    }
    if (!isset($smarty->registered_plugins['function']['convertPriceWithCurrency'])) {
        smartyRegisterFunction($smarty, 'function', 'convertPriceWithCurrency', array('Product', 'convertPriceWithCurrency'), true, $original_lazy_register);
    }
    if (!isset($smarty->registered_plugins['function']['displayWtPrice'])) {
        smartyRegisterFunction($smarty, 'function', 'displayWtPrice', array('Product', 'displayWtPrice'), true, $original_lazy_register);
    }
    if (!isset($smarty->registered_plugins['function']['displayWtPriceWithCurrency'])) {
        smartyRegisterFunction($smarty, 'function', 'displayWtPriceWithCurrency', array('Product', 'displayWtPriceWithCurrency'), true, $original_lazy_register);
    }
    if (!isset($smarty->registered_plugins['function']['displayPrice'])) {
        smartyRegisterFunction($smarty, 'function', 'displayPrice', array('Tools', 'displayPriceSmarty'), true, $original_lazy_register);
    }
}

ppSmartyRegister($smarty);

function smartyModifierPsmtrans($string, $parameters = null, $module = null)
{
    return \PSM::translate($string, $parameters, $module);
}

function smartyModifierIcp($product, $id = false, $mode = 'css')
{
    $icp = 'icp-' . ($id === false ? (int)$product : (int)$product[$id]);
    if ($mode == 'css' && ($css = smartyModifierPP($product, 'css', 'left'))) {
        $icp .= $css;
    }
    return $icp;
}

function smartyModifierPPSafeoutput($string, $type = null)
{
    switch ($type) {
        case 'html':
            return PP::safeOutput($string);
        case 'js':
        case 'javascript':
            return PP::safeOutputJS($string);
        case 'value':
            return PP::safeOutputValue($string);
        case 'htmlspecialchars':
            return htmlspecialchars(htmlspecialchars($string, ENT_QUOTES | ENT_HTML401, 'UTF-8'), ENT_COMPAT, 'UTF-8');
        default:
            return $string;
    }
}

function smartyModifierPPSafeoutputLenient($string, $type = null)
{
    switch ($type) {
        case 'html':
            return PP::safeOutputLenient($string);
        case 'js':
        case 'javascript':
            return PP::safeOutputLenientJS($string);
        case 'value':
            return PP::safeOutputLenientValue($string);
        default:
            return $string;
    }
}

function smartyModifierPP($product, $mode, $wrap = true)
{
    $key = 'pp_' . $mode;
    if (\PP::is_array($product)) {
        if (isset($product[$key])) {
            $text = $product[$key];
        }
    }
    if (!isset($text)) {
        $properties = PP::getProductProperties($product);
        if (isset($properties[$key])) {
            $text = $properties[$key];
        }
    }
    return (isset($text) && $text != '' ? ($wrap === true ? PP::wrap($text, $key, 'span', null, ['safeotput' => true]) : ($wrap == 'left' ? ' ' . $text : ($wrap == 'right' ? $text . ' ' : $text))) : '');
}

function smartyModifierPSM($value, $type, $param = null)
{
    return PP::smartyModifierPSM($value, $type, $param);
}

function smartyModifierPPOnce($string, $key = null)
{
    return PSMCache::once($key ?: $string) ? $string : '';
}

function smartyModifierPPJsScriptInline($string, $once = false, $check_for_pp = true)
{
    return PP::jsScriptInline($string, $once, $check_for_pp);
}

function smartyModifierUcfirst($value)
{
    return Tools::ucfirst($value);
}

function smartyModifierFormatQty($quantity, $properties = null)
{
    return PP::smartyFormatQty(array('quantity' => $quantity, 'properties' => $properties));
}

function smartyModifierDisplayQty($quantity, $m = null)
{
    return PP::smartyDisplayQty(array('quantity' => $quantity, 'm' => $m));
}

function smartyFunctionSmartpriceText($params, &$smarty)
{
    /*[HOOK ppropertiessmartprice]*/
    return '';
}
