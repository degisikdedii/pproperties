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

use PrestaShopBundle\Utils\FloatParser;
use PrestaShop\PrestaShop\Core\Product\ProductPresentationSettings;

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration
class PP
{
    const PP_TEMPLATE_VERSION = 1;
    const PP_MS_DEFAULT       = 0;
    const PP_MS_METRIC        = 1;
    const PP_MS_NON_METRIC    = 2; /* imperial/US */

    private static $cache_templates = array();
    private static $cache_product_properties = array();
    private static $cache_product_template_id = array();
    private static $pricerounding_supported = null;
    private static $priceComputingPrecision = null;

    public static function objectModelGetDefinition($class, &$definition)
    {
        static $def = array(
            'Combination' => array(
                'minimal_quantity' => array('type' => ObjectModelCore::TYPE_INT, 'shop' => true, 'validate' => 'validateProductQuantity'), // overrides parent definition
                'minimal_quantity_fractional' => array('type' => ObjectModelCore::TYPE_FLOAT, 'shop' => true, 'validate' => 'isUnsignedFloat')
            ),
            'OrderDetail' => array(
                'id_cart_product' => array('type' => ObjectModelCore::TYPE_INT, 'validate' => 'isUnsignedId'),
                'product_quantity_fractional' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
                'product_quantity_in_stock' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isFloat'),
                'product_quantity_return' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
                'product_quantity_refunded' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
                'product_quantity_reinjected' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
                'pp_data_type' => array('type' => ObjectModelCore::TYPE_STRING),
                'pp_data' => array('type' => ObjectModelCore::TYPE_STRING),
            ),
            'Product' => array(
                'minimal_quantity' => array('type' => ObjectModelCore::TYPE_INT, 'shop' => true, 'validate' => 'validateProductQuantity'), // overrides parent definition
                'minimal_quantity_fractional' => array('type' => ObjectModelCore::TYPE_FLOAT, 'shop' => true, 'validate' => 'isUnsignedFloat'),
                'id_pp_template' => array('type' => ObjectModelCore::TYPE_INT)
            ),
            'SpecificPrice' => array(
                'from_quantity' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat', 'required' => true), // overrides parent definition
            ),
            'Stock' => array(
                'physical_quantity_remainder' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
                'usable_quantity_remainder' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat')
            ),
            'StockAvailable' => array(
                'quantity_remainder' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
            ),
            'StockMvt' => array(
                'physical_quantity_remainder' => array('type' => ObjectModelCore::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
            ),
        );
        if (Tools::substr($class, -4) == 'Core') {
            $class = Tools::substr($class, 0, -4);
        }
        static $pproperties_plugin = null;
        if ($pproperties_plugin === null) {
            $pproperties_plugin = static::getPpropertiesPlugin();
            if ($pproperties_plugin) {
                $pproperties_plugin->objectModelGetDefinition($def);
            }
        }
        static $smartprice_plugin = null;
        if ($smartprice_plugin === null) {
            $smartprice_plugin = static::getSmartpricePlugin();
            if ($smartprice_plugin) {
                $smartprice_plugin->objectModelGetDefinition($def);
            }
        }
        if (array_key_exists($class, $def)) {
            $definition['fields'] = array_merge($definition['fields'], $def[$class]);
        }
    }

    public static function getComputingPrecision()
    {
        if (self::$priceComputingPrecision === null) {
            self::$priceComputingPrecision = version_compare(_PS_VERSION_, '1.7.7.0', '<') ? _PS_PRICE_COMPUTE_PRECISION_ :  Context::getContext()->getComputingPrecision();
        }
        return self::$priceComputingPrecision;
    }

    public static function floatParser()
    {
        static $float_parser = null;
        if ($float_parser === null) {
            $float_parser = new FloatParser();
        }
        return $float_parser;
    }

    public static function resolveLanguageId($o = null)
    {
        $id_lang = (is_numeric($o) ? (int) $o : ((static::is_array($o) && isset($o['id_lang']) ? (int) $o['id_lang'] : null)));
        if ($id_lang === null || $id_lang <= 0) {
            $id_lang = (int) Context::getContext()->language->id;
        }
        if ($id_lang <= 0) {
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        }
        return $id_lang;
    }

    public static function obtainQty($product)
    {
        return (int) (isset($product['cart_quantity']) ? $product['cart_quantity'] : $product['product_quantity']);
    }

    public static function obtainQtyFractional($product)
    {
        return (float) (isset($product['cart_quantity_fractional']) ? $product['cart_quantity_fractional'] : $product['product_quantity_fractional']);
    }

    public static function resolveQty($quantity, $quantity_fractional = false)
    {
        if ($quantity_fractional === false) {
            // assume first parameter is a product array
            // first calculate $quantity_fractional, variable $quantity is later re-assigned
            $quantity_fractional = static::obtainQtyFractional($quantity);
            $quantity = static::obtainQty($quantity);
        }
        return ((float) $quantity_fractional > 0 ? (int) $quantity * (float) $quantity_fractional : (int) $quantity);
    }

    public static function explodeQty($quantity)
    {
        $q = (int) floor((float) $quantity);
        $qr = Tools::ps_round((float) $quantity - $q, 6); // database precision = 6
        return array($q, $qr);
    }

    public static function productMinQty($db_minimum_quantity, $db_minimum_quantity_fractional, $pproperties)
    {
        return static::calcProductMinQty($db_minimum_quantity, $db_minimum_quantity_fractional, $pproperties, 'pp_minimum_quantity');
    }

    public static function productBoMinQty($db_minimum_quantity, $db_minimum_quantity_fractional, $pproperties)
    {
        return static::calcProductMinQty($db_minimum_quantity, $db_minimum_quantity_fractional, $pproperties, 'pp_bo_minimum_quantity');
    }

    protected static function calcProductMinQty($db_minimum_quantity, $db_minimum_quantity_fractional, $pproperties, $key)
    {
        $qty_policy = $pproperties['qty_policy'];
        if ($qty_policy === 0) {
            return (int) ((int) $db_minimum_quantity > 1 ? $db_minimum_quantity : $pproperties[$key]);
        } elseif ($qty_policy == 1) {
            return (int) (((int) $db_minimum_quantity_fractional > 0 ? $db_minimum_quantity_fractional : $pproperties[$key]));
        }
        return (float) ((float) $db_minimum_quantity_fractional > 0 ? $db_minimum_quantity_fractional : $pproperties[$key]);
    }

    public static function productNoAttributeMinQty($id_product, $pproperties)
    {
        $cache_key = 'PP::productNoAttributeMinQty:' . $id_product;
        if (($qty = PSMCache::retrieve($cache_key)) === null) {
            $row = Db::getInstance()->getRow(
                'SELECT `minimal_quantity`, `minimal_quantity_fractional`
                FROM `' . _DB_PREFIX_ . 'product_shop` ps
                WHERE `id_shop` = ' . (int) Context::getContext()->shop->id . '
                AND `id_product` = ' . (int) $id_product
            );
            PSMCache::store($cache_key, $qty = is_array($row) ? static::productMinQty($row['minimal_quantity'], $row['minimal_quantity_fractional'], $pproperties) : static::productMinQty(0, 0, $pproperties));
        }
        return $qty;
    }

    public static function productAttributeMinQty($id_product_attribute, $pproperties)
    {
        if (!Combination::isFeatureActive()) {
            return 1;
        }
        $cache_key = 'PP::productAttributeMinQty:' . $id_product_attribute;
        if (($qty = PSMCache::retrieve($cache_key)) === null) {
            $row = Db::getInstance()->getRow(
                'SELECT `minimal_quantity`, `minimal_quantity_fractional`
                FROM `' . _DB_PREFIX_ . 'product_attribute_shop` pas
                WHERE `id_shop` = ' . (int) Context::getContext()->shop->id . '
                AND `id_product_attribute` = ' . (int) $id_product_attribute
            );
            PSMCache::store($cache_key, $qty = is_array($row) ? static::productMinQty($row['minimal_quantity'], $row['minimal_quantity_fractional'], $pproperties) : static::productMinQty(0, 0, $pproperties));
        }
        return $qty;
    }

    public static function productAttributeMaxQty($id_product_attribute, $pproperties)
    {
        if (!Combination::isFeatureActive()) {
            return 0;
        }
        $cache_key = 'PP::productAttributeMaxQty:' . $id_product_attribute;
        if (($qty = PSMCache::retrieve($cache_key)) === null) {
            $row = static::getPpropertiesPlugin() ?
                Db::getInstance()->getRow(
                    'SELECT `maximum_quantity`
                    FROM `' . _DB_PREFIX_ . 'product_attribute_shop` pas
                    WHERE `id_shop` = ' . (int) Context::getContext()->shop->id . '
                    AND `id_product_attribute` = ' . (int) $id_product_attribute
                ) : false;
            PSMCache::store($cache_key, $qty = static::productMaxQty(is_array($row) ? $row['maximum_quantity'] : 0, $pproperties));
        }
        return $qty;
    }

    public static function productAttributeQtyStep($id_product_attribute, $pproperties)
    {
        if (!Combination::isFeatureActive()) {
            return 0;
        }
        $cache_key = 'PP::productAttributeQtyStep:' . $id_product_attribute;
        if (($qty = PSMCache::retrieve($cache_key)) === null) {
            $row = static::getPpropertiesPlugin() ?
                Db::getInstance()->getRow(
                    'SELECT `quantity_step`
                    FROM `' . _DB_PREFIX_ . 'product_attribute_shop` pas
                    WHERE `id_shop` = ' . (int) Context::getContext()->shop->id . '
                    AND `id_product_attribute` = ' . (int) $id_product_attribute
                ) : false;
            PSMCache::store($cache_key, $qty = static::productQtyStep(is_array($row) ? $row['quantity_step'] : 0, $pproperties));
        }
        return $qty;
    }

    public static function calcDefaultQty($qty, $pproperties)
    {
        $default_quantity = $pproperties['pp_default_quantity'];
        if ($qty > $default_quantity) {
            return $qty;
        }
        return $default_quantity;
    }

    public static function productMaxQty($db_maximum_quantity, $pproperties)
    {
        $qty_policy = $pproperties['qty_policy'];
        if ($qty_policy === 0 || $qty_policy === 1) {
            return (int) ((int) $db_maximum_quantity > 0 ? $db_maximum_quantity : $pproperties['pp_maximum_quantity']);
        }
        return (float) ((float) $db_maximum_quantity > 0 ? $db_maximum_quantity : $pproperties['pp_maximum_quantity']);
    }

    public static function productQtyStep($db_quantity_step, $pproperties)
    {
        $qty_policy = $pproperties['qty_policy'];
        if ($qty_policy === 0 || $qty_policy === 1) {
            return (int) ((int) $db_quantity_step > 0 ? $db_quantity_step : $pproperties['pp_qty_step']);
        }
        return (float) ((float) $db_quantity_step > 0 ? $db_quantity_step : $pproperties['pp_qty_step']);
    }

    public static function getProductProperty($product, $key, $id_lang = null)
    {
        $properties = static::getProductProperties($product, $id_lang);
        if (array_key_exists($key, $properties)) {
            return $properties[$key];
        }
        return null;
    }

    public static function getProductProperties($product, $id_lang = null)
    {
        $ms_data = ($pproperties_plugin = static::getPpropertiesPlugin()) ? $pproperties_plugin->getProductMeasurementSystemData($product) : null;
        return static::getProductPropertiesByTemplateId(static::getProductTemplateId($product), $id_lang, $ms_data);
    }

    public static function assignProductProperties(&$product, $id_lang = null)
    {
        if (!isset($product['pp_ext'])) { // array_key_exists does not work on LazyArray
            $ms_data = ($pproperties_plugin = static::getPpropertiesPlugin()) ? $pproperties_plugin->getProductMeasurementSystemData($product) : null;
            $properties = static::getProductPropertiesByTemplateId($product['id_pp_template'], $id_lang, $ms_data);
            if (is_array($product)) {
                $product = array_merge($product, $properties);
            } elseif ($product instanceof ArrayAccess) {
                $product->appendArray($properties);
            }
        }
    }

    public static function getProductPropertiesByTemplateId($template_id, $id_lang = null, $ms_data = null)
    {
        $key = (int) $template_id . '_' . (int) $id_lang . '_' . (empty($ms_data) ? '-' : implode('-', $ms_data));
        if (!isset(self::$cache_product_properties[$key])) {
            $properties = array();
            static::calcProductProperties($properties, null, $template_id, false, $id_lang, $ms_data);
            self::$cache_product_properties[$key] = $properties;
        }
        return self::$cache_product_properties[$key];
    }

    public static function calcProductProperties(&$product, $data = null, $template_id = false, $extra = false, $id_lang = null, $ms_data = null)
    {
        if (is_array($product)) {
            if ($data === null) {
                if ($template_id === false) {
                    $template_id = static::getProductTemplateId($product);
                }
                $data = static::getTemplateById($template_id);
            }
            if (!is_array($data)) {
                $data = array();
                $data['id_pp_template'] = 0;
                $data['qty_policy'] = 0;
                $data['qty_mode'] = 0;
                $data['display_mode'] = 0;
                $data['price_display_mode'] = 0;
                $data['measurement_system'] = 0; // (int) Configuration::get('PP_MEASUREMENT_SYSTEM');
                $data['unit_price_ratio'] = 0;
                $data['minimal_price_ratio'] = 0;
                $data['minimal_quantity'] = 0;
                $data['maximum_quantity'] = 0;
                $data['total_maximum_quantity'] = 0;
                $data['default_quantity'] = 0;
                $data['qty_step'] = 0;
                $data['qty_shift'] = 0;
                $data['qty_decimals'] = 0;
                $data['qty_values'] = '';
                $data['qty_ratio'] = 0;
                $data['qty_available_display'] = 0;
                $data['hidden'] = 0;
                $data['customization'] = 0;
                $data['css'] = '';
                $data['ext'] = 0;
                $data['template_properties'] = array();
            }
            static::resolveTemplate($product, $data, $id_lang, $ms_data, $extra);
        }
    }

    public static function getRetailPrice($id_product, $id_product_attribute, $usetax = null, $usereduc = false)
    {
        if ($usetax === null) {
            $price_display = Product::getTaxCalculationMethod();
            $usetax = !$price_display || $price_display == 2;
        }
        return Product::getPriceStatic($id_product, $usetax, $id_product_attribute, 6, null, false, $usereduc, 1);
    }

    public static function calcProductDisplayPrice($product, $product_properties = null, $price = null, $mode = null)
    {
        $key = null;
        $product_object = static::productAsObject($product);
        if ($product_object != null) {
            if ($product_properties === null) {
                $product_properties = static::getProductProperties($product_object);
            }
            $display_retail_price = ((($product_properties['pp_display_mode'] & 2) == 2) &&
                (((($product_properties['pp_display_mode'] & 1) == 1) && ($mode != 'unit_price')) ||
                    (!(($product_properties['pp_display_mode'] & 1) == 1) && ($mode == 'unit_price'))));
            if ($display_retail_price) {
                $id_product_attribute = Product::getDefaultAttribute($product_object->id);
                $price = static::getRetailPrice($product_object->id, $id_product_attribute, null, $mode != 'price_without_reduction');
                if (($product_properties['pp_display_mode'] & 1) == 1 && $product_object->unit_price_ratio > 0) {
                    $price = Tools::ps_round((float) $price / $product_object->unit_price_ratio, static::getComputingPrecision());
                    if (($product_properties['pp_display_mode'] & 7) == 1) {
                        if ($id_product_attribute > 0) {
                            $combination = $product_object->getAttributeCombinationsById($id_product_attribute, static::resolveLanguageId());
                            $price += Tools::ps_round($combination[0]['unit_price_impact'], static::getComputingPrecision());
                        }
                    }
                }
            } elseif (($product_properties['pp_display_mode'] & 7) == 1) {
                // reversed price display
                if ($product_object->unit_price_ratio > 0) {
                    if ($price === null) {
                        $price = static::calcProductPrice($product_object);
                    }
                    if ($mode == 'unit_price') {
                        $price = Tools::ps_round((float) $price * $product_object->unit_price_ratio, static::getComputingPrecision());
                    } else {
                        $price = Tools::ps_round((float) $price / $product_object->unit_price_ratio, static::getComputingPrecision());
                        if (($id_product_attribute = Product::getDefaultAttribute($product_object->id)) > 0) {
                            $combination = $product_object->getAttributeCombinationsById($id_product_attribute, static::resolveLanguageId());
                            $price += Tools::ps_round($combination[0]['unit_price_impact'], static::getComputingPrecision());
                        }
                    }
                }
            }
            if ($mode != 'price_without_reduction') {
                if (($product_properties['pp_display_mode'] & 1) == 1) {
                    if ($mode == 'unit_price') {
                        if (!empty($product_properties['pp_price_text'])) {
                            $key = 'pp_price_text';
                        }
                    } else {
                        if (!empty($product_properties['pp_unity_text'])) {
                            $key = 'pp_unity_text';
                        }
                    }
                } else {
                    if ($mode == 'unit_price') {
                        if (!empty($product_properties['pp_unity_text'])) {
                            $key = 'pp_unity_text';
                        }
                    } else {
                        if (!empty($product_properties['pp_price_text'])) {
                            $key = 'pp_price_text';
                        }
                    }
                }
            }
            if ($price === null) {
                $price = static::calcProductPrice($product_object);
            }
        }
        return array($key, $price);
    }

    private static function calcProductPrice($product)
    {
        $price_display = Product::getTaxCalculationMethod();
        $usetax = !$price_display || $price_display == 2;
        return $product->getPrice($usetax, null, static::getComputingPrecision());
    }

    public static function amendPresentedProductPrices(&$presentedProduct, $with_smartprice)
    {
        $id_product = static::resolveProductId($presentedProduct);
        $include_taxes = $presentedProduct['include_taxes'];
        if ($with_smartprice && ($smart_product_price = PP::smartProductPrice($presentedProduct, null, $include_taxes)) !== null) {
            $presentedProduct['price_amount'] = $smart_product_price;
        } else {
            if (!isset($presentedProduct['price_amount'])) {
                $key = $include_taxes ? 'price' : 'price_tax_exc';
                if (is_numeric($presentedProduct[$key])) {
                    $presentedProduct['price_amount'] = $presentedProduct[$key];
                } else {
                    $price_to_display = $presentedProduct[$key];
                }
            }
        }
        if (isset($presentedProduct['price_amount'])) {
            $price_to_display = static::displayPrice(static::pricerounding($presentedProduct['price_amount'], 'product', $id_product, 0, 0, $include_taxes));
        }

        $cart_or_order = $presentedProduct['pp_presenter_cart_or_order'];
        $show_price_details = !$cart_or_order || ((int) $presentedProduct['pp_display_mode'] & 8) != 8; // do not hide extra details for unit price in orders and invoices
        $presentedProduct['price_to_display'] = $price_to_display = static::span($price_to_display, 'pp_price');
        if ($show_price_details && $presentedProduct['pp_price_text']) {
            $price_to_display = static::span($price_to_display . ' ' . static::span($presentedProduct['pp_price_text'], 'pp_price_text'), 'pp_price_with_text');
            $presentedProduct['price_to_display'] = $price_to_display;
        }
        if ((int) (($presentedProduct['pp_display_mode'] & 2) == 2)) { // display retail price as unit price
            $pp_settings = $presentedProduct['pp_settings'];
            $pp_settings['retail_price'] = static::getRetailPrice($id_product, $presentedProduct['id_product_attribute'], $include_taxes);
            $presentedProduct['unit_price'] = static::displayPrice(static::pricerounding($pp_settings['retail_price'], 'product', $id_product, 0, 0, $include_taxes));
            $presentedProduct['unit_price_full'] = static::span($presentedProduct['unit_price'], 'pp_price');
            $presentedProduct['pp_settings'] = $pp_settings;
        } elseif ($presentedProduct['unit_price_ratio'] > 0 && !empty($presentedProduct['unit_price_full']) && isset($presentedProduct['price_amount'])) {
            $price_amount = $presentedProduct['price_amount'];
            if ((int) (($presentedProduct['pp_display_mode'] & 4) == 4)) { // display base unit price for all combinations
                static::amendProductCompatibility($presentedProduct);
                $combination = new Combination($presentedProduct['id_product_attribute']);
                if ($combination->price != 0) {
                    $conversion_rate = static::currencyConversionRate(null, static::resolveCurrency());
                    $price_amount -= $combination->price * $conversion_rate;
                }
            }
            $presentedProduct['unit_price'] = static::displayPrice(static::pricerounding($price_amount / $presentedProduct['unit_price_ratio'], 'product', $id_product, 0, 0, $include_taxes)); // recalculate unit_price
            $presentedProduct['unit_price_full'] = static::span($presentedProduct['unit_price'], 'pp_price');
            if (isset($presentedProduct['regular_price_amount'])) {
                $presentedProduct['unit_price_regular_price'] = static::displayPrice(static::pricerounding($presentedProduct['regular_price_amount'] / $presentedProduct['unit_price_ratio'], 'product', $id_product, 0, 0, $include_taxes));
            }
        }
        if (!empty($presentedProduct['unit_price_full'])) {
            if (!empty($presentedProduct['unity']) && Tools::strpos($presentedProduct['unit_price_full'], $presentedProduct['unity']) === false) {
                $presentedProduct['unit_price_full'] = $presentedProduct['unit_price_full'] . ' ' . $presentedProduct['unity'];
            }
            if (!$cart_or_order && (($presentedProduct['pp_display_mode'] & 1) == 1)) { // reversed price display (display unit price as price)
                $presentedProduct['price_to_display'] = static::span($presentedProduct['unit_price_full'], 'pp_reversed_price_display');
                $presentedProduct['unit_price_full'] = $price_to_display;
                if (isset($presentedProduct['unit_price_regular_price'])) {
                    $presentedProduct['regular_price'] = $presentedProduct['unit_price_regular_price'];
                    $presentedProduct['regular_price_to_display'] = static::span($presentedProduct['regular_price'], 'pp_price');
                    if (!empty($presentedProduct['unity'])) {
                        $presentedProduct['regular_price_to_display'] .= ' ' . $presentedProduct['unity'];
                    }
                }
            }
            //  64: display legacy product price disabled
            // 128: display unit price in orders and invoices
            if ($cart_or_order && (($presentedProduct['pp_display_mode'] & 128) == 128) && (($presentedProduct['pp_display_mode'] & 64) == 0)) {
                $presentedProduct['price_to_display'] .= '<br>' . static::span($presentedProduct['unit_price_full'], 'pp_unit_price_with_text');
            }
            if (!$show_price_details) { // hide extra details for unit price in orders and invoices
                $presentedProduct['unit_price_full'] = '';
            }
        }
        if (isset($presentedProduct['id_cart_product'])) {
            $bo = defined('_PS_ADMIN_DIR_');
            $qty_text = $bo ? $presentedProduct['pp_bo_qty_text'] : $presentedProduct['pp_product_qty_text'];
            $quantity = static::obtainQty($presentedProduct);
            $quantity_fractional = static::obtainQtyFractional($presentedProduct);
            $quantity_fractional_formatted = static::formatQty($quantity_fractional, $presentedProduct);
            // pp_display_mode "16" => hide extra details for quantity in orders and invoices
            $show_quantity_details = $cart_or_order && ((int) $presentedProduct['pp_display_mode'] & 16) != 16;
            // pp_display_mode "256" => display number of items instead of quantity
            $display_items = (((int) $presentedProduct['pp_display_mode'] & 256) == 256) || static::isPacksCalculator($presentedProduct);
            if (static::qtyBehavior($presentedProduct, $quantity, $presentedProduct)) {
                $presentedProduct['pp_product_quantity'] = $quantity_fractional;
                if ($show_quantity_details && $qty_text) {
                    $presentedProduct['pp_settings']['cart_quantity_details_to_display'] = $qty_text;
                }
            } else {
                $presentedProduct['pp_product_quantity'] = $quantity;
                if ($show_quantity_details) {
                    if ($quantity_fractional > 0) {
                        if ((bool) $presentedProduct['pp_presenter_pdf']) {
                            $presentedProduct['pp_settings']['cart_quantity_details_to_display'] = '<br>' . static::span('x ' . $quantity_fractional_formatted . ($qty_text ? " $qty_text" : ''), false, ['style' => 'white-space:nowrap;font-size:80%;']);
                        } else {
                            $presentedProduct['pp_settings']['cart_quantity_details_to_display'] = static::span(
                                sprintf('x %s%s', static::span($quantity_fractional_formatted, 'pp_quantity_fractional'), $qty_text ? " $qty_text" : ''),
                                false,
                                ['style' => 'white-space:nowrap;']
                            );
                        }
                    }
                }
            }
            if ($quantity_fractional > 0 && !$display_items) {
                $presentedProduct['cart_quantity_to_display'] = $presentedProduct['cart_quantity_to_display_full'] = $presentedProduct['cart_quantity_to_display_order_view'] = $quantity_fractional_formatted;
                if ($quantity > 1 || !PP::qtyBehaviorFloat($presentedProduct, $quantity, $presentedProduct)) {
                    $presentedProduct['cart_quantity_to_display_full'] = "$quantity x $quantity_fractional_formatted";
                    $presentedProduct['cart_quantity_to_display'] = $show_quantity_details ? $presentedProduct['cart_quantity_to_display_full'] : $quantity;
                    $presentedProduct['cart_quantity_to_display_order_view'] = $quantity > 1 ? static::span($quantity, 'badge badge-secondary rounded-circle') : $quantity;
                    if ($show_quantity_details) {
                        $presentedProduct['cart_quantity_to_display_order_view'] .= '<br>x ' . $quantity_fractional_formatted;
                    }
                }
            } else {
                $presentedProduct['cart_quantity_to_display'] = $presentedProduct['cart_quantity_to_display_full'] = $presentedProduct['cart_quantity_to_display_order_view'] = $quantity;
            }
            if ($qty_text) {
                $presentedProduct['cart_quantity_to_display_full'] .= " $qty_text";
                if ($show_quantity_details) {
                    $presentedProduct['cart_quantity_to_display'] .= " $qty_text";
                    $presentedProduct['cart_quantity_to_display_order_view'] .= " $qty_text";
                }
            }
            $presentedProduct['cart_quantity_to_display'] = static::span($presentedProduct['cart_quantity_to_display'], false, ['style' => 'white-space:nowrap']);
            $presentedProduct['cart_quantity_to_display_full'] = static::span($presentedProduct['cart_quantity_to_display_full'], false, ['style' => 'white-space:nowrap']);
            $presentedProduct['cart_quantity_to_display_full_no_html'] = static::noHtml($presentedProduct['cart_quantity_to_display_full']);
            $presentedProduct['pp_quantity_formatted'] = static::raw($presentedProduct['cart_quantity_to_display']);
            $presentedProduct['pp_quantity_formatted_full'] = static::raw($presentedProduct['cart_quantity_to_display_full']);
            $presentedProduct['pp_quantity_formatted_order_view'] = static::raw($presentedProduct['cart_quantity_to_display_order_view']);
            if (isset($presentedProduct['total'])) { // total is a formatted string with a currency
                $presentedProduct['total_to_display'] = $presentedProduct['total'];
                if ($cart_or_order && ((int) $presentedProduct['pp_display_mode'] & 32) != 32) { // do not hide extra details for total price in orders and invoices
                    $qty_text = $bo ? $presentedProduct['pp_bo_qty_text'] : $presentedProduct['pp_product_qty_text'];
                    if ($qty_text) {
                        $quantity = static::obtainQty($presentedProduct);
                        $quantity_fractional = static::obtainQtyFractional($presentedProduct);
                        if ($quantity_fractional > 0 && $quantity > 1) {
                            $qty = static::resolveQty($quantity, $quantity_fractional);
                            $presentedProduct['total_price_details_to_display'] = static::div(static::formatQty($qty, $presentedProduct) . ' ' . $qty_text, 'pp-wrapper total_price_details_to_display');
                            $presentedProduct['total_to_display'] .= $presentedProduct['total_price_details_to_display'];
                            $presentedProduct['pp_total_formatted'] = static::raw($presentedProduct['total_to_display']);
                        }
                    }
                }
            }
            if (isset($presentedProduct['total_amount'])) {
                $presentedProduct['pp_total_formatted'] = static::twigDisplayPrice($presentedProduct, $presentedProduct['total_amount'], 'total');
            }
        }
        $presentedProduct['pp_price_formatted'] = static::raw($presentedProduct['price_to_display']);
    }

    public static function smartProductPrice($product, $price, $usetax = null)
    {
        // smartProductPrice refers to unit price in orders and invoices
        if ($product &&
            ($product_properties = static::getProductProperties($product)) &&
            (($product_properties['pp_display_mode'] & 64) == 0) // display legacy product price disabled
        ) {
            $values = null;
            if ($usetax === null) {
                if (isset($product['include_taxes'])) {
                    $usetax = $product['include_taxes'];
                } else {
                    $price_display = Product::getTaxCalculationMethod();
                    $usetax = !$price_display || $price_display == 2;
                }
            }
            if ($smartprice_plugin = static::getSmartpricePlugin()) {
                $values = $smartprice_plugin->retrieveValues($product, $usetax);
                if (is_array($values) && isset($values['_{U}'])) {
                    $price = $values['_{U}'];
                }
            }
            // pp_display_mode "256" => display number of items instead of quantity
            $display_items = (((int) $product_properties['pp_display_mode'] & 256) == 256);
            if ($display_items && !empty($values)) {
                if (isset($product['id_order_detail'])) {
                    $price = (float) ($usetax ? $product['total_price_tax_incl'] : $product['total_price_tax_excl']);
                    $price = $price / (int) $product['product_quantity'];
                } elseif (isset($product['id_cart_product'])) {
                    $price = (float) ($usetax ? (isset($product['total_price_tax_incl']) ? $product['total_price_tax_incl'] : $product['total_wt']) : (isset($product['total_price_tax_excl']) ? $product['total_price_tax_excl'] : $product['total']));
                    $price = $price / (int) $product['cart_quantity'];
                }
            }
        }
        return $price;
    }

    public static function getAllTemplates()
    {
        foreach (Language::getLanguages() as $language) {
            static::getTemplates($language['id_lang']);
        }
        return self::$cache_templates;
    }

    public static function resetTemplates()
    {
        self::$cache_templates = array();
        self::$cache_product_properties = array();
    }

    public static function getTemplates($id_lang = null)
    {
        $id_lang = static::resolveLanguageId($id_lang);
        if (!isset(self::$cache_templates[$id_lang])) {
            $templates = array();
            $rows = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pp_template`');
            foreach ($rows as $row) {
                $template = array();
                static::resolveTemplate($template, $row, $id_lang, null, true);
                $templates[$template['id_pp_template']] = $template;
            }
            self::$cache_templates[$id_lang] = $templates;
        }
        return self::$cache_templates[$id_lang];
    }

    public static function getAdminProductsTemplates($current_id = 0, $id_lang = null)
    {
        static $cache_bo_templates = array();
        if (!isset($cache_bo_templates[$current_id])) {
            $bo_templates = array();
            $templates = static::getTemplates($id_lang);
            $bo = array();
            static::calcProductProperties($bo, false, 0, true);
            array_unshift($templates, $bo);
            foreach ($templates as $template) {
                if (($template['pp_bo_hidden'] == 0) || ($current_id == $template['id_pp_template'])) {
                    $bo = array();
                    $bo['id_pp_template']           = $template['id_pp_template'];
                    $bo['name']                     = sprintf('#%s %s', $template['id_pp_template'], $template['name']);
                    if ($template['id_pp_template'] > 0) {
                        if ($template['auto_desc'] || ($template['description'] == '')) {
                            $bo['description'] = Module::getInstanceByName('pproperties')->generateDescription($template);
                        } else {
                            $bo['description'] = $template['description'];
                        }
                    } else {
                        $bo['description'] = '';
                    }
                    $bo['qty_policy']                     = $template['pp_qty_policy'];
                    $bo['qty_mode']                       = $template['pp_qty_mode'];
                    $bo['display_mode']                   = $template['pp_display_mode'];
                    $bo['price_display_mode']             = $template['pp_price_display_mode'];
                    $bo['price_text']                     = $template['pp_price_text'];
                    $bo['unity_text']                     = $template['pp_unity_text'];
                    $bo['qty_text']                       = $template['pp_qty_text'];
                    $bo['bo_qty_text']                    = $template['pp_bo_qty_text'];
                    $bo['bo_minimum_quantity_text']       = $template['pp_bo_minimum_quantity_text'];
                    $bo['bo_maximum_quantity_text']       = $template['pp_bo_maximum_quantity_text'];
                    $bo['bo_total_maximum_quantity_text'] = $template['pp_bo_total_maximum_quantity_text'];
                    $bo['bo_default_quantity_text']       = $template['pp_bo_default_quantity_text'];
                    $bo['bo_qty_step_text']               = $template['pp_bo_qty_step_text'];
                    $bo['bo_qty_shift_text']              = $template['pp_bo_qty_shift_text'];
                    $bo['ratio']                          = $template['pp_unit_price_ratio'];
                    $bo['minimal_price_ratio']            = $template['pp_minimum_price_ratio'];
                    $bo['min_qty']                        = $template['pp_minimum_quantity'];
                    $bo['bo_min_qty']                     = $template['pp_bo_minimum_quantity'];
                    $bo['maximum_quantity']               = $template['pp_maximum_quantity'];
                    $bo['total_maximum_quantity']         = $template['pp_total_maximum_quantity'];
                    $bo['default_quantity']               = $template['pp_default_quantity'];
                    $bo['qty_step']                       = $template['pp_qty_step'];
                    $bo['qty_shift']                      = $template['pp_qty_shift'];
                    $bo['qty_decimals']                   = $template['pp_qty_decimals'];
                    $bo['qty_values']                     = $template['pp_qty_values'];
                    $bo['qty_ratio']                      = $template['pp_qty_ratio'];
                    $bo['explanation']                    = $template['pp_explanation'];
                    $bo['ext']                            = $template['pp_ext'];
                    if (static::isMultidimensional($template) && ($multidimensional_plugin = static::getMultidimensionalPlugin())) {
                        $multidimensional_plugin->amendAdminProductsTemplatesExtProp($bo, $template);
                    }
                    $bo_templates[] = $bo;
                }
            }

            $bo_templates[0]['name'] = ' ';
            $bo_templates[0]['qty_policy'] = -1;

            $cache_bo_templates[$current_id] = $bo_templates;
        }
        return $cache_bo_templates[$current_id];
    }

    public static function isMultidimensional($data)
    {
        // pp_ext_method = 98 (multidimensional disable calculations) makes pp_ext = 2
        return static::is_array($data) && isset($data['pp_ext']) && in_array((int) $data['pp_ext'], [1, 2]);
    }

    public static function isPacksCalculator($data)
    {
        return static::is_array($data) && isset($data['pp_ext']) && (int) $data['pp_ext'] == 1 && (int) $data['pp_ext_policy'] == 1;
    }

    public static function isQuantityCalculator($data)
    {
        return static::is_array($data) && isset($data['pp_ext']) && (int) $data['pp_ext'] == 1 && (int) $data['pp_ext_policy'] == 4;
    }

    public static function isQuantityMultidimensional($data)
    {
        // pp_ext_method = 98 (multidimensional disable calculations) makes pp_ext = 2
        return static::is_array($data) && isset($data['pp_ext']) && in_array((int) $data['pp_ext'], [1, 2]) && (int) $data['pp_ext_policy'] != 4;
    }

    public static function getTemplateExtProperty($template, $position, $property_name)
    {
        if (isset($template['pp_ext_prop']) && isset($template['pp_ext_prop'][$position])) {
            return $template['pp_ext_prop'][$position][$property_name];
        }
        return false;
    }

    public static function getTemplateExtPositionById($template, $id_ext_prop)
    {
        // find template position matching id_ext_prop (in case tempalte positions were re-ordered)
        if (isset($template['pp_ext_prop'])) {
            foreach ($template['pp_ext_prop'] as $index => $pp_ext_prop) {
                if ($pp_ext_prop['id_ext_prop'] == $id_ext_prop) {
                    return $index;
                }
            }
        }
        return 0;
    }

    public static function productAttributeGroups($product)
    {
        $id_product = static::resolveProductId($product);
        if ($id_product > 0) {
            $cache_key = 'PP::productAttributeGroups:' . $id_product;
            if (!PSMCache::isStored($cache_key)) {
                $result = Db::getInstance()->executeS(
                    'SELECT DISTINCT ag.`id_attribute_group`
                    FROM `' . _DB_PREFIX_ . 'product_attribute` pa
                    ' . Shop::addSqlAssociation('product_attribute', 'pa') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
                    WHERE pa.`id_product` = ' . (int) $id_product
                );
                $groups = array();
                foreach ($result as $value) {
                    $groups[] = $value['id_attribute_group'];
                }
                PSMCache::store($cache_key, $groups);
                return $groups;
            }
            return PSMCache::retrieve($cache_key);
        }
        return array();
    }

    public static function qtyBehavior($product, $quantity = null, $properties = null)
    {
        if ($quantity === null) {
            $quantity = static::obtainQty($product);
        }
        if ($properties === null) {
            $properties = static::is_array($product) && isset($product['pp_ext']) ? $product : static::getProductProperties($product);
        }
        return ($properties['qty_policy'] != 0 && (int) $quantity == 1);
    }

    public static function qtyBehaviorFloat($product, $quantity = null, $properties = null)
    {
        if ($properties === null) {
            $properties = static::is_array($product) && isset($product['pp_ext']) ? $product : static::getProductProperties($product);
        }
        return static::qtyBehavior($product, $quantity, $properties);
    }

    public static function orderEditQtyBehaviorFloat($product, $quantity = null, $properties = null)
    {
        if ($properties === null) {
            $properties = static::is_array($product) && isset($product['pp_ext']) ? $product : static::getProductProperties($product);
        }
        return static::qtyModeApproximateQuantity($properties) || static::qtyBehaviorFloat($product, $quantity, $properties);
    }

    public static function qtyPolicy($id_pp_template, $stock_behavior = false)
    {
        $qty_policy = 0;
        if ($id_pp_template > 0) {
            $properties = static::getProductPropertiesByTemplateId($id_pp_template);
            $qty_policy = $stock_behavior ? (int) $properties['pp_qty_policy'] : (int) $properties['qty_policy'];
            if ($qty_policy < 0 || $qty_policy > 2) {
                $qty_policy = 0;
            }
        }
        return $qty_policy;
    }

    public static function productQtyPolicy($product, $stock_behavior = false)
    {
        return static::qtyPolicy(static::getProductTemplateId($product), $stock_behavior);
    }

    /* Legacy use of quantity (items). NOT opposite productQtyPolicyFractional */
    public static function productQtyPolicyLegacy($product)
    {
        return static::qtyPolicyLegacy(static::productQtyPolicy($product));
    }

    public static function productQtyPolicyWholeUnits($product)
    {
        return static::qtyPolicyWholeUnits(static::productQtyPolicy($product));
    }

    /* Quantity in fractional unit. NOT opposite productQtyPolicyLegacy */
    public static function productQtyPolicyFractional($product)
    {
        return static::qtyPolicyFractional(static::productQtyPolicy($product));
    }

    /* Legacy use of quantity (items). NOT opposite qtyPolicyFractional */
    public static function qtyPolicyLegacy($qty_policy)
    {
        return ((int) $qty_policy == 0);
    }

    public static function qtyPolicyWholeUnits($qty_policy)
    {
        return ((int) $qty_policy == 1);
    }

    /* Quantity in fractional unit. NOT opposite qtyPolicyLegacy */
    public static function qtyPolicyFractional($qty_policy)
    {
        return ((int) $qty_policy == 2);
    }

    public static function qtyModeApproximateQuantity($properties)
    {
        return (((int) $properties['pp_qty_mode'] & 0x1) == 0x1) && in_array((int) $properties['pp_qty_policy'], array(1, 2));
    }

    public static function qtyModeAggregate($properties)
    {
        return (((int) $properties['pp_qty_mode'] & 0x2) == 0x2) && !static::isQuantityMultidimensional($properties);
    }

    public static function normalizeProductQty($qty, $product, $stock_behavior = false)
    {
        return static::normalizeQty($qty, static::productQtyPolicy($product, $stock_behavior));
    }

    public static function normalizeQty($qty, $qty_policy)
    {
        if (static::qtyPolicyFractional($qty_policy)) {
            $qty = static::toFloat($qty);
        } else {
            $qty = (int) $qty;
        }
        return ($qty < 0 ? 0 : $qty);
    }

    public static function editBothQuantities($properties)
    {
        return static::isMultidimensional($properties) && static::qtyModeApproximateQuantity($properties);
    }

    public static function getIntNonNegativeValue($key, $default_value = 0)
    {
        $value = (int) Tools::getValue($key, $default_value);
        return (int) ($value < 0 ? 0 : $value);
    }

    public static function getFloatNonNegativeValue($key, $default_value = 0)
    {
        $value = static::toFloat(Tools::getValue($key, $default_value));
        return (float) ($value < 0 ? 0 : $value);
    }

    public static function getAliasValue($key, $default_value = null)
    {
        $value = Tools::getValue($key, null);
        if ($value !== null) {
            $value = preg_replace(Tools::cleanNonUnicodeSupport('/[^a-zA-Z0-9_]/'), '', (string) $value);
            if (preg_match(Tools::cleanNonUnicodeSupport('/^[a-zA-Z][a-zA-Z0-9_]*$/'), $value)) {
                return Tools::truncateString($value, 32);
            }
        }
        return $default_value;
    }

    public static function explodeSpecificValues($str)
    {
        $str = trim($str);
        if ($str != '') {
            $a = explode('|', $str);
            foreach ($a as $k => &$v) {
                $v = (float) $v;
            }
            return $a;
        }
        return null;
    }

    public static function getSpecificValues($key, $qty_policy, $allow_zero = false)
    {
        $values = trim(Tools::getValue($key));
        $specific_values = array();
        if ($values != '') {
            $values = str_replace(';', '|', $values);
            $values = str_replace(' ', '|', $values);
            $values = explode('|', $values);
            foreach ($values as $value) {
                $value = trim($value);
                if ($allow_zero && $value == '0') {
                    $specific_values[] = $value;
                    continue;
                }
                if (!empty($value)) {
                    if ($allow_zero && $value == '0') {
                        $specific_values[] = 0;
                    } else {
                        if ($qty_policy == 2) {
                            $value = static::toFloat($value);
                        } else {
                            $value = (int) $value;
                        }
                        if ($value > 0) {
                            $specific_values[] = $value;
                        }
                    }
                }
            }
            $specific_values = array_unique($specific_values);
            asort($specific_values);
        }
        return $specific_values;
    }

    public static function amendExplanation($explanation, $properties, $prop = null)
    {
        if ($explanation) {
            if ($prop === null) {
                $prop = !empty($properties['pp_settings']) ? $properties['pp_settings'] : $properties;
            }
            $explanation = static::substituteMacro($explanation, '{MIN}', $prop, 'minimum_quantity', 'qty_text');
            $explanation = static::substituteMacro($explanation, '{MAX}', $prop, 'maximum_quantity', 'qty_text');
            $explanation = static::substituteMacro($explanation, '{TMAX}', $prop, 'total_maximum_quantity', 'qty_text');
            $explanation = static::substituteMacro($explanation, '{STEP}', $prop, 'qty_step', 'qty_text');
            $explanation = static::substituteMacro($explanation, '{URATIO}', $prop, 'unit_price_ratio', '');
            $explanation = static::substituteMacro($explanation, '{CRMIN}', $properties, 'pp_ext_minimum_quantity', 'pp_ext_text');
            $explanation = static::substituteMacro($explanation, '{CRMAX}', $properties, 'pp_ext_maximum_quantity', 'pp_ext_text');
            $explanation = static::substituteMacro($explanation, '{CRTMAX}', $properties, 'pp_ext_total_maximum_quantity', 'pp_ext_total_text');
        }
        return $explanation;
    }

    public static function substituteMacro($str, $macro, $source, $qty_key, $text_key)
    {
        if (Tools::strpos($str, $macro) !== false && isset($source[$qty_key])) {
            $s = PP::formatQty($source[$qty_key]);
            if (isset($source[$text_key])) {
                $s .= PSM::amendForTranslation($source[$text_key]);
            }
            return str_replace($macro, $s, $str);
        }
        return $str;
    }

    public static function is_array($o) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        return is_array($o) || $o instanceof ArrayAccess;
    }

    public static function toFloat($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return static::floatParser()->fromString(trim($value));
    }

    public static function resolveQuantities(&$minimum_quantity, &$maximum_quantity, &$default_quantity, &$qty_step, $qty_policy, $options = null)
    {
        $db = $options['db'] ?? false;
        $allow_zero = $options['allow_zero'] ?? false;
        $specific_values = $options['specific_values'] ?? null;
        if ($options && array_key_exists('total_maximum_quantity', $options)) {
            $total_maximum_quantity = &$options['total_maximum_quantity'];
        } else {
            $total_maximum_quantity = 0;
        }
        if ($db) {
            if ($specific_values) {
                $minimum_quantity = 0;
                $maximum_quantity = 0;
                $qty_step = 0;
                $default_quantity = (PP::isSpecificValue($default_quantity, $specific_values) ? $default_quantity : 0);
            } else {
                if ($qty_step > 0) {
                    $options = array('qty_step' => $qty_step);
                    if ($minimum_quantity != 0) {
                        $minimum_quantity = static::resolveInputQty($minimum_quantity, $qty_policy, $options);
                    }
                    if ($maximum_quantity != 0) {
                        $maximum_quantity = static::resolveInputQty($maximum_quantity, $qty_policy, $options);
                    }
                    if ($total_maximum_quantity != 0) {
                        $total_maximum_quantity = static::resolveInputQty($total_maximum_quantity, $qty_policy, $options);
                    }
                    if ($default_quantity != 0) {
                        $options['minimum_quantity'] = $minimum_quantity;
                        $options['maximum_quantity'] = $maximum_quantity;
                        $default_quantity = static::resolveInputQty($default_quantity, $qty_policy, $options);
                    }
                }
                if ($minimum_quantity < 0) {
                    $minimum_quantity = 0;
                }
                if ($maximum_quantity != 0 && $maximum_quantity < $minimum_quantity) {
                    $maximum_quantity = $minimum_quantity;
                }
                if ($total_maximum_quantity != 0 && $total_maximum_quantity < $maximum_quantity) {
                    $total_maximum_quantity = $maximum_quantity;
                }
                if ($default_quantity != 0) {
                    if ($default_quantity < $minimum_quantity) {
                        $default_quantity = $minimum_quantity;
                    }
                    if ($maximum_quantity != 0 && $default_quantity > $maximum_quantity) {
                        $default_quantity = 0;
                    }
                }
            }
        } else {
            $options = array('qty_step' => $qty_step, 'specific_values' => $specific_values);
            if ($minimum_quantity <= 0 && !$allow_zero) {
                $minimum_quantity = static::missingMinimumQuantity($qty_policy);
            }
            $minimum_quantity = static::resolveInputQty($minimum_quantity, $qty_policy, $options);
            $options['minimum_quantity'] = $minimum_quantity;
            if ($maximum_quantity < 0) {
                $maximum_quantity = 0;
            }
            if ($maximum_quantity != 0) {
                $maximum_quantity = static::resolveInputQty($maximum_quantity, $qty_policy, $options);
            }
            if ($total_maximum_quantity < 0) {
                $total_maximum_quantity = 0;
            }
            if ($total_maximum_quantity != 0 && $total_maximum_quantity < $maximum_quantity) {
                $total_maximum_quantity = $maximum_quantity;
            }
            if ($maximum_quantity == 0 && $total_maximum_quantity != 0) {
                $maximum_quantity = $total_maximum_quantity;
            }
            $options['maximum_quantity'] = $maximum_quantity;
            $default_quantity = static::resolveInputQty($default_quantity, $qty_policy, $options);
        }
    }

    public static function resolveInputQty($qty, $prop, $options = array(), $obey_pp_ext = true, $operator = false)
    {
        if ($prop instanceof ArrayAccess) {
            $prop = (array) $prop;
        }
        if ($options instanceof ArrayAccess) {
            $options = (array) $options;
        }
        if (is_array($options) && array_key_exists('minimal_quantity', $options) && !array_key_exists('minimum_quantity', $options)) {
            throw new PrestaShopException('PP::resolveInputQty use "minimum_quantity" instead of "minimal_quantity"');
        }
        $default_quantity = $options['default_quantity'] ?? false;
        $qty_step = $options['qty_step'] ?? false;
        $minimum_quantity = $options['minimum_quantity'] ?? false;
        $maximum_quantity = $options['maximum_quantity'] ?? false;
        $specific_values = $options['specific_values'] ?? null;
        if (is_array($prop)) {
            if ($obey_pp_ext && ($prop['pp_ext'] ?? false)) {
                throw new PrestaShopException('PP::resolveInputQty $prop not properly set for $prop[pp_ext]');
            }
            $qty_policy = $prop['qty_policy'] ?? $prop['pp_qty_policy'] ?? 0;
            if ($specific_values === null && array_key_exists('specific_values', $prop) && is_array($prop['specific_values'])) {
                $specific_values = $prop['specific_values'];
            } else {
                if ($default_quantity === false) {
                    if (array_key_exists('default_quantity', $prop)) {
                        $default_quantity = $prop['default_quantity'];
                    } elseif (array_key_exists('pp_default_quantity', $prop)) {
                        $default_quantity = $prop['pp_default_quantity'];
                    }
                }
                if ($qty_step === false) {
                    if (array_key_exists('qty_step', $prop)) {
                        $qty_step = $prop['qty_step'];
                    } elseif (array_key_exists('pp_qty_step', $prop)) {
                        $qty_step = $prop['pp_qty_step'];
                    }
                }
                if ($minimum_quantity === false) {
                    if (array_key_exists('minimum_quantity', $prop)) {
                        $minimum_quantity = $prop['minimum_quantity'];
                    } elseif (array_key_exists('pp_minimum_quantity', $prop)) {
                        $minimum_quantity = $prop['pp_minimum_quantity'];
                    }
                }
                if ($maximum_quantity === false) {
                    if (array_key_exists('maximum_quantity', $prop)) {
                        $maximum_quantity = $prop['maximum_quantity'];
                    } elseif (array_key_exists('pp_maximum_quantity', $prop)) {
                        $maximum_quantity = $prop['pp_maximum_quantity'];
                    }
                }
            }
        } else {
            $qty_policy = $prop;
        }
        if (!is_numeric($qty_policy)) {
            $qty_policy = 2;
        }
        $qty = static::normalizeQty($qty, $qty_policy);
        if ($qty == 0 && $default_quantity !== false) {
            $qty = $default_quantity;
        }
        if (is_array($specific_values)) {
            $qty = static::resolveSpecificValue($qty, $specific_values, $operator);
        } else {
            if ($maximum_quantity && (round($qty, 8) > round($maximum_quantity, 8))) {
                $qty = $maximum_quantity;
            }
            if ($minimum_quantity && (round($qty, 8) < round($minimum_quantity, 8))) {
                $qty = $minimum_quantity;
            }
            if ($qty_step > 0) {
                if ($operator == 'up') {
                    $qty += $qty_step;
                } elseif ($operator == 'down') {
                    $qty -= $qty_step;
                }
                $q = floor($qty / $qty_step);
                if (round($q * $qty_step, 8) < round($qty, 8)) {
                    $qty = ($q + 1) * $qty_step;
                }
                if ($maximum_quantity && (round($qty, 8) > round($maximum_quantity, 8))) {
                    $qty = abs($qty - $qty_step);
                }
                if ($minimum_quantity && (round($qty, 8) < round($minimum_quantity, 8))) {
                    $qty = $minimum_quantity;
                }
            }
        }
        return $qty;
    }

    public static function isSpecificValue($value, $specific_values)
    {
        if (is_array($specific_values)) {
            $value = round($value, 8);
            foreach ($specific_values as $specific_value) {
                if ($value == round($specific_value, 8)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function resolveSpecificValue($value, $specific_values, $operator = false)
    {
        $value = round($value, 8);
        if ($operator == 'down') {
            foreach (array_reverse($specific_values) as $specific_value) {
                if ($value >= round($specific_value, 8)) {
                    return $specific_value;
                }
            }
            return $specific_values[0];
        } else {
            foreach ($specific_values as $specific_value) {
                if ($value <= round($specific_value, 8)) {
                    return $specific_value;
                }
            }
            return $specific_values[count($specific_values) - 1];
        }
    }

    public static function isValidMeasurementSystem($ms)
    {
        return in_array($ms, array(static::PP_MS_METRIC, static::PP_MS_NON_METRIC));
    }

    public static function resolveMeasurementSystem($ms = false)
    {
        if ($ms !== false) {
            if ((int) $ms == static::PP_MS_METRIC) {
                return (int) $ms;
            } elseif ((int) $ms == static::PP_MS_NON_METRIC) {
                return (int) $ms;
            }
        }
        $ms = (int) Configuration::get('PP_MEASUREMENT_SYSTEM');
        return $ms != static::PP_MS_NON_METRIC ? static::PP_MS_METRIC : static::PP_MS_NON_METRIC;
    }

    public static function isMeasurementSystemFOActivated()
    {
        return ((int) Configuration::get('PP_MEASUREMENT_SYSTEM_FO_ACTIVATED') == 1);
    }

    public static function presentQty($qty, $currency = null)
    {
        if (!is_numeric($qty)) {
            return $qty;
        }
        $str = (string) ((float) $qty);
        return static::getDecimalSign() == ',' ? str_replace('.', ',', $str) : $str;
    }

    public static function formatQty($qty, $properties = null)
    {
        if (!is_numeric($qty)) {
            return $qty;
        }
        $decimals = $precision = 0;
        if (static::is_array($properties)) {
            if (static::isMultidimensional($properties)) {
                $precision = $properties['pp_ext_precision'] ?? static::getProductPropertiesByTemplateId($properties['id_pp_template'])['pp_ext_precision'];
            } else {
                $decimals = $properties['pp_qty_decimals'] ?? 0;
            }
        }
        if ($decimals > 0) {
            $str = sprintf("%.{$decimals}f", (float) $qty);
            return static::getDecimalSign() == ',' ? str_replace('.', ',', $str) : $str;
        }
        if ($precision != 0) { // precision can be negative
            $str = (string) round((float) $qty, $precision);
        } else {
            $str = (string) static::normalizeDbDecimal($qty);
        }
        return static::getDecimalSign() == ',' ? str_replace('.', ',', $str) : $str;
    }

    public static function normalizeDbDecimal($qty)
    {
        if (is_float($qty) || (is_numeric($qty) && strpos((string) $qty, '.') !== false)) { // handle also numbers that come from the database as strings and not float
            $qty = round((float) $qty, 6); // database precision = 6
            $str = Tools::substr(sprintf('%.6f', $qty), -6);
            if (strpos($str, '999') !== false || (Tools::substr($str, -1) != '0' && strpos($str, '000') !== false)) {
                return round((float) $qty, 5); // try to avoid computer rounding problems
            }
        }
        return $qty;
    }

    public static function formatFileSize($bytes, $decimals = 2)
    {
        $suffix = 'kMGTPEZY';
        $number = floor((Tools::strlen($bytes) - 1) / 3);
        return static::presentQty(sprintf("%.{$decimals}f", $bytes / pow(1024, $number))) . ' ' . @$suffix[$number - 1] . 'B';
    }

    public static function calcProductPricesStatic(&$product, $id_product, $id_product_attribute, $quantity, $quantity_fractional, $qty)
    {
        $context = Context::getContext();
        if ($quantity > 0) {
            $id_customer = (isset($context->customer) ? $context->customer->id : null);
            $address = Address::initialize(null, true);
            $specific_price_output = null;
            $price_with_reduction_tax_excl = Product::getPriceStatic(
                $id_product,
                false,
                $id_product_attribute,
                6,
                null,
                false,
                true,
                $qty,
                false,
                $id_customer,
                null,
                $address->id,
                $specific_price_output,
                true,
                true,
                $context,
                true,
                null
            );
            $price_with_reduction_wt = Product::getPriceStatic(
                $id_product,
                true,
                $id_product_attribute,
                6,
                null,
                false,
                true,
                $qty,
                false,
                $id_customer,
                null,
                $address->id,
                $specific_price_output,
                true,
                true,
                $context,
                true,
                null
            );
            $total_tax_excl = PP::calcPrice($price_with_reduction_tax_excl, $quantity, $quantity_fractional, $id_product, false, false);
            $total_wt = PP::calcPrice($price_with_reduction_wt, $quantity, $quantity_fractional, $id_product, true, false);
            if ($smartprice = PP::getSmartpricePlugin()) {
                $outcome = $smartprice->processPrice(
                    $product,
                    array($total_tax_excl, $total_wt),
                    $id_product,
                    $id_product_attribute,
                    $quantity,
                    $quantity_fractional,
                    $specific_price_output
                );
                if (is_array($outcome)) {
                    $total_tax_excl = $outcome['total'][0];
                    $total_wt = $outcome['total'][1];
                    if (!empty($outcome['text'])) {
                        $product['pp_smartprice_text'] = $outcome['text'];
                    }
                    PP::amendPresentedProductPrices($product, true);
                }
            }
            $total_tax_excl = Tools::ps_round(PP::pricerounding($total_tax_excl, 'total', $id_product, $quantity, $quantity_fractional, false), PP::getComputingPrecision());
            $total_wt = Tools::ps_round(PP::pricerounding($total_wt, 'total', $id_product, $quantity, $quantity_fractional, true), PP::getComputingPrecision());
        } else {
            $total_tax_excl = $total_wt = 0;
        }
        return [$total_tax_excl, $total_wt];
    }

    public static function calcPrice($base_price, $quantity, $quantity_fractional, $product = null, $include_taxes = null, $round_type = -1, $precision = -1)
    {
        if ($round_type !== false) {
            if ($round_type == -1) {
                $round_type = Configuration::get('PS_ROUND_TYPE');
            }
            switch ($round_type) {
                case Order::ROUND_TOTAL:
                case Order::ROUND_LINE:
                case Order::ROUND_ITEM:
                    break;
                default:
                    $round_type = Order::ROUND_ITEM;
                    break;
            }
            if ($precision == -1) {
                $precision = static::getComputingPrecision();
            }
            if ($round_type == Order::ROUND_ITEM) {
                $base_price = Tools::ps_round($base_price, $precision);
            }
        }
        if ((float) $quantity_fractional > 0) {
            $price_fractional = ($base_price * (float) $quantity_fractional);
            if ($round_type == Order::ROUND_ITEM) {
                $price_fractional = Tools::ps_round($price_fractional, $precision);
            }
        } else {
            $price_fractional = $base_price;
        }
        if ($product) {
            $properties = static::getProductProperties($product);
            if ($properties['pp_ext'] == 1 && (float) $properties['pp_ext_minimum_price_ratio'] > 0) {
                $min_price = ($base_price * (float) $properties['pp_ext_minimum_price_ratio']);
                if ($round_type == Order::ROUND_ITEM) {
                    $min_price = Tools::ps_round($min_price, $precision);
                }
                if (round($price_fractional, 8) < round($min_price, 8)) {
                    $price_fractional = $min_price;
                }
            }
        }
        $price = $price_fractional * (int) $quantity;
        if (isset($properties) && (float) $properties['pp_minimum_price_ratio'] > 0) {
            $min_price = ($base_price * (float) $properties['pp_minimum_price_ratio']);
            if ($round_type == Order::ROUND_ITEM) {
                $min_price = Tools::ps_round($min_price, $precision);
            }
            if (round($price, 8) < round($min_price, 8)) {
                $price = $min_price;
            }
        }
        if ($round_type !== false) {
            if ($round_type != Order::ROUND_ITEM) {
                $price = Tools::ps_round($price, $precision);
            }
            $price = static::pricerounding($price, 'total', static::resolveProductId($product), $quantity, $quantity_fractional, $include_taxes);
        }
        return $price;
    }

    public static function paypalCalcQuantity($base_price, $product, $wt)
    {
        list($quantity, $price) = static::paypalCalc($base_price, $product, $wt);
        return $quantity;
    }

    public static function paypalCalcPrice($base_price, $product, $wt)
    {
        list($quantity, $price) = static::paypalCalc($base_price, $product, $wt);
        return $price;
    }

    public static function paypalCalc($base_price, $product, $wt)
    {
        if (isset($product['smartprice']['smartprice_values'])) {
            $quantity = 1;
            $smartprice_values = $product['smartprice']['smartprice_values'];
            $price = static::calcPrice(($wt ? $smartprice_values['total_wt'] : $smartprice_values['total']), $quantity, 0, null, $wt);
        } else {
            $quantity = $product['cart_quantity'];
            if ($quantity > 1) {
                $price1 = static::calcPrice($base_price, $quantity, $product['cart_quantity_fractional'], $product, $wt);
                $price2 = static::calcPrice($base_price, $quantity, $product['cart_quantity_fractional'], null, $wt);
                if ($price1 != $price2) {
                    $price = $price1;
                    $quantity = 1;
                } else {
                    $price = static::calcPrice($base_price, 1, $product['cart_quantity_fractional'], null, $wt);
                }
            } else {
                $price = static::calcPrice($base_price, 1, $product['cart_quantity_fractional'], $product, $wt);
            }
        }
        return array($quantity, $price);
    }

    // https://www.prestashop.com/forums/topic/608078-guide-how-to-configurecustomize-your-currency-in-prestashop-17/
    // translations\cldr\main--en-US--numbers: 'standard' ==> removed in PS 1.7.6.0
    public static function getDecimalSign()
    {
        static $decimal_sign = null;
        if ($decimal_sign === null) {
            if (preg_match('/\D/', Tools::displayNumber(0.1), $matches)) {
                $decimal_sign = $matches[0];
            } else {
                $decimal_sign = '.';
            }
        }
        return $decimal_sign;
    }

    public static function performPricerounding()
    {
        return self::$pricerounding_supported === true || self::$pricerounding_supported === null;
    }

    public static function pricerounding($price, $type, $id_product = 0, $quantity = 0, $quantity_fractional = 0, $include_taxes = null)
    {
        if (static::performPricerounding()) {
            // cast quantity to float because in can be the overall quantity with a fractional part or come as a string from the database
            $result = Hook::exec(
                'actionPriceRounding',
                array(
                    'price' => &$price,
                    'type' => $type, // type either 'product' to round the product price or 'total' to round different totals
                    'id_product' => (int) $id_product, // product id or 0
                    'quantity' => (float) $quantity,
                    'quantity_fractional' => (float) $quantity_fractional,
                    'include_taxes' => $include_taxes // include_taxes = true, when the price includes taxes, false if the price does not include taxes and null if the taxes information is unknown
                ),
                null,
                true
            );
            self::$pricerounding_supported = is_array($result) && count($result) > 0;
        }
        return $price;
    }

    public static function resolveCurrency($currency = null)
    {
        if ($currency !== null && $currency !== false) {
            if (is_numeric($currency) && (int) $currency > 0) {
                return Currency::getCurrencyInstance((int) $currency);
            }
            if ($currency instanceof Currency) {
                return $currency;
            }
        }
        $c = Context::getContext()->currency;
        if ($c instanceof Currency) {
            return $c;
        }
        return Currency::getCurrencyInstance((int) Configuration::get('PS_CURRENCY_DEFAULT'));
    }

    public static function getSpecificPriceFromQty($product)
    {
        return static::qtyPolicyLegacy(static::productQtyPolicy($product, true)) ? 1 : 0;
    }

    public static function resolveProductId($obj)
    {
        if ($obj instanceof ProductBase) {
            $id = $obj->id;
        } elseif ($obj instanceof OrderDetail) {
            $id = $obj->product_id;
        } elseif ($obj instanceof ObjectModel) {
            $id = (isset($obj->id_product) ? $obj->id_product : 0);
        } elseif (static::is_array($obj)) {
            static::amendProductCompatibility($obj);
            $id = (isset($obj['id_product']) ? (int) $obj['id_product'] : 0);
        } elseif (is_numeric($obj)) {
            $id = (int) $obj;
        } else {
            $id = 0;
        }
        return (int) $id;
    }

    public static function productAsObject($obj)
    {
        if ($obj instanceof ProductBase && $obj->id) {
            return $obj;
        }
        $id = static::resolveProductId($obj);
        if ($id > 0) {
            return new Product($id);
        }
        return null;
    }

    public static function productPresenterPresent(ProductPresentationSettings $settings, &$product, Language $language)
    {
        static::amendPresentedUrls($product, $product);
        $smartprice_plugin = static::getSmartpricePlugin();
        if ($product['id_pp_template'] || ($smartprice_plugin && $smartprice_plugin->productUsesPluginFeatures($product))) {
            if (!isset($product['pp_presenter_type'])) { // array_key_exists does not work on LazyArray
                $product['pp_presenter_type'] = (isset($settings->presenter_type) ? $settings->presenter_type : '_general_');
                $product['pp_presenter_pdf'] = (isset($settings->presenter_pdf) ? $settings->presenter_pdf : null);
                $product['id_lang'] = (int) $language->id;
                $product['include_taxes'] = $settings->include_taxes;
                $cart_or_order = $product['pp_presenter_cart_or_order'] = in_array($product['pp_presenter_type'], array('cart', 'order'));
                static::assignProductProperties($product, $product['id_lang']);
                static::amendProductCompatibility($product);
                static::productResolveQuantities($product);
                if (!empty($product['pp_ms_display'])) {
                    if ($pproperties_plugin = static::getPpropertiesPlugin()) {
                        $pproperties_plugin->productPresenterPresent($product);
                    }
                }
                $product['name_to_display_no_html'] = array();
                $product['pp_settings'] = array();
                if (static::isMultidimensional($product)) {
                    if ($multidimensional_plugin = static::getMultidimensionalPlugin()) {
                        $multidimensional_plugin->productPresenterPresent($product);
                    } else {
                        $product['pp_ext'] = 0;
                    }
                }
                $pp_settings = &$product['pp_settings'];
                $pp_settings['id_pp_template'] = (int) $product['id_pp_template'];
                $pp_settings['pp_ext'] = $product['pp_ext'];
                $pp_settings['qty_policy'] = (int) $product['qty_policy'];
                $pp_settings['minimum_quantity'] = $product['minimum_quantity'];
                $pp_settings['maximum_quantity'] = $product['pp_maximum_quantity'];
                $pp_settings['total_maximum_quantity'] = $product['pp_total_maximum_quantity'];
                $pp_settings['default_quantity'] = $product['default_quantity'];
                $pp_settings['qty_step'] = $product['qty_step'];
                $pp_settings['qty_shift'] = $product['qty_shift'];
                $pp_settings['qty_decimals'] = $product['qty_decimals'];
                $pp_settings['specific_values'] = $product['specific_values'];
                $pp_settings['qty_ratio'] = $product['pp_qty_ratio'];
                static::resolveQuantities(
                    $pp_settings['minimum_quantity'],
                    $pp_settings['maximum_quantity'],
                    $pp_settings['default_quantity'],
                    $pp_settings['qty_step'],
                    $pp_settings['qty_policy'],
                    array('total_maximum_quantity' => &$pp_settings['total_maximum_quantity'], 'specific_values' => $pp_settings['specific_values'])
                );
                if (!isset($product['quantity_wanted'])) {
                    $product['quantity_wanted'] = $pp_settings['default_quantity'];
                }
                $pp_settings['pp_display_mode']        = (int) $product['pp_display_mode'];
                $pp_settings['pp_price_display_mode']  = (int) $product['pp_price_display_mode'];
                $pp_settings['pp_minimum_price_ratio'] = $product['pp_minimum_price_ratio'];
                $pp_settings['qty_text']               = $product['pp_qty_text'];
                $pp_settings['unit_price_ratio']       = $product['unit_price_ratio'];
                $pp_settings['unity_text']             = $product['unity'];
                $pp_settings['css']                    = $product['pp_css'];
                if (static::isQuantityCalculator(($product))) {
                    $pp_settings['css'] .= ' pp-quantity-wanted-hidden';
                }
                if (!empty(static::getPPDataType($product))) {
                    $pp_settings['pp_data_type'] = $product['pp_data_type'];
                }
                $product['pp_explanation'] = static::amendExplanation($product['pp_explanation'], $product, $pp_settings);
                static::amendPresentedProductPrices($product, isset($product['id_cart_product']));
                if ($cart_or_order) {
                    if (isset($product['update_quantity_url'])) {
                        $product['update_quantity_url'] .= '&op=update';
                    }
                } else {
                    if (isset($product['show_quantities']) && $product['show_quantities'] && $product['pp_qty_available_display'] == 2) {
                        $product['show_quantities'] = 0;
                    }
                    $pp_settings['stock_quantity'] = $product['quantity'];
                    $product['quantity_to_display'] = static::formatQty($product['quantity'], $pp_settings);
                    $product['minimum_quantity_to_display'] = static::formatQty($product['minimum_quantity']);
                    if ($product['pp_qty_text']) {
                        $product['quantity_label'] = $product['pp_qty_text'];
                        $product['quantity_to_display'] = $product['quantity_to_display'] . ' ' . $product['pp_qty_text'];
                        $product['minimum_quantity_to_display'] = $product['minimum_quantity_to_display'] . ' ' . $product['pp_qty_text'];
                    }
                }
            }
            if ($product['pp_price_text'] && isset($product['discount_to_display']) && isset($product['regular_price']) && ($product['discount_to_display'] != $product['regular_price'])) {
                $product['discount_to_display'] = $product['discount_to_display'] . ' ' . static::span($product['pp_price_text'], 'pp_price_text');
                if (isset($product['discount_amount_to_display'])) {
                    $product['discount_amount_to_display'] = $product['discount_amount_to_display'] . ' ' . static::span($product['pp_price_text'], 'pp_price_text');
                }
            }
            if (!empty($product['pp_ms_display'])) {
                if ($pproperties_plugin = static::getPpropertiesPlugin()) {
                    $pproperties_plugin->productPresenterPresent($product);
                }
            }
            if (static::isMultidimensional($product)) {
                if ($multidimensional_plugin = static::getMultidimensionalPlugin()) {
                    $multidimensional_plugin->productPresenterPresent($product);
                }
            }
            if ($smartprice_plugin) {
                $smartprice_plugin->productPresenterPresent($product);
            }
            if ($product['pp_customization']) {
                $hook_params = array(
                    'hook' => 'presentProduct',
                    'product' => &$product,
                    'type' => $product['pp_presenter_type'],
                    'pdf' => (bool) $product['pp_presenter_pdf'],
                );
                Hook::exec('ppropertiesCustom', $hook_params, Module::getModuleIdByName('ppropertiescustom'), true);
                if (isset($product['show_quantities']) && ('bulk' === static::getPPDataType($product))) {
                    $product['show_quantities'] = 0;
                }
            }
            static::productResolveInputInformation($product);
        } else {
            $product['pp_presenter_type'] = null;
        }
    }

    public static function addQuantityInformation(ProductPresentationSettings $settings, &$product, Language $language, $show_availability)
    {
        if ($show_availability && $settings->stock_management_enabled && ($show_availability = ($product['pp_qty_available_display'] != 2))) {
            if ('bulk' === static::getPPDataType($product)) {
                $show_availability = false;
                $bulk = static::getProductBulk($product);
                if (!empty($bulk['bulk'])) {
                    if (!static::bulkCheckQuantities($product, $bulk)) {
                        $show_availability = true;
                        $product['availability_message'] = Context::getContext()->getTranslator()->trans(
                            'There are not enough products in stock',
                            array(),
                            'Shop.Notifications.Error'
                        );
                        $product['availability'] = 'unavailable';
                        $product['availability_date'] = null;
                    }
                }
                $product['show_availability'] = $show_availability;
                $show_availability = null;
            }
        }
        return $show_availability;
    }

    public static function amendPresentedUrls(&$presentedProduct, $product)
    {
        if (isset($product['id_cart_product'])) {
            foreach (array('remove_from_cart_url', 'up_quantity_url', 'down_quantity_url', 'update_quantity_url') as $key) {
                if (isset($presentedProduct[$key]) && Tools::strpos($presentedProduct[$key], '&icp=') === false) {
                    $presentedProduct[$key] .= "&icp={$product['id_cart_product']}";
                }
            }
        }
    }

    public static function allowAddToCartUrlOnProductListing($product)
    {
        $allow = isset($product['pp_css']) ? Tools::strpos($product['pp_css'], 'pp-product-list-add-to-cart-hidden') === false : true;
        return $allow && (!static::isMultidimensional($product) || ((bool) ($multidimensional_plugin = static::getMultidimensionalPlugin()) && $multidimensional_plugin->allowAddToCartUrlOnProductListing($product)));
    }

    public static function orderCreateProductAllowAddToCart($product)
    {
        $multidimensional_plugin = static::getMultidimensionalPlugin();
        return !static::isMultidimensional($product) || ((bool) ($multidimensional_plugin = static::getMultidimensionalPlugin()) && method_exists($multidimensional_plugin, 'orderCreateProductAllowAddToCart') && $multidimensional_plugin->orderCreateProductAllowAddToCart($product));
    }

    public static function shouldEnableAddToCartButton($product, ProductPresentationSettings $settings)
    {
        $enable = null;
        if ('bulk' === static::getPPDataType($product)) {
            $bulk = static::getProductBulk($product);
            $enable = !empty($bulk['bulk']);
            if ($enable && $settings->stock_management_enabled) {
                $enable = static::bulkCheckQuantities($product, $bulk);
            }
        }
        if (!empty($product['pp_customization'])) {
            $hook_params = array(
                'hook' => 'shouldEnableAddToCartButton',
                'shouldEnableAddToCartButton' => $enable,
                'stock_management_enabled' => $settings->stock_management_enabled,
                'product' => $product,
            );
            $res = Hook::exec('ppropertiesCustom', $hook_params, Module::getModuleIdByName('ppropertiescustom'), true);
            if (isset($res['ppropertiescustom']) && is_bool($res['ppropertiescustom'])) {
                $enable = $res['ppropertiescustom'];
            }
        }
        return $enable;
    }

    public static function bulkCheckQuantities($product, $bulk)
    {
        $id_product = $bulk['id_product'];
        if ((int) $product['id_product'] != (int) $id_product) {
            // should not happen
            throw new PrestaShopException('Product id does not match "bulk" id_product');
        }
        $isAvailableWhenOutOfStock = Product::isAvailableWhenOutOfStock($product['out_of_stock']);
        $unavailable = false;
        foreach ($bulk['bulk'] as $id_product_attribute => $d) {
            if ($id_product_attribute) {
                $unavailable = !$isAvailableWhenOutOfStock && !Attribute::checkAttributeQty($id_product_attribute, $d['quantity']);
                if ($unavailable) {
                    break;
                }
            } else {
                throw new PrestaShopException('Product "bulk" without attributes not supported');
            }
        }
        return !$unavailable;
    }

    public static function orderDetailCheckProductStock($product, $delta_quantity)
    {
        if ('bulk' === static::getPPDataType($product) && ($bulk = static::resolvePPData($product))) {
            $update_quantity = true;
            $delta_quantity = 0;
            foreach ($bulk['bulk'] as $id_product_attribute => $data) {
                $update_quantity &= StockAvailable::updateQuantity($product['id_product'], $id_product_attribute, -$data['quantity'], $product['id_shop'], true);
                $delta_quantity += $data['quantity'];
            }
        } else {
            $update_quantity = StockAvailable::updateQuantity($product['id_product'], $product['id_product_attribute'], -$delta_quantity, $product['id_shop'], true);
        }
        return array($update_quantity, $delta_quantity);
    }

    public static function productProductPresent(&$product, $include_taxes = null, Language $language = null)
    {
        if (!isset($product['pp_presenter_type'])) {
            $context = Context::getContext();
            $factory = new ProductPresenterFactory($context);
            $settings = $factory->getPresentationSettings();
            if ($include_taxes !== null) {
                $settings->include_taxes = $include_taxes;
            }
            $settings->presenter_type = 'product';
            static::productPresenterPresent($settings, $product, $language ?? $context->language);
        }
    }

    public static function cartProductPresent(&$product, $include_taxes = null, Language $language = null)
    {
        if (!isset($product['pp_presenter_type'])) {
            $context = Context::getContext();
            $factory = new ProductPresenterFactory($context);
            $settings = $factory->getPresentationSettings();
            if ($include_taxes !== null) {
                $settings->include_taxes = $include_taxes;
            }
            $settings->presenter_type = 'cart';
            static::productPresenterPresent($settings, $product, $language ?? $context->language);
        }
    }

    public static function orderProductPresent(&$product, $include_taxes = null, Language $language = null, $pdf = false)
    {
        if (!isset($product['pp_presenter_type'])) {
            $context = Context::getContext();
            $factory = new ProductPresenterFactory($context);
            $settings = $factory->getPresentationSettings();
            if ($include_taxes !== null) {
                $settings->include_taxes = $include_taxes;
            }
            $settings->presenter_type = 'order';
            $settings->presenter_pdf = $pdf;
            static::productPresenterPresent($settings, $product, $language ?? $context->language);
        }
    }

    public static function smartyOrderProductPresentPdf(&$product, $pdf = false)
    {
        if ($pdf && !isset($product['pp_presenter_type'])) {
            static::orderProductPresent($product, null, null, $pdf);
        }
    }

    public static function orderPresenterPresent($order)
    {
        if (version_compare(_PS_VERSION_, '1.7.5.0', '>=')) {
            $order_presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter();
        } else {
            $order_presenter = new \PrestaShop\PrestaShop\Adapter\Order\OrderPresenter();
        }
        return $order_presenter->present($order);
    }

    public static function orderPresenterGetProducts(&$orderProduct, $cartProduct)
    {
        if ($cartProduct['id_pp_template'] && isset($cartProduct['pp_presenter_type'])) {
            $orderProduct['pp_presenter_type'] = $cartProduct['pp_presenter_type'];
            $orderProduct['pp_presenter_cart_or_order'] = $cartProduct['pp_presenter_cart_or_order'];
            $orderProduct['pp_presenter_pdf'] = $cartProduct['pp_presenter_pdf'];
            $orderProduct['id_lang'] = $cartProduct['id_lang'];
            $orderProduct['include_taxes'] = $cartProduct['include_taxes'];
            $orderProduct['pp_settings'] = $cartProduct['pp_settings'];
            static::amendPresentedProductPrices($orderProduct, true);
            if (static::isMultidimensional($cartProduct)) {
                if ($multidimensional_plugin = static::getMultidimensionalPlugin()) {
                    $multidimensional_plugin->orderPresenterGetProducts($orderProduct, $cartProduct);
                }
            }
            if ($smartprice_plugin = static::getSmartpricePlugin()) {
                $smartprice_plugin->orderPresenterGetProducts($orderProduct, $cartProduct);
            }
        }
    }

    public static function findProduct($collection, $product)
    {
        $products = (isset($collection['products']) ? $collection['products'] : $collection);
        if (is_array($products) || ($products instanceof Traversable)) {
            foreach ($products as $p) {
                if ($p['id_cart_product'] == $product['id_cart_product']) {
                    return $p;
                }
            }
        }
        return $product;
    }

    public static function productResolveQuantities(&$product, $id_lang = null)
    {
        $id_product_attribute = (int) (isset($product['id_product_attribute']) ? $product['id_product_attribute'] : 0);
        if (isset($product['resolved_quantities_id_product_attribute']) && $product['resolved_quantities_id_product_attribute'] == $id_product_attribute) {
            return;
        }
        $id_product = static::resolveProductId($product);
        static::assignProductProperties($product, $id_lang);
        $cache_key = 'PP::productResolveQuantities:' . $id_product . '-' . $id_product_attribute;
        if (($resolved_quantities = PSMCache::retrieve($cache_key)) === null) {
            if ($id_product_attribute) {
                $minimum_quantity = static::productAttributeMinQty($id_product_attribute, $product);
                $maximum_quantity = (isset($product['maximum_quantity']) ? static::productAttributeMaxQty($id_product_attribute, $product) : $product['pp_maximum_quantity']);
                $qty_step = (isset($product['quantity_step']) ? static::productAttributeQtyStep($id_product_attribute, $product) : $product['pp_qty_step']);
            } else {
                $minimum_quantity = isset($product['minimal_quantity']) ? static::productMinQty($product['minimal_quantity'], $product['minimal_quantity_fractional'], $product) : static::productNoAttributeMinQty($id_product, $product);
                $maximum_quantity = (isset($product['maximum_quantity']) ? static::productMaxQty($product['maximum_quantity'], $product) : $product['pp_maximum_quantity']);
                $qty_step = (isset($product['qty_step']) ? $product['qty_step'] : (isset($product['quantity_step']) ? static::productQtyStep($product['quantity_step'], $product) : $product['pp_qty_step']));
            }
            $default_quantity = $product['pp_default_quantity'];
            static::resolveQuantities(
                $minimum_quantity,
                $maximum_quantity,
                $default_quantity,
                $qty_step,
                $product['qty_policy'],
                array('specific_values' => $product['specific_values'])
            );
            $product['minimal_quantity'] = $minimum_quantity;
            $resolved_quantities = array(
                'minimum_quantity' => $minimum_quantity,
                'maximum_quantity' => $maximum_quantity,
                'default_quantity' => $default_quantity,
                'qty_step' => $qty_step,
            );
            PSMCache::store($cache_key, $resolved_quantities);
        }
        $product['resolved_quantities_id_product_attribute'] = $id_product_attribute;
        $product['minimum_quantity'] = $resolved_quantities['minimum_quantity'];
        $product['maximum_quantity'] = $resolved_quantities['maximum_quantity'];
        $product['default_quantity'] = $resolved_quantities['default_quantity'];
        $product['qty_step'] = $resolved_quantities['qty_step'];
        if (isset($product['minimal_quantity_fractional'])) {
            unset($product['minimal_quantity_fractional']);
        }
    }

    public static function productResolveInputInformation(&$product)
    {
        if (!empty($product['quantity_wanted'])) {
            $id_product = $product['id_product'];
            $quantity = 0;
            if ($qty = PP::getProductBulk($product)) {
                $id_product_attribute = 0;
                $quantity = $qty['quantity'];
                $quantity_fractional = 0;
            } else {
                $quantity_wanted = $product['quantity_wanted'];
                $id_product_attribute = $product['id_product_attribute'] ?? Product::getDefaultAttribute($id_product);
                if ($product['pp_ext'] == 1) {
                    if (static::isPacksCalculator($product)) {
                        $quantity = empty($product['pp_packs_calculator_quantity']) ? 1 : $product['pp_packs_calculator_quantity'];
                        $quantity_fractional = 0;
                    } elseif (isset($product['pp_ext_calculated_quantity'])) {
                        $quantity_fractional = (float) ($product['pp_ext_calculated_quantity'] === null ? 1 : $product['pp_ext_calculated_quantity']);
                        if ($quantity_fractional > 0) {
                            $quantity = $quantity_wanted;
                        }
                    } else {
                        $quantity_fractional = 1;
                    }
                    if (static::isQuantityCalculator($product)) {
                        if (PP::qtyPolicyFractional($product['pp_qty_policy'])) {
                            $quantity = 1;
                        } else {
                            $quantity = (int) $quantity_fractional;
                            $quantity_fractional = 0;
                        }
                    }
                } else {
                    if (PP::qtyPolicyFractional($product['pp_qty_policy'])) {
                        $quantity = 1;
                        $quantity_fractional = $quantity_wanted;
                    } else {
                        $quantity = $quantity_wanted;
                        $quantity_fractional = 0;
                    }
                }
                $qty = array($quantity, $quantity_fractional);
            }
            $product['pp_input_information'] = array(
                'id_product' => $id_product,
                'id_product_attribute' => $id_product_attribute,
                'quantity' => $quantity,
                'quantity_fractional' => $quantity_fractional,
                'qty' => $qty,
            );
            $product['pp_quantity_wanted'] = PP::resolveQty($quantity, $quantity_fractional);
        }
    }

    public static function displayAjaxProductRefresh($url, $id_product)
    {
        if ($url) {
            $url .= '&pp_refresh_id=' . Tools::getValue('pp_refresh_id', 0);
            if ($multidimensional_plugin = static::getMultidimensionalPlugin()) {
                $url = $multidimensional_plugin->displayAjaxProductRefresh($url, $id_product);
            }
        }
        return $url;
    }

    public static function getPriceStatic(
        $id_product,
        $usetax,
        $id_product_attribute,
        $decimals,
        $divisor,
        $only_reduc,
        $usereduc,
        $quantity,
        $force_associated_tax,
        $id_customer,
        $id_cart,
        $id_address,
        &$specific_price_output,
        $with_ecotax,
        $use_group_reduction,
        Context $context,
        $use_customer_price,
        $id_customization
    ) {
        if (!empty($quantity['bulk'])) {
            $total = 0;
            $id_product = $quantity['id_product'];
            foreach ($quantity['bulk'] as $id_product_attribute => $data) {
                $price = Product::getPriceStatic($id_product, $usetax, $id_product_attribute, $decimals, $divisor, $only_reduc, $usereduc, $data['quantity'], $force_associated_tax, $id_customer, $id_cart, $id_address, $specific_price_output, $with_ecotax, $use_group_reduction, $context, $use_customer_price, $id_customization);
                $total += $price * $data['quantity'];
            }
            return static::bulkPriceCalculation($id_product, $total, $quantity);
        }
    }

    public static function priceCalculation(
        $id_shop,
        $id_product,
        $id_product_attribute,
        $id_country,
        $id_state,
        $zipcode,
        $id_currency,
        $id_group,
        $quantity,
        $use_tax,
        $decimals,
        $only_reduc,
        $use_reduc,
        $with_ecotax,
        &$specific_price,
        $use_group_reduction,
        $id_customer,
        $use_customer_price,
        $id_cart,
        $real_quantity,
        $id_customization
    ) {
        if (!empty($quantity['bulk'])) {
            $total = 0;
            $id_product = $quantity['id_product'];
            foreach ($quantity['bulk'] as $id_product_attribute => $data) {
                $price = Product::priceCalculation($id_shop, $id_product, $id_product_attribute, $id_country, $id_state, $zipcode, $id_currency, $id_group, $data['quantity'], $use_tax, $decimals, $only_reduc, $use_reduc, $with_ecotax, $specific_price, $use_group_reduction, $id_customer, $use_customer_price, $id_cart, $real_quantity, $id_customization);
                $total += $price * $data['quantity'];
            }
            return static::bulkPriceCalculation($id_product, $total, $quantity);
        }
    }

    private static function bulkPriceCalculation($id_product, $total, $quantity)
    {
        $price = $total / $quantity['quantity'];
        if ($smartprice_plugin = static::getSmartpricePlugin()) {
            $price = $smartprice_plugin->amendPrice(
                array(
                    'id_product' => $id_product,
                    'id_product_attribute' => 0,
                    'price' => $price,
                    'quantity' => $quantity['quantity'],
                    'specific_price' => null,
                    'data' => array('bulk' => $quantity['bulk']),
                )
            );
        }
        return $price;
    }

    public static function getProductBulk($entity)
    {
        if ('bulk' === static::getPPDataType($entity)) {
            if (isset($entity['pp_data'])) {
                $raw = $entity['pp_data'];
                $quantity_wanted = null;
            } else {
                $raw = Tools::getValue('product_bulk');
                $quantity_wanted = Tools::getValue('qty');
            }
            return static::resolvePPData($raw, $quantity_wanted);
        }
    }

    public static function getPPDataType($entity)
    {
        return !empty($entity['pp_data_type']) ? $entity['pp_data_type'] : null;
    }

    public static function resolvePPData($raw, $quantity_wanted = null)
    {
        if (!empty($raw)) {
            if (is_array($raw) && !empty($raw['pp_data'])) {
                $raw = $raw['pp_data'];
            }
            $cache_key = 'PP::resolvePPData:' . $raw . $quantity_wanted;
            if ($data = PSMCache::retrieve($cache_key)) {
                return $data;
            }
            $value = null;
            $data = json_decode($raw, true);
            if (isset($data['t']) && $data['t'] == 'bulk') {
                if (isset($data['v']) && $data['v'] == 1 && !empty($data['d'])) {
                    $bulk = array();
                    $qty = 0;
                    foreach ($data['d'] as $item) {
                        list($id_product_attribute, $quantity, $attributes) = explode(':', $item);
                        $bulk[$id_product_attribute] = array(
                            'quantity' => $quantity,
                            'attributes' => explode(',', $attributes),
                        );
                        $qty += $quantity;
                    }
                    if ($quantity_wanted !== null && $qty != $quantity_wanted) {
                        // should not happen
                        throw new PrestaShopException('input quantity_wanted does not match sum of quantities specified');
                    }
                    $value = array('id_product' => $data['id_product'], 'bulk' => $bulk, 'quantity' => $qty, 'raw' => $raw);
                }
            }
            PSMCache::store($cache_key, $value);
            return $value;
        }
    }

    public static function amendProduct(&$product)
    {
        if (static::is_array($product) && !isset($product['pp_ext'])) {
            if (!isset($product['id_pp_template'])) {
                $product['id_pp_template'] = static::getProductTemplateId($product);
            }
            static::assignProductProperties($product);
            if ($product['pp_unit_price_ratio'] > 0 || !isset($product['unit_price_ratio'])) {
                $product['unit_price_ratio'] = $product['pp_unit_price_ratio'];
            }
            if (!empty($product['pp_unity_text']) || !isset($product['unity'])) {
                $product['unity'] = $product['pp_unity_text'];
            }
            if ($product['unit_price_ratio'] > 0 && isset($product['price'])) {
                $product['unit_price'] = ($product['price'] / $product['unit_price_ratio']); // recalculate unit_price
            }
            if ((int) (($product['pp_display_mode'] & 2) == 2)) { // display retail price as unit price
                if (empty($product['unity'])) {
                    $product['unity'] = ' ';
                }
                if ((float) $product['unit_price_ratio'] == 0) {
                    $product['unit_price_ratio'] = 1.0;
                }
            }
            if (isset($product['product_quantity_fractional'])) {
                $product['product_qty'] = static::resolveQty((int) $product['product_quantity'], (float) $product['product_quantity_fractional']);
            }
            $product['qty_step'] = $product['id_pp_template'] > 0 ? (static::productQtyStep(isset($product['quantity_step']) ? $product['quantity_step'] : 0, $product)) : 0;
        }
        return $product;
    }

    public static function amendProducts(&$products)
    {
        foreach ($products as &$product) {
            static::amendProduct($product);
        }
        return $products;
    }

    public static function amendProductCompatibility(&$product)
    {
        if (static::is_array($product)) {
            if (isset($product['product_id']) && !isset($product['id_product'])) {
                $product['id_product'] = $product['product_id'];
            }
            if (isset($product['product_attribute_id']) && !isset($product['id_product_attribute'])) {
                $product['id_product_attribute'] = $product['product_attribute_id'];
            }
        }
    }

    public static function filterProductCustomizedDatas($product, $customized_datas)
    {
        $product_customized_datas = array();
        // compatibility
        $id_product = (isset($product['id_product']) ? 'id_product' : 'product_id');
        $id_product_attribute = (isset($product['id_product_attribute']) ? 'id_product_attribute' : 'product_attribute_id');
        if (isset($customized_datas[(int) $product[$id_product]][(int) $product[$id_product_attribute]])) {
            foreach ($customized_datas[(int) $product[$id_product]][(int) $product[$id_product_attribute]] as $id_address_delivery => $customization_per_address) {
                foreach ($customization_per_address as $id_customization => $customization) {
                    if ($customization['id_cart_product'] == $product['id_cart_product']) {
                        $product_customized_datas[(int) $product[$id_product]][(int) $product[$id_product_attribute]][$id_address_delivery][$id_customization] = $customization;
                    }
                }
            }
        }
        return $product_customized_datas;
    }

    public static function setProductCustomizedDatas(&$product, $customized_datas)
    {
        $product['customizedDatas'] = null;
        // compatibility
        $id_product = (isset($product['id_product']) ? 'id_product' : 'product_id');
        $id_product_attribute = (isset($product['id_product_attribute']) ? 'id_product_attribute' : 'product_attribute_id');
        if (isset($customized_datas[(int) $product[$id_product]][(int) $product[$id_product_attribute]])) {
            foreach ($customized_datas[(int) $product[$id_product]][(int) $product[$id_product_attribute]] as $id_address_delivery => &$customization_per_address) {
                foreach ($customization_per_address as $id_customization => &$customization) {
                    if ($customization['id_cart_product'] == $product['id_cart_product']) {
                        $customization['qty'] = static::resolveQty((int) $customization['quantity'], (float) $customization['quantity_fractional']);
                        $product['customizedDatas'][$id_address_delivery][$id_customization] = $customized_datas[(int) $product[$id_product]][(int) $product[$id_product_attribute]][$id_address_delivery][$id_customization];
                    }
                }
            }
        } else {
            $product['customizationQuantityTotal'] = 0;
        }
    }

    public static function getProductTemplateId($obj)
    {
        if ($obj instanceof ProductBase) {
            return (int) $obj->id_pp_template;
        }
        if (static::is_array($obj) && isset($obj['id_pp_template'])) {
            return $obj['id_pp_template'];
        }
        $id = static::resolveProductId($obj);
        if ($id > 0) {
            if (!isset(self::$cache_product_template_id[$id])) {
                self::$cache_product_template_id[$id] = (int) Db::getInstance()->getValue('SELECT `id_pp_template` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . (int) $id);
            }
            $id = self::$cache_product_template_id[$id];
        }
        return $id;
    }

    private static function resolveTemplate(&$template, $data = false, $id_lang = null, $ms_data = null, $extra = false)
    {
        if (is_array($data)) {
            $id_pp_template = $data['id_pp_template'];
            $template['id_pp_template'] = $id_pp_template;
            $template['pp_qty_policy'] = $data['qty_policy'];
            $template['pp_qty_mode'] = $data['qty_mode'];
            $template['pp_display_mode'] = $data['display_mode'];
            $template['pp_price_display_mode'] = $data['price_display_mode'];
            $template['pp_bo_measurement_system'] = $data['measurement_system'];
            $template['pp_unit_price_ratio'] = $data['unit_price_ratio'];
            $template['pp_minimum_price_ratio'] = $data['minimal_price_ratio'];
            $template['db_minimum_quantity'] = $data['minimal_quantity'];
            $template['db_maximum_quantity'] = $data['maximum_quantity'];
            $template['db_total_maximum_quantity'] = $data['total_maximum_quantity'];
            $template['db_default_quantity'] = $data['default_quantity'];
            $template['db_qty_step'] = $data['qty_step'];
            $template['db_qty_shift'] = $data['qty_shift'];
            $template['db_qty_decimals'] = $data['qty_decimals'];
            $template['db_qty_values'] = (string) $data['qty_values'];
            $template['db_qty_ratio'] = $data['qty_ratio'];
            $template['pp_bo_qty_available_display'] = $data['qty_available_display'];
            $template['pp_bo_hidden'] = $data['hidden'];
            $template['pp_customization'] = $data['customization'];
            $template['pp_css'] = (string) $data['css'];
            $template['pp_ext'] = $data['ext'];
            if (isset($data['template_properties'])) {
                $template_properties = $data['template_properties'];
            }
        }
        if (!isset($template_properties) || !is_array($template_properties)) {
            $template_properties = array();
            $rows = Db::getInstance()->executeS('SELECT `pp_name`, `id_pp_property` FROM `' . _DB_PREFIX_ . 'pp_template_property` WHERE `id_pp_template` = ' . (int) $template['id_pp_template']);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $template_properties[$row['pp_name']] = $row['id_pp_property'];
                }
            }
        }

        $pproperties_plugin = static::getPpropertiesPlugin();
        $ms = $pproperties_plugin ? $pproperties_plugin->resolveMeasurementSystem($template, $ms_data) : false;
        if (!static::isValidMeasurementSystem($ms)) {
            $ms = static::resolveMeasurementSystem($template['pp_bo_measurement_system']);
        }
        $template['pp_measurement_system'] = $ms;
        $template['pp_qty_available_display'] = $template['pp_bo_qty_available_display'];

        if (static::isMultidimensional($template)) {
            $db = Db::getInstance();
            $template_ext = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pp_template_ext` WHERE `id_pp_template` = ' . (int) $template['id_pp_template']);
            $template_ext_prop = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pp_template_ext_prop` WHERE `id_pp_template` = ' . (int) $template['id_pp_template'] . ' ORDER BY `position`');
            if ($template_ext !== false && $template_ext_prop !== false && count($template_ext_prop) && (int) $template_ext['method'] > 0) {
                $template['pp_ext_policy'] = (int) $template_ext['policy'];
                $template['pp_ext_method'] = (int) $template_ext['method'];
                $template['pp_ext_title'] = static::resolveProperty($template_ext['title'], $id_lang, $ms);
                $template['pp_ext_property'] = static::resolveProperty($template_ext['property'], $id_lang, $ms);
                $template['pp_ext_text'] = static::resolveProperty($template_ext['text'], $id_lang, $ms);
                $template['pp_ext_precision'] = (int) $template_ext['precision'];
                $template['pp_ext_explanation'] = static::resolveProperty($template_ext['explanation'], $id_lang, $ms);
                $template['pp_qty_ext_text'] = $template['pp_ext_text'];
                $template['pp_ext_minimum_quantity'] = (float) $template_ext['minimum_quantity'];
                $template['pp_ext_maximum_quantity'] = (float) $template_ext['maximum_quantity'];
                $template['pp_ext_minimum_quantity_text'] = static::resolveProperty($template_ext['minimum_quantity_text'], $id_lang, $ms);
                $template['pp_ext_maximum_quantity_text'] = static::resolveProperty($template_ext['maximum_quantity_text'], $id_lang, $ms);
                $template['pp_ext_minimum_price_ratio'] = (float) $template_ext['minimal_price_ratio'];

                $multidimensional_plugin = static::getMultidimensionalPlugin();
                if ($multidimensional_plugin) {
                    $multidimensional_plugin->resolveTemplate($template, $template_ext, $id_lang, $ms);
                }

                $pp_ext_prop = array();
                $position = 1; // renumber positions and ignore $row['position'] => protect from wrong database values (rows are ordered by position)
                foreach ($template_ext_prop as $row) {
                    $ext = array();
                    $ext['id_ext_prop'] = (int) $row['id_ext_prop'];
                    $ext['position'] = $position;
                    $ext['property'] = static::resolveProperty($row['property'], $id_lang, $ms);
                    $ext['text'] = static::resolveProperty($row['text'], $id_lang, $ms);
                    $ext['order_text'] = static::resolveProperty($row['order_text'], $id_lang, $ms);
                    $ext['minimum_quantity'] = (float) $row['minimum_quantity'];
                    $ext['maximum_quantity'] = (float) $row['maximum_quantity'];
                    $ext['default_quantity'] = (float) $row['default_quantity'];
                    $ext['qty_step']         = (float) $row['qty_step'];
                    $ext['qty_ratio']        = (float) $row['qty_ratio'];
                    if ($multidimensional_plugin) {
                        $multidimensional_plugin->resolveTemplateExtRow($ext, $row, $id_lang, $ms);
                    }
                    $pp_ext_prop[$position] = $ext;
                    $position++;
                }
                $template['pp_ext_prop'] = $pp_ext_prop;
                if ($template['pp_qty_available_display'] == 0 && $template['pp_ext_policy'] != 4) { // (pp_ext_policy == 4) quantity calculator
                    $template['pp_qty_available_display'] = 2;
                }
                if (isset($multidimensional_plugin->pro)) {
                    $template['pp_ext_pro'] = 1;
                }
                if (!(bool) static::getMultidimensionalPlugin()) {
                    $template['pp_bo_hidden'] = 1;
                }
            } else {
                $template['pp_ext'] = 0;
            }
        }

        if (isset($template_properties['pp_explanation'])) {
            $template['pp_bo_buy_block_index'] = (int) $template_properties['pp_explanation'];
            $template['pp_explanation'] = static::resolveProperty($template_properties['pp_explanation'], $id_lang, $ms);
        } else {
            $template['pp_bo_buy_block_index'] = 0;
            $template['pp_explanation'] = '';
        }
        $template['pp_unity_text'] = (isset($template_properties['pp_unity_text']) ? static::resolveProperty($template_properties['pp_unity_text'], $id_lang, $ms) : '');
        $template['pp_qty_text'] = (isset($template_properties['pp_qty_text']) ? static::resolveProperty($template_properties['pp_qty_text'], $id_lang, $ms) : '');
        $template['pp_price_text'] = (isset($template_properties['pp_price_text']) ? static::resolveProperty($template_properties['pp_price_text'], $id_lang, $ms) : '');
        $template['pp_unit_price_ratio'] = (float) (isset($template['pp_unit_price_ratio']) ? $template['pp_unit_price_ratio'] : 0);
        $template['pp_minimum_price_ratio'] = (float) (isset($template['pp_minimum_price_ratio']) ? $template['pp_minimum_price_ratio'] : 0);
         // 98 - multidimensional disable calculation
        $template['pp_product_qty_text'] = (isset($template['pp_qty_ext_text']) && !static::isPacksCalculator($template) && !static::isQuantityCalculator($template) && $template['pp_ext_method'] != 98 ? $template['pp_qty_ext_text'] : $template['pp_qty_text']);
        if (isset($template['pp_qty_policy'])) {
            $q = (int) $template['pp_qty_policy'];
            $template['pp_qty_policy'] = in_array($q, array(0, 1, 2)) ? $q : 0;
        } else {
            $template['pp_qty_policy'] = 0;
        }
        $template['qty_policy'] = ($template['pp_ext'] == 1 && $template['pp_ext_policy'] != 4 ? 0 : $template['pp_qty_policy']); // (pp_ext_policy == 4) quantity calculator
        $template['pp_qty_values'] = $template['db_qty_values'];
        $template['specific_values'] = static::explodeSpecificValues($template['pp_qty_values']);
        if ($template['pp_qty_policy'] == 2 && ($template['pp_ext'] != 1 || $template['pp_ext_policy'] == 4)) { // (pp_ext_policy == 4) quantity calculator
            if ((float) $template['db_minimum_quantity'] > 0) {
                $template['pp_minimum_quantity'] = (float) $template['db_minimum_quantity'];
                $template['pp_bo_minimum_quantity_customized'] = 1;
            } else {
                $template['pp_minimum_quantity'] = static::missingMinimumQuantity(2);
                $template['pp_bo_minimum_quantity_customized'] = 0;
            }
            $template['pp_maximum_quantity'] = (float) $template['db_maximum_quantity'];
            $template['pp_total_maximum_quantity'] = (float) $template['db_total_maximum_quantity'];
            if ($template['pp_total_maximum_quantity'] != 0 && $template['pp_total_maximum_quantity'] < $template['pp_maximum_quantity']) {
                $template['pp_total_maximum_quantity'] = $template['pp_maximum_quantity'];
            }
            if ((float) $template['db_default_quantity'] > 0) {
                // db_default_quantity is validated against template minimum quantity, quantity step and specific values when template is saved
                $template['pp_default_quantity'] = (float) $template['db_default_quantity'];
                $template['pp_has_default_quantity'] = 1;
            } else {
                $template['pp_default_quantity'] = (is_array($template['specific_values']) ? (float) $template['specific_values'][0] : static::missingDefaultQuantity(2));
                $template['pp_has_default_quantity'] = 0;
            }
            $template['pp_bo_minimum_quantity'] = 0; // used in products's page in back office as an indicator to use template's default value
            $template['pp_bo_qty_text'] = $template['pp_product_qty_text']; // used in back office
            $template['pp_bo_minimum_quantity_text'] =
                $template['pp_bo_maximum_quantity_text'] =
                $template['pp_bo_total_maximum_quantity_text'] =
                $template['pp_bo_default_quantity_text'] =
                $template['pp_bo_qty_step_text'] =
                $template['pp_bo_qty_shift_text'] = $template['pp_product_qty_text'];
            $template['pp_qty_step'] = (float) $template['db_qty_step'];
            $template['pp_qty_shift'] = (float) $template['db_qty_shift'];
            $template['qty_shift'] = $template['pp_qty_step'] ?: ($template['pp_qty_shift'] ?: static::quantityShift(2));
            $template['pp_qty_decimals'] = (int) $template['db_qty_decimals'];
            $template['qty_decimals'] = $template['pp_qty_decimals'] ?: -1;
            // $dot = strpos($template['pp_qty_step'], '.');
            // $template['qty_decimals'] = ($dot === false ? 0 : Tools::strlen($template['pp_qty_step']) - $dot - 1);
            $template['pp_qty_ratio'] = (float) $template['db_qty_ratio'];
        } else {
            $template['pp_bo_minimum_quantity'] = ($template['pp_qty_policy'] == 0 ? 1 : 0); // used in products's page in back office as an indicator to use template's default value
            if ((int) $template['db_minimum_quantity'] > $template['pp_bo_minimum_quantity']) {
                $template['pp_minimum_quantity'] = (int) $template['db_minimum_quantity'];
                $template['pp_bo_minimum_quantity_customized'] = 1;
            } else {
                $template['pp_minimum_quantity'] = static::missingMinimumQuantity(0);
                $template['pp_bo_minimum_quantity_customized'] = 0;
            }
            $template['pp_maximum_quantity'] = (int) $template['db_maximum_quantity'];
            $template['pp_total_maximum_quantity'] = (int) $template['db_total_maximum_quantity'];
            if ((int) $template['db_default_quantity'] > 0) {
                $template['pp_default_quantity'] = (int) $template['db_default_quantity'];
                $template['pp_has_default_quantity'] = 1;
            } else {
                $template['pp_default_quantity'] = (is_array($template['specific_values']) ? (float) $template['specific_values'][0] : static::missingDefaultQuantity(0));
                $template['pp_has_default_quantity'] = 0;
            }
            $template['pp_bo_qty_text'] = (in_array($template['pp_qty_policy'], array(0, 1)) ? '' : $template['pp_product_qty_text']); // used in back office
            $template['pp_bo_minimum_quantity_text'] =
                $template['pp_bo_maximum_quantity_text'] =
                $template['pp_bo_total_maximum_quantity_text'] =
                $template['pp_bo_default_quantity_text'] =
                $template['pp_bo_qty_step_text'] =
                $template['pp_bo_qty_shift_text'] = ($template['pp_ext'] == 1 ? '' : $template['pp_bo_qty_text']);
            $template['pp_qty_step'] = (int) $template['db_qty_step'];
            $template['pp_qty_shift'] = (int) $template['db_qty_shift'];
            $template['qty_shift'] = $template['pp_qty_step'] ?: ($template['pp_qty_shift'] ?: static::quantityShift(0));
            $template['qty_decimals'] = $template['pp_qty_decimals'] = 0;
            $template['pp_qty_ratio'] = (int) $template['db_qty_ratio'];
        }

        if ($extra) {
            $result = array();
            if ((int) $id_pp_template > 0) {
                $result['name'] = static::resolveTemplateLangAttribute((int) $id_pp_template, $id_lang, 'name');
                $result['description'] = static::resolveTemplateLangAttribute((int) $id_pp_template, $id_lang, $ms != 2 ? 'description_1' : 'description_2');
                $result['auto_desc'] = (int) empty($result['description']);
            } else {
                $result['name'] = '';
                $result['auto_desc'] = 1;
                $result['description'] = '';
            }
            $template['name'] = $result['name'];
            $template['auto_desc'] = $result['auto_desc'];
            $template['description'] = $result['description'];
        }
        if ($pproperties_plugin) {
            $pproperties_plugin->resolveTemplate($template, $data, $id_lang, $ms);
        }
        if ($template['pp_customization']) {
            $hook_params = array(
                'hook' => 'resolveTemplate',
                'template' => $template,
            );
            $res = Hook::exec('ppropertiesCustom', $hook_params, Module::getModuleIdByName('ppropertiescustom'), true);
            if (isset($res['ppropertiescustom']) && is_array($res['ppropertiescustom'])) {
                foreach ($res['ppropertiescustom'] as $key => $value) {
                    $template[$key] = $value;
                }
            }
            if ('bulk' === static::getPPDataType($template)) {
                $template['qty_policy'] = 0;
            }
        }
    }

    public static function getTemplateName($id_pp_template, $with_id = false, $id_lang = null)
    {
        $cache_key = 'PP::templateNames:' . (int) $id_lang . '-' . ($with_id ? 1 : 0);
        if (!PSMCache::isStored($cache_key)) {
            $id_lang = static::resolveLanguageId($id_lang);
            $rows = Db::getInstance()->executeS(
                'SELECT DISTINCT name, t.id_pp_template as id
                FROM ' . _DB_PREFIX_ . 'pp_template t
                LEFT JOIN `' . _DB_PREFIX_ . 'pp_template_lang` tl
                    ON (t.`id_pp_template` = tl.`id_pp_template`
                    AND tl.`id_lang` = ' . (int) $id_lang . ')
                WHERE tl.`id_lang` = ' . (int) $id_lang . '
                ORDER BY id'
            );
            $result = array_column($rows, 'name', 'id');
            foreach ($result as $id => &$name) {
                if (empty($name)) {
                    $name = static::resolveTemplateLangAttribute((int) $id, $id_lang, 'name');
                }
                if ($with_id) {
                    $name = "# {$id} {$name}";
                }
            }
            PSMCache::store($cache_key, $result);
        }
        $names = PSMCache::retrieve($cache_key);
        return (array_key_exists($id_pp_template, $names) ? $names[$id_pp_template] : '');
    }

    public static function resolveTemplateLangAttribute($id_pp_template, $id_lang, $key)
    {
        if ((int) $id_pp_template > 0) {
            $id_lang = static::resolveLanguageId($id_lang);
            $text = static::getTemplateAttribute($id_pp_template, $key, $id_lang);
            if (empty($text)) {
                $id_lang_default = (int) Configuration::get('PS_LANG_DEFAULT');
                if ($id_lang != $id_lang_default) {
                    $text = static::getTemplateAttribute($id_pp_template, $key, $id_lang_default);
                }
                if ($id_lang_default != 1 && (empty($text))) {
                    $text = static::getTemplateAttribute($id_pp_template, $key, 1);
                }
                if (empty($text)) {
                    // try to find not empty text for any language
                    $rows = Db::getInstance()->executeS('SELECT `' . $key . '` FROM `' . _DB_PREFIX_ . 'pp_template_lang` WHERE `id_pp_template` = ' . (int) $id_pp_template);
                    foreach ($rows as $row) {
                        if (!empty($row[$key])) {
                            $text = $row[$key];
                            break;
                        }
                    }
                }
            }
            return $text ?: '';
        }
        return '';
    }

    public static function getTemplateById($id_pp_template)
    {
        return ($id_pp_template > 0 ? Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pp_template` WHERE `id_pp_template` = ' . (int) $id_pp_template) : false);
    }

    private static function getTemplateAttribute($id_pp_template, $key, $id_lang)
    {
        return Db::getInstance()->getValue(
            'SELECT `' . $key . '` FROM `' . _DB_PREFIX_ . 'pp_template_lang` WHERE `id_pp_template` = ' . (int) $id_pp_template . ' AND `id_lang` = ' . $id_lang
        );
    }

    public static function resolveProperty($id_pp_property, $id_lang, $ms)
    {
        static $cache_properties = array();
        $id_lang = static::resolveLanguageId($id_lang);
        $key = $id_pp_property . '_' . $id_lang . '_' . $ms;
        if (!isset($cache_properties[$key])) {
            $result = static::getProperty($id_pp_property, $id_lang, $ms);
            if ($result === false) {
                $result = static::getProperty($id_pp_property, Configuration::get('PS_LANG_DEFAULT'), $ms);
                if ($result === false) {
                    $result = static::getProperty($id_pp_property, 1, $ms);
                    if ($result === false) {
                        $result = '';
                    }
                }
            }
            $cache_properties[$key] = $result;
        }
        return $cache_properties[$key];
    }

    private static function getProperty($id_pp_property, $id_lang, $ms)
    {
        return Db::getInstance()->getValue(
            'SELECT `text_' . ((int) $ms != 2 ? '1' : '2') . '` FROM `' . _DB_PREFIX_ . 'pp_property_lang` WHERE `id_pp_property` = '
                . (int) $id_pp_property . ' AND `id_lang` = ' . (int) $id_lang
        );
    }

    public static function setQtyO(&$obj, $quantity)
    {
        if (static::qtyPolicyFractional(static::qtyPolicy(static::getProductTemplateId($obj), true))) {
            return static::hydrateQtyO($obj, 'quantity', $quantity);
        }
        $obj->quantity = (int) $quantity;
        $obj->quantity_remainder = 0;
    }

    public static function setQtyA($arr, $quantity)
    {
        if (static::qtyPolicyFractional(static::qtyPolicy(static::getProductTemplateId($arr), true))) {
            return static::hydrateQtyA($arr, 'quantity', $quantity);
        }
        $arr['quantity'] = (int) $quantity;
        $arr['quantity_remainder'] = 0;
        return $arr;
    }

    public static function resolveIcp($icp)
    {
        return ((int) $icp == 0 ? (int) Tools::getValue('icp', 0) : (int) $icp);
    }

    public static function sqlIcp($icp)
    {
        return ((int) $icp > 0 ? ' AND `id_cart_product` = ' . (int) $icp : '');
    }

    public static function sqlQty($column, $prefix = false, $column_fractional = false)
    {
        $col = ($prefix === false ? '`' . $column : $prefix . '.`' . $column);
        if ($column_fractional === false) {
            $col_frac = $col . '_fractional';
        } else {
            $col_frac = ($prefix === false ? '`' . $column_fractional : $prefix . '.`' . $column_fractional);
        }
        return 'IF(' . $col_frac . '` > 0, ' . $col . '` * ' . $col_frac . '`, ' . $col . '`)';
    }

    private static function safeOutputPregReplaceCallback($matches)
    {
        $s = $matches[0];
        $s = str_replace('"', '%%%PP_Q%%%', $s);
        $s = str_replace('<', '%%%PP_LT%%%', $s);
        $s = str_replace('>', '%%%PP_GT%%%', $s);
        return $s;
    }

    public static function safeOutput($string, $flags = false, $mode = 0, $lenient = false)
    {
        if (is_array($string)) {
            array_walk($string, array('PP', 'safeOutputWalk'), array($flags, $mode, $lenient));
            return $string;
        }

        if (Tools::strlen($string) > 0) {
            if ($lenient) {
                $string = preg_replace_callback(
                    '!<(span|div|pre)( *)(class="|style=")*[0-9a-zA-Z:#;"=_\- ]*>!',
                    'static::safeOutputPregReplaceCallback',
                    $string
                );
            }

            if ($mode === 0) {
                $string = htmlentities($string, ($flags === false ? ENT_NOQUOTES : $flags), 'UTF-8');
            } elseif ($mode === 1) {
                $string = htmlspecialchars($string, ($flags === false ? ENT_NOQUOTES : $flags), 'UTF-8');
            }
            // elseif ($mode === 2)
            //  $string = htmlspecialchars($string, ($flags === false ? ENT_NOQUOTES : $flags), 'UTF-8');

            $string = str_replace('&lt;sup&gt;', '<sup>', $string);
            $string = str_replace('&lt;/sup&gt;', '</sup>', $string);
            if ($lenient) {
                $string = str_replace('%%%PP_Q%%%', ($mode === 1 ? "\\\"" : '"'), $string);
                $string = str_replace('%%%PP_LT%%%', '<', $string);
                $string = str_replace('%%%PP_GT%%%', '>', $string);
            }
        }
        return $string;
    }

    public static function safeOutputLenient($string, $flags = false, $mode = 0)
    {
        // static $lenient = array(
        //     array(
        //         'search' => array('&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'),
        //         'replace' => '<br>'
        //     ),
        //     array(
        //         'search' => array('&lt;/div&gt;', '&lt;/span&gt;', '&lt;/pre&gt;'),
        //         'replace' => array('</div>', '</span>', '</pre>')
        //     ),
        // );
        static $search1 = array('&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;');
        static $search2 = array('&lt;/div&gt;', '&lt;/span&gt;', '&lt;/pre&gt;');
        static $lenient = array(
            array(
                'search' => '',
                'replace' => '<br>'
            ),
            array(
                'search' => '',
                'replace' => array('</div>', '</span>', '</pre>')
            ),
        );
        $lenient[0]['search'] = $search1;
        $lenient[1]['search'] = $search2;
        $s = static::safeOutput($string, $flags, $mode, true);
        foreach ($lenient as $array) {
            $s = str_replace($array['search'], $array['replace'], $s);
        }
        return $s;
    }

    /* for javascript (string in javascript should be quoted using double quotes) */
    public static function safeOutputJS($string)
    {
        $string = static::safeOutput($string, ENT_COMPAT, 1);
        return preg_replace('/\R/u', ' ', $string);
    }

    /* for javascript (string in javascript should be quoted using double quotes) */
    public static function safeOutputLenientJS($string)
    {
        $string = static::safeOutputLenient($string, ENT_COMPAT, 1);
        return preg_replace('/\R/u', ' ', $string);
    }

    /* for HTML value field (input, textarea, option select, etc.) should be quoted using double quotes */
    public static function safeOutputValue($string)
    {
        return static::safeOutput($string, ENT_COMPAT, 2);
    }

    /* for HTML value field (input, textarea, option select, etc.) should be quoted using double quotes */
    public static function safeOutputLenientValue($string)
    {
        return static::safeOutput($string, ENT_COMPAT, 2, true);
    }

    public static function outputJS($string)
    {
        $string = str_replace('"', "\\\"", $string);
        $string = preg_replace('/\R/u', ' ', $string);
        return $string;
    }

    private static function safeOutputWalk(&$value, $key, $data)
    {
        list($flags, $mode, $lenient) = $data;
        $value = static::safeOutput($value, $flags, $mode, $lenient);
    }

    public static function noHtml($string)
    {
        $search = array('<sup>2</sup>', '<br>', '<br/>', '<br />');
        $replace = array('2', ' ', ' ', ' ');
        return str_replace($search, $replace, $string);
    }

    public static function wrap($text, $class = false, $wrap = 'span', ?array $attrs = null, ?array $options = null)
    {
        $allow_empty_text = $options['allow_empty_text'] ?? false;
        if (Tools::strlen($text) > 0 || $allow_empty_text) {
            $safeotput = $options['safeotput'] ?? false;
            if (is_string($class)) {
                $wrap = sprintf('%s class="%s"', $wrap, $class);
            }
            if ($attrs) {
                foreach ($attrs as $k => $v) {
                    $wrap = sprintf('%s %s="%s"', $wrap, $k, $v);
                }
            }
            $token = explode(' ', $wrap)[0];
            return sprintf('<%s>%s</%s>', $wrap, $safeotput ? static::safeOutput($text) : $text, $token);
        }
        return '';
    }

    public static function span($text, $class = false, ?array $attrs = null)
    {
        return static::wrap($text, $class, 'span', $attrs, ['allow_empty_text' => true]);
    }

    public static function div($text, $class = false, ?array $attrs = null)
    {
        return static::wrap($text, $class, 'div', $attrs, ['allow_empty_text' => true]);
    }

    public static function wrapA($text, $class = false, ?array $attrs = null)
    {
        return static::wrap($text, $class, 'a', $attrs, ['allow_empty_text' => true]);
    }

    public static function wrapI($text, $class = false, ?array $attrs = null)
    {
        return static::wrap($text, $class, 'i', $attrs, ['allow_empty_text' => true]);
    }

    public static function wrapProperty($properties, $property, $wrap = 'span')
    {
        return static::wrap($properties[$property], $property, $wrap);
    }

    public static function hydrateQtyO(&$obj, $key, $quantity = null, $remainder_provider = null)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $obj = static::hydrateQtyInternal($obj, $k, $quantity, $remainder_provider);
            }
        } else {
            $obj = static::hydrateQtyInternal($obj, $key, $quantity, $remainder_provider);
        }
        return $obj;
    }

    public static function hydrateQtyA($arr, $key, $quantity = null, $remainder_provider = null)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $arr = static::hydrateQtyInternal($arr, $k, $quantity, $remainder_provider);
            }
        } else {
            $arr = static::hydrateQtyInternal($arr, $key, $quantity, $remainder_provider);
        }
        return $arr;
    }

    protected static function hydrateQtyInternal(&$obj, $key, $quantity = null, $remainder_provider = null)
    {
        $key_remainder = $key . '_remainder';
        if ($quantity === null) {
            $quantity = $obj[$key];
        } elseif (is_string($quantity)) {
            $quantity = static::floatParser()->fromString($quantity);
        }
        if (is_object($remainder_provider)) {
            $quantity += $remainder_provider->{$key_remainder};
        }
        if (is_array($obj)) {
            list($obj[$key], $obj[$key_remainder]) = static::explodeQty($quantity);
        } else {
            list($obj->{$key}, $obj->{$key_remainder}) = static::explodeQty($quantity);
        }
        return $obj;
    }

    public static function getPpropertiesPlugin()
    {
        static $pproperties_plugin = null;
        if ($pproperties_plugin === null) {
            $pproperties_plugin = PSM::getPlugin('ppropertiesplugin');
        }
        return $pproperties_plugin;
    }

    public static function getMultidimensionalPlugin()
    {
        static $multidimensional_plugin = null;
        if ($multidimensional_plugin === null) {
            $multidimensional_plugin = PSM::getPlugin('ppropertiesmultidimensional');
        }
        return $multidimensional_plugin;
    }

    public static function getSmartpricePlugin()
    {
        static $smartprice_plugin = null;
        if ($smartprice_plugin === null) {
            $smartprice_plugin = PSM::getPlugin('ppropertiessmartprice');
        }
        return $smartprice_plugin;
    }

    public static function displayPrice($price, $currency = null, $no_utf8 = false, Context $context = null, $decimals = false)
    {
        if ($decimals === false) {
            return Tools::displayPrice($price, $currency, $no_utf8, $context);
        }
        // TODO
        return Tools::displayPrice($price, $currency, $no_utf8, $context);
    }

    public static function currencyConversionRate(Currency $currency_from = null, Currency $currency_to = null)
    {
        if ($currency_from === $currency_to) {
            return 1.0;
        }
        if ($currency_from === null) {
            $currency_from = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        if ($currency_to === null) {
            $currency_to = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        if ($currency_from->id == Configuration::get('PS_CURRENCY_DEFAULT')) {
            $rate = $currency_to->conversion_rate;
        } else {
            $amount = 1.0;
            $conversion_rate = ($currency_from->conversion_rate == 0 ? 1 : $currency_from->conversion_rate);
            // Convert amount to default currency (using the old currency rate)
            $amount = $amount / $conversion_rate;
            // Convert to new currency
            $rate = $amount * $currency_to->conversion_rate;
        }
        return $rate;
    }

    public static function convertPriceFull($amount, Currency $currency_from = null, Currency $currency_to = null, $round = true)
    {
        if ($round) {
            return Tools::convertPriceFull($amount, $currency_from, $currency_to);
        }
        if ($currency_from === $currency_to) {
            return $amount;
        }
        if ($currency_from === null) {
            $currency_from = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        if ($currency_to === null) {
            $currency_to = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        if ($currency_from->id == Configuration::get('PS_CURRENCY_DEFAULT')) {
            $amount *= $currency_to->conversion_rate;
        } else {
            $conversion_rate = ($currency_from->conversion_rate == 0 ? 1 : $currency_from->conversion_rate);
            // Convert amount to default currency (using the old currency rate)
            $amount = $amount / $conversion_rate;
            // Convert to new currency
            $amount *= $currency_to->conversion_rate;
        }
        return $amount;
    }

    public static function amendTinymceText($text)
    {
        if ($text) {
            if (substr_count($text, '<p>') == 1) {
                // remove the beginning and ending p tag
                $text = trim($text);
                $text = preg_replace('/^<p>/', '', $text);
                $text = preg_replace('/<\/p>$/', '', $text);
            }
        }
        return $text;
    }

    public static function normalizeFilename($filename)
    {
        return str_replace('\\', '/', $filename);
    }

    private static function missingMinimumQuantity($qty_policy)
    {
        return static::qtyPolicyFractional($qty_policy) ? 0.1 : 1;
    }

    private static function missingDefaultQuantity($qty_policy)
    {
        return static::qtyPolicyFractional($qty_policy) ? 0.5 : 1;
    }

    private static function quantityShift($qty_policy)
    {
        return static::qtyPolicyFractional($qty_policy) ? 0.5 : 1;
    }

    public static function jsScriptInline($str, $once = false, $check_for_pp = true)
    {
        if ($once && !PSMCache::once('PP::jsScriptInline:' . $str)) {
            return '';
        }
        return '<script data-keepinline="true">' . ($check_for_pp ? 'if (typeof window.pp === "object" && pp.ready){' : '') . $str . ($check_for_pp ? '}' : '') . '</script>';
    }

    public static function raw($s)
    {
        return new \Twig_Markup($s, 'UTF-8');
    }

    /* smarty plugin */
    private static $smarty_generated = false;
    private static $smarty_generated_data = null;
    private static $smarty_cart = null;
    private static $smarty_order = null;
    private static $smarty_product = null;
    private static $smarty_currency = null;
    private static $smarty_bo = null;
    private static $smarty_pdf = false;
    private static $smarty_multiline_prefix = false;
    private static $smarty_multiline_suffix = false;

    public static function smartyModifierPSM($value, $type, $param = null)
    {
        if ($type == 'qty') {
            $behavior = static::qtyBehavior($value, $value[$param]);
            $value = ($behavior ? static::resolveQty($value[$param], $value[$param . '_fractional']) : (int) $value[$param]);
        } elseif ($type == 'customization_quantity') {
            if (self::$smarty_generated_data) {
                if (isset(self::$smarty_generated_data['cart'])) {
                    $product = self::$smarty_generated_data['cart'];
                    $key = 'cart_quantity';
                } elseif (isset(self::$smarty_generated_data['order'])) {
                    $product = self::$smarty_generated_data['order'];
                    $key = 'cart_quantity';
                }
            }
            $behavior = (isset($product) ? static::qtyBehavior($product, $product[$key]) : false);
            $value = ($behavior ? static::resolveQty($value['quantity'], $value['quantity_fractional']) : (int) $value['quantity']);
        }
        return $value;
    }

    public static function smartyCompile($tag, $mode, $item, &$smarty)
    {
        if ($tag == 'foreach') {
            $ret = '';
            $item = (string) $item;
            if (($pos = strpos($item, '$_smarty_tpl->tpl_vars[')) !== false) {
                $item = Tools::substr(Tools::substr($item, $pos + 23), 0, -1);
            }
            if (strpos($item, '\'') === false && strpos($item, '"') === false) {
                $key = $item;
                $item = "'{$item}'";
            } else {
                $key = trim((string) $item, '\'"');
            }
            if ($key == 'product' || $key == 'customization' || $key == 'newproduct' || $key == 'orderProduct' || $key == 'order_detail') {
                // NOTE: when Smarty uses "include" the $smarty->_current_file is set incorrectly after returning from include.
                // It means, that $smarty->_current_file should be used with care.
                $current_file = static::normalizeFilename($smarty->_current_file);
                // $filename = basename($smarty->_current_file);
                if ($key == 'customization') {
                    $generated = 'customization';
                } elseif ($key == 'orderProduct') {
                    if (stripos($smarty->_current_file, 'crossselling.tpl') !== false) {
                        $generated = 'crossselling';
                    } else {
                        return '';
                    }
                } else {
                    if ($mode == 'open') {
                        $ret .= "<?php \PP::smartyFilterProductCustomizedDatas(\$_smarty_tpl->tpl_vars[{$item}]->value,\$_smarty_tpl);?>";
                    }
                    if (stripos($current_file, 'cart') !== false) {
                        $generated = 'cart';
                    } elseif (stripos($current_file, 'order') !== false || $key == 'order_detail') {
                        if (stripos($smarty->_current_file, 'order-slip') !== false) {
                            $generated = 'order-slip';
                        } else {
                            $generated = 'order';
                        }
                    } else {
                        $generated = 'product';
                    }
                }
                switch ($mode) {
                    case 'open':
                        $bo = defined('_PS_ADMIN_DIR_') && stripos($current_file, static::normalizeFilename(_PS_ADMIN_DIR_)) === 0 ? ',\'bo\'=>true' : '';
                        $pdf = (stripos($current_file, '/pdf/') !== false) || (stripos($current_file, '/gwadvancedinvoice/') !== false);
                        $pdf_param1 = ($pdf ? 'true' : 'false');
                        $pdf_param2 = ($pdf ? ',\'pdf\'=>true' : '');
                        if ($generated == 'order' || $generated == 'order-slip') {
                            $ret .= "<?php \PP::smartyOrderProductPresentPdf(\$_smarty_tpl->tpl_vars[{$item}]->value,$pdf_param1);?>";
                        }
                        $ret .= "<?php \PP::smartyPPAssign(array('$generated'=>\$_smarty_tpl->tpl_vars[{$item}]->value,'generated'=>'$generated'$bo$pdf_param2));?>";
                        break;
                    case 'close':
                        $ret .= "<?php \PP::smartyPPAssign(array('generated'=>'$generated'));?>";
                        break;
                    default:
                        break;
                }
            }
            return $ret;
        }
        return '';
    }

    public static function smartyPPAssign($params = null, &$smarty = null)
    {
        if ($params === null) {
            $params = array();
        }
        $generated = (array_key_exists('generated', $params) ? $params['generated'] : false);
        self::$smarty_generated = ($generated !== false && array_key_exists($generated, $params) ? $params[$generated] : null);
        if (self::$smarty_generated !== null) {
            if (self::$smarty_generated_data === null) {
                self::$smarty_generated_data = array();
            }
            self::$smarty_generated_data[$generated] = self::$smarty_generated;
        }
        self::$smarty_cart = (array_key_exists('cart', $params) ? $params['cart'] : ($generated == 'cart' ? self::$smarty_generated : null));
        self::$smarty_order = (array_key_exists('order', $params) ? $params['order'] : ($generated == 'order' ? self::$smarty_generated : null));
        self::$smarty_product = (array_key_exists('product', $params) ? $params['product'] : (($generated == 'product' || $generated == 'crossselling') ? self::$smarty_generated : null));
        self::$smarty_currency = static::smartyGetCurrency($params, $smarty);
        self::$smarty_bo = (array_key_exists('bo', $params) ? $params['bo'] : defined('_PS_ADMIN_DIR_'));
        self::$smarty_pdf = array_key_exists('pdf', $params);
        self::$smarty_multiline_prefix = (array_key_exists('multiline_prefix', $params) ? $params['multiline_prefix'] : (self::$smarty_pdf ? '<div style="line-height:4px;">' : ''));
        self::$smarty_multiline_suffix = (array_key_exists('multiline_suffix', $params) ? $params['multiline_suffix'] : (self::$smarty_pdf ? '</div>' : ''));
        if ($generated === false || $generated == 'product' || $generated == 'crossselling') {
            if (self::$smarty_generated) {
                switch ($generated) {
                    case 'product':
                        if (!is_array(self::$smarty_product)
                            || !array_key_exists('id_pp_template', self::$smarty_product)
                            || array_key_exists('cart_quantity_fractional', self::$smarty_product)
                            || array_key_exists('product_quantity_fractional', self::$smarty_product)) {
                            if (self::$smarty_product instanceof ProductBase && self::$smarty_product->id) {
                                self::$smarty_product = array(
                                    'id_product' => self::$smarty_product->id,
                                    'id_pp_template' => self::$smarty_product->id_pp_template
                                );
                            } else {
                                self::$smarty_product = null;
                            }
                        }
                        break;
                    case 'crossselling':
                        if (!is_array(self::$smarty_product)) {
                            self::$smarty_product = null;
                        }
                        break;
                }
            }
        }
    }

    private static function resolveProductType($product)
    {
        if (!empty($product['id_order'])) {
            return 'order';
        }
        if (!empty($product['id_cart_product'])) {
            return 'cart';
        }
        return 'product';
    }

    public static function smartyFormatQty($params, &$smarty = null)
    {
        if (array_key_exists('qty', $params)) {
            $quantity = (float) $params['qty'];
        } elseif (array_key_exists('quantity', $params)) {
            $quantity = (float) $params['quantity'];
        } else {
            $quantity = 0;
        }
        return static::formatQty($quantity, $params['properties'] ?? null);
    }

    public static function convertQty($params, &$smarty = null)
    {
        list($data, $quantity, $quantity_fractional, $qty_behavior, $approximate_quantity, $bo) = static::resolveSmartyParams($params);
        $qty = 0;
        if ($quantity_fractional > 0) {
            $edit = (array_key_exists('m', $params) && $params['m'] === 'edit');
            if ($edit && $approximate_quantity) {
                $qty = static::normalizeDbDecimal(static::resolveQty($quantity, $quantity_fractional));
            } elseif ($qty_behavior) {
                $qty = $quantity_fractional;
            }
        }
        return ($qty > 0 ? round((float) $qty, 8) : (int) $quantity);
    }

    public static function twigDisplayQty($product, $quantity = null, $m = null)
    {
        return static::raw(static::smartyDisplayQty([self::resolveProductType($product) => $product, 'quantity' => $quantity, 'm' => $m]));
    }

    public static function smartyDisplayQty($params, &$smarty = null)
    {
        list($data, $quantity, $quantity_fractional, $qty_behavior, $approximate_quantity, $bo) = static::resolveSmartyParams($params);
        $br = (array_key_exists('br', $params) ? (bool) ($params['br'] == 'false' ? false : $params['br']) : true);
        if ($data != null) {
            $cart_or_order = ($data['_data_type'] = 'cart' || $data['_data_type'] == 'order' || self::$smarty_cart || self::$smarty_order);
            $m = array_key_exists('m', $params) ? $params['m'] : null;
            $wrap = array_key_exists('wrap', $params);
            if ($bo) {
                $text_class = 'pp_bo_qty_text';
                $text = $data['pp_bo_qty_text'];
            } else {
                $text_class = 'pp_qty_text';
                $text = $data['pp_product_qty_text'];
            }
            if ($m === 'inline') {
                $params['qty'] = $quantity;
                $display = static::smartyFormatQty($params);
                $pp_display_mode_16 = ($cart_or_order && ((int) $data['pp_display_mode'] & 16) == 16); // hide extra details for quantity in orders and invoices
                if ($pp_display_mode_16) {
                    $text = '';
                }
                if (Tools::strlen($text) > 0) {
                    $display .= ' <span class="' . $text_class . '">' . static::safeOutput($text) . '</span>';
                }
                return $display;
            } else {
                // pp_display_mode "256" => display number of items instead of quantity
                $display_items = (((int) $data['pp_display_mode'] & 256) == 256) || static::isPacksCalculator($data);
                if ($quantity_fractional > 0 && !$display_items) {
                    $close = true;
                    $pp_display_mode_16 = ($cart_or_order && ((int) $data['pp_display_mode'] & 16) == 16);
                    if ($pp_display_mode_16) {
                        $text = '';
                    }
                    if ($m === 'unit') {
                        if ($pp_display_mode_16) {
                            return '';
                        }
                        if ($qty_behavior) {
                            $display = (Tools::strlen($text) > 0 ? ' <span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">' . static::safeOutput($text) . '</span>' : '');
                            $close = false;
                        } else {
                            $display = ($wrap ? '<span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">x ' : ($br ? '<br>' : '') . '<span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">x ');
                        }
                    } elseif ($m === 'edit') {
                        if ($approximate_quantity) {
                            unset($params['m']);
                            $display = static::smartyDisplayQty($params);
                        } else {
                            $display = '';
                        }
                        if ($qty_behavior || $approximate_quantity) {
                            $display = (Tools::strlen($text) > 0 ? ' <span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">' . static::safeOutput($text) . '</span>' : '');
                            $close = false;
                        } else {
                            $pp_display_mode_16 = ($cart_or_order && ((int) $data['pp_display_mode'] & 16) == 16); // hide extra details for quantity in orders and invoices
                            if ($pp_display_mode_16) {
                                $display = '';
                                $close = false;
                            } else {
                                $display = ($wrap ? '<span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">x ' : ($br ? '<br>' : '') . '<span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">x ');
                            }
                        }
                    } elseif ($m === 'fractional') {
                        $display = '<span class="pp_qty_wrapper" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">x ';
                    } else {
                        if ($m === 'overall') {
                            $params['qty'] = $quantity;
                            $display = static::smartyFormatQty($params);
                            if ($pp_display_mode_16 || Tools::strlen($text) == 0 || !$qty_behavior) {
                                return $display;
                            }
                            return $display . ' ' . static::safeOutput($text);
                        } else {
                            if ($data['pp_ext'] != 1 && ($quantity == 1 || $qty_behavior)) {
                                if ($pp_display_mode_16) {
                                    $params['qty'] = $quantity_fractional;
                                    return static::smartyFormatQty($params);
                                }
                                $display = '<span class="' . $text_class . '">';
                            } else {
                                if ($pp_display_mode_16) {
                                    return $quantity;
                                }
                                $display = $quantity . ($wrap ? '<span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">x' : ($br ? '<br>' : '') . '<span class="' . $text_class . '" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '"> x ');
                            }
                        }
                    }
                    if ($close) {
                        $params['qty'] = $quantity_fractional;
                        if ($m === 'total') {
                            $params['properties'] = $data;
                        }
                        $display .= '<span class="pp_quantity_fractional">';
                        $display .= static::smartyFormatQty($params) . '</span>';
                        if (Tools::strlen($text) > 0) {
                            if ($m === 'fractional') {
                                $display .= ' <span class="' . $text_class . '">' . static::safeOutput($text) . '</span>';
                            } else {
                                $display .= ' ' . static::safeOutput($text);
                            }
                        }
                        $display .= '</span>';
                    }
                } else {
                    $display = in_array($m, ['unit', 'edit', 'fractional']) ? '' : $quantity;
                }
                return static::smartyAmendDisplay($params, $display);
            }
        }
        return ($quantity ? $quantity : '');
    }

    public static function smartyConvertPrice($params, &$smarty = null)
    {
        $currency = static::smartyGetCurrency($params, $smarty);
        $price = (array_key_exists('price', $params) ? $params['price'] : null);
        $product = (array_key_exists('product', $params) ? $params['product'] : self::$smarty_product);
        $m = array_key_exists('m', $params) ? $params['m'] : null;
        $usetax = (array_key_exists('usetax', $params) ? $params['usetax'] : null);
        if ($m == 'smart_product_price') {
            $price = static::smartProductPrice($product, $price, $usetax);
            $product = null;
        }
        if ($product != null) {
            $product_properties = static::getProductProperties($product);
            list($key, $price) = static::calcProductDisplayPrice($product, $product_properties, $price, $m);
            $display = (is_numeric($price) ? static::displayPrice(static::pricerounding($price, 'product', 0, 0, $usetax), $currency) : ($price === null ? 0 : $price));
            if ($key) {
                $display .= ' <span class="' . $key . '">' . static::safeOutput($product_properties[$key]) . '</span>';
            }
        } else {
            $display = static::displayPrice(($price === null ? 0 : static::pricerounding($price, 'product', 0, 0, $usetax)), $currency);
        }
        return $display;
    }

    public static function twigDisplayPrice($product, $price = null, $m = null)
    {
        return static::raw(static::smartyDisplayPrice([self::resolveProductType($product) => $product, 'price' => $price, 'm' => $m]));
    }

    public static function smartyDisplayPrice($params, &$smarty = null)
    {
        $currency = static::smartyGetCurrency($params, $smarty);
        $price = array_key_exists('price', $params) ? $params['price'] : $params['p'];
        $m = array_key_exists('m', $params) ? $params['m'] : null;
        $usetax = (array_key_exists('usetax', $params) ? $params['usetax'] : null);

        list($data, $quantity, $quantity_fractional, $qty_behavior, $approximate_quantity, $bo) = static::resolveSmartyParams($params);
        $br = (array_key_exists('br', $params) ? (bool) ($params['br'] == 'false' ? false : $params['br']) : true);
        if ($data != null) {
            $pp_display_mode = (int) $data['pp_display_mode'];
            $pack_calculator = (static::isPacksCalculator($data) && $data['unit_price_ratio'] > 0);
            if ($m === 'total') {
                $display = static::displayPrice(static::pricerounding($price, 'total', static::resolveProductId($data), $quantity, $quantity_fractional, $usetax), $currency);
                if (($pp_display_mode & 32) == 32) { // hide extra details for total price in orders and invoices
                    return $display;
                }
                $text_key = ($pack_calculator ? 'pp_ext_text' : ($bo ? 'pp_bo_qty_text' : 'pp_product_qty_text'));
                $text = $data[$text_key] ?? '';
                if (Tools::strlen($text) > 0) {
                    if ($quantity > 1 || $pack_calculator) {
                        if ($pack_calculator) {
                            $qty = (int) $quantity * $data['unit_price_ratio'];
                        } else {
                            $qty = ($quantity_fractional > 0 ? static::resolveQty($quantity, $quantity_fractional) : $quantity);
                        }
                        $display .= (($br ? '<br>' : '') . ' <span class="pp_price_text" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">');
                        $display .= static::formatQty($qty, $data);
                        $display .= ' ' . static::safeOutput($text);
                        $display .= '</span>';
                    }
                }
            } else {
                if ($m === 'smart_product_price') { // smart_product_price refers to unit price in orders and invoices
                    $usetax = (array_key_exists('usetax', $params) ? $params['usetax'] : null);
                    $price = static::smartProductPrice($data, $price, $usetax);
                }
                $display = static::displayPrice(static::pricerounding($price, 'product', static::resolveProductId($data), $quantity, $quantity_fractional, $usetax), $currency);
                if ((($pp_display_mode & 8) == 8)) { // hide extra details for unit price in orders and invoices
                    return $display;
                }
                $text = $data['pp_price_text'];
                if (!empty($text)) {
                    $display .= (($br ? '<br>' : '') . ' <span class="pp_price_text" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">');
                    $display .= static::safeOutput($text) . '</span>';
                }
                if ($m === 'smart_product_price') {
                    //  64: display legacy product price disabled
                    // 128: display unit price in orders and invoices
                    // note: we are using smartyDisplayPrice only in the back office (mails, invoices)
                    if ($data['unit_price_ratio'] > 0 && (($pp_display_mode & 128) != 0) && (($data['pp_display_mode'] & 64) == 0)) {
                        $unit_price = static::displayPrice(static::pricerounding($price / $data['unit_price_ratio'], 'product', $quantity, $quantity_fractional, $usetax), $currency); // recalculate unit_price
                        $display .= (($br ? '<br>' : ' ') . $unit_price);
                        $text = $data['unity'];
                        if (!empty($text)) {
                            $display .= (($br ? '<br>' : '') . ' <span class="pp_price_text" style="white-space: nowrap;' . (self::$smarty_pdf ? ' font-size:80%;' : '') . '">');
                            $display .= static::safeOutput($text) . '</span>';
                        }
                    }
                }
            }
            $display = static::smartyAmendDisplay($params, $display);
        } else {
            $display = static::displayPrice(static::pricerounding($price, $m === 'total' ? 'total' : 'product', null, $quantity, $quantity_fractional, $usetax), $currency);
        }
        return $display;
    }

    private static function resolveSmartyParams($params)
    {
        $data = $quantity = $quantity_fractional = null;
        $bo = (array_key_exists('bo', $params) ? $params['bo'] : defined('_PS_ADMIN_DIR_'));
        $order = (array_key_exists('order', $params) ? $params['order'] : null);
        if ($order === null) {
            $cart = (array_key_exists('cart', $params) ? $params['cart'] : null);
            if ($cart === null) {
                $product = (array_key_exists('product', $params) ? $params['product'] : null);
                if ($product === null) {
                    $order = self::$smarty_order;
                    $cart = self::$smarty_cart;
                    $product = self::$smarty_product;
                }
            }
        }
        if ($order != null) {
            $data = $order;
            $data['_data_type'] = 'order';
            $quantity = (int) $data['product_quantity'];
            $quantity_fractional = (float) $data['product_quantity_fractional'];
        } else {
            if ($cart != null) {
                $data = $cart;
                $data['_data_type'] = 'cart';
                $quantity = (int) $data['cart_quantity'];
                $quantity_fractional = (float) $data['cart_quantity_fractional'];
            } else {
                if ($product != null) {
                    $data = $product;
                    $data['_data_type'] = 'product';
                    if (isset($data['quantity'])) {
                        $quantity = (int) $data['quantity'];
                    }
                    if (isset($data['quantity_fractional'])) {
                        $quantity_fractional = (float) $data['quantity_fractional'];
                    }
                }
            }
        }
        if ($data != null) {
            $properties = static::getProductProperties($data);
            $qty_behavior = static::qtyBehavior($data, $quantity, $properties);
            $approximate_quantity = static::qtyModeApproximateQuantity($properties);
            static::amendProductCompatibility($data);
            if (!isset($data['pp_ext'])) {
                $data = array_merge($data, $properties);
            }
        } else {
            $qty_behavior = false;
            $approximate_quantity = false;
        }
        if (array_key_exists('quantity', $params) && $params['quantity'] !== null) {
            // cast to float: can be overall quantity with fractional part or come as a string from the database
            $quantity = (float) $params['quantity'];
        }
        if (array_key_exists('quantity_fractional', $params) && $params['quantity_fractional'] !== null) {
            $quantity_fractional = (float) $params['quantity_fractional'];
        }
        return array($data, $quantity, $quantity_fractional, $qty_behavior, $approximate_quantity, $bo);
    }

    public static function smartyGetCurrency($params, &$smarty = null)
    {
        if (array_key_exists('currency', $params)) {
            $currency = $params['currency'];
            if (Validate::isLoadedObject($currency)) {
                self::$smarty_currency = $currency;
            } elseif (is_numeric($params['currency'])) {
                $currency = Currency::getCurrencyInstance((int) $params['currency']);
                if (Validate::isLoadedObject($currency)) {
                    self::$smarty_currency = $currency;
                }
            }
        }
        if (self::$smarty_currency === null && $smarty !== null && isset($smarty->tpl_vars['currency'])) {
            $currency = $smarty->tpl_vars['currency']->value;
            if (Validate::isLoadedObject($currency)) {
                self::$smarty_currency = $currency;
            }
        }
        return self::$smarty_currency;
    }

    public static function smartyFilterProductCustomizedDatas($item, &$smarty)
    {
        $key = (isset($smarty->tpl_vars['customizedDatas']) ? 'customizedDatas' : (isset($smarty->tpl_vars['customized_datas']) ? 'customized_datas' : null));
        if ($key) {
            $allKey = 'all_' . $key;
            if (!isset($smarty->tpl_vars[$allKey])) {
                $smarty->tpl_vars[$allKey] = clone $smarty->tpl_vars[$key];
            }
            if (isset($smarty->tpl_vars[$allKey])) {
                $smarty->tpl_vars[$key] = new Smarty_variable(static::filterProductCustomizedDatas($smarty->tpl_vars[$item]->value, $smarty->tpl_vars[$allKey]->value), $smarty->tpl_vars[$key]->nocache, $smarty->tpl_vars[$key]->scope);
            }
        }
    }

    private static function smartyAmendDisplay($params, $display)
    {
        if (is_string($display) && Tools::strlen($display) > 0) {
            $wrap = (array_key_exists('wrap', $params) ? $params['wrap'] : null);
            if ($wrap == 'input-group-addon') {
                $display = static::div($display, 'input-group-addon');
            }
            if (strpos($display, '<br') !== false) {
                $prefix  = (array_key_exists('prefix', $params) ? $params['prefix'] : null);
                $suffix  = (array_key_exists('suffix', $params) ? $params['suffix'] : null);
                if ($prefix !== null || $suffix !== null) {
                    $s = (self::$smarty_multiline_prefix === false ? '' : self::$smarty_multiline_prefix);
                    if ($prefix !== null) {
                        $s .= $prefix;
                    }
                    $s .= $display;
                    if ($suffix !== null) {
                        $s .= $suffix;
                    }
                    $display = $s . (self::$smarty_multiline_suffix === false ? '' : self::$smarty_multiline_suffix);
                }
            }
        }
        return $display;
    }

    public static function index($dir)
    {
        // URL Encode online https://www.urlencoder.io
        // example: https://.../modules/pproperties?cfg=amendProductDisplay=%23mainProduct
        // example: https://.../modules/pproperties?cfg=touchspin_verticalupclass=fa fa-angle-up
        // example: https://.../modules/pproperties?cfg=touchspin_verticaldownclass=fa fa-angle-down
        if (($value = Tools::getValue('debug')) !== false) {
            Configuration::updateGlobalValue('PP_DEBUG', (string) $value);
        } elseif (($value = Tools::getValue('cfg')) !== false) {
            $cfg = (string) Configuration::getGlobalValue('PP_CFG');
            $cfg = !empty($cfg) ? json_decode($cfg, true) : array();
            list($key, $value) = explode('=', (string) $value, 2);
            if ($value == '') {
                unset($cfg[$key]);
            } else {
                $cfg[$key] = $value;
            }
            if (!empty($cfg)) {
                Configuration::updateGlobalValue('PP_CFG', json_encode($cfg));
            } else {
                Configuration::deleteByName('PP_CFG');
            }
        }
        PSM::support($dir);
    }
}
