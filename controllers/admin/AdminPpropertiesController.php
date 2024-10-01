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
class AdminPpropertiesController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules'));
        }
        if ($this->ajax) {
            $this->content_only = true;
        }
    }

    public function postProcess()
    {
        if ($this->ajax) {
            return parent::postProcess();
        }
        $this->redirect_after = $this->context->link->getAdminLink(
            'AdminModules',
            true,
            null,
            array('module_name' => $this->module->name, 'tab_module' => $this->module->tab, 'configure' => $this->module->name)
        );
    }

    public function ajaxProcessBulkManageTemplates()
    {
        if ($this->access('edit') && $this->module->integrated) {
            $bulk_action_selected_products = Tools::getValue('bulk_action_selected_products');
            if (is_array($bulk_action_selected_products)  && count($bulk_action_selected_products) < 1) {
                throw new Exception('AdminPpropertiesController->ajaxProcessBulkAssignTemplate() should always receive at least one ID. Zero given.', 5003);
            }
            $id_pp_template = (int) Tools::getValue('id_pp_template');
            if ($id_pp_template <= 0) {
                throw new Exception('AdminPpropertiesController->ajaxProcessBulkAssignTemplate() template ID should always be greater than zero.', 5003);
            }
            $op = Tools::getValue('op');
            if ($op == 'assign' || $op == 'remove') {
                $sql = 'UPDATE `' . _DB_PREFIX_ . 'product` SET `id_pp_template` = ' . ($op == 'assign' ? $id_pp_template : 0) .
                    ' WHERE `id_product` in (' . implode(',', $bulk_action_selected_products) . ') and ' .
                    ($op == 'assign' ? '(`id_pp_template` = 0 or `id_pp_template` is NULL)' : '`id_pp_template` = ' . $id_pp_template);
                DB::getInstance()->execute($sql);
                die(json_encode('success'));
            }
        }
        die();
    }

    public function ajaxProcessCallPlugin()
    {
        if ($name = Tools::getValue('plugin')) {
            $plugin = PSM::getPlugin($name);
            if ($plugin && method_exists($plugin, 'ajaxProcessCallPlugin')) {
                $plugin->ajaxProcessCallPlugin();
            }
        }
        die(array('status' => 'error'));
    }

    public function ajaxProcessIntegrationModuleCheckForUpdates()
    {
        $status = 'error';
        if ($json = Tools::getValue('json')) {
            $request = json_decode($json, true);
            $status = $this->module->setupInstance()->downloadExtraModule($request['module'], $request['ver'], true);
        }
        $json = array('status' => $status);
        $this->content = json_encode($json);
        die($this->content);
    }

    public function ajaxProcessIntegrationModuleIgnore()
    {
        $this->content = $this->module->setupInstance()->processIntegrationModule(1);
        die($this->content);
    }

    public function ajaxProcessIntegrationModuleIntegrate()
    {
        $this->content = $this->module->setupInstance()->processIntegrationModule(0);
        die($this->content);
    }

    public function ajaxProcessInfoQuery()
    {
        $result = array('status' => false);
        if (time() > (int) Configuration::get('PP_INFO_CHECK_TIME')) {
            $old_content = $this->getInfo();
            $msg = ($old_content === false ? 0 : $old_content[0]);
            $params = array_merge(
                array(
                    'key' => $this->module->name,
                    'ver' => PSM::moduleVersion($this->module),
                    'msg' => $msg,
                    'iso_country' => Tools::strtoupper(Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'))),
                    'iso_lang' => Context::getContext()->language->iso_code,
                ),
                PSM::psmInfo($this->module->name),
                $this->processInfoQueryExtraParams()
            );
            $content = PSM::getContent('', $params);
            $check_info_offset = 3600;
            if ($content !== false) {
                $content = explode('|', $content);
                if (is_numeric($content[0])) {
                    if (!$this->infoIgnore(false, $content[0])) {
                        if (Validate::isCleanHtml($content[1])) {
                            $this->putInfo($content);
                            $check_info_offset = 86400;
                        }
                    }
                } else {
                    if ($content[0] == 'hide') {
                        Configuration::deleteByName('PP_INFO_CONTENT');
                    }
                }
            }
            Configuration::updateValue('PP_INFO_CHECK_TIME', time() + $check_info_offset);
        }

        $content = $this->getInfo();
        if ($content !== false) {
            if (!$this->infoIgnore($content)) {
                if (Validate::isCleanHtml($content[1])) {
                    $result['status'] = 'success';
                    $result['content'] = $content[1];
                }
            }
        }
        $this->content = json_encode($result);
        die($this->content);
    }

    public function ajaxProcessInfoIgnore()
    {
        $content = $this->getInfo();
        if ($content !== false) {
            $this->putInfo($content[0] . '|ignore');
        }
    }

    public function ajaxProcessGetHelp()
    {
        $result = array('status' => false);
        $ref = Tools::getValue('ref');
        if ($ref) {
            $result['status'] = 'success';
            $result['content'] = 'Information not found';
            $content = $this->module->advice($ref, true);
            if ($content !== false) {
                $result['content'] = PP::wrap($content, false, 'section');
            }
        }
        $this->content = json_encode($result);
        die($this->content);
    }

    private function infoIgnore($content = false, $id = 0)
    {
        if ($content === false) {
            $content = $this->getInfo();
        }
        if ($content !== false) {
            if ($content[1] == 'ignore') {
                if ((int) $id > 0) {
                    return ((int) $content[0] == (int) $id);
                }
                return true;
            }
        }
        return false;
    }

    private function putInfo($str)
    {
        Configuration::updateGlobalValue('PP_INFO_CONTENT', bin2hex($str));
    }

    private function getInfo()
    {
        $contents = Configuration::getGlobalValue('PP_INFO_CONTENT');
        if ($contents !== false) {
            $content = explode('|', hex2bin($contents));
            if (is_numeric($content[0])) {
                return $content;
            }
        }
        return false;
    }

    private function moduleVersion($name)
    {
        return Db::getInstance()->getValue('SELECT version FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = \'' . pSQL($name) . '\'');
    }

    private function processInfoQueryExtraParams()
    {
        $params = array();
        $plugins = $this->module->plugins();
        foreach ($plugins as $name => $_) {
            if (Module::isInstalled($name)) {
                $params[$name] = $this->moduleVersion($name);
            }
        }
        $shop_url = ShopUrl::getShopUrls($this->context->shop->id)->where('main', '=', 1)->getFirst();
        $params['shop'] = ($shop_url ? $shop_url->getURL() : Tools::getShopDomain());
        $params['email'] = Configuration::get('PS_SHOP_EMAIL');
        $params['ps_ver'] = _PS_VERSION_;
        $params['theme'] = (defined('_PARENT_THEME_NAME_') && _PARENT_THEME_NAME_ ? _PARENT_THEME_NAME_ : _THEME_NAME_);
        return $params;
    }
}
