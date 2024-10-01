<?php
/**
 * Product Properties Extension
 *
 * Extends product properties and add support for products with fractional
 * units of measurements (for example: weight, length, volume).
 *
 * NOTICE OF LICENSE
 *
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

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductDataProvider;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration
class PProperties extends Module implements WidgetInterface
{
    const USER_START_ID            = 100;
    const PROPERTY_TYPE_GENERAL    = 1;
    const PROPERTY_TYPE_BLOCK_TEXT = 2;
    const PROPERTY_TYPE_EXT        = 3;
    const DIMENSIONS               = 3;
    const PHPVERSION               = 7.1;

    public $integrated = false;
    public $integration_test_result = array();
    public $multidimensional_plugin = false;

    private $default_language_id;
    private $active_languages;

    public function __construct()
    {
        $this->name = 'pproperties';
        $this->tab = 'administration';
        $this->version = '3.3.15';
        $this->author = 'psandmore';
        $this->module_key = 'a78315086f12ede793183c113b223617';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.7.4', 'max' => '1.7.8');
        $this->pp_versions_compliancy = array('min' => '1.7.4', 'max' => '1.7.8.99');
        $this->bootstrap = true;

        parent::__construct($this->name);

        $this->displayName = $this->l('Product Properties Extension');
        $this->description = $this->l('Extends product properties and add support for products with fractional units of measurements (for example: weight, length, volume)');
        $this->confirmUninstall = $this->l('When you uninstall this module the user data is not lost and remains in the database. It will be available next time you install the module.');
        $this->adminTab = 'AdminPproperties';
        $this->adminTabName = 'Product Properties Extension';

        $this->psmIntegrate();
        if (Module::isInstalled($this->name)) {
            $this->integrated = (Configuration::getGlobalValue('PP_INTEGRATION') == $this->integrationKey());
            $this->multidimensional_plugin = PP::getMultidimensionalPlugin();
            if (defined('_PS_ADMIN_DIR_')) {
                $this->description .= '<br><small>' . $this->confirmUninstall . '</small>';
            }
        } else {
            static $done;
            if (!$done) {
                $done = true;
                // backward compatibility
                Tools::deleteFile(_PS_ROOT_DIR_ . '/classes/psm.php');
                Tools::deleteFile(_PS_ROOT_DIR_ . '/classes/PSM.php');
                Tools::deleteFile(_PS_ROOT_DIR_ . '/classes/PP.php');
            }
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }
        @set_time_limit(300);
        if (version_compare(phpversion(), self::PHPVERSION, '<')) {
            $this->_errors[] = sprintf($this->l('Requires PHP version %s or above. Currently running PHP %s version.'), self::PHPVERSION, phpversion());
            return false;
        }
        if (!parent::install()) {
            return false;
        }

        $hooks = [
            'displayHeader',
            'displayFooter',
            'displayBackOfficeHeader',
            'displayOverrideTemplate',
            'displayProductAdditionalInfo',
            'displayProductPriceBlock',
            'displayProductPproperties',
            'displayAdminBulkManageTemplates',
            'displayAdminProductPproperties',
            'displayAdminProductsExtra',
            'filterProductContent',
            'actionAdminControllerSetMedia',
            'actionAdminProductsListingFieldsModifier',
            'actionAdminProductsListingResultsModifier',
            'actionAdminStockCoverListingFieldsModifier',
            'actionAdminStockInstantStateListingFieldsModifier',
            'actionAdminStockManagementListingFieldsModifier',
            'actionAdminStockMvtListingFieldsModifier',
            'actionCartSave',
            'actionFrontControllerSetMedia',
            'actionModuleInstallAfter',
            'actionModuleUpgradeAfter',
            'actionModuleUninstallBefore',
            'actionModuleUninstallAfter',
            'actionObjectOrderDetailAddAfter',
            'adminPproperties',
            'plugins',
            'translations',
        ];
        if (!$this->registerHook($hooks)) {
            return false;
        }

        if (!Configuration::hasKey('PP_SHOW_IN_MENU', null, 0, 0)) {
            Configuration::updateGlobalValue('PP_SHOW_IN_MENU', 1);
        }
        if ((int) Configuration::get('PP_MEASUREMENT_SYSTEM') == 0) {
            $w = Configuration::get('PS_WEIGHT_UNIT');
            Configuration::updateValue('PP_MEASUREMENT_SYSTEM', (Tools::strtolower($w) === 'lb') ? 2 : 1);
        }
        if (!Configuration::hasKey('PP_POWEREDBY')) {
            Configuration::updateValue('PP_POWEREDBY', 1);
        }
        if (!Configuration::hasKey('PP_TEMPLATE_NAME_IN_CATALOG')) {
            Configuration::updateValue('PP_TEMPLATE_NAME_IN_CATALOG', 1);
        }
        Configuration::updateGlobalValue('PP_INSTALL_TIME', time());

        if (!Configuration::get('PS_SMARTY_FORCE_COMPILE')) {
            Configuration::updateValue('PS_SMARTY_FORCE_COMPILE', _PS_SMARTY_CHECK_COMPILE_);
        }

        $setup = $this->setupInstance();
        $setup->install();
        PSM::moduleVersion($this, true);
        $setup->installAdminTab($this->adminTab, Configuration::getGlobalValue('PP_SHOW_IN_MENU'));
        PSM::clearCache();

        // backward compatability
        if (($amendProductDisplay = (string) Configuration::getGlobalValue('PP_JS_AMENDPRODUCTDISPLAY')) || ($loader = (string) Configuration::getGlobalValue('PP_JS_LOADER'))) {
            $cfg = array();
            if ($amendProductDisplay) {
                $cfg['amendProductDisplay'] = $amendProductDisplay;
                Configuration::deleteByName('PP_JS_AMENDPRODUCTDISPLAY');
            }
            if ($loader) {
                $cfg['loader'] = $loader;
                Configuration::deleteByName('PP_JS_LOADER');
            }
            if (!empty($cfg)) {
                Configuration::updateGlobalValue('PP_CFG', json_encode($cfg));
            }
        }

        return true;
    }

    public function uninstall()
    {
        $this->uninstalling = true;
        @set_time_limit(300);
        $plugins = $this->plugins();
        foreach ($plugins as $name => $_) {
            if (Module::isInstalled($name)) {
                $this->_errors[] = sprintf($this->l('Please uninstall the "%s" module.'), Module::getModuleName($name));
            }
        }
        if ($this->_errors) {
            return false;
        }

        $setup = $this->setupInstance();
        $setup->uninstallAdminTab($this->adminTab);
        if (!parent::uninstall()) {
            return false;
        }

        $setup->uninstall();
        Configuration::deleteByName('PP_INTEGRATION');
        Configuration::deleteByName('PP_INTEGRATION_CHECK');
        Configuration::deleteByName('PP_INTEGRATION_EXTRA_MODULES');
        Configuration::deleteByName('PP_MODULE_MEDIA');
        Configuration::deleteByName('PP_INFO_CONTENT');
        Configuration::deleteByName('PP_INFO_CHECK_TIME');
        PSM::moduleVersion($this, false);

        Tools::generateIndex();
        return true;
    }

    public function disable($force_all = false)
    {
        if (!empty($this->uninstalling) || (!$force_all && Shop::isFeatureActive())) {
            return parent::disable($force_all);
        }
        $translations = $this->translations();
        $this->_errors[] = $translations['To disable the module please uninstall it.'];
        return false;
    }

    public function renderWidget($hook_name, array $params)
    {
        if ($this->integrated) {
            if (array_key_exists('product', $params) && isset($params['product']['pp_settings'])) {
                $widget_variables = $this->getWidgetVariables($hook_name, $params);
                $widget_variables['product'] = $params['product'];
                $this->smarty->assign($widget_variables);
                $result = $this->fetch('module:pproperties/views/templates/hook/front/product.tpl');
                return is_string($result) ? trim(preg_replace('/\s?<!--.*?-->\s?/s', '', $result)) : $result; // clean comments (required for mails)
            }
            if ($this->isSymfonyContext()) {
                if ($hook_name === 'displayAdminBulkManageTemplates' && isset($params['route']) && $params['route'] === 'admin_product_catalog') {
                    return $this->get('twig')->render('@Modules/pproperties/views/templates/hook/admin/bulk_manage_templates.html.twig', array(
                        'templates' => PP::getAdminProductsTemplates(),
                        'adminppropertiesurl' => $this->context->link->getAdminLink($this->adminTab, true),
                    ));
                }
            }
        }
    }

    public function getWidgetVariables($hook_name, array $params)
    {
        return array(
            'hook_name' => $hook_name,
            'hook_type' => isset($params['type']) ? $params['type'] : '',
            'hook_origin' => isset($params['hook_origin']) ? $params['hook_origin'] : '',
        );
    }

    public function hookDisplayAdminProductPproperties($params)
    {
        if ($this->integrated) {
            if (array_key_exists('product', $params) && isset($params['product']['pp_settings'])) {
                $this->smarty->assign(array(
                    'hook_name' => 'displayAdminProductPproperties',
                    'hook_type' => isset($params['type']) ? $params['type'] : '',
                    'hook_origin' => isset($params['hook_origin']) ? $params['hook_origin'] : '',
                    'product' => $params['product'],
                ));
                return $this->display('pproperties', 'admin/product.tpl');
            }
        }
    }

    public function hookDisplayHeader($params)
    {
        return $this->createHeaderJsScript();
    }

    public function hookDisplayFooter($params)
    {
        if (Tools::getValue('ajax')) {
            return;
        }
        return (int) Configuration::get('PP_POWEREDBY') ? PP::span('', 'powered_by_psandmore_placeholder') : '';
    }

    public function hookDisplayOverrideTemplate($param)
    {
        if ('module:pproperties/_partials/product' === $param['template_file']) {
            return $this->getTemplatePath('front/_partials/product.tpl');
        }
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $tab = Tools::getValue('tab');
        $controller = Tools::getValue('controller');
        if (Tools::strtolower($tab) === 'adminselfupgrade' || Tools::strtolower($controller) === 'adminselfupgrade') {
            $this->smarty->assign(
                [
                    'helper_key' => 'displayBackOfficeHeader_upgrade',
                    'message' => sprintf($this->l('%s: Please uninstall this module before upgrading and obtain, if needed, version compatible with your new PrestaShop version.'), $this->displayName),
                    'compatibilityText' => $this->compatibilityText(),
                ]
            );
            return $this->fetch('module:pproperties/views/templates/admin/helper.tpl');
        } else {
            if (PSM::isBackOfficeSupportedController($this->context->controller)) {
                if (Tools::getValue('configure') != 'pproperties') {
                    $last_integration_check = Configuration::getGlobalValue('PP_INTEGRATION_CHECK');
                    if (time() > ($last_integration_check + ($this->integrated ? 3600 : 3))) {
                        $this->checkIntegration($this->setupInstance());
                    }
                    if (!$this->integrated) {
                        $this->smarty->assign(
                            [
                                'helper_key' => 'displayBackOfficeHeader_integration_warning',
                                'message' => sprintf(
                                    $this->l('%s: Integration warning. Your site will not work properly until you resolve the integration problems. %s'),
                                    $this->displayName,
                                    PP::wrapA(
                                        $this->l('(click here)'),
                                        false,
                                        [
                                            'href' => 'index.php?controller=adminmodules&configure=pproperties&token=' . Tools::getAdminTokenLite('AdminModules') . '&tab_module=administration&module_name=pproperties',
                                            'style' => 'text-decoration:underline;color:inherit'
                                        ]
                                    )
                                ),
                            ]
                        );
                        return $this->fetch('module:pproperties/views/templates/admin/helper.tpl');
                    }
                }
                $html = $this->createHeaderJsScript(true);
                if ($this->integrated && $this->context->controller->controller_name === 'AdminAttributeGenerator') {
                    $template_id = PP::getProductTemplateId(Tools::getValue('id_product'));
                    if ($template_id > 0) {
                        $properties = PP::getProductPropertiesByTemplateId($template_id);
                        if (!empty($properties['pp_bo_qty_text'])) {
                            $this->smarty->assign(
                                [
                                    'helper_key' => 'displayBackOfficeHeader_AdminAttributeGenerator',
                                    'pp_bo_qty_text' => $properties['pp_bo_qty_text'],
                                ]
                            );
                            $html .= $this->fetch('module:pproperties/views/templates/admin/helper.tpl');
                        }
                    }
                }
                return $html;
            }
        }
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int) $params['id_product'];
        $productAdapter = new ProductDataProvider();
        $product = $productAdapter->getProduct($id_product);
        $id_pp_template = PP::getProductTemplateId($product);
        $this->context->smarty->assign(
            array(
                'integrated' => $this->integrated,
                'multidimensional' => (bool) $this->multidimensional_plugin,
                'id_product' => $id_product,
                'id_pp_template' => $id_pp_template,
                'product' => $product,
                '_path' => $this->getPathUri(),
                '_PS_ADMIN_IMG_' => _PS_ADMIN_IMG_,
                's_header' => $this->l('Assign or change product template'),
                's_product_template' => $this->l('Product template'),
                's_hint' => $this->l('Please save this product before making any other changes.'),
                's_advice' => $this->l('You can assign or remove template for several products in one operation using bulk actions in product\'s catalog.'),
                's_configure_templates' => $this->l('Configure templates'),
                's_edit_template' => $this->l('Edit this template'),
                's_user_guide' => $this->l('Read user guide'),
            )
        );
        if (!$this->integrated) {
            $this->context->smarty->assign('integration_warning', $this->l('Please resolve integration problems.'));
        }
        if (!(bool) $this->multidimensional_plugin) {
            $this->context->smarty->assign('multidimensional_warning', $this->translations()['multidimensional_plugin_not_installed']);
        }
        $translations = $this->getTranslations('AdminProducts');
        foreach ($translations as $key => $value) {
            $this->context->smarty->assign($key, $value);
        }
        $this->context->smarty->assign('hook_html', Hook::exec('adminPproperties', array('mode' => 'displayAdminProductsExtra', 'id_product' => $id_product, 'product' => $product, 'integrated' => $this->integrated), null, true));
        $this->content = $this->display('pproperties', 'admin/products_extra.tpl');
        return $this->content;
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        if (!Tools::getValue('ajax')) {
            $this->context->controller->registerStylesheet('modules-pproperties', 'modules/' . $this->name . '/views/css/pproperties.css', array('media' => 'all', 'priority' => 500));
            $this->context->controller->registerStylesheet('modules-pproperties-custom', 'modules/' . $this->name . '/css/custom.css', array('media' => 'all', 'priority' => 999));
            $this->context->controller->registerJavascript('modules-pproperties', 'modules/' . $this->name . '/views/js/pproperties.min.js', array('position' => 'bottom', 'priority' => 500));
            $this->context->controller->registerJavascript('modules-pproperties-custom', 'modules/' . $this->name . '/js/custom.js', array('position' => 'bottom', 'priority' => 999));
            $media = Configuration::getGlobalValue('PP_MODULE_MEDIA');
            if (is_string($media)) {
                $media = json_decode($media, true);
                if (count($media)) {
                    $i = 0;
                    foreach ($media as $name => $files) {
                        if (Module::isInstalled($name)) {
                            if (Module::isEnabled($name)) {
                                foreach ($files as $file) {
                                    $len = Tools::strlen($file);
                                    if (Tools::strrpos($file, '.css') === $len - 4) {
                                        $this->context->controller->registerStylesheet('modules-' . $name . (++$i), $file, array('media' => 'all', 'priority' => 900));
                                    } elseif (Tools::strrpos($file, '.js') == $len - 3) {
                                        $this->context->controller->registerJavascript('modules-' . $name . (++$i), $file, array('position' => 'bottom', 'priority' => 900));
                                    }
                                }
                            }
                        } else {
                            $this->unregisterModuleMedia($name);
                        }
                    }
                }
            }
        }
    }

    public function hookActionModuleInstallAfter($params)
    {
        $this->setupInstance()->moduleInstalled($params['object']);
        $this->unregisterModuleMedia($params['object']);
    }

    public function hookActionModuleUpgradeAfter($params)
    {
        $this->setupInstance()->moduleUpgraded($params['object']);
        $this->unregisterModuleMedia($params['object']);
    }

    public function hookActionModuleUninstallBefore($params)
    {
        $this->setupInstance()->moduleUninstall($params['object']);
    }

    public function hookActionModuleUninstallAfter($params)
    {
        $this->setupInstance()->moduleUninstalled($params['object']);
        $this->unregisterModuleMedia($params['object']);
    }

    public function hookActionObjectOrderDetailAddAfter($params)
    {
        $order_detail = $params['object'];
        $pp_data_type = $order_detail->pp_data_type;
        if ($pp_data_type) {
            $data = PP::resolvePPData($order_detail->pp_data);
            if ($pp_data_type === 'bulk' && !empty($data['bulk'])) {
                if ((int) $data['id_product'] != (int) $order_detail->product_id) {
                    // should not happen
                    throw new PrestaShopException('OrderDetail product_id does not match "bulk" id_product');
                }
                $values = array();
                foreach ($data['bulk'] as $id_product_attribute => $d) {
                    if ($id_product_attribute) {
                        $values[] = array(
                            'id_order'             => (int) $order_detail->id_order,
                            'id_order_detail'      => (int) $order_detail->id,
                            'id_shop'              => (int) $order_detail->id_shop,
                            'id_cart_product'      => (int) $order_detail->id_cart_product,
                            'id_product'           => (int) $order_detail->product_id,
                            'id_product_attribute' => (int) $id_product_attribute,
                            'quantity'             => (float) $d['quantity'],
                            'data_type'            => $pp_data_type,
                            'data'                 => pSQL($order_detail->pp_data),
                        );
                    } else {
                        throw new PrestaShopException('OrderDetail "bulk" without attributes not supported');
                    }
                }
                if ($values) {
                    Db::getInstance()->insert('pp_order_detail', $values, true);
                }
            }
        }
    }

    public function hookActionCartSave($params)
    {
        $cart = $params['cart'];
        if (!empty($cart->last_icp)) {
            // support modules that create records in the 'customization' table
            Db::getInstance()->update(
                'customization',
                array(
                    'id_cart_product' => (int) $cart->last_icp
                ),
                'id_cart_product = 0 AND id_cart = ' . (int) $cart->id
            );
        }
    }

    public function hookFilterProductContent($params)
    {
        if ($this->integrated) {
            $presentedProduct = &$params['object'];
            $smartprice = PP::getSmartpricePlugin();
            if ($presentedProduct['id_pp_template'] || ($smartprice && $smartprice->productUsesPluginFeatures($presentedProduct))) {
                $pp_settings = $presentedProduct['pp_settings'];
                if (!empty($presentedProduct['quantity_wanted'])) {
                    PP::productResolveInputInformation($presentedProduct);
                    $id_product = $presentedProduct['pp_input_information']['id_product'];
                    $id_product_attribute = $presentedProduct['pp_input_information']['id_product_attribute'];
                    $quantity = $presentedProduct['pp_input_information']['quantity'];
                    $quantity_fractional = $presentedProduct['pp_input_information']['quantity_fractional'];
                    $qty = $presentedProduct['pp_input_information']['qty'];
                    $include_taxes = $presentedProduct['include_taxes'];
                    list($total_tax_excl, $total_wt) = PP::calcProductPricesStatic($presentedProduct, $id_product, $id_product_attribute, $quantity, $quantity_fractional, $qty);
                    $priceFormatter = new PriceFormatter();
                    $total_amount_tax_excl = $pp_settings['total_amount_tax_excl'] = $total_tax_excl;
                    $total_amount_wt = $pp_settings['total_amount_wt'] = $total_wt;
                    $pp_settings['total_tax_excl'] = $priceFormatter->format($total_amount_tax_excl);
                    $pp_settings['total_wt'] = $priceFormatter->format($total_amount_wt);
                    if ($include_taxes) {
                        $pp_settings['total_amount'] = $total_amount_wt;
                        $pp_settings['total'] = $pp_settings['total_wt'];
                    } else {
                        $pp_settings['total_amount'] = $total_amount_tax_excl;
                        $pp_settings['total'] = $pp_settings['total_tax_excl'];
                    }
                    $translations = $this->getTranslations();
                    $pp_settings['total_amount_to_display'] = sprintf($translations['s_total_amount_to_display'], $pp_settings['total']);
                    $pp_settings['total_amount_to_display_tax_excl'] = sprintf($translations['s_total_amount_to_display_tax_excl'], $pp_settings['total_tax_excl']);
                    $pp_settings['total_amount_to_display_wt'] = sprintf($translations['s_total_amount_to_display_tax_incl'], $pp_settings['total_wt']);
                    if ($presentedProduct['pp_price_display_mode'] == 1) {
                        $presentedProduct['price_to_display'] = $pp_settings['total'];
                    }
                }
                $presentedProduct['pp_settings'] = $pp_settings;
            }
        }
        return $params;
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        if (PSM::isBackOfficeSupportedController($this->context->controller)) {
            if (!PSM::adminControllerUsesNewTheme($params)) {
                $this->context->controller->addJquery();
            }
            $css_files = array('views/css/pproperties_admin.css');
            $js_files = array();
            $add_extra = false;
            if (in_array($this->context->controller->controller_name, array('AdminProducts', 'AdminOrders', 'AdminCarts', 'AdminStockManagement', 'AdminStockMvt', 'AdminStockInstantState', 'AdminStockcOver'))) {
                $add_extra = true;
            } elseif ($this->context->controller->controller_name === 'AdminModules' && Tools::getValue('configure') === 'pproperties') {
                $this->context->controller->addJqueryUI('ui.tabs', 'base');
                $add_extra = true;
            }
            if (!$add_extra && $this->multidimensional_plugin && $this->multidimensional_plugin->hookActionAdminControllerSetMediaIsSupportedController($this->context->controller->controller_name)) {
                $add_extra = true;
            }
            if ($add_extra) {
                $js_files[] = 'views/js/pproperties_admin.min.js';
            }
            $uri = $this->getPathUri();
            foreach ($css_files as $file) {
                $this->context->controller->addCSS($uri . $file);
            }
            foreach ($js_files as $file) {
                $this->context->controller->addJS($uri . $file);
            }
        }
    }

    public function hookActionAdminProductsListingFieldsModifier($params)
    {
        if (!$this->integrated) {
            return;
        }
        if (isset($params['sql_select'])) {
            $params['sql_select']['id_pp_template'] = array('table' => 'p', 'field' => 'id_pp_template');
            $params['sql_select']['sav_quantity_remainder'] = array('table' => 'sav', 'field' => 'quantity_remainder');
        }
        if (isset($params['sql_where']) && is_array($params['sql_where'])) {
            foreach ($params['sql_where'] as &$value) {
                if (strpos($value, 'sav.`quantity`') !== false) {
                    $value = str_replace('sav.`quantity`', '(sav.`quantity` + sav.`quantity_remainder`)', $value);
                }
            }
        }
    }

    public function hookActionAdminProductsListingResultsModifier($params)
    {
        if (!$this->integrated) {
            return;
        }
        static $pp_template_name_in_catalog = null;
        static $ps_stock_management = null;
        if ($pp_template_name_in_catalog === null) {
            $pp_template_name_in_catalog = (bool) Configuration::get('PP_TEMPLATE_NAME_IN_CATALOG');
            $ps_stock_management = Configuration::get('PS_STOCK_MANAGEMENT');
        }
        if (isset($params['products']) && is_array($params['products'])) {
            foreach ($params['products'] as &$product) {
                if (!empty($product['id_pp_template'])) {
                    if ($pp_template_name_in_catalog) {
                        $template_name = PP::getTemplateName($product['id_pp_template'], true);
                        if ($template_name != '') {
                            static $template_title = null;
                            static $token = null;
                            if ($template_title === null) {
                                $template_title = PP::safeOutput($this->l('This product uses Product Properties Extension template'));
                                $token = Tools::getAdminTokenLite('AdminModules');
                            }
                            $href = 'index.php?controller=adminmodules&configure=pproperties&token=' . $token . '&tab_module=administration&module_name=pproperties&clickEditTemplate&mode=edit&pp=1&id=' . $product['id_pp_template'];
                            $icon = PP::wrapI('reorder', 'material-icons icon-template');
                            $product['pp_template'] = '<br>' . PP::wrapA($icon . PP::span($template_name), 'pp_list_template', ['title' => $template_title, 'href' => $href, 'target' => '_blank']);
                        }
                    }
                    if ($ps_stock_management) {
                        if (isset($product['sav_quantity_remainder'])) {
                            $product['sav_quantity'] = $product['sav_quantity'] + $product['sav_quantity_remainder'];
                        }
                        $properties = PP::getProductPropertiesByTemplateId($product['id_pp_template']);
                        $product['sav_quantity_to_display'] = PP::presentQty($product['sav_quantity']) . PP::wrapProperty($properties, 'pp_bo_qty_text');
                    }
                } else {
                    if ($ps_stock_management) {
                        $product['sav_quantity_to_display'] = PP::presentQty($product['sav_quantity']) . PP::span(' ', 'pp_bo_qty_text');
                    }
                }
            }
        }
    }

    public function callbackSavQuantityActionAdminProductsListingFieldsModifier($echo, $tr)
    {
        return $this->adminControllerDisplayListContentQuantity($echo, $tr, 'sav_quantity', 'products sav');
    }

    public function hookActionAdminStockCoverListingFieldsModifier($params)
    {
        $params['fields']['qty_sold']['callback'] = 'callbackSoldQuantityActionAdminStockCoverListingFieldsModifier';
        $params['fields']['qty_sold']['callback_object'] = $this;
        $params['fields']['stock']['callback'] = 'callbackStockQuantityActionAdminStockCoverListingFieldsModifier';
        $params['fields']['stock']['callback_object'] = $this;
    }

    public function callbackSoldQuantityActionAdminStockCoverListingFieldsModifier($echo, $tr)
    {
        return $this->adminControllerDisplayListContentQuantity($echo, $tr, 'qty_sold', 'stock-cover sold');
    }

    public function callbackStockQuantityActionAdminStockCoverListingFieldsModifier($echo, $tr)
    {
        return $this->adminControllerDisplayListContentQuantity($echo, $tr, 'stock', 'stock-cover stock');
    }

    public function hookActionAdminStockInstantStateListingFieldsModifier($params)
    {
        $params['fields']['real_quantity']['callback'] = 'callbackSoldQuantityActionAdminStockInstantStateListingFieldsModifier';
        $params['fields']['real_quantity']['callback_object'] = $this;
    }

    public function hookAdminPproperties($params)
    {
        switch ($params['mode']) {
            case 'checkIntegrationAfter':
                $this->setupInstance()->adminTab($this->adminTab);
                break;
            case 'ads':
                $summary = isset($params['summary']) ? $params['summary'] : false;
                break;
            case 'contact_us':
                break;
        }
    }

    public function hookPlugins($params)
    {
        return $this->plugins();
    }

    public function hookTranslations($params)
    {
        return $this->translations();
    }

    public function callbackSoldQuantityActionAdminStockInstantStateListingFieldsModifier($echo, $tr)
    {
        return $this->adminControllerDisplayListContentQuantity($echo, $tr, 'real_quantity', 'stock-instant-state real');
    }

    public function hookActionAdminStockManagementListingFieldsModifier($params)
    {
        $params['fields']['stock']['callback'] = 'callbackSoldQuantityActionAdminStockManagementListingFieldsModifier';
        $params['fields']['stock']['callback_object'] = $this;
    }

    public function callbackSoldQuantityActionAdminStockManagementListingFieldsModifier($echo, $tr)
    {
        return $this->adminControllerDisplayListContentQuantity($echo, $tr, 'stock', 'stock-management stock');
    }

    public function hookActionAdminStockMvtListingFieldsModifier($params)
    {
        $params['fields']['physical_quantity']['callback'] = 'callbackSoldQuantityActionAdminStockMvtListingFieldsModifier';
        $params['fields']['physical_quantity']['callback_object'] = $this;
    }

    public function callbackSoldQuantityActionAdminStockMvtListingFieldsModifier($echo, $tr)
    {
        return $this->adminControllerDisplayListContentQuantity($echo, $tr, 'physical_quantity', 'stock-mvt physical');
    }

    public function adminControllerDisplayListContentQuantity($echo, $tr, $key, $css = '')
    {
        if (isset($tr[$key])) {
            if ($key === 'real_quantity' || $key === 'qty_sold') {
                $value = $tr[$key];
                $id_product = $tr['id_product'];
            } elseif ($key === 'stock') {
                $value = $tr[$key];
                $id_product = $tr['id'];
            } else {
                $key_remainder = $key . '_remainder';
                if (($key === 'sav_quantity' || $key === 'physical_quantity' || $key === 'usable_quantity') && isset($tr[$key_remainder])) {
                    $value = $tr[$key] + $tr[$key_remainder];
                    if (isset($tr['id_product'])) {
                        $id_product = $tr['id_product'];
                    } else {
                        if (isset($tr['id_stock'])) {
                            $id_product = (int) Db::getInstance()->getValue(
                                'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'stock` WHERE `id_stock` = ' . (int) $tr['id_stock']
                            );
                        }
                    }
                }
            }
            if (isset($value) && is_numeric($value) && (float) $value != 0) {
                $properties = PP::getProductProperties($id_product);
                return PP::span(
                    PP::presentQty($value, Context::getContext()->currency) . PP::span(
                        PP::span(
                            ' ' . $properties['pp_bo_qty_text'],
                            'pp_bo_qty_text'
                        ),
                        'pp_bo_qty_text_wrapper'
                    ),
                    'pp_list_qty_wrapper ' . $css
                );
            }
        }
        return $echo;
    }

    public function translations()
    {
        return $this->getTranslations();
    }

    public function getTranslations($key = null)
    {
        static $translations = null;
        static $translations_by_key = null;
        if ($translations === null) {
            $translator = Context::getContext()->getTranslator();
            $translations = array(
                'name' => Tools::strtolower($translator->trans('Name', array(), 'Admin.Global')),
                'description' => Tools::strtolower($translator->trans('Description', array(), 'Admin.Global')),
                'enabled' => Tools::strtolower($translator->trans('Enabled', array(), 'Admin.Global')),
                'disabled' => Tools::strtolower($translator->trans('Disabled', array(), 'Admin.Global')),
                'default' => Tools::strtolower($translator->trans('Default', array(), 'Admin.Global')),
                'or' => Tools::strtolower($translator->trans('Or', array(), 'Admin.Global')),
                'documentation' => Tools::strtolower($translator->trans('Documentation', array(), 'Install')),
                's_multiplication' => $this->l('multiplication: dimensions in all directions are multiplied (giving area or volume)'),
                's_summation' => $this->l('summation: dimensions in all directions are added'),
                's_single_dimension' => $this->l('single dimension'),
                's_disable_calculation' => $this->l('disable calculation'),
                's_custom_calculation' => $this->l('custom calculation'),
                's_pp_explanation' => $this->l('inline explanation'),
                's_pp_minimum_price_ratio' => $this->l('quantity threshold for minimum price'),
                'To disable the module please uninstall it.' => $this->l('To disable the module please uninstall it.'),
                'integration test failed' => $this->l('integration test failed'),
                'Run Setup' => $this->l('Run Setup'),
                'incompatible_plugin_api_version' => $this->l('Required "%s" plugin API version %s, found plugin API version %s.'),
                'version' => Tools::strtolower($translator->trans('Version', array(), 'Admin.Global')),
                'Pro' => $this->l('Pro'),
                'Premium' => $this->l('Premium'),
                'user guide' => $this->l('user guide'),
                'leave blank to use default' => $this->l('leave blank to use default'),
                'leave blank to disable this feature' => $this->l('leave blank to disable this feature'),
                'item' => $this->l('item'),
                'items' => $this->l('items'),
                'in stock' => $this->l('in stock'),
                'out of stock' => $this->l('out of stock'),
                'quantity_update_not_supported' => $this->l('Quantity update not supported.'),
                'invalid_cart_product_reference' => $this->l('Product not found in the cart (invalid cart product reference).'),
                'cannot_order_more_than' => $this->l('You cannot order more than %quantity% %text% for %name%.'),
                'cannot_order_more_than_in_total' => $this->l('You cannot order more than %quantity% %text% in total for %name%.'),
                'should_add_at_least' => $this->l('You should add at least %quantity% %text% for %name%.'),
                'multidimensional_plugin_not_installed' => $this->l('Multidimensional plugin not installed.'),
                'Manage templates' => $this->l('Manage templates'),
                'Please choose template.' => $this->l('Please choose template.'),
                'Assign template' => $this->l('Assign template'),
                'Remove template' => $this->l('Remove template'),
                'Edit template' => $this->l('Edit template'),
                'Product Properties Extension template ID' => $this->l('Product Properties Extension template ID'),
                'template' => $this->l('template'),
                'templates' => $this->l('templates'),
                'statistics' => $this->l('statistics'),
                'show less' => $this->l('show less'),
                'show more' => $this->l('show more'),
                'show less details' => $this->l('show less details'),
                'show more details' => $this->l('show more details'),
                'assign_or_remove_template_for_several_products_at_once' => $this->l('You can assign or remove template for several products at once. Please choose the template below and select products from the list.'),
                'Changing template assignment' => $this->l('Changing template assignment'),
                'Changing product...' => $this->l('Changing product...'),
                'Changing template in progress...' => $this->l('Changing template in progress...'),
                'Changing template failed.' => $this->l('Changing template failed.'),
                'show_in_menu' => $this->l('Show module in PS&More menu section'),
                'upload_failed' => $this->l('File upload failed.'),
                'error_delete_directory' => $this->l('Cannot delete "%s" directory.'),
                'error_create_file' => $this->l('Cannot create "%s" file.'),
                'auto' => $this->l('auto'),
                'visible' => $this->l('visible'),
                'hidden' => $this->l('hidden'),
                'explanation' => $this->l('explanation'),
                'attribute' => Tools::strtolower($translator->trans('Attribute', array(), 'Admin.Global')),
                'attributes' => Tools::strtolower($translator->trans('Attributes', array(), 'Admin.Global')),
                'text' => Tools::strtolower($translator->trans('Text', array(), 'Admin.Global')),
                'texts' => $this->l('texts'),
                'dimension' => $this->l('dimension'),
                'dimensions' => $this->l('dimensions'),
                'metric' => $this->l('metric'),
                'non metric' => $this->l('non metric'),
                'non metric (imperial/US)' => $this->l('non metric (imperial/US)'),
                'CSS classes' => $this->l('CSS classes'),
                'Run analysis' => $this->l('Run analysis'),
                's_price' => $this->l('price'),
                'requires Multidimensional plugin Pro or Premium' => $this->l('requires Multidimensional plugin Pro or Premium'),
                'requires Multidimensional plugin Premium' => $this->l('requires Multidimensional plugin Premium'),
                'display measurement system' => $this->l('display measurement system'),
                'add a block allowing customers to choose the preferred unit measurement system on the product page' => $this->l('add a block allowing customers to choose the preferred unit measurement system on the product page'),
            );
            $t = $this->l('price %s');
            $translations['s_total_amount_to_display'] = ($t === 'price %s' ? $translations['s_price'] . ' %s' : $t);
            $t = $this->l('price %s (tax excl.)');
            $translations['s_total_amount_to_display_tax_excl'] = ($t === 'price %s (tax excl.)' ? $translations['s_price'] . ' %s ' . $translator->trans('(tax excl.)', array(), 'Shop.Theme.Global') : $t);
            $t = $this->l('price %s (tax incl.)');
            $translations['s_total_amount_to_display_tax_incl'] = ($t === 'price %s (tax incl.)' ? $translations['s_price'] . ' %s ' . $translator->trans('(tax incl.)', array(), 'Shop.Theme.Global') : $t);
            if (!$this->multidimensional_plugin || !isset($this->multidimensional_plugin->pro) || Module::isInstalled('psmdemo')) {
                $translations['s_disable_calculation'] .= ' (' . $translations['requires Multidimensional plugin Pro or Premium'] . ')';
                $translations['s_custom_calculation'] .= ' (' . $translations['requires Multidimensional plugin Pro or Premium'] . ')';
            }
            $s1 = $this->l('The minimum quantity required to buy this product');
            $s2 = $this->l('(set to %d to use the template default)');
            $translations_by_key = array();
            $translations_by_key['AdminProducts'] = array(
                's_ppMinQtyExpl_disable' => $this->l('The minimum quantity required to buy this product (set to 1 to disable this feature)'),
                's_ppMinQtyExplShort_0' => sprintf($s2, 1),
                's_ppMinQtyExplShort_1' => sprintf($s2, 0),
                's_ppMinQtyExplShort_2' => sprintf($s2, 0),
                's_ppMinQtyExplShort_ext' => sprintf($s2, 0),
                's_minimum_quantity' => $this->l('minimum quantity defined in template is'),
                's_pp_unity_text_expl' => $this->l('specified by template'),
                's_pack_hint' => $this->l('You can only add to a pack products sold in items (cannot add products sold by weight, length, etc.).'),
                's_pp_qty_policy' => $this->l('quantity policy'),
                's_pp_qty_mode' => $this->l('quantity mode'),
                's_pp_display_mode' => $this->l('display mode'),
                's_pp_price_display_mode' => $this->l('price display mode'),
                's_pp_price_text' => $this->l('price text'),
                's_pp_qty_text' => $this->l('quantity text'),
                's_pp_unity_text' => $this->l('unit price text'),
                's_pp_unit_price_ratio' => $this->l('unit price ratio'),
                's_pp_minimum_price_ratio' => $this->l('quantity threshold for minimum price'),
                's_pp_minimum_quantity' => $this->l('minimum quantity'),
                's_pp_maximum_quantity' => $this->l('maximum quantity'),
                's_pp_total_maximum_quantity' => $this->l('total maximum quantity'),
                's_pp_default_quantity' => $this->l('default quantity'),
                's_pp_qty_step' => $this->l('quantity step'),
                's_pp_qty_shift' => $this->l('quantity shift'),
                's_pp_qty_decimals' => $this->l('quantity decimals'),
                's_pp_qty_values' => $this->l('specific quantity values'),
                's_pp_qty_ratio' => $this->l('quantity ratio'),
                's_pp_explanation' => $this->l('inline explanation'),
                's_pp_qty_policy_0' => $this->l('items'),
                's_pp_qty_policy_1' => $this->l('whole units'),
                's_pp_qty_policy_2' => $this->l('fractional units'),
                's_pp_qty_policy_ext' => $this->l('multidimensional'),
                's_pp_qty_mode_0' => $this->l('exact quantity'),
                's_pp_qty_mode_1' => $this->l('approximate quantity'),
                's_pp_qty_mode_2' => $this->l('aggregate quantities'),
                's_pp_qty_mode_options' => array(2),
                's_pp_display_mode_0' => $this->l('normal'),
                's_pp_display_mode_1' => $this->l('reversed price display (display unit price as price)'),
                's_pp_display_mode_2' => $this->l('display retail price as unit price'),
                's_pp_display_mode_4' => $this->l('display base unit price for all combinations'),
                's_pp_display_mode_8' => $this->l('hide extra details for unit price in orders and invoices'),
                's_pp_display_mode_16' => $this->l('hide extra details for quantity in orders and invoices'),
                's_pp_display_mode_32' => $this->l('hide extra details for product total price in orders and invoices'),
                's_pp_display_mode_64' => $this->l('display legacy product price'),
                's_pp_display_mode_128' => $this->l('display unit price in orders and invoices'),
                's_pp_display_mode_256' => $this->l('display number of items instead of quantity'),
                's_pp_display_mode_options' => array(2, 4, 64, 128, 256, 8, 16, 32), # order in the array defines an order of the presentation on the screen
                's_pp_price_display_mode_0' => $this->l('normal'),
                's_pp_price_display_mode_1' => $this->l('as product price'),
                's_pp_price_display_mode_16' => $this->l('hide price display'),
            );
            $translations_by_key['AdminProducts']['maximum quantity'] = $translations_by_key['AdminProducts']['s_pp_maximum_quantity'];
            $translations_by_key['AdminProducts']['quantity step'] = $translations_by_key['AdminProducts']['s_pp_qty_step'];
            $translations_by_key['AdminProducts']['quantity shift'] = $translations_by_key['AdminProducts']['s_pp_qty_shift'];
            $translations_by_key['AdminProducts']['quantity decimals'] = $translations_by_key['AdminProducts']['s_pp_qty_decimals'];
            $translations_by_key['AdminProducts']['quantity ratio'] = $translations_by_key['AdminProducts']['s_pp_qty_ratio'];
            $translations_by_key['AdminProducts']['s_ppMinQtyExpl_0'] = $s1 . ' ' . $translations_by_key['AdminProducts']['s_ppMinQtyExplShort_0'];
            $translations_by_key['AdminProducts']['s_ppMinQtyExpl_1'] = $s1 . ' ' . $translations_by_key['AdminProducts']['s_ppMinQtyExplShort_1'];
            $translations_by_key['AdminProducts']['s_ppMinQtyExpl_2'] = $s1 . ' ' . $translations_by_key['AdminProducts']['s_ppMinQtyExplShort_2'];
            $translations_by_key['AdminProducts']['s_ppMinQtyExpl_ext'] = $this->l('Minimum quantity in multidimensional template refers to the number of items') . ' ' . $translations_by_key['AdminProducts']['s_ppMinQtyExplShort_ext'];
            foreach ($translations_by_key as $_ => $value) {
                $translations = array_merge($translations, $value);
            }
        }
        if ($key !== null) {
            return $translations_by_key[$key];
        }
        return $translations;
    }

    /** Called in Back Office when user clicks "Configure" */
    public function getContent()
    {
        if (Configuration::get('PS_DISABLE_NON_NATIVE_MODULE')) {
            return PP::div($this->l('Non PrestaShop modules disabled.'), 'module_error alert alert-danger');
        }
        $this->tabAccess = PSM::tabAccess($this->adminTab);
        $this->active_languages = $this->context->controller->getLanguages();
        $this->default_language_id = PP::resolveLanguageId();
        $translations = $this->translations();
        $translator = Context::getContext()->getTranslator();

        $setup = $this->setupInstance();
        if (!(int) Tools::getValue('pp')) {
            $this->checkIntegration($setup);
        }

        $tab = '0';
        $output0 = $output1 = $output2 = $output3 = $output4 = '';
        $templates = null;
        $properties = null;
        if (Tools::isSubmit('submitRestoreDefaults')) {
            $tab = '0';
            if ($this->tabAccess['edit'] === '1') {
                $setup->insertData(true);
            } else {
                $output0 = $this->displayError($this->tabAccess['no_permission']);
            }
        } elseif (Tools::isSubmit('cancelSaveTemplate')) {
            $tab = '0';
        } elseif (Tools::isSubmit('cancelSaveProperty')) {
            $tab = '1';
        } elseif (Tools::isSubmit('submitSaveTemplate') || Tools::isSubmit('submitSaveTemplateAndStay')) {
            if ($this->tabAccess['edit'] === '1') {
                $result = $this->saveTemplate();
            } else {
                $result = array();
                $result['error'] = $this->tabAccess['no_permission'];
                $result['templates'] = null;
            }
            if ($result['error'] === '') {
                $tab = Tools::isSubmit('submitSaveTemplateAndStay') ? '4' : '0';
            } else {
                $templates = $result['templates'];
                $output4 = $this->displayError($result['error']);
                $tab = '4';
            }
        } elseif (Tools::isSubmit('submitSaveProperty')) {
            if ($this->tabAccess['edit'] === '1') {
                $result = $this->saveProperty();
            } else {
                $result = array();
                $result['error'] = $this->tabAccess['no_permission'];
                $result['properties'] = null;
            }
            if ($result['error'] === '') {
                $tab = '1';
            } else {
                $properties = $result['properties'];
                $output4 = $this->displayError($result['error']);
                $tab = '4';
            }
        } elseif (Tools::isSubmit('submitConfigSettings')) {
            $tab = '2';
            if ($this->tabAccess['edit'] === '1') {
                Configuration::updateValue('PP_MEASUREMENT_SYSTEM', PP::getIntNonNegativeValue('measurement_system', 1));
                Configuration::updateValue('PP_MEASUREMENT_SYSTEM_FO_ACTIVATED', PP::getIntNonNegativeValue('measurement_system_fo_activated', 0));
                Configuration::updateValue('PP_POWEREDBY', PP::getIntNonNegativeValue('poweredby', 0));
                Configuration::updateValue('PP_TEMPLATE_NAME_IN_CATALOG', PP::getIntNonNegativeValue('template_name_in_catalog', 1));
                // Configuration::updateValue('PP_SHOW_POSITIONS', PP::getIntNonNegativeValue('show_positions', 0));
                Configuration::updateGlobalValue('PP_SHOW_IN_MENU', PP::getIntNonNegativeValue('show_in_menu', 1));
                $setup->adminTab($this->adminTab, Configuration::getGlobalValue('PP_SHOW_IN_MENU'));
                $output2 = $this->displayConfirmation($translator->trans('Settings updated', array(), 'Admin.Global'));
            } else {
                $output2 = $this->displayError($this->tabAccess['no_permission']);
            }
        } elseif (Tools::isSubmit('submitSetup')) {
            $tab = '2';
            if ($this->tabAccess['edit'] === '1') {
                $setup->runSetup();
            } else {
                $output2 = $this->displayError($this->tabAccess['no_permission']);
            }
        } elseif (Tools::isSubmit('clickClearCache')) {
            $tab = '2';
            Configuration::deleteByName('PP_INFO_CHECK_TIME');
            PSM::clearCache();
        } elseif (Tools::isSubmit('submitStatistics')) {
            $tab = '3';
        } elseif (Tools::isSubmit('clickEditTemplate')) { // NOTE: all 'click' test must be at the end. They are performed as GET and can interfier with 'submit' buttons.
            $tab = '4';
        } elseif (Tools::isSubmit('clickDeleteTemplate')) {
            $tab = '0';
            if ($this->tabAccess['edit'] === '1' && $this->tabAccess['delete'] === '1') {
                $this->deleteTemplate();
            } else {
                $output0 = $this->displayError($this->tabAccess['no_permission']);
            }
        } elseif (Tools::isSubmit('clickHiddenStatusTemplate')) {
            $tab = '0';
            if ($this->tabAccess['edit'] === '1') {
                $this->changeHiddenStatus();
            } else {
                $output0 = $this->displayError($this->tabAccess['no_permission']);
            }
        } elseif (Tools::isSubmit('clickEditProperty')) {
            $tab = '4';
        } elseif (Tools::isSubmit('clickDeleteProperty')) {
            $tab = '1';
            if ($this->tabAccess['edit'] === '1' && $this->tabAccess['delete'] === '1') {
                $this->deleteProperty();
            } else {
                $output1 = $this->displayError($this->tabAccess['no_permission']);
            }
        }

        $html = '';
        if (version_compare(_PS_VERSION_, $this->pp_versions_compliancy['min']) < 0
            || version_compare(_PS_VERSION_, $this->pp_versions_compliancy['max']) > 0) {
            $html .= $this->displayError(sprintf('%s %s<br>%s<br>', $this->l('This module is not fully compatible with the installed PrestaShop version.'), $this->compatibilityText(), $this->l('Please upgrade to the newer version.')));
        }
        if (count($this->integration_test_result) != 0) {
            $html .= $this->displayError($translations['integration test failed']);
            $tab = '2';
        }

        $tabs = array();
        $tabs[0] = array(
            'type' => 'templates',
            'name' => Tools::ucfirst($translations['templates']),
            'icon' => '<i class="material-icons icon-template">reorder</i>',
            'html' => $output0 . $this->getTemplatesTabHtml(),
        );
        $tabs[1] = array(
            'type' => 'properties',
            'name' => $this->l('Properties'),
            'icon' => '<i class="icon-tag"></i>',
            'html' => $output1 . $this->getPropertiesTabHtml(),
        );
        $tabs[2] = array(
            'type' => 'settings',
            'name' => $translator->trans('Settings', array(), 'Admin.Global'),
            'icon' => '<i class="icon-AdminAdmin"></i>',
            'html' => $output2 . $this->getSettingsTabHtml(Tools::isSubmit('submitSetup')),
        );
        $tabs[3] = array(
            'type' => 'statistics',
            'name' => Tools::ucfirst($translations['statistics']),
            'icon' => '<i class="icon-AdminParentStats"></i>',
            'html' => $output3 . $this->getStatisticsTabHtml(Tools::isSubmit('submitStatistics')),
        );
        if (($tab == 4) && (Tools::isSubmit('clickEditTemplate') || Tools::isSubmit('submitSaveTemplate') || Tools::isSubmit('submitSaveTemplateAndStay'))) {
            $mode = Tools::getValue('mode');
            if ($mode == 'add') {
                $title = $this->l('Add template');
            } elseif ($mode == 'copy') {
                $title = $this->l('Add template');
            } else {
                $mode = 'edit';
                $title = $this->l('Edit template');
            }
            $tabs[4] = array(
                'type' => 'modifyTemplate',
                'name' => $title,
                'icon' => '<i class="icon-edit"></i>',
                'html' => $output4 . $this->getEditTemplateTabHtml($templates, $mode, $title),
            );
        } elseif (($tab == 4) && (Tools::isSubmit('clickEditProperty') || Tools::isSubmit('submitSaveProperty'))) {
            $mode = Tools::getValue('mode');
            if ($mode == 'add') {
                $type = (int) Tools::getValue('type');
                if ($type == self::PROPERTY_TYPE_GENERAL) {
                    $title = $this->l('Add property attribute');
                } elseif ($type == self::PROPERTY_TYPE_BLOCK_TEXT) {
                    $title = $this->l('Add property text');
                } else {
                    $title = $this->l('Add property dimension');
                }
            } else {
                $mode = 'edit';
                $title = $this->l('Edit property');
            }
            $tabs[4] = array(
                'type' => 'modifyProperty',
                'name' => $title,
                'icon' => '<i class="icon-edit"></i>',
                'html' => $output4 . $this->getEditPropertyTabHtml($properties, $mode, $title),
            );
        }

        $helper = $this->createTemplate('pproperties');
        $helper->tpl_vars['html'] = $html;
        $helper->tpl_vars['tabs'] = $tabs;
        $helper->tpl_vars['active'] = $tab;
        $helper->tpl_vars['version'] = PSM::moduleVersion($this);
        $helper->tpl_vars['ppe_id'] = PSM::psmId($this->name);
        $helper->tpl_vars['_path'] = $this->getPathUri();
        $helper->tpl_vars['translations'] = $this->translations();
        $helper->tpl_vars['s_pp_info_ignore'] = $this->l("don't show this message again");
        $helper->tpl_vars['token_adminpproperties'] = Tools::getAdminTokenLite($this->adminTab);
        $helper->tpl_vars['adminppropertiesurl'] = $this->context->link->getAdminLink($this->adminTab, true);
        $run_setup = $translations['Run Setup'];
        $helper->tpl_vars['jstranslations'] = PP::safeOutputLenientJS(
            array(
                'rerun' => $run_setup,
                'integration_module_success_IntegrationModuleIgnore' => $this->l('ignored'),
                'integration_module_success_IntegrationModuleIntegrate' => $this->l('integation activated') . ' - ' . $run_setup,
                'integration_module_rerun_IntegrationModuleCheckForUpdates' => $run_setup,
                'integration_module_downloaded_IntegrationModuleCheckForUpdates' => $this->l('update downloaded') . ' - ' . $run_setup,
                'integration_module_no_updates_IntegrationModuleCheckForUpdates' => $this->l('no updates available - please contact customer support'),
                'integration_module_permission_denied_IntegrationModuleCheckForUpdates' => Context::getContext()->getTranslator()->trans('The server does not have permissions for writing.', array(), 'Admin.Notifications.Error'),
                'integration_module_error' => $this->l('error occurred'),
                'extra_modules_dir' => PSMHelper::ppSetupExtraModulesDir(true),
            )
        );
        return $helper->generate();
    }

    private function getTemplatesTabHtml()
    {
        $translations = $this->translations();
        $helper = $this->createTemplate('templates');
        if ($this->integrated) {
            $templates = PP::getTemplates($this->default_language_id);
            $buy_block_text = array();
            foreach ($templates as &$template) {
                $qty_mode = array();
                if (($template['pp_qty_mode'] & 1) == 1) {
                    $qty_mode[] = 1;
                }
                $index = 2;
                foreach ($translations['s_pp_qty_mode_options'] as $i) {
                    if (($template['pp_qty_mode'] & $i) == $i) {
                        $qty_mode[] = $index;
                    }
                    $index++;
                }
                $template['qty_mode'] = implode(',', $qty_mode);
                $display_mode = array();
                if (($template['pp_display_mode'] & 1) == 1) {
                    $display_mode[] = 1;
                }
                $index = 2;
                foreach ($translations['s_pp_display_mode_options'] as $i) {
                    if (($template['pp_display_mode'] & $i) == $i) {
                        $display_mode[] = $index;
                    }
                    $index++;
                }
                $template['display_mode'] = implode(',', $display_mode);
                if ($template['auto_desc']) {
                    $template['description'] = $this->generateDescription($template);
                }
                if ($template['pp_explanation']) {
                    $buy_block_text[$template['pp_bo_buy_block_index']] = PP::safeOutputLenient($template['pp_explanation']);
                }
            }
            ksort($buy_block_text, SORT_NUMERIC);

            $helper->tpl_vars['templates'] = PP::safeOutputLenient($templates);
            $helper->tpl_vars['buy_block_text'] = $buy_block_text;

            $helper->tpl_vars['qty_mode_text'] = array(
                $translations['s_pp_qty_mode_1'],
            );
            foreach ($translations['s_pp_qty_mode_options'] as $i) {
                $helper->tpl_vars['qty_mode_text'][] = $translations['s_pp_qty_mode_' . $i];
            }

            $helper->tpl_vars['display_mode_text'] = array(
                $translations['s_pp_display_mode_1'],
            );
            foreach ($translations['s_pp_display_mode_options'] as $i) {
                $helper->tpl_vars['display_mode_text'][] = $translations['s_pp_display_mode_' . $i];
            }
        }
        return $helper->generate();
    }

    private function getPropertiesTabHtml()
    {
        $helper = $this->createTemplate('properties');
        if ($this->integrated) {
            $all_properties = $this->getAllProperties();
            $metric = (PP::resolveMeasurementSystem() != PP::PP_MS_NON_METRIC);

            $helper->tpl_vars['properties'] = $all_properties[$this->default_language_id];
            $helper->tpl_vars['property_types'] = $this->getPropertyTypes();
            $helper->tpl_vars['types'] = array(
                'attributes' => array('id' => self::PROPERTY_TYPE_GENERAL, 'metric' => true, 'nonmetric' => true),
                'texts' => array('id' => self::PROPERTY_TYPE_BLOCK_TEXT, 'metric' => $metric, 'nonmetric' => !$metric),
                'dimensions' => ($this->multidimensional_plugin ? array('id' => self::PROPERTY_TYPE_EXT, 'metric' => true, 'nonmetric' => true) : false),
            );
        }
        return $helper->generate();
    }

    private function getSettingsTabHtml($display)
    {
        $translator = Context::getContext()->getTranslator();
        $html = '';
        $translations = $this->translations();
        if ($this->integrated) {
            $helper = $this->createHelperForm('pp_settings_form', $translator->trans('Settings', array(), 'Admin.Global'), 'submitConfigSettings');
            $yes_no = array(
                array(
                    'value' => 1,
                    'label' => $translator->trans('Yes', array(), 'Admin.Global')
                ),
                array(
                    'value' => 0,
                    'label' => $translator->trans('No', array(), 'Admin.Global')
                )
            );
            $form = array(
                'input' => array(
                    array(
                        'label' => $this->l('Measurement system'),
                        'type'  => 'radio',
                        'name'  => 'measurement_system',
                        'label_col' => 3,
                        'desc'  => $this->l('unit measurement system used by default (can be overridden in template)'),
                        'values' => array(
                            array(
                                'id'    => 'measurement_system_1',
                                'value' => PP::PP_MS_METRIC,
                                'label' => $translations['metric']
                            ),
                            array(
                                'id'    => 'measurement_system_2',
                                'value' => PP::PP_MS_NON_METRIC,
                                'label' => $translations['non metric (imperial/US)']
                            )
                        ),
                    ),
                    /*
                    array(
                        'label' => Tools::ucfirst($translations['display measurement system']),
                        'type'  => 'switch',
                        'name'  => 'measurement_system_fo_activated',
                        'label_col' => 3,
                        'desc'  => $this->l('customer selected measurement system works only for templates that use the default unit measurement system'),
                        'hint'  => $this->l('Add a block allowing customers to choose their preferred measurement system.'),
                        'values'=> array(
                            array(
                                'id'    => 'measurement_system_fo_activated_on',
                                'value' => 1,
                            ),
                            array(
                                'id'    => 'measurement_system_fo_activated_off',
                                'value' => 0,
                            )
                        ),
                    ),
                    */
                    array(
                        'label' => $this->l('Display "Powered by PS&More"'),
                        'type'  => 'switch',
                        'name'  => 'poweredby',
                        'label_col' => 3,
                        'values' => $yes_no,
                    ),
                    array(
                        'label' => $this->l('Show templates in the catalog'),
                        'type'  => 'switch',
                        'name'  => 'template_name_in_catalog',
                        'label_col' => 3,
                        'desc'  => $this->l('show or hide template names in the products catalog'),
                        'values' => $yes_no,
                    ),
                    /*
                    array(
                        'label' => $this->l('Display positions'),
                        'type'  => 'switch',
                        'name'  => 'show_positions',
                        'label_col' => 3,
                        'desc'  => $this->l('show or hide position names on the product\'s page (use it only for testing)'),
                        'values'=> array(
                            array(
                                'id'    => 'show_positions_on',
                                'value' => 1,
                            ),
                            array(
                                'id'    => 'show_positions_off',
                                'value' => 0,
                            )
                        ),
                    ),
                    */
                    array(
                        'label' => $translations['show_in_menu'],
                        'type' => 'switch',
                        'name' => 'show_in_menu',
                        'label_col' => 3,
                        'values' => $yes_no,
                    ),
                    array(
                        'type' => 'clearcache',
                        'name' => $translator->trans('Clear cache', array(), 'Admin.Advparameters.Feature'),
                    ),
                ),
            );
            $helper->fields_value['measurement_system'] = PP::getIntNonNegativeValue('measurement_system', Configuration::get('PP_MEASUREMENT_SYSTEM'));
            $helper->fields_value['measurement_system_fo_activated'] = (int) Configuration::get('PP_MEASUREMENT_SYSTEM_FO_ACTIVATED');
            $helper->fields_value['poweredby'] = (int) Configuration::get('PP_POWEREDBY');
            $helper->fields_value['template_name_in_catalog'] = (int) Configuration::get('PP_TEMPLATE_NAME_IN_CATALOG');
            // $helper->fields_value['show_positions'] = (int) Configuration::get('PP_SHOW_POSITIONS');
            $helper->fields_value['show_in_menu'] = (int) Configuration::getGlobalValue('PP_SHOW_IN_MENU');
            $html .= $this->generateForm($helper, $form);
        }

        $setup = $this->setupInstance();
        $integration = array();
        $modified_files = $setup->checkModifiedFiles();
        $extra_modules = $setup->checkExtraModulesIntegrity(true);
        if (empty($this->version_mismatch_notes)) {
            if (count($this->integration_test_result) == 0) {
                $integration['btn_action'] = 'submitSetup';
                $integration['btn_title'] = $translations['Run Setup'];
                if ($display) {
                    $integration['confirmation'] = $this->displayConfirmation($this->l('Setup completed successfully.'));
                    $res = $modified_files;
                    $res = array_replace_recursive($res, $extra_modules);
                    if (isset($this->integration_test_result_notes)) {
                        $res = array_merge_recursive($res, $this->integration_test_result_notes);
                    }
                    $integration['display'] = $this->showIntegrationTestResults($res);
                }
            } else {
                $this->integration_test_result = array_replace_recursive($this->integration_test_result, $modified_files);
                $this->integration_test_result = array_replace_recursive($this->integration_test_result, $extra_modules);
                if (isset($this->integration_test_result_notes)) {
                    $this->integration_test_result = array_merge_recursive($this->integration_test_result, $this->integration_test_result_notes);
                }
                $integration['btn_action'] = 'submitSetup';
                $integration['btn_title'] = $translations['Run Setup'];
                $integration['display'] = $this->showIntegrationTestResults($this->integration_test_result);
                $integration['hasDesc'] = true;
                $integration['_path'] = $this->getPathUri();
            }
        }
        $helper = $this->createTemplate('integration');
        $helper->tpl_vars['integration'] = $integration;
        $helper->tpl_vars['integration_instructions'] = $this->l('Integration Instructions');
        $html .= $helper->generate();
        return $html;
    }

    private function getStatisticsTabHtml($display)
    {
        $helper = $this->createTemplate('statistics');
        if ($this->integrated) {
            @set_time_limit(300);
            if ($display) {
                $db = Db::getInstance();
                $templates = PP::getTemplates($this->default_language_id);

                $statistics = array();
                $used_templates = array();
                $rows = $db->executeS('SELECT count(`id_pp_template`) as count, `id_pp_template` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_pp_template` > 0 group by `id_pp_template`');
                foreach ($rows as $row) {
                    $statistics[$row['id_pp_template']] = $row['count'];
                    $used_templates[$row['id_pp_template']] = $row['id_pp_template'];
                }
                $rows = array();
                foreach ($templates as $template) {
                    $id_pp_template = $template['id_pp_template'];
                    unset($used_templates[$id_pp_template]);
                    $row = array();
                    $row['id'] = $id_pp_template;
                    $row['name'] = $template['name'];
                    $row['count'] = (isset($statistics[$id_pp_template]) ? $statistics[$id_pp_template] : 0);
                    if ($row['count'] > 0) {
                        $products = $db->executeS('SELECT DISTINCT p.`id_product`, pl.`name` FROM `' . _DB_PREFIX_ . 'product` p LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int) $this->default_language_id . ') WHERE p.`id_pp_template` = ' . (int) $id_pp_template . ' order by p.`id_product` asc');
                        foreach ($products as &$product) {
                            $product['href'] = $this->context->link->getAdminLink('AdminProducts', true, array('id_product' => $product['id_product']));
                        }
                        $row['products'] = $products;
                    }
                    $rows[] = $row;
                }
                $helper->tpl_vars['existing'] = $rows;
                if (!empty($used_templates)) {
                    $products = $db->executeS('SELECT DISTINCT p.`id_product`, p.`id_pp_template`, pl.`name` FROM `' . _DB_PREFIX_ . 'product` p LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int) $this->default_language_id . ') WHERE p.`id_pp_template` in (' . implode(',', $used_templates) . ') order by p.`id_product` asc');
                    if (is_array($products) && count($products) > 0) {
                        foreach ($products as &$product) {
                            $product['href'] = $this->context->link->getAdminLink('AdminProducts', true, array('id_product' => $product['id_product']));
                        }
                        $helper->tpl_vars['missing'] = $products;
                    }
                }
            }
        }
        return $helper->generate();
    }

    private function getEditTemplateTabHtml($templates, $mode, $title)
    {
        if (!$this->integrated) {
            $helper = $this->createHelperForm('pp_template_form', $title, null, 'icon-edit');
            return $this->generateForm($helper, array());
        }

        $translator = Context::getContext()->getTranslator();
        $translations = $this->translations();
        if ($mode == 'add') {
            $id = 0;
            if ($templates == null) {
                foreach ($this->active_languages as $language) {
                    $template = PP::getProductPropertiesByTemplateId($id);
                    $template['name'] = '';
                    $template['auto_desc'] = 1;
                    $template['description'] = '';
                    $templates[$language['id_lang']][$id] = $template;
                }
            }
        } else {
            $id = PP::getIntNonNegativeValue('id');
        }

        if ($templates == null) {
            $templates = PP::getAllTemplates();
        }

        $template = $templates[$this->default_language_id][$id];
        $ms = PP::resolveMeasurementSystem($template['pp_bo_measurement_system']);
        $multidimensional_pro = $this->multidimensional_plugin && isset($this->multidimensional_plugin->pro);

        $all_properties = $this->getAllProperties($ms);
        foreach ($all_properties as $key => $_) {
            uasort($all_properties[$key], array($this, 'compareProperties'));
        }
        $property_types = $this->getPropertyTypes();

        $buttons = array(
            array(
                'title' => $translator->trans('Cancel', array(), 'Admin.Actions'),
                'type'  => 'submit',
                'name'  => 'cancelSaveTemplate',
                'icon'  => 'process-icon-cancel',
            )
        );
        if ($mode == 'edit') {
            $buttons[] = array(
                'title' => $translator->trans('Save and stay', array(), 'Admin.Actions'),
                'type'  => 'submit',
                'name'  => 'submitSaveTemplateAndStay',
                'class' => 'btn btn-default pull-right pp-action-btn' . ($this->tabAccess['edit'] === '1' ? '' : ' disabled'),
                'icon' => 'process-icon-save'
            );
        }
        $multidimensional_quantity = PP::isQuantityMultidimensional($template);
        $multidimensional_note = ($multidimensional_quantity ? '<br>-- ' . $this->l('in multidimensional template the quantity refers to the number of items') . ' --' : '');
        $multidimensional_note_form_group_class = ($multidimensional_quantity ? 'two-lines' : '');
        $helper = $this->createHelperForm('pp_template_form', $title, 'submitSaveTemplate', 'icon-edit');

        $s_pp_qty_mode_checkboxes = array();
        foreach ($translations['s_pp_qty_mode_options'] as $i) {
            $s_pp_qty_mode_checkboxes[] = array(
                'values' => array(
                    'query' => array(
                        array(
                            'id'   => $i,
                            'name' => $translations['s_pp_qty_mode_' . $i],
                            'val'  => '1'
                        ),
                    ),
                    'id'   => 'id',
                    'name' => 'name'
                ),
                'separate' => true
            );
        }
        $s_pp_display_mode_checkboxes = array();
        foreach ($translations['s_pp_display_mode_options'] as $i) {
            $s_pp_display_mode_checkboxes[] = array(
                'values' => array(
                    'query' => array(
                        array(
                            'id'   => $i,
                            'name' => $translations['s_pp_display_mode_' . $i],
                            'val'  => '1'
                        ),
                    ),
                    'id'   => 'id',
                    'name' => 'name'
                ),
                'separate' => true
            );
        }

        $form = array(
            'input' => array(
                array(
                    'type'  => 'div',
                    'label' => $translator->trans('ID', array(), 'Admin.Global'),
                    'name'  => $id,
                    'class' => 'control-text',
                    'condition' => ($mode == 'edit'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['name'],
                    'name'  => 'name_input',
                    'lang'  => true,
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['description'],
                    'name'  => 'description_input',
                    'lang'  => true,
                    'desc'  => $this->l('leave blank to use auto generated description'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $translations['s_pp_qty_policy'],
                    'name'   => 'pp_qty_policy',
                    'desc'   => $this->l('ordered quantity specifies number of items (pieces, packs, etc.) or one item of the specified number of whole or fractional units (kg, m, ft, etc.)'),
                    'values' => array(
                        array('id' => 'pp_qty_policy_0', 'value' => 0, 'label' => $translations['s_pp_qty_policy_0']),
                        array('id' => 'pp_qty_policy_1', 'value' => 1, 'label' => $translations['s_pp_qty_policy_1']),
                        array('id' => 'pp_qty_policy_2', 'value' => 2, 'label' => $translations['s_pp_qty_policy_2']),
                        array('id' => 'pp_qty_policy_3', 'value' => 3, 'label' => $translations['s_pp_qty_policy_ext']),
                    ),
                    'advice' => array(
                        'type' => 'label description',
                        'class' => 'pp_qty_policy_0 pp_qty_policy_1',
                        'bind' => '[type=radio][name=pp_qty_policy]',
                        'html' => sprintf($this->l('Learn the difference between "%s" and "%s".'), $translations['s_pp_qty_policy_0'], $translations['s_pp_qty_policy_1']),
                        'key' => 'pp_qty_policy',
                    ),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $translations['s_pp_qty_mode'],
                    'name'   => 'pp_qty_mode',
                    'desc'   => $this->l('product quantity can be measured exactly or only approximately (the exact amount cannot be ordered) - only if quantity policy is set to units'),
                    'values' => array(
                        array('id' => 'pp_qty_mode_0', 'value' => 0, 'label' => $translations['s_pp_qty_mode_0']),
                        array('id' => 'pp_qty_mode_1', 'value' => 1, 'label' => $translations['s_pp_qty_mode_1']),
                    ),
                    'checkbox_name' => 'pp_qty_mode',
                    'checkboxes' => $s_pp_qty_mode_checkboxes,
                    'advice' => $this->advice('pp_qty_mode'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $translations['s_pp_display_mode'],
                    'name'   => 'pp_display_mode',
                    'values' => array(
                        array('id' => 'pp_display_mode_0', 'value' => 0, 'label' => $translations['s_pp_display_mode_0']),
                        array('id' => 'pp_display_mode_1', 'value' => 1, 'label' => $translations['s_pp_display_mode_1']),
                    ),
                    'checkbox_name' => 'pp_display_mode',
                    'checkboxes' => $s_pp_display_mode_checkboxes,
                    'advice' => $this->advice('pp_display_mode'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $translations['s_pp_price_display_mode'],
                    'name'   => 'pp_price_display_mode',
                    'desc'   => $this->l('show calculated price separately, display it in the position of the product price or hide the calculated price'),
                    'values' => array(
                        array('id' => 'pp_price_display_mode_0',  'value' => 0,  'label' => $translations['s_pp_price_display_mode_0']),
                        array('id' => 'pp_price_display_mode_1',  'value' => 1,  'label' => $translations['s_pp_price_display_mode_1']),
                        array('id' => 'pp_price_display_mode_16', 'value' => 16, 'label' => $translations['s_pp_price_display_mode_16']),
                    ),
                    'advice' => $this->advice('pp_price_display_mode'),
                ),
                $this->createHelperFormSelect(
                    'pp_price_text',
                    array(
                        'label' => $translations['s_pp_price_text'],
                        'desc'  => $this->l('displayed after the product\'s price')
                    ),
                    self::PROPERTY_TYPE_GENERAL,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                $this->createHelperFormSelect(
                    'pp_qty_text',
                    array(
                        'label' => $translations['s_pp_qty_text'],
                        'desc'  => $this->l('displayed after the product\'s quantity')
                    ),
                    self::PROPERTY_TYPE_GENERAL,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                $this->createHelperFormSelect(
                    'pp_unity_text',
                    array(
                        'label' => $translations['s_pp_unity_text'],
                        'desc'  => $this->l('displayed for products with unit price greater than zero')
                    ),
                    self::PROPERTY_TYPE_GENERAL,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_unit_price_ratio'],
                    'name'  => 'unit_price_ratio',
                    'class' => 'fixed-width-xl',
                    'desc'  => $this->l('used to auto calculate unit price in product catalog'),
                    'advice' => $this->advice('pp_unit_price_ratio'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_minimum_price_ratio'],
                    'name'  => 'minimum_price_ratio',
                    'class' => 'fixed-width-xl',
                    'desc'  => $this->l('used to calculate minimum price for quantity less than the specified threshold'),
                    'advice' => $this->advice('pp_minimum_price_ratio'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_minimum_quantity'],
                    'name'  => 'minimum_quantity',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('minimum quantity required to buy a product'), $translations['leave blank to use default']) . $multidimensional_note,
                    'form_group_class' => $multidimensional_note_form_group_class,
                    'advice' => $this->advice('pp_minimum_quantity'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_maximum_quantity'],
                    'name'  => 'maximum_quantity',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('maximum quantity for a product in the order per line'), $translations['leave blank to disable this feature']) . $multidimensional_note,
                    'form_group_class' => $multidimensional_note_form_group_class,
                    'advice' => $this->advice('pp_maximum_quantity'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_total_maximum_quantity'],
                    'name'  => 'total_maximum_quantity',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('total maximum quantity for a product per order'), $translations['leave blank to disable this feature']) . $multidimensional_note,
                    'form_group_class' => $multidimensional_note_form_group_class,
                    'advice' => $this->advice('pp_total_maximum_quantity'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_default_quantity'],
                    'name'  => 'default_quantity',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('initial quantity to buy a product'), $translations['leave blank to use default']),
                    'advice' => $this->advice('pp_default_quantity'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['quantity step'],
                    'name'  => 'qty_step',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $translations['quantity step'], $translations['leave blank to use default']),
                    'advice' => $this->advice('pp_qty_step'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['quantity shift'],
                    'name'  => 'qty_shift',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $translations['quantity shift'], $translations['leave blank to use default']),
                    'advice' => $this->advice('pp_qty_shift'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['quantity decimals'],
                    'name'  => 'qty_decimals',
                    'class' => 'fixed-width-xl',
                    'desc'  => Tools::strtolower(sprintf('%s (%s)', $translator->trans('Choose how many decimals you want to display', array(), 'Admin.Shopparameters.Help'), $translations['leave blank to use default'])),
                    'advice' => $this->advice('pp_qty_decimals'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_qty_values'],
                    'name'  => 'qty_values',
                    'advice' => $this->advice('pp_qty_values'),
                ),
                // array(
                //     'type'  => 'text',
                //     'label' => $translations['quantity ratio'],
                //     'name'  => 'qty_ratio',
                //     'class' => 'fixed-width-xl',
                //     'desc'  => sprintf('%s (%s)', $this->l('the ratio between the quantity entered by user and the quantity used for price calculation'), $translations['leave blank to disable this feature']),
                //     'advice' => $this->advice('pp_qty_ratio'),
                // ),
                $this->createHelperFormSelect(
                    'pp_explanation',
                    array('label' => $translations['s_pp_explanation']),
                    self::PROPERTY_TYPE_BLOCK_TEXT,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['CSS classes'],
                    'name'  => 'pp_css',
                    'desc'  => $this->l('specify valid CSS classes separated by space (these classes will be added to HTML for products using this template)') .
                        '<br>' . sprintf($this->l('add your classes definitions in the "%s" file'), PSM::normalizePath('themes/' . _THEME_NAME_ . '/modules/pproperties/css/custom.css')),
                    'advice' => $this->advice('pp_css'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $this->l('display available quantities mode'),
                    'name'   => 'pp_bo_qty_available_display',
                    'desc'   => $this->l('display available quantities on the product page based on the template configuration (only if enabled in preferences)') .
                        ($template['pp_bo_qty_available_display'] == 0 ? '<br>' . ($template['pp_qty_available_display'] == 2 ? $this->l('-- available quantities will be hidden on the product page for current template --') : $this->l('-- available quantities will be displayed on the product page for current template --')) : ''),
                    'values' => array(
                        array('id' => 'pp_bo_qty_available_display_0', 'value' => 0, 'label' => $translations['auto']),
                        array('id' => 'pp_bo_qty_available_display_1', 'value' => 1, 'label' => $translations['visible']),
                        array('id' => 'pp_bo_qty_available_display_2', 'value' => 2, 'label' => $translations['hidden']),
                    ),
                    'advice' => $this->advice('pp_bo_qty_available_display'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $this->l('measurement system'),
                    'name'   => 'pp_bo_measurement_system',
                    'desc'   => $this->l('unit measurement system used by this template (default - use measurement system defined in Settings)') .
                        ($template['pp_bo_measurement_system'] == 0 ? '<br>-- ' . $this->l('measurement system defined in Settings') . ': ' . (PP::resolveMeasurementSystem() == PP::PP_MS_METRIC ? $translations['metric'] : $translations['non metric']) . ' --': ''),
                    'values' => array(
                        array('id' => 'pp_bo_measurement_system_0', 'value' => PP::PP_MS_DEFAULT, 'label' => $translations['default']),
                        array('id' => 'pp_bo_measurement_system_1', 'value' => PP::PP_MS_METRIC, 'label' => $translations['metric']),
                        array('id' => 'pp_bo_measurement_system_2', 'value' => PP::PP_MS_NON_METRIC, 'label' => $translations['non metric']),
                    ),
                    'advice' => $this->advice('pp_bo_measurement_system'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $this->l('visible in catalog'),
                    'name'   => 'pp_bo_hidden',
                    'desc'   => $this->l('hidden template is not visible in the product catalog, but still used in the shop'),
                    'values' => array(
                        array('id' => 'pp_bo_hidden_0', 'value' => 0, 'label' => $translations['visible']),
                        array('id' => 'pp_bo_hidden_1', 'value' => 1, 'label' => $translations['hidden']),
                    ),
                    'advice' => $this->advice('pp_bo_hidden'),
                ),
                array(
                    'type'   => 'radio',
                    'label'  => $this->l('customization'),
                    'name'   => 'pp_customization',
                    'desc'   => $this->l('if you do not use user defined customizations, leave this option disabled'),
                    'values' => array(
                        array('id' => 'pp_customization_1', 'value' => 1, 'label' => $translations['enabled']),
                        array('id' => 'pp_customization_0', 'value' => 0, 'label' => $translations['disabled']),
                    ),
                    'advice' => $this->advice('pp_customization'),
                ),
                array(
                    'type'  => 'hidden',
                    'name'  => 'id',
                ),
                array(
                    'type'  => 'hidden',
                    'name'  => 'mode',
                ),
            ),
            'buttons' => $buttons,
        );

        $helper->fields_value['id'] = $id;
        $helper->fields_value['mode'] = $mode;
        $helper->fields_value['name_input'] = array();
        $helper->fields_value['description_input'] = array();
        foreach ($this->active_languages as $language) {
            $id_lang = $language['id_lang'];
            $helper->fields_value['name_input'][$id_lang] = isset($templates[$id_lang]) ? PP::safeOutputValue($templates[$id_lang][$id]['name']) : '';
            $helper->fields_value['description_input'][$id_lang] = isset($templates[$id_lang]) ? PP::safeOutputValue((isset($templates[$id_lang][$id]['auto_desc']) && $templates[$id_lang][$id]['auto_desc']) || ($mode == 'copy') ? '' : $templates[$id_lang][$id]['description']) : '';
        }
        $helper->fields_value['pp_qty_mode'] = (int) (($template['pp_qty_mode'] & 1) == 1);
        foreach ($translations['s_pp_qty_mode_options'] as $i) {
            $helper->fields_value['pp_qty_mode_' . $i] = (int) (($template['pp_qty_mode'] & $i) == $i);
        }
        $helper->fields_value['pp_display_mode'] = (int) (($template['pp_display_mode'] & 1) == 1);
        foreach ($translations['s_pp_display_mode_options'] as $i) {
            $helper->fields_value['pp_display_mode_' . $i] = (int) (($template['pp_display_mode'] & $i) == $i);
        }
        $helper->fields_value['pp_price_display_mode'] = $template['pp_price_display_mode'];
        $helper->fields_value['unit_price_ratio'] = (float) $template['pp_unit_price_ratio'] > 0 ? PP::presentQty($template['pp_unit_price_ratio']) : '';
        $helper->fields_value['minimum_price_ratio'] = (float) $template['pp_minimum_price_ratio'] > 0 ? PP::presentQty($template['pp_minimum_price_ratio']) : '';
        $helper->fields_value['minimum_quantity'] = $template['pp_bo_minimum_quantity_customized'] ? PP::presentQty($template['pp_minimum_quantity']) : '';
        $helper->fields_value['maximum_quantity'] = (float) $template['pp_maximum_quantity'] > 0 ? PP::presentQty($template['pp_maximum_quantity']) : '';
        $helper->fields_value['total_maximum_quantity'] = (float) $template['pp_total_maximum_quantity'] > 0 ? PP::presentQty($template['pp_total_maximum_quantity']) : '';
        $helper->fields_value['default_quantity'] = $template['pp_has_default_quantity'] ? PP::presentQty($template['pp_default_quantity']) : '';
        $helper->fields_value['qty_step'] = (float) $template['pp_qty_step'] > 0 ? PP::presentQty($template['pp_qty_step']) : '';
        $helper->fields_value['qty_shift'] = (float) $template['pp_qty_shift'] > 0 ? PP::presentQty($template['pp_qty_shift']) : '';
        $helper->fields_value['qty_decimals'] = (int) $template['pp_qty_decimals'] > 0 ? (int) $template['pp_qty_decimals'] : '';
        $helper->fields_value['qty_values'] = $template['pp_qty_values'];
        // $helper->fields_value['qty_ratio'] = (float) $template['pp_qty_ratio'] > 0 ? PP::presentQty($template['pp_qty_ratio']) : '';
        $helper->fields_value['pp_css'] = $template['pp_css'];
        $helper->fields_value['pp_bo_qty_available_display'] = $template['pp_bo_qty_available_display'];
        $helper->fields_value['pp_bo_measurement_system'] = $template['pp_bo_measurement_system'];
        $helper->fields_value['pp_bo_hidden'] = $template['pp_bo_hidden'];
        $helper->fields_value['pp_customization'] = $template['pp_customization'];

        $dimensions = isset($template['pp_ext_method']) && isset($template['pp_ext_prop']) ? count($template['pp_ext_prop']) : 0;
        if ($dimensions == 0) {
            $value = 0;
        } elseif ($dimensions == 1 && ($template['pp_ext_method'] == 1 || $template['pp_ext_method'] == 2)) {
            $value = 3;
        } else {
            $value = $template['pp_ext_method'];
        }
        $helper->fields_value['pp_ext_method'] = $value;
        $helper->fields_value['pp_ext_method_fallback'] = $value;
        $helper->fields_value['pp_ext_policy'] = isset($template['pp_ext_policy']) ? $template['pp_ext_policy'] : 0;
        $helper->fields_value['pp_qty_policy'] = $helper->fields_value['pp_ext_method'] > 0 && $helper->fields_value['pp_ext_method'] != 98 && $helper->fields_value['pp_ext_policy'] != 4 ? 3 : $template['pp_qty_policy'];
        $helper->fields_value['pp_ext_precision'] = isset($template['pp_ext_precision']) && $template['pp_ext_precision'] != 0 ? $template['pp_ext_precision'] : ''; // precision can be negative
        $helper->fields_value['pp_ext_minimum_quantity'] = isset($template['pp_ext_minimum_quantity']) && $template['pp_ext_minimum_quantity'] > 0 ? PP::presentQty($template['pp_ext_minimum_quantity']) : '';
        $helper->fields_value['pp_ext_maximum_quantity'] = isset($template['pp_ext_maximum_quantity']) && $template['pp_ext_maximum_quantity'] > 0 ? PP::presentQty($template['pp_ext_maximum_quantity']) : '';
        $helper->fields_value['pp_ext_total_maximum_quantity'] = isset($template['pp_ext_total_maximum_quantity']) && $template['pp_ext_total_maximum_quantity'] > 0 ? PP::presentQty($template['pp_ext_total_maximum_quantity']) : '';
        $helper->fields_value['pp_ext_minimum_quantity_text'] = isset($template['pp_ext_minimum_quantity_text']) ? $template['pp_ext_minimum_quantity_text'] : '';
        $helper->fields_value['pp_ext_maximum_quantity_text'] = isset($template['pp_ext_maximum_quantity_text']) ? $template['pp_ext_maximum_quantity_text'] : '';
        $helper->fields_value['pp_ext_total_maximum_quantity_text'] = isset($template['pp_ext_total_maximum_quantity_text']) ? $template['pp_ext_total_maximum_quantity_text'] : '';
        $helper->fields_value['pp_ext_minimum_price_ratio'] = isset($template['pp_ext_minimum_price_ratio']) && $template['pp_ext_minimum_price_ratio'] > 0 ? PP::presentQty($template['pp_ext_minimum_price_ratio']) : '';

        $pp_ext_method_options_query = array(
            array('id' => 0, 'name' => '&nbsp;'),
            array('id' => 1, 'name' => $translations['s_multiplication']),
            array('id' => 2, 'name' => $translations['s_summation']),
            array('id' => 3, 'name' => $translations['s_single_dimension']),
        );
        if ($multidimensional_pro) {
            $options = Hook::exec('adminPproperties', array('mode' => 'pp_ext_method_options_query'), null, true);
            if (isset($options['ppropertiesmultidimensional']) && is_array($options['ppropertiesmultidimensional'])) {
                foreach ($options['ppropertiesmultidimensional'] as $value) {
                    $pp_ext_method_options_query[] = $value;
                }
            }
        } else {
            $pp_ext_method_options_query[] = array('id' => 98, 'name' => $translations['s_disable_calculation']);
            $pp_ext_method_options_query[] = array('id' => 99, 'name' => $translations['s_custom_calculation']);
        }

        $dimensions_form = array(
            'legend'  => array(
                'title' => Tools::ucfirst($translations['dimensions']),
            ),
            'multidimensional-feature' => array(
                'text' => $this->l('this feature is disabled if calculation method is not specified'),
                'disabled' => $this->l('This feature is disabled. To enable this feature please install multidimensional plugin from PS&More store.'),
                'readme_url' => ($this->multidimensional_plugin ? $this->multidimensional_plugin->readmeUrl() : ''),
                'readme_pdf' => ($this->multidimensional_plugin ? PSM::translate('user guide', null, 'ppropertiesmultidimensional') : ''),
            ),
            'input' => array(
                array(
                    'type'  => 'select',
                    'label' => $this->l('calculation method'),
                    'name'  => 'pp_ext_method',
                    'options' => array(
                        'query' => $pp_ext_method_options_query,
                        'id' => 'id', 'name' => 'name'
                    ),
                    'advice' => $this->advice('pp_ext_method'),
                ),
                array(
                    'type'  => 'hidden',
                    'name'  => 'pp_ext_method_fallback',
                ),
                $this->createHelperFormSelect(
                    'pp_ext_title',
                    array(
                        'label' => $this->l('dimensions block title'),
                        'desc'  => $this->l('text displayed as a title of the dimensions block'),
                        'form_group_class' => 'dimensions-toggle bottom-divider',
                    ),
                    self::DIMENSIONS,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                $this->createHelperFormSelect(
                    'pp_ext_property',
                    array(
                        'label' => $this->l('calculation result label'),
                        'desc'  => $this->l('text displayed as a label before the calculation result (leave blank to hide the calculation result)'),
                        'form_group_class' => 'dimensions-toggle',
                    ),
                    self::DIMENSIONS,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                $this->createHelperFormSelect(
                    'pp_ext_text',
                    array(
                        'label' => $this->l('calculation result text'),
                        'desc'  => $this->l('text displayed after the calculation result'),
                        'form_group_class' => 'dimensions-toggle',
                    ),
                    array(self::PROPERTY_TYPE_GENERAL, self::DIMENSIONS),
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                array(
                    'type'  => 'text',
                    'label' => $this->l('calculation result precision'),
                    'name'  => 'pp_ext_precision',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('Choose how you want to display the calculation result'), $translations['leave blank to use default']),
                    'advice' => $this->advice('pp_ext_precision'),
                    'disabled' => ($this->multidimensional_plugin && Tools::version_compare($this->multidimensional_plugin->apiVersion(), '3.10', '>=') ? '' : 'disabled'),
                ),
                $this->createHelperFormSelect(
                    'pp_ext_explanation',
                    array(
                        'label' => $translations['s_pp_explanation'],
                        'desc'  => sprintf('%s<br>%s', $this->l('text displayed below the calculation result'), $this->l('You can use macros to substitute dynamic quantity values.')),
                        'form_group_class' => 'dimensions-toggle',
                    ),
                    self::PROPERTY_TYPE_BLOCK_TEXT,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                array(
                    'type'  => 'text',
                    'label' => $this->l('calculation minimum quantity'),
                    'name'  => 'pp_ext_minimum_quantity',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('minimum quantity for the calculated result'), $translations['leave blank to disable this feature']),
                    'form_group_class' => 'dimensions-toggle top-divider',
                    'advice' => $this->advice('pp_ext_minimum_quantity'),
                ),
                array(
                    'type'  => 'text',
                    'label' => $this->l('calculation maximum quantity'),
                    'name'  => 'pp_ext_maximum_quantity',
                    'class' => 'fixed-width-xl',
                    'desc'  => sprintf('%s (%s)', $this->l('maximum quantity for the calculated result'), $translations['leave blank to disable this feature']),
                    'form_group_class' => 'dimensions-toggle',
                    'advice' => $this->advice('pp_ext_maximum_quantity'),
                ),
                $this->createHelperFormSelect(
                    'pp_ext_minimum_quantity_text',
                    array(
                        'label' => $this->l('calculated minimum quantity text'),
                        'desc'  => $this->l('text displayed when the calculated quantity is less than the specified minimum') .
                            '<br>' .
                            $this->l('You can use macros to substitute dynamic quantity values.'),
                        'form_group_class' => 'dimensions-toggle',
                    ),
                    self::PROPERTY_TYPE_BLOCK_TEXT,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                $this->createHelperFormSelect(
                    'pp_ext_maximum_quantity_text',
                    array(
                        'label' => $this->l('calculated maximum quantity text'),
                        'desc'  => sprintf('%s<br>%s', $this->l('text displayed when the calculated quantity is more than the specified maximum'), $this->l('You can use macros to substitute dynamic quantity values.')),
                        'form_group_class' => 'dimensions-toggle',
                    ),
                    self::PROPERTY_TYPE_BLOCK_TEXT,
                    $helper,
                    $template,
                    $all_properties,
                    $property_types
                ),
                array(
                    'type'  => 'text',
                    'label' => $translations['s_pp_minimum_price_ratio'],
                    'name'  => 'pp_ext_minimum_price_ratio',
                    'class' => 'fixed-width-xl',
                    'desc'  => $this->l('used to calculate minimum price for calculated quantity less than the specified threshold'),
                    'form_group_class' => 'dimensions-toggle top-divider',
                    'advice' => $this->advice('pp_ext_minimum_price_ratio'),
                ),
                array(
                    'type'  => 'radio',
                    'label' => $this->l('dimensions policy'),
                    'name'  => 'pp_ext_policy',
                    'desc'  => $this->l('dimensions can be specified by the customer or used by the packs calculator'),
                    //'desc'  => ('dimensions can be specified by the customer or used by the packs calculator or used as the product properties affecting price, visible in the shop and editable only in the back office'),
                    'form_group_class' => 'pp_ext_policy dimensions-toggle bottom-divider',
                    'values' => array_merge(
                        array(
                            array('id' => 'pp_ext_policy_0', 'value' => 0, 'label' => $translations['default']),
                            array('id' => 'pp_ext_policy_1', 'value' => 1, 'label' => $this->l('packs calculator')),
                            //array('id' => 'pp_ext_policy_2', 'value' => 2, 'label' => ('product properties')),
                        ),
                        $multidimensional_pro ? array(
                            array('id' => 'pp_ext_policy_4', 'value' => 4, 'label' => ('quantity calculator')),
                        ) : array()
                    ),
                    'advice' => $this->advice('pp_ext_policy'),
                ),
                /*
                array(
                    'type'  => 'text',
                    'label' => $this->l('Show in position'),
                    'name'  => 'pp_ext_show_position',
                    'class' => 'pp_ext_show_position',
                    'desc'  => $this->l('specify display position of the dimensions block on the product\'s page'),
                    'hint'  => $this->l('To show position names enable "Display positions" options in the "Settings" tab.'),
                ),
                */
            ),
            'buttons' => $buttons,
        );

        if (!PP::getPpropertiesPlugin()) {
            $this->addToForm(
                $form,
                array(
                    array(
                        'type'  => 'radio',
                        'label' => $translations['display measurement system'],
                        'name'  => 'pp_ms_display',
                        'desc'  => $translations['add a block allowing customers to choose the preferred unit measurement system on the product page'],
                        'form_group_class' => 'pp_ms_display requires_ppropertiesplugin',
                        'values' => array(
                            array('id' => 'pp_ms_display_1', 'value' => 1, 'label' => PSM::translate('visible')),
                            array('id' => 'pp_ms_display_0', 'value' => 0, 'label' => PSM::translate('hidden')),
                        ),
                        'advice_key' => 'pp_ms_display',
                    ),
                ),
                'pp_bo_measurement_system',
                1,
                $helper,
                $template,
                $all_properties,
                $property_types
            );
            $helper->fields_value['pp_ms_display'] = 0;
        }
        if ($multidimensional_pro) {
            $this->addToForm(
                $dimensions_form,
                array(
                    $this->createHelperFormSelect(
                        'pp_ext_total_property',
                        array(
                            'label' => $this->l('total calculation result label'),
                            'desc'  => $this->l('text displayed as a label before the total calculation result (leave blank to hide the total calculation result)'),
                            'form_group_class' => 'dimensions-toggle',
                        ),
                        self::DIMENSIONS,
                        $helper,
                        $template,
                        $all_properties,
                        $property_types
                    ),
                    $this->createHelperFormSelect(
                        'pp_ext_total_text',
                        array(
                            'label' => $this->l('total calculation result text'),
                            'desc'  => $this->l('text displayed after the total calculation result'),
                            'form_group_class' => 'dimensions-toggle',
                        ),
                        array(self::PROPERTY_TYPE_GENERAL, self::DIMENSIONS),
                        $helper,
                        $template,
                        $all_properties,
                        $property_types
                    ),
                ),
                'pp_ext_precision',
                0,
                $helper,
                $template,
                $all_properties,
                $property_types
            );
            $this->addToForm(
                $dimensions_form,
                array(
                    array(
                        'type'  => 'text',
                        'label' => $this->l('calculation total maximum quantity'),
                        'name'  => 'pp_ext_total_maximum_quantity',
                        'class' => 'fixed-width-xl',
                        'desc'  => sprintf('%s (%s)', $this->l('total maximum quantity for the calculated result'), $translations['leave blank to disable this feature']),
                        'form_group_class' => 'dimensions-toggle',
                        'advice' => $this->advice('pp_ext_total_maximum_quantity'),
                    ),
                ),
                'pp_ext_maximum_quantity',
                1,
                $helper,
                $template,
                $all_properties,
                $property_types
            );
            $this->addToForm(
                $dimensions_form,
                array(
                    $this->createHelperFormSelect(
                        'pp_ext_total_maximum_quantity_text',
                        array(
                            'label' => $this->l('calculated total maximum quantity text'),
                            'desc'  => sprintf('%s<br>%s', $this->l('text displayed when the total calculated quantity is more than the specified maximum'), $this->l('You can use macros to substitute dynamic quantity values.')),
                            'form_group_class' => 'dimensions-toggle',
                        ),
                        self::PROPERTY_TYPE_BLOCK_TEXT,
                        $helper,
                        $template,
                        $all_properties,
                        $property_types
                    ),
                ),
                'pp_ext_maximum_quantity_text',
                1,
                $helper,
                $template,
                $all_properties,
                $property_types
            );
            foreach ($dimensions_form['input'] as $key => &$value) {
                if (isset($value['name']) && $value['name'] == 'pp_ext_explanation') {
                    $value['form_group_class'] .= ' top-divider';
                    break;
                }
            }
            unset($key, $value);
        }
        $hook_form_inputs = Hook::exec('adminPproperties', array('mode' => 'createEditTemplateFormInput', 'id_pp_template' => $id, 'template' => $template), null, true);
        if (is_array($hook_form_inputs)) {
            foreach ($hook_form_inputs as $hook_module => $hook_inputs) {
                if (is_array($hook_inputs)) {
                    foreach ($hook_inputs as $hook_input) {
                        if (isset($hook_input['form'])) {
                            $this->addToForm(
                                $form,
                                $hook_input['form'],
                                $hook_input['where']['name'],
                                $hook_input['where']['offset'],
                                $helper,
                                $template,
                                $all_properties,
                                $property_types,
                                $hook_input['values']
                            );
                        }
                        if (isset($hook_input['dimensions_form'])) {
                            $this->addToForm(
                                $dimensions_form,
                                $hook_input['dimensions_form'],
                                $hook_input['where']['name'],
                                $hook_input['where']['offset'],
                                $helper,
                                $template,
                                $all_properties,
                                $property_types,
                                $hook_input['values']
                            );
                        }
                    }
                }
            }
        }

        $th = array(
            $this->l('dimension'),
            $this->l('quantity text'),
            $this->l('minimum quantity'),
            $this->l('maximum quantity'),
            $this->l('default quantity'),
            $this->l('quantity step'),
            $this->l('quantity ratio'),
            $this->l('order quantity text'),
        );
        $dimensions_form['dimensions-table'] = array(
            'th'    => $th,
            'tbody' => array(),
        );

        $max_dimensions = (isset($template['pp_ext_prop']) ? count($template['pp_ext_prop']) : 3);
        if ((!$this->multidimensional_plugin || !isset($this->multidimensional_plugin->pro)) && $max_dimensions < 3) {
            $max_dimensions = 3;
        }
        for ($dimension_index = 1; $dimension_index <= $max_dimensions; $dimension_index++) {
            $td = array();
            $value = PP::getTemplateExtProperty($template, $dimension_index, 'property');
            $td[] = $this->createHelperFormSelect(
                'dimension_' . $dimension_index,
                array('data_type' => 'dimension_', 'data_position' => $dimension_index),
                self::PROPERTY_TYPE_EXT,
                $helper,
                $value,
                $all_properties,
                $property_types
            );

            $value = PP::getTemplateExtProperty($template, $dimension_index, 'text');
            $td[] = $this->createHelperFormSelect(
                'dimension_text_' . $dimension_index,
                array('data_type' => 'dimension_text_', 'data_position' => $dimension_index),
                array(self::PROPERTY_TYPE_GENERAL, self::DIMENSIONS),
                $helper,
                $value,
                $all_properties,
                $property_types
            );

            $td[] = array('type' => 'text', 'name' => 'dimension_minimum_quantity_' . $dimension_index, 'data_type' => 'dimension_minimum_quantity_', 'data_position' => $dimension_index);
            $td[] = array('type' => 'text', 'name' => 'dimension_maximum_quantity_' . $dimension_index, 'data_type' => 'dimension_maximum_quantity_', 'data_position' => $dimension_index);
            $td[] = array('type' => 'text', 'name' => 'dimension_default_quantity_' . $dimension_index, 'data_type' => 'dimension_default_quantity_', 'data_position' => $dimension_index);
            $td[] = array('type' => 'text', 'name' => 'dimension_qty_step_' . $dimension_index, 'data_type' => 'dimension_qty_step_', 'data_position' => $dimension_index);
            $td[] = array('type' => 'text', 'name' => 'dimension_qty_ratio_' . $dimension_index, 'data_type' => 'dimension_qty_ratio_', 'data_position' => $dimension_index);

            $helper->fields_value['dimension_minimum_quantity_' . $dimension_index] = ((float) PP::getTemplateExtProperty($template, $dimension_index, 'minimum_quantity') > 0 ? PP::presentQty(PP::getTemplateExtProperty($template, $dimension_index, 'minimum_quantity')) : '');
            $helper->fields_value['dimension_maximum_quantity_' . $dimension_index] = ((float) PP::getTemplateExtProperty($template, $dimension_index, 'maximum_quantity') > 0 ? PP::presentQty(PP::getTemplateExtProperty($template, $dimension_index, 'maximum_quantity')) : '');
            $helper->fields_value['dimension_default_quantity_' . $dimension_index] = ((float) PP::getTemplateExtProperty($template, $dimension_index, 'default_quantity') > 0 ? PP::presentQty(PP::getTemplateExtProperty($template, $dimension_index, 'default_quantity')) : '');
            $helper->fields_value['dimension_qty_step_' . $dimension_index] = ((float) PP::getTemplateExtProperty($template, $dimension_index, 'qty_step') > 0 ? PP::presentQty(PP::getTemplateExtProperty($template, $dimension_index, 'qty_step')) : '');
            $helper->fields_value['dimension_qty_ratio_' . $dimension_index] = ((float) PP::getTemplateExtProperty($template, $dimension_index, 'qty_ratio') > 0 ? PP::presentQty(PP::getTemplateExtProperty($template, $dimension_index, 'qty_ratio')) : '');
            $value = PP::getTemplateExtProperty($template, $dimension_index, 'order_text');
            $td[] = $this->createHelperFormSelect(
                'dimension_order_text_' . $dimension_index,
                array('data_type' => 'dimension_order_text_', 'data_position' => $dimension_index),
                array(self::PROPERTY_TYPE_GENERAL, self::DIMENSIONS),
                $helper,
                $value,
                $all_properties,
                $property_types
            );

            $tr = array();
            $tr[] = array('td' => $td);
            $tbody = array('tr' => $tr);
            $tbody['dimension_index'] = $dimension_index;
            if (isset($template['pp_ext_prop'])) {
                if (isset($template['pp_ext_prop'][$dimension_index]['id_ext_prop'])) {
                    $tbody['id_ext_prop'] = (int) $template['pp_ext_prop'][$dimension_index]['id_ext_prop'];
                } else {
                    $tbody['id_ext_prop'] = 0;
                    $has_missing_id_ext_prop = true;
                }
            } else {
                $tbody['id_ext_prop'] = $dimension_index;
            }
            $dimensions_form['dimensions-table']['tbody'][] = $tbody;
        }
        if (isset($has_missing_id_ext_prop)) {
            $ids = array();
            foreach ($dimensions_form['dimensions-table']['tbody'] as $tbody) {
                if ($tbody['id_ext_prop'] != 0) {
                    $ids[] = $tbody['id_ext_prop'];
                }
            }
            $missing = array_diff(range(1, max($ids)), $ids);
            foreach ($dimensions_form['dimensions-table']['tbody'] as &$tbody) {
                if ($tbody['id_ext_prop'] == 0) {
                    if ($missing) {
                        $tbody['id_ext_prop'] = array_shift($missing);
                    } else {
                        $tbody['id_ext_prop'] = max($ids) + 1;
                    }
                    $ids[] = $tbody['id_ext_prop'];
                }
            }
        }

        // $dimensions_form['dimensions-table']['help'] = array();
        // $dimensions_form['dimensions-table']['help']['class'] = '';
        // $dimensions_form['dimensions-table']['help']['text'][] = 'some help';

        $forms = array('form' => $form, 'dimensions_form' => $dimensions_form);
        $hook_forms = Hook::exec('adminPproperties', array('mode' => 'displayEditTemplateForm', 'id_pp_template' => $id), null, true);
        if (is_array($hook_forms)) {
            foreach ($hook_forms as $hook_module => $hook_form) {
                if (isset($hook_form['form'])) {
                    if (!isset($hook_form['form']['buttons'])) {
                        $hook_form['form']['buttons'] = $buttons;
                    }
                    $forms[$hook_module . '_form'] = $hook_form['form'];
                }
            }
        }
        foreach ($forms as &$f) {
            foreach ($f['input'] as &$input) {
                if (!isset($input['label_col'])) {
                    $input['label_col'] = 3;
                }
                if (!isset($input['col'])) {
                    $input['col'] = 9;
                }
            }
        }

        $html = $this->generateForm(
            $helper,
            $forms,
            array('id_pp_template' => $id, 'multidimensional' => $this->multidimensional_plugin, 'script' => array('multidimensional'))
        );
        return $html;
    }

    private function getEditPropertyTabHtml($properties, $mode, $title)
    {
        if (!$this->integrated) {
            $helper = $this->createHelperForm('pp_template_form', $title, null, 'icon-edit');
            return $this->generateForm($helper, array());
        }

        $translator = Context::getContext()->getTranslator();
        $translations = $this->translations();
        $type = (int) Tools::getValue('type');
        if ($mode == 'add') {
            $id = 0;
            if ($properties == null) {
                foreach ($this->active_languages as $language) {
                    $property = array();
                    $property['id_pp_property'] = $id;
                    $property['type'] = $type;
                    $property['text'] = '';
                    $property['text_1'] = '';
                    $property['text_2'] = '';
                    $properties[$language['id_lang']][$id] = $property;
                }
            }
        } else {
            $id = PP::getIntNonNegativeValue('id');
            if ($properties == null) {
                $properties = $this->getAllProperties();
            }
        }

        $tinymce = ($type == self::PROPERTY_TYPE_BLOCK_TEXT);
        $helper = $this->createHelperForm('pp_property_form', $title, 'submitSaveProperty', 'icon-edit');
        $form = array(
            'tinymce' => $tinymce,
            'input' => array(
                array(
                    'label' => $translator->trans('ID', array(), 'Admin.Global'),
                    'type'  => 'div',
                    'name'  => $id,
                    'class' => 'control-text',
                    'condition' => ($mode == 'edit'),
                ),
                array(
                    'label' => $translations['metric'],
                    'type'  => ($tinymce ? 'textarea' : 'text'),
                    'name'  => 'text_1_input',
                    'autoload_rte' => $tinymce,
                    'lang'  => true,
                ),
                array(
                    'label' => $translations['non metric (imperial/US)'],
                    'type'  => ($tinymce ? 'textarea' : 'text'),
                    'name'  => 'text_2_input',
                    'autoload_rte' => $tinymce,
                    'lang'  => true,
                ),
                array(
                    'type'  => 'hidden',
                    'name'  => 'id',
                ),
                array(
                    'type'  => 'hidden',
                    'name'  => 'mode',
                ),
                array(
                    'type'  => 'hidden',
                    'name'  => 'type',
                ),
            ),
            'buttons' => array(
                array(
                    'title' => $translator->trans('Cancel', array(), 'Admin.Actions'),
                    'type'  => 'submit',
                    'name'  => 'cancelSaveProperty',
                    'icon'  => 'process-icon-cancel',
                ),
            ),
        );
        $helper->fields_value['id'] = $id;
        $helper->fields_value['mode'] = $mode;
        $helper->fields_value['type'] = $type;
        $helper->fields_value['text_1_input'] = array();
        $helper->fields_value['text_2_input'] = array();

        foreach ($properties[$this->default_language_id] as $id_pp_property => $property) {
            if ($id_pp_property == $id) {
                foreach ($this->active_languages as $language) {
                    $id_lang = $language['id_lang'];
                    $helper->fields_value['text_1_input'][$id_lang] = PP::safeOutputValue($properties[$id_lang][$id]['text_1']);
                    $helper->fields_value['text_2_input'][$id_lang] = PP::safeOutputValue($properties[$id_lang][$id]['text_2']);
                }
                break;
            }
        }
        return $this->generateForm($helper, $form);
    }

    private function addHelperTemplateVars($helper)
    {
        $helper->tpl_vars['access_edit'] = ($this->tabAccess['edit'] === '1');
        $helper->tpl_vars['integrated'] = $this->integrated;
        $helper->tpl_vars['version_mismatch_notes'] = empty($this->version_mismatch_notes) ? null : $this->version_mismatch_notes;
        $helper->tpl_vars['integration_message'] = $this->l('Please go to the "Settings" tab and resolve the integration problems.');
    }

    private function showIntegrationTestResults($results)
    {
        foreach ($results as &$value) {
            if (is_array($value)) {
                asort($value);
            }
        }
        return $results;
    }

    private function createTemplate($name)
    {
        $helper = new Helper();
        $helper->module = $this;
        $helper->base_folder = 'pproperties/';
        $helper->base_tpl = $name . '.tpl';
        $helper->setTpl($helper->base_tpl);

        $token = Tools::getAdminTokenLite('AdminModules');
        $current = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->tpl_vars['_PS_ADMIN_IMG_'] = _PS_ADMIN_IMG_;
        $helper->tpl_vars['current'] = $current;
        $helper->tpl_vars['currenturl'] = $current . '&token=' . $token . '&pp=1&';
        $helper->tpl_vars['token'] = $token;
        $this->addHelperTemplateVars($helper);
        return $helper;
    }

    private function createHelperForm($id_form, $form_title, $submit_action, $icon = null)
    {
        static $first_call = true;
        $helper = new HelperForm();
        $helper->first_call = $first_call;
        $first_call = false;
        $helper->module = $this;
        $helper->title = $this->displayName;
        $helper->name_controller = $this->name;
        $helper->base_tpl = 'pproperties_form.tpl';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->languages = $this->active_languages;
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $this->default_language_id;
        $helper->allow_employee_form_lang = $this->context->controller->allow_employee_form_lang;
        $helper->toolbar_scroll = true;
        $helper->submit_action = '';
        $helper->id_form = $id_form;
        if ($this->integrated) {
            $helper->pp_form = array(
                'legend'  => array(
                    'title' => $form_title,
                ),
                'submit' => array(
                    'title' => Context::getContext()->getTranslator()->trans('Save', array(), 'Admin.Actions'),
                    'id'    => $id_form . '_submit_btn',
                    'name'  => $submit_action,
                    'class' => 'btn btn-default pull-right pp-action-btn' . ($this->tabAccess['edit'] === '1' ? '' : ' disabled')
                ),
            );
            if ($icon !== null) {
                $helper->pp_form['legend']['icon'] = $icon;
            }
            $helper->tpl_vars['s_read_more'] = $this->l('Read more');
            $helper->tpl_vars['s_read_explanation'] = $this->l('Read explanation');
        } else {
            $helper->pp_form = array();
        }
        $this->addHelperTemplateVars($helper);
        return $helper;
    }

    private function createHelperFormSelect($name, $data, $type, $helper, $template, $all_properties, $property_types)
    {
        if ($type !== false && !is_array($type)) {
            $type = array($type);
        }
        $options = array();
        $helper->fields_value[$name] = 0;
        $options[] = array('id' => 0, 'name' => '&nbsp;');
        foreach ($all_properties[$this->default_language_id] as $id => $prop) {
            if ($type === false || in_array($property_types[$id], $type)) {
                $options[] = array(
                    'id'   => $id,
                    'name' => PP::safeOutputValue($prop['text'])
                );
                if (is_array($template)) {
                    if (isset($template[$name]) && $template[$name] == $prop['text']) {
                        $helper->fields_value[$name] = $id;
                    }
                } else {
                    if ($template == $prop['text']) {
                        $helper->fields_value[$name] = $id;
                    }
                }
            }
        };
        $select = array(
            'type' => 'select',
            'name' => $name,
            'options' => array('query' => $options, 'id' => 'id', 'name' => 'name'),
            'advice' => $this->advice($name),
        );
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $select[$key] = $value;
            }
        }
        return $select;
    }

    private function addToForm(&$form, $input, $where, $offset, $helper, $template, $all_properties, $property_types, $values = null)
    {
        foreach ($form['input'] as $key => $value) {
            if (isset($value['name']) && $value['name'] === $where) {
                foreach ($input as &$in) {
                    if (isset($in['create_options'])) {
                        $select = $this->createHelperFormSelect(
                            $in['name'],
                            $in,
                            $in['create_options'],
                            $helper,
                            $template,
                            $all_properties,
                            $property_types
                        );
                        unset($in['create_options']);
                        foreach ($select as $k => $v) {
                            $in[$k] = $v;
                        }
                    }
                    if (isset($in['advice_key'])) {
                        $in['advice'] = $this->advice($in['advice_key']);
                    }
                }
                array_splice($form['input'], $key + $offset, 0, $input);
                break;
            }
        }
        if ($values) {
            foreach ($values as $key => $value) {
                $helper->fields_value[$key] = $value;
            }
        }
    }

    private function generateForm($helper, $form, $tpl_vars = null)
    {
        $vars = array('form' => array());
        if (!isset($form['form'])) {
            $form = array('form' => $form);
        }
        if (!isset($form['form']['id_form']) && isset($helper->id_form)) {
            $form['form']['id_form'] = $helper->id_form;
        }
        foreach ($form as $key => $f) {
            $vars[$key] = array('form' => array_merge($helper->pp_form, $f));
            if (isset($vars[$key]['form']['buttons']) && $vars[$key]['form']['buttons'] === false) {
                unset($vars[$key]['form']['buttons']);
                unset($vars[$key]['form']['submit']);
            }
        }
        if (is_array($tpl_vars)) {
            foreach ($tpl_vars as $key => $value) {
                $vars['form'][$key] = $value;
            }
        }

        $vars['form']['_PS_ADMIN_IMG_'] = _PS_ADMIN_IMG_;
        $vars['form']['access_edit'] = ($this->tabAccess['edit'] === '1');
        static $pp_input_generated = false;
        if (!$pp_input_generated) {
            $pp_input_generated = true;
            $vars['form']['form']['input'][] = array('type' => 'hidden', 'name' => 'pp');
            $helper->fields_value['pp'] = 1;
        }
        return $helper->generateForm($vars);
    }

    private function saveTemplate()
    {
        $result = array();
        $result['error'] = '';
        $mode = Tools::getValue('mode');
        if ($mode === 'add') {
            $id = 0;
        } elseif ($mode === 'copy') {
            $id = PP::getIntNonNegativeValue('id');
        } else {
            $mode = 'edit';
            $id = PP::getIntNonNegativeValue('id');
        }
        if ($id < 0) {
            return $result;
        }

        $errors = array();
        $templates = array();

        $template_properties = array();
        $template_properties['pp_explanation'] = PP::getIntNonNegativeValue('pp_explanation');
        $template_properties['pp_price_text'] = PP::getIntNonNegativeValue('pp_price_text');
        $template_properties['pp_qty_text'] = PP::getIntNonNegativeValue('pp_qty_text');
        $template_properties['pp_unity_text'] = PP::getIntNonNegativeValue('pp_unity_text');

        $translations = $this->translations();
        $price_display_mode = PP::getIntNonNegativeValue('pp_price_display_mode');
        if (!in_array($price_display_mode, array(0, 1, 16))) {
            $price_display_mode = 0;
        }
        $pp_bo_qty_available_display = PP::getIntNonNegativeValue('pp_bo_qty_available_display');
        if (!in_array($pp_bo_qty_available_display, array(0, 1, 2))) {
            $pp_bo_qty_available_display = 0;
        }
        $measurement_system = PP::getIntNonNegativeValue('pp_bo_measurement_system');
        $unit_price_ratio = PP::getFloatNonNegativeValue('unit_price_ratio');
        $minimum_price_ratio = PP::getFloatNonNegativeValue('minimum_price_ratio');

        $qty_policy = PP::getIntNonNegativeValue('pp_qty_policy');
        $qty_policy = ($qty_policy == 3 ? 2 : $qty_policy);
        $ext_method = PP::getIntNonNegativeValue($this->multidimensional_plugin ? 'pp_ext_method' : 'pp_ext_method_fallback');
        if ($ext_method < 0) {
            $ext_method = 0;
        }
        if ($ext_method == 3) { // multidimensional single dimension
            $ext_method = 2; // multidimensional summation
        }
        $ext_policy = 0;
        if ($ext_method > 0 && $ext_method != 98) { // 98 - multidimensional disable calculation
            $ext_policy = ($ext_method != 98 ? PP::getIntNonNegativeValue('pp_ext_policy') : 0);
            if (!in_array($ext_policy, [0, 1, 4])) {
                $ext_policy = 0;
            }
            // ($ext_policy == 1) packs calculator; ($ext_policy == 4) multidimensional quantity calculator
            $qty_policy = ($ext_policy == 1 ? 0 : ($ext_policy == 4 ? $qty_policy : 2));
        }
        $qty_mode = (PP::getIntNonNegativeValue('pp_qty_mode') != 0 ? 1 : 0);
        foreach ($translations['s_pp_qty_mode_options'] as $i) {
            if (PP::getIntNonNegativeValue('pp_qty_mode_' . $i) > 0) {
                $qty_mode += $i;
            }
        }
        if ($qty_policy == 0) {
            $qty_mode &= ~1;
            $qty_mode &= ~2;
        }
        // (ext_method == 98) multidimensional disable calculation; ($ext_policy == 4) multidimensional quantity calculator
        if ($ext_method > 0 && $ext_method != 98 && $ext_policy != 4) {
            $qty_mode &= ~2;
        }
        $display_mode = (PP::getIntNonNegativeValue('pp_display_mode') != 0 ? 1 : 0);
        foreach ($translations['s_pp_display_mode_options'] as $i) {
            if (PP::getIntNonNegativeValue('pp_display_mode_' . $i) > 0) {
                $display_mode += $i;
            }
        }
        // (ext_method == 98) multidimensional disable calculation; ($ext_policy == 4) multidimensional quantity calculator
        if ($qty_policy == 2 && (in_array($ext_method, array(0, 98)) || $ext_policy == 4)) {
            $minimum_quantity       = PP::getFloatNonNegativeValue('minimum_quantity');
            $maximum_quantity       = PP::getFloatNonNegativeValue('maximum_quantity');
            $total_maximum_quantity = PP::getFloatNonNegativeValue('total_maximum_quantity');
            $default_quantity       = PP::getFloatNonNegativeValue('default_quantity');
            $qty_step               = PP::getFloatNonNegativeValue('qty_step');
            $qty_shift              = PP::getFloatNonNegativeValue('qty_shift');
            $qty_decimals           = PP::getIntNonNegativeValue('qty_decimals');
            $qty_ratio              = PP::getFloatNonNegativeValue('qty_ratio');
        } else {
            $minimum_quantity       = PP::getIntNonNegativeValue('minimum_quantity');
            $maximum_quantity       = PP::getIntNonNegativeValue('maximum_quantity');
            $total_maximum_quantity = PP::getIntNonNegativeValue('total_maximum_quantity');
            $default_quantity       = PP::getIntNonNegativeValue('default_quantity');
            $qty_step               = PP::getIntNonNegativeValue('qty_step');
            $qty_shift              = PP::getIntNonNegativeValue('qty_shift');
            $qty_decimals           = 0;
            $qty_ratio              = PP::getIntNonNegativeValue('qty_ratio');
        }

        $specific_values = PP::getSpecificValues('qty_values', $qty_policy);
        $qty_values = implode('|', $specific_values);
        PP::resolveQuantities(
            $minimum_quantity,
            $maximum_quantity,
            $default_quantity,
            $qty_step,
            $qty_policy,
            array('total_maximum_quantity' => &$total_maximum_quantity, 'specific_values' => $specific_values, 'db' => true)
        );
        if ($qty_step > 0) {
            $qty_shift = 0;
        }
        if ($specific_values) {
            if ($qty_policy == 0) {
                $qty_policy = 1;
            }
            $qty_shift = 0;
        }
        $hidden = (PP::getIntNonNegativeValue('pp_bo_hidden') == 1 ? 1 : 0);
        $customization = (PP::getIntNonNegativeValue('pp_customization') == 1 ? 1 : 0);
        $ms = PP::resolveMeasurementSystem($measurement_system);
        $css = Tools::getValue('pp_css');
        foreach ($this->active_languages as $language) {
            $data = array();
            $data['id_pp_template']             = $id;
            $data['qty_policy']                 = $qty_policy;
            $data['qty_mode']                   = $qty_mode;
            $data['display_mode']               = $display_mode;
            $data['price_display_mode']         = $price_display_mode;
            $data['measurement_system']         = $measurement_system;
            $data['unit_price_ratio']           = $unit_price_ratio;
            $data['minimal_price_ratio']        = $minimum_price_ratio;
            $data['minimal_quantity']           = $minimum_quantity;
            $data['maximum_quantity']           = $maximum_quantity;
            $data['total_maximum_quantity']     = $total_maximum_quantity;
            $data['default_quantity']           = $default_quantity;
            $data['qty_step']                   = $qty_step;
            $data['qty_shift']                  = $qty_shift;
            $data['qty_decimals']               = $qty_decimals;
            $data['qty_values']                 = $qty_values;
            $data['qty_ratio']                  = $qty_ratio;
            $data['ext']                        = ($ext_method > 0 ? 1 : 0);
            $data['qty_available_display']      = $pp_bo_qty_available_display;
            $data['css']                        = $css;
            $data['hidden']                     = $hidden;
            $data['customization']              = $customization;
            $data['template_properties']        = $template_properties;

            $template = array();
            PP::calcProductProperties($template, $data);
            // correct pp_ext, that reset in PP::calcProductProperties
            if ($ext_method > 0) {
                $template['pp_ext'] = ($ext_method == 98 ? 2 : 1); // 98 multidimensional disable calculations
            } else {
                $template['pp_ext'] = 0;
            }
            $id_lang = $language['id_lang'];
            $this->getValue($template, 'name', $translations['name'], $errors, $id_lang);
            $template['description'] = Tools::getValue('description_input_' . $id_lang);
            $templates[$id_lang][$id] = $template;
        }

        if (count($errors) == 0) {
            $db = Db::getInstance();
            if ($mode === 'edit') {
                $id_pp_template = $id;
            } else {
                $id_pp_template = $this->getNextId($db, 'pp_template', 'id_pp_template');
                $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'pp_template` (id_pp_template, version) VALUE (' . $id_pp_template . ', 0)');
                foreach ($this->active_languages as $language) {
                    $id_lang = $language['id_lang'];
                    $templates[$id_lang][$id]['id_pp_template'] = $id_pp_template;
                }
            }
            $db->update(
                'pp_template',
                array(
                    'version'                    => PP::PP_TEMPLATE_VERSION,
                    'qty_policy'                 => $template['pp_qty_policy'],
                    'qty_mode'                   => $template['pp_qty_mode'],
                    'display_mode'               => $template['pp_display_mode'],
                    'price_display_mode'         => $template['pp_price_display_mode'],
                    'measurement_system'         => $template['pp_bo_measurement_system'],
                    'unit_price_ratio'           => $template['pp_unit_price_ratio'],
                    'minimal_price_ratio'        => $template['pp_minimum_price_ratio'],
                    'minimal_quantity'           => $template['db_minimum_quantity'],
                    'maximum_quantity'           => $template['db_maximum_quantity'],
                    'total_maximum_quantity'     => $template['db_total_maximum_quantity'],
                    'default_quantity'           => $template['db_default_quantity'],
                    'qty_step'                   => $template['db_qty_step'],
                    'qty_shift'                  => $template['db_qty_shift'],
                    'qty_decimals'               => $template['db_qty_decimals'],
                    'qty_values'                 => $template['db_qty_values'],
                    'qty_ratio'                  => $template['db_qty_ratio'],
                    'ext'                        => $template['pp_ext'],
                    'qty_available_display'      => $template['pp_bo_qty_available_display'],
                    'hidden'                     => $template['pp_bo_hidden'],
                    'customization'              => $template['pp_customization'],
                    'css'                        => $template['pp_css'],
                ),
                'id_pp_template = ' . $id_pp_template
            );
            $db->delete('pp_template_property', 'id_pp_template = ' . $id_pp_template);
            foreach ($template_properties as $key => $value) {
                $template_properties[$key] = "({$id_pp_template},'{$key}',{$value})";
            }
            $db->execute('INSERT INTO ' . _DB_PREFIX_ . 'pp_template_property (id_pp_template,pp_name,id_pp_property) VALUES ' . implode(',', $template_properties));

            foreach ($this->active_languages as $language) {
                $id_lang = $language['id_lang'];
                $template = $templates[$id_lang][$id];
                $r = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pp_template_lang` WHERE id_pp_template = ' . (int) $id_pp_template . ' AND id_lang=' . (int) $id_lang);
                if ($r === false) {
                    $r = array('description_1' => '', 'description_2' => '', 'id_pp_template' => $id_pp_template, 'id_lang' => $id_lang);
                }
                $auto_desc = ($template['description'] === '' ? 1 : 0);
                $r[$ms != 2 ? 'description_1' : 'description_2'] = pSQL($template['description'], true);
                $r[$ms != 2 ? 'auto_desc_1' : 'auto_desc_2'] = $auto_desc;
                $r['name'] = pSQL($template['name'], true);
                $db->delete('pp_template_lang', 'id_pp_template = ' . $id_pp_template . ' AND id_lang=' . $id_lang);
                $db->insert('pp_template_lang', $r);
            }

            if ($this->multidimensional_plugin) {
                $this->multidimensional_plugin->saveTemplate($id_pp_template, $ext_method, $ext_policy);
            }
            Hook::exec('adminPproperties', array('mode' => 'actionTemplateSave', 'id_pp_template' => $id_pp_template));
            $templates = null;
            PP::resetTemplates();
        } else {
            $result['error'] .= $this->l('Please fix the following errors:');
            foreach ($errors as $error) {
                $result['error'] .= PP::div($error);
            }
        }
        $result['templates'] = $templates;
        return $result;
    }

    private function saveProperty()
    {
        $result = array();
        $result['error'] = '';
        $mode = Tools::getValue('mode');
        $type = Tools::getValue('type');
        if ($mode === 'add') {
            $id = 0;
        } else {
            $mode = 'edit';
            $id = PP::getIntNonNegativeValue('id');
        }
        if ($id < 0) {
            return $result;
        }

        $type = Tools::getValue('type');
        $errors = array();
        $properties = array();

        $translations = $this->translations();
        foreach ($this->active_languages as $language) {
            $property = array();
            $id_lang = $language['id_lang'];
            $this->getValue($property, 'text_1', $translations['metric'], $errors, $id_lang, 'text_2');
            $this->getValue($property, 'text_2', $translations['non metric'], $errors, $id_lang, 'text_1');
            $properties[$id_lang][$id] = $property;
        }

        if (count($errors) == 0) {
            $db = Db::getInstance();
            if ($mode === 'edit') {
                $id_pp_property = $id;
            } else {
                $id_pp_property = $this->getNextId($db, 'pp_property', 'id_pp_property');
                $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'pp_property` (id_pp_property, type) VALUE (' . $id_pp_property . ', ' . $type . ')');
                foreach ($this->active_languages as $language) {
                    $properties[$language['id_lang']][$id]['id_pp_property'] = $id_pp_property;
                }
            }
            foreach ($this->active_languages as $language) {
                $id_lang = $language['id_lang'];
                $r = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pp_property_lang` WHERE id_pp_property = ' . (int) $id_pp_property . ' AND id_lang=' . (int) $id_lang);
                if ($r === false) {
                    $r = array('text_1' => '', 'text_2' => '', 'id_pp_property' => $id_pp_property, 'id_lang' => $id_lang);
                }
                $property = $properties[$id_lang][$id];
                $text_1 = pSQL($property['text_1'], true);
                $text_2 = pSQL($property['text_2'], true);
                if (empty($text_1)) {
                    $text_1 = $text_2;
                }
                if (empty($text_2)) {
                    $text_2 = $text_1;
                }
                if (!empty($text_1) && !empty($text_2)) {
                    $r['text_1'] = $text_1;
                    $r['text_2'] = $text_2;
                    $db->delete('pp_property_lang', 'id_pp_property = ' . $id_pp_property . ' AND id_lang=' . $id_lang);
                    $db->insert('pp_property_lang', $r);
                }
            }
        } else {
            $result['error'] .= $this->l('Please fix the following errors:');
            foreach ($errors as $error) {
                $result['error'] .= PP::div($error);
            }
        }
        $result['properties'] = $properties;
        return $result;
    }

    private function deleteTemplate()
    {
        $id = PP::getIntNonNegativeValue('id');
        if ($id <= 0) {
            return;
        }

        $db = Db::getInstance();
        $db->delete('pp_template', 'id_pp_template = ' . $id);
        $db->delete('pp_template_lang', 'id_pp_template = ' . $id);
        $db->delete('pp_template_property', 'id_pp_template = ' . $id);
        $db->delete('pp_template_ext', 'id_pp_template = ' . $id);
        $db->delete('pp_template_ext_prop', 'id_pp_template = ' . $id);
        Hook::exec('adminPproperties', array('mode' => 'actionTemplateDelete', 'id_pp_template' => $id));
    }

    private function deleteProperty()
    {
        $id = PP::getIntNonNegativeValue('id');
        if ($id > 0) {
            $db = Db::getInstance();
            $db->delete('pp_property', 'id_pp_property = ' . $id);
            $db->delete('pp_property_lang', 'id_pp_property = ' . $id);
            $db->delete('pp_template_property', 'id_pp_property = ' . $id);
        }
    }

    private function changeHiddenStatus()
    {
        $id = PP::getIntNonNegativeValue('id');
        if ($id <= 0) {
            return;
        }

        Db::getInstance()->update(
            'pp_template',
            array('hidden' => ((int) Tools::getValue('show', 1) ? '0' : '1')),
            '`id_pp_template` = ' . $id
        );
    }

    public function generateDescription($template)
    {
        $desc = '';
        if (PP::isMultidimensional($template)) {
            $desc .= $template['pp_ext_policy'] == 1 ? $this->l('Product uses packs calculator feature') : ($template['pp_ext_policy'] == 4 ? $this->l('Product uses quantity calculator feature') : $this->l('Product uses multidimensional feature'));
            if ($template['pp_ext_policy'] == 4) {
                if ($template['pp_qty_policy'] == 1) {
                    $desc .= ', ' . $this->l('whole units');
                } elseif ($template['pp_qty_policy'] == 2) {
                    $desc .= ', ' . $this->l('fractional units');
                }
            }
        } else {
            if ($template['pp_qty_policy'] == 1) {
                $desc .= $this->l('Product sold in whole units');
            } elseif ($template['pp_qty_policy'] == 2) {
                $desc .= $this->l('Product sold in fractional units');
            } else {
                $desc .= $this->l('Product sold in items');
            }
        }
        if (PP::qtyModeApproximateQuantity($template)) {
            $desc .= ', ' . $this->l('approximate quantity and price (the exact quantity cannot be ordered)');
        }
        if (PP::qtyModeAggregate($template)) {
            $desc .= ', ' . $this->l('aggregate quantities');
        }
        if (($template['pp_display_mode'] & 1) == 1) {
            $desc .= ', ' . $this->l('reversed price display');
        }
        if (($template['pp_display_mode'] & 2) == 2) {
            $desc .= ', ' . $this->l('retail price');
        }
        return $desc;
    }

    private function getValue(&$template, $key, $name, &$errors, $id_lang, $alt_key = null)
    {
        $default_value = Tools::getValue($key . '_input_' . $this->default_language_id);
        if (empty($default_value) && $alt_key) {
            $default_value = Tools::getValue($alt_key . '_input_' . $this->default_language_id);
        }
        if (!empty($default_value)) {
            $default_value = PP::amendTinymceText($default_value);
        } else {
            $default_language = Language::getLanguage($this->default_language_id);
            $errors[$key] = sprintf($this->l('%s cannot be empty in %s'), $name, $default_language['name']);
        }
        $value = Tools::getValue($key . '_input_' . $id_lang);
        if (empty($value) && $alt_key) {
            $value = Tools::getValue($alt_key . '_input_' . $id_lang);
        }
        if (!empty($value)) {
            $value = PP::amendTinymceText($value);
            if (!Validate::isCleanHtml($value)) {
                $language = Language::getLanguage($id_lang);
                $errors[$key] = sprintf('Invalid %s for %s language', $name, $language['name']);
            }
        }
        $template[$key] = $value;
    }

    private function getPropertyTypes()
    {
        $result = array();
        $rows = Db::getInstance()->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'pp_property`');
        foreach ($rows as $row) {
            $result[$row['id_pp_property']] = $row['type'];
        }
        return $result;
    }

    private function compareProperties($str1, $str2)
    {
        return strnatcasecmp(strip_tags($str1['text']), strip_tags($str2['text']));
    }

    private function getAllProperties($ms = false)
    {
        $ms = PP::resolveMeasurementSystem($ms);
        $all_properties = array();
        $db = Db::getInstance();
        $rows = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pp_property_lang`');
        $pp_property = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pp_property`');
        foreach ($this->active_languages as $language) {
            $id_lang = $language['id_lang'];
            $properties = array();
            foreach ($pp_property as $property) {
                $id_pp_property = $property['id_pp_property'];
                $property['text'] = '';
                $property['text_1'] = '';
                $property['text_2'] = '';
                $found = $this->getAllPropertiesLang($property, $rows, $id_pp_property, $id_lang, $ms);
                if (!$found) {
                    $this->getAllPropertiesLang($property, $rows, $id_pp_property, (int) Configuration::get('PS_LANG_DEFAULT'), $ms);
                }
                if (empty($property['text'])) {
                    $this->getAllPropertiesLang($property, $rows, $id_pp_property, 0, $ms);
                }
                $properties[$id_pp_property] = $property;
            }
            $all_properties[$id_lang] = $properties;
        }
        return $all_properties;
    }

    private function getAllPropertiesLang(&$property, $rows, $id_pp_property, $id_lang, $ms = false)
    {
        foreach ($rows as $row) {
            if (($row['id_pp_property'] == $id_pp_property)) {
                $text = ($ms != 2 ? $row['text_1'] : $row['text_2']);
                if (($id_lang != 0 && $row['id_lang'] == $id_lang) || ($id_lang == 0 && !empty($text))) {
                    $property['text'] = $text;
                    $property['text_1'] = $row['text_1'];
                    $property['text_2'] = $row['text_2'];
                    return true;
                }
            }
        }
        return false;
    }

    public function integrationKey()
    {
        return _PS_VERSION_ . '|' . $this->integrationVersion();
    }

    public function integrationVersion()
    {
        return $this->version;
    }

    public function setupInstance()
    {
        return PSMHelper::ppSetupInstance($this);
    }

    public function plugins()
    {
        return array(
            'ppropertiesplugin' => array(['3.9'], null),
            'ppropertiesmultidimensional' => array(['3.9', '3.10'], null),
            'ppropertiessmartprice' => array(['3.11'], null),
        );
    }

    public function registerModuleMedia($params)
    {
        if (is_array($params) && Module::isInstalled($this->name)) {
            $media = Configuration::getGlobalValue('PP_MODULE_MEDIA');
            $media = (is_string($media) ? json_decode($media, true) : array());
            foreach ($params as $name => $files) {
                $media[$name] = $files;
            }
            Configuration::updateGlobalValue('PP_MODULE_MEDIA', json_encode($media));
        }
    }

    public function unregisterModuleMedia($params)
    {
        if (Validate::isLoadedObject($params)) {
            $params = array($params->name);
        }
        if (is_array($params)) {
            $media = Configuration::getGlobalValue('PP_MODULE_MEDIA');
            if (is_string($media)) {
                $media = json_decode($media, true);
                foreach ($params as $name) {
                    if (array_key_exists($name, $media)) {
                        unset($media[$name]);
                    }
                }
                if (count($media)) {
                    Configuration::updateGlobalValue('PP_MODULE_MEDIA', json_encode($media));
                } else {
                    Configuration::deleteByName('PP_MODULE_MEDIA');
                }
            }
        }
    }

    public function advice($key, $content = false)
    {
        static $advices = null;
        if ($advices === null) {
            $advices = PSM::loadAdvices(dirname(__FILE__));
            $hook = Hook::exec('adminPproperties', array('mode' => 'help.inc'), null, true);
            if (is_array($hook)) {
                foreach ($hook as $_ => $help) {
                    if (is_array($help)) {
                        $advices = array_merge($advices, $help);
                    }
                }
            }
        }
        if (array_key_exists($key, $advices)) {
            if ($content === false) {
                return array(
                    'type' => 'label',
                    'key' => $key,
                );
            } else {
                return $advices[$key];
            }
        }
        return false;
    }

    private function createHeaderJsScript($bo = false)
    {
        $cfg = (string) Configuration::getGlobalValue('PP_CFG');
        $this->smarty->assign(
            [
                'helper_key' => 'createHeaderJsScript',
                'psversion' => _PS_VERSION_,
                'version' => PSM::moduleVersion($this),
                'theme' => str_replace(array('.', ' '), '_', defined('_PARENT_THEME_NAME_') && _PARENT_THEME_NAME_ ? _PARENT_THEME_NAME_ : _THEME_NAME_),
                'controller' => Tools::strtolower($this->context->controller->php_self),
                'module' => Tools::getValue('module'),
                'debug' => (int) Configuration::getGlobalValue('PP_DEBUG'),
                'cfg' => empty($cfg) ? '{}' : $cfg,
            ]
        );
        if ($this->integrated) {
            $this->smarty->assign(
                [
                    'decimalSign' => PP::getDecimalSign(),
                ]
            );
        }
        if ($bo === false && (int) Configuration::get('PP_POWEREDBY')) {
            $this->smarty->assign(
                [
                    'psandmore_url' => PSM::authorUrl(),
                    'powered_by_psandmore_text' => sprintf($this->l('Powered by %s'), 'PS&amp;More&trade;'),
                    'powered_by_psandmore_title' => sprintf($this->l('This site is using Product Properties Extension powered by %s'), 'PS&amp;More&trade;'),
                ]
            );
        } elseif ($bo === true && PSM::isDemo()) {
            $this->smarty->assign(
                [
                    'demo' => true,
                ]
            );
        }
        return $this->fetch('module:pproperties/views/templates/admin/helper.tpl');
    }

    private function getNextId($db, $table, $column)
    {
        $max_id = (int) $db->getValue('SELECT max(`' . $column . '`) FROM `' . _DB_PREFIX_ . $table . '`');
        if ($max_id < self::USER_START_ID) {
            return self::USER_START_ID;
        }
        return ++$max_id;
    }

    private function compatibilityText()
    {
        if ($this->pp_versions_compliancy['min'] == $this->pp_versions_compliancy['max']) {
            return sprintf($this->l('This version of %s module works only with PrestaShop version %s.'), $this->displayName, $this->pp_versions_compliancy['min']);
        } else {
            return sprintf($this->l('This version of %s module works only with PrestaShop versions %s - %s.'), $this->displayName, $this->pp_versions_compliancy['min'], $this->pp_versions_compliancy['max']);
        }
    }

    private function checkIntegration($setup)
    {
        Hook::exec('adminPproperties', ['mode' => 'checkIntegrationBefore', 'integrated' => $this->integrated]);
        $setup->sanityCheck();
        $setup->checkIntegration();
        if (!$this->integrated) {
            $setup->runSetup();
        }
        Hook::exec('adminPproperties', ['mode' => 'checkIntegrationAfter', 'integrated' => $this->integrated]);
    }

    private function psmIntegrate()
    {
        if (!defined('PSM_DIR') && version_compare(phpversion(), self::PHPVERSION, '>=')) {
            if (is_file(dirname(__FILE__) . '/psm.config.inc')) {
                include_once(dirname(__FILE__) . '/psm.config.inc');
                $str =
                    'if (is_file(_PS_MODULE_DIR_.\'' . basename(dirname(__FILE__)) . '/psm.config.inc' . '\')) {' .
                    'include_once(_PS_MODULE_DIR_.\'' . basename(dirname(__FILE__)) . '/psm.config.inc' . '\');' .
                    '}';
                $add = false;
                if (is_file(_PS_CUSTOM_CONFIG_FILE_)) {
                    $content = php_strip_whitespace(_PS_CUSTOM_CONFIG_FILE_);
                    if (empty($content) || strpos($content, $str) === false) {
                        $str = "\n" . $str;
                        $add = true;
                    }
                } else {
                    $str = "<?php\n" . $str;
                    $add = true;
                }
                if ($add) {
                    file_put_contents(_PS_CUSTOM_CONFIG_FILE_, $str . "\n", FILE_APPEND);
                    if (function_exists('opcache_invalidate')) {
                        opcache_invalidate(_PS_CUSTOM_CONFIG_FILE_);
                    }
                }
            }
        }
    }
}
