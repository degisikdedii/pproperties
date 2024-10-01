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
class PPSetup extends PSMSetup30
{
    const VERSION_SIGNATURE_START = 'PP_VERSION[';
    const VERSION_REQUIRED_SIGNATURE_START = 'PP_VERSION_REQUIRED[';
    const VERSION_SIGNATURE_END    = ']';
    const VERSION_INDEX            = 0;
    const VERSION_REQUIRED_INDEX   = 1;
    const VERSION_CHECK_INDEX      = 0;
    const VERSION_CHECK_DESC_INDEX = 1;
    const EXTRA_MODULE     = 'module';
    const EXTRA_VER        = 'version';
    const EXTRA_ENABLED    = 'enabled';
    const EXTRA_INTEGRATED = 'integrated';
    const EXTRA_IGNORE     = 'ignore';
    const EXTRA_PERMISSION = 'permission';

    protected $install_mode;
    protected $extra;
    protected $check_integration_processed;
    private $module_mutating;
    private static $s_cache = array();
    private static $version_integrity = true;
    private static $version_mismatch_notes = array();
    protected static $ppsetup_custom = array();
    protected static $ppsetup_custom_config = null;

    public function install()
    {
        @set_time_limit(300);
        $this->install_mode = true;
        $this->setupAndIntegration();
    }

    public function uninstall()
    {
        @set_time_limit(300);
        $this->setup();
    }

    public function runSetup()
    {
        @set_time_limit(300);
        //$start_time = microtime(true);

        $this->install_mode = true;
        $this->extra = $this->getExtraModulesConfiguration();
        $this->setupAndIntegration();

        //$time_elapsed = microtime(true) - $start_time;
        //p('runSetup'.$time_elapsed);
    }

    public function sanityCheck()
    {
        $this->setupSmarty(true);
    }

    public function runIntegrationTest()
    {
        @set_time_limit(300);
        $this->setupAndIntegration();
    }

    public function checkIntegration()
    {
        @set_time_limit(300);
        $this->extra = $this->getExtraModulesConfiguration();
        $this->setupAndIntegration();
        $this->check_integration_processed = true;
    }

    public function moduleInstalled($module, $install = true)
    {
        if (Validate::isLoadedObject($module)) {
            $extra_modules = $this->getExtraModules();
            foreach ($extra_modules as $data) {
                if ($module->name == $data['name']) {
                    @set_time_limit(300);
                    $install_mode = $this->install_mode;
                    $this->install_mode = $install;
                    $this->module_mutating = $module;
                    $this->setupExtraModules();
                    $this->install_mode = $install_mode;
                    Configuration::deleteByName('PP_INTEGRATION_CHECK');
                    Configuration::deleteByName('PP_INFO_CHECK_TIME');
                    if ($module->name == 'paypal') {
                        Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT', 0);
                    }
                    break;
                }
            }
        }
    }

    public function moduleUpgraded($module)
    {
        $this->moduleInstalled($module);
    }

    public function moduleUninstall($module)
    {
        $this->moduleInstalled($module, false);
    }

    public function moduleUninstalled($module)
    {
    }

    public function checkModifiedFiles()
    {
        $res = array();
        list(,,,, $pp_files,) = $vars1 = $this->getSetupVars1();
        foreach ($pp_files as $pp_file) {
            list(,, $rel_path, $file, $override_filename, $orig_rel_path) = $this->getSetupVars2($vars1, $pp_file);
            if ($override_filename) {
                $override_file = _PS_ROOT_DIR_ . '/' . $override_filename;
                if (is_file($override_file)) {
                    $res[$this->l('Overridden files')][] = sprintf(
                        $this->l('File "%s" overridden by "%s"'),
                        PSM::normalizePath($orig_rel_path),
                        PSM::normalizePath($override_filename)
                    );
                }
            }
            if (!PSM::md5filesCompare($pp_file, $file)) {
                $modified_by = array();
                $plugins = $this->module->plugins();
                foreach ($plugins as $name => $_) {
                    $content = null;
                    if (Module::isInstalled($name)) {
                        if ($content === null) {
                            $content = Tools::file_get_contents($file);
                        }
                        if (Tools::strpos($content, '[modified by ' . $name . ']') !== false) {
                            $modified_by[$name] = $name;
                        }
                    }
                }
                $res[$this->l('Modified files')][] = PSM::normalizePath($rel_path) . (count($modified_by) ? ' ' . sprintf($this->l('(modified by %s)'), implode(', ', $modified_by)) : '');

                $compatibility = $this->checkVersionCompatibility($file, $rel_path, $pp_file);
                if (Tools::strlen($compatibility[self::VERSION_CHECK_DESC_INDEX]) > 0) {
                    if ($compatibility[self::VERSION_CHECK_INDEX] == false) {
                        $res[$this->l('Files integration')][] = $compatibility[self::VERSION_CHECK_DESC_INDEX];
                    } else {
                        $res[$this->l('Warnings')][] = $compatibility[self::VERSION_CHECK_DESC_INDEX];
                    }
                }
            }
        }

        foreach (array('css', 'js') as $type) {
            foreach (scandir(dirname(__FILE__) . '/views/' . $type) as $filename) {
                $path = false;
                if (PSM::endsWith($filename, '.css', false)) {
                    if (Tools::strpos($filename, 'custom.css') === false) {
                        $path = 'themes/' . _THEME_NAME_ . '/css/modules/' . $this->module->name . '/views/css/' . $filename;
                    }
                } elseif (PSM::endsWith($filename, '.js', false)) {
                    if (Tools::strpos($filename, 'custom.js') === false) {
                        $path = 'themes/' . _THEME_NAME_ . '/js/modules/' . $this->module->name . '/views/js/' . $filename;
                    }
                }
                if ($path) {
                    $override_path = _PS_ROOT_DIR_ . '/' . $path;
                    if (is_file($override_path)) {
                        $res[$this->l('Overridden files')][] = sprintf(
                            $this->l('File "%s" overridden by "%s"'),
                            PSM::normalizePath(dirname(__FILE__) . '/' . $filename, 'relative'),
                            PSM::normalizePath($path)
                        );
                    }
                }
            }
        }
        return $res;
    }

    public function processIntegrationModule($mode)
    {
        $status = 'error';
        if ($json = Tools::getValue('json')) {
            $data = $this->getExtraModulesConfiguration();
            if ($data != null) {
                $request = json_decode($json, true);
                $mod = $request['module'];
                $ver = $request['ver'];
                foreach ($data as &$module) {
                    if (strcmp($mod, $module[self::EXTRA_MODULE]) == 0 &&
                        strcmp((string)$ver, (string)$module[self::EXTRA_VER]) == 0) {
                        $status = 'success';
                        $module[self::EXTRA_IGNORE] = $mode;
                        $this->saveExtraModulesConfiguration($data);
                        break;
                    }
                }
            }
        }
        $json = array('status' => $status);
        return json_encode($json);
    }

    protected function setup()
    {
        $md5_psfiles = $this->getXmlMd5File('prestashop/' . _PS_VERSION_ . '.xml', 'ps_root_dir', _PS_VERSION_);
        list(,,,, $pp_files,) = $vars1 = $this->getSetupVars1();
        foreach ($pp_files as $pp_file) {
            list($setup_rel_path, $setup_amended_rel_path, $rel_path, $file,,) = $this->getSetupVars2($vars1, $pp_file);
            if ($this->install_mode) {
                $compatibility = $this->checkVersionCompatibility($file, $rel_path, $pp_file);
                if ($compatibility[self::VERSION_CHECK_INDEX] == false) {
                    if (is_array($md5_psfiles)) {
                        $ps_files = array_keys($md5_psfiles);
                    }
                    $do_copy = !file_exists($file);
                    if (!$do_copy) {
                        if (isset($ps_files) && is_array($ps_files) && in_array($setup_amended_rel_path, $ps_files)) {
                            $do_copy = PSM::md5Compare($file, $md5_psfiles[$setup_amended_rel_path]);
                        }
                    }
                    if ($do_copy) {
                        $this->backupFile($file);
                        if (!is_dir(dirname($file))) {
                            mkdir(dirname($file), 0755, true);
                        }
                        @chmod($file, 0755);
                        Tools::deleteFile($file);
                        Tools::copy($pp_file, $file);
                    }
                }
            } else {
                $restore = PSM::md5filesCompare($pp_file, $file);
                if ($restore) {
                    $this->restoreFile($file);
                    $delete = PSM::md5filesCompare($pp_file, $file);
                    if ($delete) {
                        if (is_array($md5_psfiles)) {
                            $ps_files = array_keys($md5_psfiles);
                        }
                        if (is_array($ps_files) && !in_array($setup_rel_path, $ps_files)) {
                            Tools::deleteFile($file);
                        }
                    }
                }
            }
        }

        if ($this->install_mode) {
            $result = $this->updateDB();
            if ($result) {
                $this->insertData(false);
            }
        }

        $this->setupSmarty($this->install_mode);
        $this->getPPSetupEx()->setup();
        $this->setupPlugins();
        $this->setupExtraModules();

        if ($this->install_mode) {
            PSM::protectDirectory(self::getBackupRootDirectory());
        } else {
            $this->cleanup();
        }
    }

    protected function setupExtraModuleUninstalled()
    {
    }

    private function setupPlugins()
    {
        $plugins = $this->plugins();
        foreach ($plugins as $ppsetup) {
            $ppsetup->setup();
        }
    }

    private function setupExtraModules()
    {
        if ($this->install_mode) {
            $this->extraModules(false);
        } else {
            $extra_modules = $this->getExtraModules();
            $db = PSM::getDB();
            foreach ($extra_modules as $data) {
                foreach ($data['files'] as $files) {
                    $pp_file = $files['file'];
                    if (basename($pp_file) == 'ppsetup.php') {
                        $installed = $this->isModuleInstalled($db, $data['name'], $data['version']);
                        if ($installed) {
                            if (empty($this->module_mutating) || $this->module_mutating->name == $data['name']) {
                                $ppsetup = $this->getPPSetup($data['name'], $pp_file);
                                $ppsetup->setup();
                                if (!empty($this->module_mutating)) {
                                    return;
                                }
                            }
                        } else {
                            if (($m = Module::getInstanceByName($data['name'])) && $m->version == $data['version']) {
                                $ppsetup = $this->getPPSetup($data['name'], $pp_file);
                                $ppsetup->setupExtraModuleUninstalled();
                            }
                        }
                    } else {
                        $file = _PS_MODULE_DIR_ . $files['rel_path'];
                        $restore = PSM::md5filesCompare($files['file'], $file);
                        if ($restore) {
                            $this->restoreFile($file);
                        }
                    }
                }
            }
        }
    }

    private function setupAndIntegration()
    {
        if (!isset($this->module->integration_test_result)) {
            $this->module->integration_test_result = array();
        }
        if ($this->install_mode) {
            $this->setup();
        }
        $this->checkFilesIntegrity();
        $this->getPPSetupEx()->checkIntegrity();
        $this->checkDbIntegrity();
        $this->checkSmartyIntegrity();
        $this->checkPluginsIntegrity();
        $this->checkExtraModulesIntegrity();

        $this->module->integrated = (count($this->module->integration_test_result) == 0);
        $this->checkVersionIntegrity($this->module->name);
        if ($this->module->integrated) {
            Configuration::updateGlobalValue('PP_INTEGRATION', $this->module->integrationKey());
        } else {
            Configuration::deleteByName('PP_INTEGRATION');
        }
        Configuration::updateGlobalValue('PP_INTEGRATION_CHECK', time());
    }

    private function checkVersionIntegrity($name)
    {
        $cache_key = 'checkVersionIntegrity' . $name;
        if (!isset(static::$s_cache[$cache_key])) {
            $version_key = $name . '_version';
            $cfg_version = Configuration::getGlobalValue($version_key);
            $version_integrity = true;
            if (!empty($cfg_version)) {
                $module = $name == $this->module->name ? $this->module : Module::getInstanceByName($name);
                if ($cfg_version != $module->version) {
                    $key = $module->displayName;
                    if (!isset($this->module->version_mismatch_notes[$key])) {
                        static::$version_mismatch_notes[$key][] = $this->l('Version mismatch detected.');
                        static::$version_mismatch_notes[$key][] = sprintf($this->l('You installed %s version %s but the currently running version is %s.'), $module->displayName, $cfg_version, $module->version);
                        static::$version_mismatch_notes[$key][] = '<br>';
                        static::$version_mismatch_notes[$key][] = sprintf($this->l('In order to properly upgrade the module, please restore the original files of the previously installed %s version %s and uninstall the module.'), $key, $cfg_version);
                        static::$version_mismatch_notes[$key][] = $this->module->confirmUninstall;
                        static::$version_mismatch_notes[$key][] = $this->l('Follow installation and upgrading instructions in the module documentation.');
                    }
                    $version_integrity = false;
                }
            }
            static::$s_cache[$cache_key] = $version_integrity;
            static::$version_integrity &= $version_integrity;
        }
        $this->module->integrated &= static::$version_integrity;
        $this->module->version_mismatch_notes = static::$version_mismatch_notes;
    }

    private function checkFilesIntegrity()
    {
        $result = true;
        list(,,,, $pp_files,) = $vars1 = $this->getSetupVars1();
        foreach ($pp_files as $pp_file) {
            list(,, $rel_path, $file,,) = $this->getSetupVars2($vars1, $pp_file);
            $result &= $this->checkFileIntegrity($file, $rel_path, $pp_file);
        }
        return $result;
    }

    private function checkFileIntegrity($file, $rel_path, $pp_file)
    {
        $compatibility = $this->checkVersionCompatibility($file, $rel_path, $pp_file);
        if ($compatibility[self::VERSION_CHECK_INDEX] == false) {
            $this->module->integration_test_result[$this->l('Files integration')][] = $compatibility[self::VERSION_CHECK_DESC_INDEX];
        }
        return $compatibility[self::VERSION_CHECK_INDEX];
    }

    private function checkPluginsIntegrity()
    {
        if ($this->check_integration_processed) {
            return;
        }
        $plugins = $this->module->plugins();
        foreach ($plugins as $name => $info) {
            list($_, $base) = $info;
            if ($plugin = PSM::getPlugin($name, $base)) {
                if ($error = PSM::checkPluginCompatibility($name, $plugin->apiVersion(), $plugins)) {
                    $this->module->integration_test_result[$this->l('Plugins')][] = $error;
                }
            }
        }
        $plugins = $this->plugins();
        foreach ($plugins as $name => $ppsetup) {
            $ppsetup->checkIntegrity();
        }
    }

    public function checkExtraModulesIntegrity($ignored_only = false)
    {
        $res = array();
        if (!$this->check_integration_processed) {
            $this->extraModules(true);
            $data = $this->getExtraModulesConfiguration();
            if ($data != null) {
                $extra_modules_list = $this->getExtraModulesList();
                $key = $this->l('Modules');
                foreach ($data as $module) {
                    if ($module[self::EXTRA_ENABLED] && !$module[self::EXTRA_INTEGRATED]) {
                        $name = $module[self::EXTRA_MODULE];
                        $ver = $module[self::EXTRA_VER];
                        if ($module[self::EXTRA_IGNORE]) {
                            if ($ignored_only) {
                                $a_activate = PP::wrapA($this->l('activate integration'), false, ['href' => '#', 'data-mode' => 'IntegrationModuleIntegrate']);
                                $more = sprintf(' (%s).', PP::span(
                                    $a_activate,
                                    'pp-integration-module integrate',
                                    ['data-module' => $name, 'data-ver' => $ver]
                                ));
                                $res[$key][] = sprintf($this->l('Module "%s" not integrated. Integration postponed by user'), $name) . $more;
                            }
                        } else {
                            if (!$ignored_only) {
                                $a_ignore = PP::wrapA($this->l('ignore'), false, ['href' => '#', 'data-mode' => 'IntegrationModuleIgnore']);
                                if (empty($extra_modules_list[$name])) {
                                    $more = sprintf('(%s)', PP::span(
                                        sprintf('%s %s', $a_ignore, $this->l('and allow to work without proper integration')),
                                        'pp-integration-module check-for-update-or-ignore',
                                        ['data-module' => $name, 'data-ver' => $ver]
                                    ));
                                } else {
                                    $permission = $module[self::EXTRA_PERMISSION] ? PP::span(Context::getContext()->getTranslator()->trans('The server does not have permissions for writing.', array(), 'Admin.Notifications.Error'), 'failure permission_denied') : '';
                                    $a_check_for_update = PP::wrapA(
                                        sprintf('%s%s', PP::wrapI('', 'icon-refresh'), $this->l('check for update')),
                                        false,
                                        ['href' => '#', 'data-mode' => 'IntegrationModuleCheckForUpdates']
                                    );
                                    $more = sprintf('(%s)', PP::span(
                                        sprintf('%s%s %s %s %s', $permission, $a_check_for_update, PSM::translate('or'), $a_ignore, $this->l('and allow to work without proper integration')),
                                        'pp-integration-module check-for-update-or-ignore',
                                        ['data-module' => $name, 'data-ver' => $ver]
                                    ));
                                }
                                if ($ver == 'all') {
                                    $this->module->integration_test_result[$key][] = sprintf($this->l('Integration failed for module "%s"'), $name) . $more;
                                } else {
                                    $this->module->integration_test_result[$key][] = sprintf($this->l('Integration failed for module "%s" version %s'), $name, $ver) . $more;
                                }
                            }
                        }
                    }
                }
            }
        }
        return ($ignored_only ? $res : $this->module->integrated && count($this->module->integration_test_result) == 0);
    }

    private function extraModules($integration_check_only)
    {
        $modules = array();
        $result = array();
        $permission_denied = $this->downloadExtraModules($integration_check_only);
        $extra_modules = $this->getExtraModules();
        $db = PSM::getDB();
        foreach ($extra_modules as &$data) {
            $data['enabled'] = $this->isModuleEnabled($data['name'], $data['version']);
            $data['integrated'] = false;
            $data['ignore'] = false;
            if ($data['enabled']) {
                $data['ignore'] = $this->ignoreExtraModule($data['name'], $data['version']);
                if (!$data['ignore'] && count($data['files']) > 0) {
                    $data['integrated'] = true;
                    foreach ($data['files'] as $files) {
                        $pp_file = $files['file'];
                        if (basename($pp_file) == 'ppsetup.php') {
                            $ppsetup = $this->getPPSetup($data['name'], $pp_file);
                            if (!$integration_check_only) {
                                $ppsetup->setup();
                            }
                            $integrated = $ppsetup->checkIntegrity();
                            $this->checkVersionIntegrity($data['name']);
                        } else {
                            $rel_path = $files['rel_path'];
                            $file = _PS_MODULE_DIR_ . $rel_path;
                            $compatibility = $this->checkVersionCompatibility($file, $rel_path, $pp_file);
                            $integrated = $compatibility[self::VERSION_CHECK_INDEX];
                            if (!$integration_check_only) {
                                if (!$integrated) {
                                    if (is_file($file)) {
                                        $this->backupFile($file, true);
                                        Tools::copy($pp_file, $file);
                                    }
                                    $integrated = $this->checkFileIntegrity($file, $rel_path, $pp_file);
                                }
                            }
                        }
                        $data['integrated'] &= $integrated;
                    }
                }
            } else {
                if (!$integration_check_only) {
                    foreach ($data['files'] as $files) {
                        $pp_file = $files['file'];
                        if (basename($pp_file) == 'ppsetup.php') {
                            if (($m = Module::getInstanceByName($data['name'])) && $m->version == $data['version']) {
                                $ppsetup = $this->getPPSetup($data['name'], $pp_file);
                                $ppsetup->setupExtraModuleUninstalled();
                            }
                        }
                    }
                }
            }
            if ($data['version'] != 'all') {
                $modules[$data['name']] = ((isset($modules[$data['name']]) ? $modules[$data['name']] : false) | (bool) $data['integrated']);
            }
            $result[] = array(
                self::EXTRA_MODULE     => $data['name'],
                self::EXTRA_VER        => $data['version'],
                self::EXTRA_ENABLED    => $data['enabled'] ? 1 : 0,
                self::EXTRA_INTEGRATED => $data['integrated'] ? 1 : 0,
                self::EXTRA_IGNORE     => $data['ignore'] ? 1 : 0,
                self::EXTRA_PERMISSION => isset($permission_denied[$data['name']]) ? 1 : 0,
            );
        }
        foreach ($modules as $name => $integrated) {
            if (!$integrated && $this->isModuleEnabled(($name))) {
                $version = $this->moduleVersion($db, $name);
                $found = false;
                foreach ($result as $module) {
                    if ($module[self::EXTRA_MODULE] == $name && $module[self::EXTRA_VER] == $version) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result[] = array(
                        self::EXTRA_MODULE     => $name,
                        self::EXTRA_VER        => $version,
                        self::EXTRA_ENABLED    => 1,
                        self::EXTRA_INTEGRATED => 0,
                        self::EXTRA_IGNORE     => $this->ignoreExtraModule($name, $version) ? 1 : 0,
                        self::EXTRA_PERMISSION => isset($permission_denied[$name]) ? 1 : 0,
                    );
                }
            }
        }
        $this->saveExtraModulesConfiguration($result);
    }

    public function checkSmartyIntegrity()
    {
        if ($this->check_integration_processed) {
            return;
        }
        if (!parent::checkSmartyIntegrity()) {
            $this->module->integration_test_result[] =
                sprintf($this->l('Integration failed for smarty file "%s"'), $this->smartyConfigFile(false)) . $this->notWritableWarning($this->smartyConfigFile());
        }
    }

    private function getPPSetup($name, $file)
    {
        $ppsetup = PSMHelper::ppSetupInstance($this->module, $name, $file);
        $ppsetup->install_mode = $this->install_mode;
        return $ppsetup;
    }

    private function getPPSetupEx()
    {
        return $this->getPPSetup('ex', dirname(__FILE__) . '/setup/ppsetup.php');
    }

    private function plugins()
    {
        $excludes = array('.', '..');
        $plugins = array();
        $dir = dirname(__FILE__) . '/plugins/';
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (!in_array($file, $excludes)) {
                    $plugin = $dir . '/' . $file;
                    $ppsetup = $plugin . '/ppsetup.php';
                    if (is_dir($plugin) && is_file($ppsetup)) {
                        $plugins[$file] = $this->getPPSetup($file, $ppsetup);
                    }
                }
            }
        }
        return $plugins;
    }

    private function getExtraModulesConfiguration()
    {
        $c = Configuration::getGlobalValue('PP_INTEGRATION_EXTRA_MODULES');
        if (is_string($c)) {
            return json_decode($c, true);
        }
        return null;
    }

    private function saveExtraModulesConfiguration($data)
    {
        Configuration::updateGlobalValue('PP_INTEGRATION_EXTRA_MODULES', json_encode(array_values($data)));
    }

    private function ignoreExtraModule($name, $ver)
    {
        if (is_array($this->extra)) {
            foreach ($this->extra as $module) {
                if (strcmp($module[self::EXTRA_MODULE], $name) == 0 && strcmp($module[self::EXTRA_VER], $ver) == 0) {
                    return (bool) $module[self::EXTRA_IGNORE];
                }
            }
        }
        return false;
    }

    public function checkReplacedStringsAndReport($filename, $param, $key = null)
    {
        $problems_count = 0;
        $result = $this->checkReplacedStrings($filename, $param);
        $length = count($result);
        if ($length > 0) {
            $key = ($key == null ? $this->l('Files integration') : $key);
            $test_results = array();
            $notes = array();
            for ($i = 0; $i < $length; $i++) {
                foreach ($result[$i] as $type => $value) {
                    $info = '';
                    $details = '';
                    $note = false;
                    switch ($type) {
                        case 'file_not_found':
                            $filename = $value;
                            $problems_count++;
                            $info = PP::span(' ' . $this->l('(file not found)'), 'warning');
                            break;
                        case 'backup_failed':
                            $filename = $value;
                            $problems_count++;
                            $info = PP::span(' ' . $this->l('(backup failed)'), 'warning');
                            $info .= $this->notWritableWarning($filename);
                            break;
                        case 'string_not_found':
                            $problems_count++;
                            $info = $this->notWritableWarning($filename);
                            if (Tools::strlen($info) == 0) {
                                $info = PP::span(' ' . $this->l('(string not found)'), 'warning');
                                $details = sprintf(
                                    '<br>%s<br>%s',
                                    PP::span(
                                        sprintf('%s%s', PP::span($this->l('searching for:') . ' ', 'warning'), PP::safeOutput(self::truncateString($value[0], 150))),
                                        'warning-details'
                                    ),
                                    PP::span(
                                        sprintf('%s%s', PP::span($this->l('substitute:') . ' ', 'warning'), PP::safeOutput(self::truncateString($value[1], 150))),
                                        'warning-details'
                                    )
                                );
                            }
                            break;
                        case 'string_not_found_note':
                            $note = true;
                            $info = $this->notWritableWarning($filename);
                            if (Tools::strlen($info) == 0) {
                                // only information message, do not increment $problems_count
                                $info = PP::span(' ' . $this->l('(optional string not found, no action required)'), 'note');
                                $details = sprintf(
                                    '<br>%s<br>%s',
                                    PP::span(
                                        sprintf('%s%s', PP::span($this->l('searching for:') . ' ', 'note'), PP::safeOutput(self::truncateString($value[0], 150))),
                                        'note-details'
                                    ),
                                    PP::span(
                                        sprintf('%s%s', PP::span($this->l('substitute:') . ' ', 'note'), PP::safeOutput(self::truncateString($value[1], 150))),
                                        'note-details'
                                    )
                                );
                            }
                            break;
                        case 'string_count':
                            $problems_count++;
                            $info = $this->notWritableWarning($filename);
                            if (Tools::strlen($info) == 0) {
                                $info = PP::span(' ' . sprintf($this->l('(string expected to be found %d %s, found %d %s)'), $value[2], PSM::plural($value[2], $this->l('time'), $this->l('times')), $value[3], PSM::plural($value[3], $this->l('time'), $this->l('times'))), 'warning');
                                $details = sprintf(
                                    '<br>%s<br>%s',
                                    PP::span(
                                        sprintf('%s%s', PP::span($this->l('searching for:') . ' ', 'warning'), PP::safeOutput(self::truncateString($value[0], 150))),
                                        'warning-details'
                                    ),
                                    PP::span(
                                        sprintf('%s%s', PP::span($this->l('substitute:') . ' ', 'warning'), PP::safeOutput(self::truncateString($value[1], 150))),
                                        'warning-details'
                                    )
                                );
                            }
                            break;
                        case 'duplicate_string_found':
                            $problems_count++;
                            $info = $this->notWritableWarning($filename);
                            if (Tools::strlen($info) == 0) {
                                $info = PP::span(' ' . $this->l('(duplicate string found)'), 'warning');
                                $details = sprintf('<br>%s', PP::span(PP::safeOutput(self::truncateString($value, 150)), 'warning-details'));
                            }
                            break;
                        default:
                            $problems_count++;
                            break;
                    }
                    $str = '<br>' . sprintf(
                        $note ? $this->l('String replacement note for file: %s%s') : $this->l('String replacement warning for file: %s%s'),
                        PSM::normalizePath($filename, 'relative'),
                        $info
                    );
                    if (!in_array($str, $note ? $notes : $test_results)) {
                        if ($note) {
                            $notes[] = $str;
                        } else {
                            $test_results[] = $str;
                        }
                    }
                    if (Tools::strlen($details) > 0) {
                        if ($note) {
                            $notes[] = $details;
                        } else {
                            $test_results[] = $details;
                        }
                    }
                }
            }
            if (count($test_results)) {
                $str = implode('', $test_results);
                if (!isset($this->module->integration_test_result[$key]) || !in_array($str, $this->module->integration_test_result[$key])) {
                    $this->module->integration_test_result[$key][] = $str;
                }
            }
            if (count($notes)) {
                if (!isset($this->module->integration_test_result_notes)) {
                    $this->module->integration_test_result_notes = array();
                }
                $str = implode('', $notes);
                if (!isset($this->module->integration_test_result_notes[$key]) || !in_array($str, $this->module->integration_test_result_notes[$key])) {
                    $this->module->integration_test_result_notes[$key][] = $str;
                }
            }
        }
        return ($problems_count == 0);
    }

    protected function amendSetupCustom(&$params, $entry)
    {
        if (static::$ppsetup_custom_config === null) {
            if (!empty($s = Configuration::getGlobalValue('PPSETUP_CUSTOM'))) {
                static::$ppsetup_custom_config = psm_unser(hex2bin($s));
            } else {
                static::$ppsetup_custom_config = array();
            }
        }
        $f = str_replace(array('/setup/', '/ppsetup.php'), array('/setup/custom/', '/ppsetup.inc'), $this->fullpath);
        if (!file_exists($f) && key_exists($f, static::$ppsetup_custom_config)) {
            $dir = str_replace('/ppsetup.inc', '', $f);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                PSM::protectDirectory($dir);
            }
            file_put_contents($f, static::$ppsetup_custom_config[$f]);
        }
        if (is_file($f)) {
            if (!key_exists($f, static::$ppsetup_custom)) {
                $ppsetup_custom = null;
                @include($f);
                if (is_array($ppsetup_custom) && count($ppsetup_custom)) {
                    static::$ppsetup_custom[$f] = $ppsetup_custom;
                    static::$ppsetup_custom_config[$f] = Tools::file_get_contents($f);
                } else {
                    static::$ppsetup_custom[$f] = array();
                    unset(static::$ppsetup_custom_config[$f]);
                }
                if (count(static::$ppsetup_custom_config)) {
                    Configuration::updateGlobalValue('PPSETUP_CUSTOM', bin2hex(psm_ser(static::$ppsetup_custom_config)));
                } else {
                    Configuration::deleteByName('PPSETUP_CUSTOM');
                }
            }
            if (isset(static::$ppsetup_custom[$f][$entry])) {
                foreach ($params as &$param) {
                    foreach ($param['files'] as $file) {
                        if (!empty($file)) {
                            foreach (static::$ppsetup_custom[$f][$entry] as $custom_param) {
                                foreach ($custom_param['files'] as $custom_file) {
                                    if (!empty($custom_file)) {
                                        if ($file == $custom_file) {
                                            foreach (array('optional', 'ignore') as $key) {
                                                if (isset($custom_param[$key])) {
                                                    $param[$key] = $custom_param[$key];
                                                }
                                            }
                                            foreach (array('append', 'prepend', 'replace') as $key) {
                                                if (isset($custom_param[$key])) {
                                                    if (isset($param[$key])) {
                                                        foreach ($custom_param[$key] as $custom_args) {
                                                            $found = false;
                                                            foreach ($param[$key] as &$args) {
                                                                if ($args[0] == $custom_args[0]) {
                                                                    $args = $custom_args;
                                                                    $found = true;
                                                                }
                                                            }
                                                            if (!$found) {
                                                                $param[$key][] = $custom_args;
                                                            }
                                                        }
                                                    } else {
                                                        $param[$key] = $custom_param[$key];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                foreach (static::$ppsetup_custom[$f][$entry] as $custom_param) {
                    foreach ($custom_param['files'] as $custom_file) {
                        if (!empty($custom_file)) {
                            $found = false;
                            foreach ($params as $param) {
                                foreach ($param['files'] as $file) {
                                    if ($file == $custom_file) {
                                        $found = true;
                                        break 2;
                                    }
                                }
                            }
                            if (!$found) {
                                $_custom_param = $custom_param;
                                $_custom_param['files'] = array($custom_file);
                                $params[] = $_custom_param;
                            }
                        }
                    }
                }
            }
        }
    }

    protected function processReplaceInFiles(&$params, $replace, $dir)
    {
        $this->amendSetupCustom($params, $dir);
        $result = true;
        foreach ($params as $param) {
            foreach ($param['files'] as $file) {
                if (!empty($file)) {
                    $core = true;
                    $filename = false;
                    if (Tools::strpos($file, 'classes/') === 0 || Tools::strpos($file, 'controllers/') === 0) {
                        $filename = $dir . 'override/' . $file;
                    } elseif (Tools::strpos($file, 'pdf/') === 0) {
                        if (is_file(_PS_THEME_DIR_ . $file)) {
                            $filename = _PS_THEME_DIR_ . $file;
                            $core = false;
                        } elseif (defined('_PS_PARENT_THEME_DIR_') && _PS_PARENT_THEME_DIR_ && is_file(_PS_PARENT_THEME_DIR_ . $file)) {
                            $filename = _PS_PARENT_THEME_DIR_ . $file;
                            $core = false;
                        }
                    }
                    if ($replace) {
                        if ($filename && is_file($filename) && is_writable($filename)) {
                            $this->replaceStrings($filename, $param, $this->install_mode);
                        }
                        if ($core) {
                            $filename = $dir . $file;
                            if (is_file($filename) && is_writable($filename)) {
                                $this->replaceStrings($filename, $param, $this->install_mode);
                            } elseif ($dir == _PS_THEME_DIR_ && defined('_PS_PARENT_THEME_DIR_') && _PS_PARENT_THEME_DIR_) {
                                $filename = _PS_PARENT_THEME_DIR_ . $file;
                                if (is_file($filename) && is_writable($filename)) {
                                    $this->replaceStrings($filename, $param, $this->install_mode);
                                }
                            }
                        }
                    } else {
                        if ($core) {
                            $filename = $dir . $file;
                            if (!is_file($filename) && $dir == _PS_THEME_DIR_ && defined('_PS_PARENT_THEME_DIR_') && _PS_PARENT_THEME_DIR_) {
                                $filename = _PS_PARENT_THEME_DIR_ . $file;
                            }
                        }
                        $result &= $this->checkReplacedStringsAndReport($filename, $param);
                    }
                }
            }
        }
        return $result;
    }

    protected function processModuleFiles($module, $params, $replace)
    {
        $this->amendSetupCustom($params, $module);
        $result = true;
        foreach ($params as $param) {
            if (count($param['files'])) {
                $str = $param['files'][0];
                if (!empty($str)) {
                    $type = Tools::substr($str, Tools::strrpos($str, '.') + 1);
                    $modulebase = _PS_MODULE_DIR_ . $module . '/';
                    switch ($type) {
                        case 'tpl':
                            $dirs = array(_PS_THEME_DIR_ . 'modules/' . $module . '/');
                            if (defined('_PS_PARENT_THEME_DIR_')) {
                                $dirs[] = _PS_PARENT_THEME_DIR_ . 'modules/' . $module . '/';
                            }
                            $dirs[] = $modulebase . 'views/templates/hook/';
                            break;
                        case 'js':
                            $dirs = array(_PS_THEME_DIR_ . 'js/modules/' . $module . '/');
                            if (defined('_PS_PARENT_THEME_DIR_')) {
                                $dirs[] = _PS_PARENT_THEME_DIR_ . 'js/modules/' . $module . '/';
                            }
                            break;
                        case 'php':
                            $dirs = array();
                            break;
                        default:
                            return;
                    }
                    foreach ($param['files'] as $file) {
                        if (!empty($file)) {
                            $filename = null;
                            foreach ($dirs as $dir) {
                                if (is_file($dir . $file)) {
                                    $filename = $dir . $file;
                                    break;
                                }
                            }
                            if ($filename == null) {
                                if ($module == 'pproperties' && (Tools::strpos($file, 'classes/core/classes/') === 0)) {
                                    static $_VERSION_ = null;
                                    if ($_VERSION_ === null) {
                                        $_VERSION_ = Tools::substr(_PS_VERSION_, 0, strrpos(_PS_VERSION_, '.'));
                                    }
                                    $class = str_replace('classes/core/', '', $file);
                                    $filename = PSM_CLASSES_CORE_DIR . _PS_VERSION_ . '/' . $class;
                                    if (!is_file($filename)) {
                                        $filename = PSM_CLASSES_CORE_DIR . $_VERSION_ . '/' . $class;
                                        if (!is_file($filename)) {
                                            $filename = PSM_CLASSES_CORE_DIR . $class;
                                        }
                                    }
                                } else {
                                    $filename = $modulebase . $file;
                                }
                            }
                            if ($replace) {
                                if (is_writable($filename)) {
                                    $this->replaceStrings($filename, $param, $this->install_mode);
                                }
                            } else {
                                $result &= $this->checkReplacedStringsAndReport($filename, $param, $this->l('Modules'));
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    protected function setupMail($params, $install)
    {
        $dirs = scandir(_PS_MAIL_DIR_);
        foreach ($dirs as $dirname) {
            $dir = _PS_MAIL_DIR_ . $dirname;
            if ($dirname != '.' && $dirname != '..' && is_dir($dir)) {
                foreach ($params as $param) {
                    foreach ($param['files'] as $file) {
                        if (!empty($file)) {
                            $filename = $dir . '/' . $file;
                            if (is_file($filename)) {
                                $content = Tools::file_get_contents($filename);
                                if ($install) {
                                    if (Tools::strpos($content, $param['target']) === false) {
                                        $pos = Tools::strpos($content, $param['condition']);
                                        if ($pos !== false) {
                                            $insert = Tools::strpos($content, $param['delimiter'], $pos);
                                            if ($insert !== false) {
                                                $newcontent = PSM::substrReplace($content, $param['replace'], $insert + Tools::strlen($param['delimiter']), 0);
                                                @file_put_contents($filename, $newcontent);
                                            }
                                        }
                                    }
                                } else {
                                    $newcontent = str_replace($param['replace'], '', $content, $count);
                                    if ($count > 0) {
                                        @file_put_contents($filename, $newcontent);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function notWritableWarning($filename)
    {
        return (is_writable($filename) ? '' : '<span class="warning"> (' . Context::getContext()->getTranslator()->trans('The server does not have permissions for writing.', array(), 'Admin.Notifications.Error') . ')</span>');
    }

    private function moduleVersion($db, $name)
    {
        return ($db->getValue('SELECT version FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = \'' . pSQL($name) . '\''));
    }

    private function isModuleEnabled($name, $version = 'all')
    {
        if (Module::isEnabled($name)) {
            if ($version == 'all') {
                return true;
            }
            return ($version == $this->moduleVersion(PSM::getDB(), $name));
        }
        return ($this->module_mutating && $this->module_mutating->name == $name && ($this->module_mutating->version == $version || $version == 'all'));
    }

    private function isModuleInstalled($db, $name, $version)
    {
        if (Module::isInstalled($name)) {
            if (strcmp($version, 'all') == 0) {
                return true;
            }
            return ($version == $this->moduleVersion($db, $name));
        }
        return false;
    }

    public function downloadExtraModule($name, $version, $force = false)
    {
        $extra_modules_list = $this->getExtraModulesList();
        if (empty($extra_modules_list[$name])) {
            $status = 'no_updates';
        } else {
            $extra_modules_dir = PSMHelper::ppSetupExtraModulesDir();
            if ($force || (!is_dir($extra_modules_dir . '/' . $name . '/all') && !is_dir($extra_modules_dir . '/' . $name . '/' . $version))) {
                $status = 'error';
                $params = array_merge(
                    array(
                        'key' => $this->module->name,
                        'ver' => $this->module->version,
                        'module' => $name,
                        'version' => $version,
                    ),
                    PSM::psmInfo($this->module->name)
                );
                $content = PSM::getContent('integration.php', $params);
                if ($content) {
                    if ($content == '_none_') {
                        if ($version == 'all' || strpos('pproperties', $name) == 0) {
                            $status = 'rerun';
                        } else {
                            $status = 'no_updates';
                        }
                    } else {
                        $dir = $extra_modules_dir . '/' . $name . '/';
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $zip_file = $dir . $version . '.zip';
                        Tools::deleteFile($zip_file);
                        if (file_put_contents($zip_file, $content) !== false) {
                            if (Tools::ZipExtract($zip_file, $dir)) {
                                $status = 'downloaded';
                            } else {
                                $status = 'permission_denied';
                            }
                            Tools::deleteFile($zip_file);
                        }
                    }
                }
            } else {
                $status = 'downloaded';
            }
        }
        return $status;
    }

    private function downloadExtraModules($integration_check_only)
    {
        $permission_denied = [];
        $db = PSM::getDB();
        foreach ($this->getExtraModulesList() as $name => $downloadable) {
            if ($downloadable && $this->isModuleEnabled($name)) {
                $status = $this->downloadExtraModule($name, $this->moduleVersion($db, $name), !$integration_check_only);
                if ($status == 'permission_denied') {
                    $permission_denied[$name] = true;
                }
            }
        }
        PSM::protectDirectory(PSMHelper::ppSetupExtraModulesDir());
        return $permission_denied;
    }

    private function getExtraModulesList()
    {
        static $extra_modules_list = null;
        if ($extra_modules_list === null) {
            $extra_modules_list = array();
            $exclude_files = self::excludeFiles();
            $extra_modules_dir = PSMHelper::ppSetupExtraModulesDir();
            foreach (scandir($extra_modules_dir) as $dir) {
                if (in_array($dir, $exclude_files) || !is_dir($extra_modules_dir . '/' . $dir)) {
                    continue;
                }
                $extra_modules_list[$dir] = null;
            }
            $file = $extra_modules_dir . '/extra_modules_list.xml';
            if (file_exists($file)) {
                $content  = Tools::file_get_contents($file);
                $xml = simplexml_load_string($content, null, LIBXML_NOCDATA);
                if ($xml && isset($xml->module)) {
                    foreach ($xml->module as $module) {
                        $name = Tools::strtolower((string) $module->name);
                        if (!isset($module->plugin) || !$module->plugin) {
                            $extra_modules_list[$name] = true; // downloadable module
                        }
                    }
                }
            }
        }
        return $extra_modules_list;
    }

    private function getExtraModules()
    {
        $db = PSM::getDB();
        $extra_modules = array();
        $extra_modules_dir = PSMHelper::ppSetupExtraModulesDir();
        foreach ($this->getExtraModulesList() as $name => $downloadable) {
            if ($this->isModuleEnabled(($name))) {
                $version = $this->moduleVersion($db, $name);
            } elseif (($m = Module::getInstanceByName($name))) {
                $version = $m->version;
            } else {
                $version = 0;
            }

            $key = $name . ':' . $version;
            $extra_modules[$key] = array(
                'name'    => $name,
                'version' => $version,
                'files'   => array()
            );
            if ($version > 0) {
                $files = self::directoryListing($extra_modules_dir . '/' . $name . '/' . $version, '_media_');
                if (count($files) == 0) {
                    $version = 'all';
                    $files = self::directoryListing($extra_modules_dir . '/' . $name . '/all', '_media_');
                }
                if (count($files)) {
                    foreach ($files as $file) {
                        $rel_path = str_replace($version . '/', '', Tools::substr(str_replace($extra_modules_dir, '', $file), 1));
                        $extra_modules[$key]['files'][] = array(
                            'rel_path' => $rel_path,
                            'file'     => $file
                        );
                    }
                }
            }
        }
        return $extra_modules;
    }

    private function checkVersionCompatibility($file, $rel_path, $pp_file)
    {
        $integration_version = PSM::integrationVersion($this->module);
        $result = array();
        $result[self::VERSION_CHECK_INDEX] = false;
        $version = $this->getVersion($file);
        if ($version === false) {
            $result[self::VERSION_CHECK_DESC_INDEX] = '[1] ' . sprintf($this->l('File is missing: %s'), PSM::normalizePath($rel_path, 'relative'));
        } else {
            $details = $this->notWritableWarning($file);
            if ($version[self::VERSION_INDEX] === false) {
                $result[self::VERSION_CHECK_DESC_INDEX] = '[2] ' . sprintf($this->l('File not compatible: %s (missing version signature)') . $details, PSM::normalizePath($rel_path, 'relative'));
            } else {
                $pp_version = $this->getVersion($pp_file);
                if (Tools::version_compare($integration_version, $version[self::VERSION_INDEX], '=')) {
                    $result[self::VERSION_CHECK_INDEX] = true;
                    $result[self::VERSION_CHECK_DESC_INDEX] = '';
                    if ($pp_version[self::VERSION_REQUIRED_INDEX] !== false) {
                        if ($version[self::VERSION_REQUIRED_INDEX] !== false) {
                            if (Tools::version_compare($version[self::VERSION_REQUIRED_INDEX], $pp_version[self::VERSION_REQUIRED_INDEX], '<')) {
                                $result[self::VERSION_CHECK_INDEX] = false;
                                $result[self::VERSION_CHECK_DESC_INDEX] = '[3] ' . sprintf($this->l('Compatibility warning for file: %s (expected version %s, found version %s)') . $details, PSM::normalizePath($rel_path, 'relative'), str_replace('#', '-R', $pp_version[self::VERSION_REQUIRED_INDEX]), str_replace('#', '-R', $version[self::VERSION_REQUIRED_INDEX]));
                            }
                        } else {
                            $result[self::VERSION_CHECK_DESC_INDEX] = '[4] ' . sprintf($this->l('Compatibility warning for file: %s (expected version %s, no version information found)') . $details, PSM::normalizePath($rel_path, 'relative'), str_replace('#', '-R', $pp_version[self::VERSION_REQUIRED_INDEX]));
                        }
                    }
                } else {
                    if ($pp_version[self::VERSION_REQUIRED_INDEX] !== false) {
                        if (Tools::version_compare($version[self::VERSION_INDEX], $pp_version[self::VERSION_REQUIRED_INDEX], '>=')) {
                            $result[self::VERSION_CHECK_INDEX] = true;
                            $result[self::VERSION_CHECK_DESC_INDEX] = '';
                        } else {
                            if ($version[self::VERSION_REQUIRED_INDEX] !== false) {
                                if (Tools::version_compare($version[self::VERSION_REQUIRED_INDEX], $pp_version[self::VERSION_REQUIRED_INDEX], '<')) {
                                    $result[self::VERSION_CHECK_DESC_INDEX] = '[5] ' . sprintf($this->l('Compatibility warning for file: %s (expected version %s, found version %s)') . $details, PSM::normalizePath($rel_path, 'relative'), str_replace('#', '-R', $pp_version[self::VERSION_REQUIRED_INDEX]), str_replace('#', '-R', $version[self::VERSION_REQUIRED_INDEX]));
                                } else {
                                    $result[self::VERSION_CHECK_INDEX] = true;
                                    if (Tools::version_compare($version[self::VERSION_REQUIRED_INDEX], $pp_version[self::VERSION_REQUIRED_INDEX], '>')) {
                                        $result[self::VERSION_CHECK_DESC_INDEX] = '[6] ' . sprintf($this->l('Compatibility warning for file: %s (expected version %s, found version %s)') . $details, PSM::normalizePath($rel_path, 'relative'), str_replace('#', '-R', $pp_version[self::VERSION_REQUIRED_INDEX]), str_replace('#', '-R', $version[self::VERSION_REQUIRED_INDEX]));
                                    } else {
                                        $result[self::VERSION_CHECK_DESC_INDEX] = '';
                                    }
                                }
                            } else {
                                $result[self::VERSION_CHECK_DESC_INDEX] = '[7] ' . sprintf($this->l('File not compatible: %s (expected version %s, found version %s)') . $details, PSM::normalizePath($rel_path, 'relative'), str_replace('#', '-R', $pp_version[self::VERSION_REQUIRED_INDEX]), $version[self::VERSION_INDEX]);
                            }
                        }
                    } else {
                        $result[self::VERSION_CHECK_DESC_INDEX] = '[8] ' . sprintf($this->l('File not compatible: %s (expected version %s, found version %s)') . $details, PSM::normalizePath($rel_path, 'relative'), $integration_version, $version[self::VERSION_INDEX]);
                    }
                }
            }
        }
        return $result;
    }

    private function getSetupVars1()
    {
        $key = 'getSetupVars1';
        if (!isset(static::$s_cache[$key])) {
            $admin_dir = Tools::substr(str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_), 1) . '/';
            $theme_dir = Tools::substr(str_replace(_PS_ROOT_DIR_, '', _PS_THEME_DIR_), 1);
            $setup_dir = dirname(__FILE__) . '/setup/core';
            $override_dir = dirname(__FILE__) . '/override';
            $pp_files = self::directoryListing($setup_dir, array('_version_', '_media_'));
            $pp_files_current_version = self::directoryListing($setup_dir . '/_version_/' . _PS_VERSION_);
            if ($pp_files_current_version) {
                $pp_files = array_merge($pp_files, $pp_files_current_version);
            }
            $override_files = self::directoryListing($override_dir);
            static::$s_cache[$key] = array($admin_dir, $theme_dir, $setup_dir, $override_dir, $pp_files, $override_files);
        }
        return static::$s_cache[$key];
    }

    private function getSetupVars2($vars1, $pp_file)
    {
        list($admin_dir, $theme_dir, $setup_dir,) = $vars1;
        $setup_rel_path = Tools::substr(str_replace($setup_dir, '', $pp_file), 1);
        $length = Tools::strlen($setup_rel_path);
        if (Tools::strrpos($setup_rel_path, '._tpl') == $length - 5) {
            $setup_amended_rel_path = Tools::substr($setup_rel_path, 0, $length - 5) . '.tpl';
        } elseif (Tools::strrpos($setup_rel_path, '._js') == $length - 4) {
            $setup_amended_rel_path = Tools::substr($setup_rel_path, 0, $length - 4) . '.js';
        } else {
            $setup_amended_rel_path = $setup_rel_path;
        }

        $rel_path = $setup_amended_rel_path;
        $override_filename = false;
        if (Tools::strpos($rel_path, 'admin/') === 0) {
            $rel_path = PSM::normalizePath(str_replace('admin/', $admin_dir, $rel_path));
        } elseif (Tools::strpos($rel_path, 'pdf/') === 0) {
            $override_filename = PSM::normalizePath($theme_dir . $rel_path);
        } elseif (Tools::strpos($rel_path, 'themes/default-bootstrap/') === 0) {
            $rel_path = PSM::normalizePath(str_replace('themes/default-bootstrap/', $theme_dir, $rel_path));
        } elseif (Tools::strpos($rel_path, '_version_/legacy/themes/default-bootstrap/') === 0) {
            $rel_path = PSM::normalizePath(str_replace('_version_/legacy/themes/default-bootstrap/', $theme_dir, $rel_path));
        } elseif (Tools::strpos($rel_path, '_version_/legacy/pdf/') === 0) {
            $setup_amended_rel_path = str_replace('_version_/legacy/pdf/', 'pdf/', $setup_amended_rel_path);
            $rel_path = PSM::normalizePath(str_replace('_version_/legacy/pdf/', 'pdf/', $rel_path));
            $override_filename = PSM::normalizePath($theme_dir . $rel_path);
        }
        if (Tools::strpos($setup_amended_rel_path, 'admin/themes/default/template/controllers') === 0) {
            $override_filename = str_replace(
                'admin/themes/default/template/controllers',
                'override/controllers/admin/templates',
                $setup_amended_rel_path
            );
        }

        $file = _PS_ROOT_DIR_ . '/' . $rel_path;
        $orig_rel_path = $rel_path;
        if ($override_filename) {
            $override_file = _PS_ROOT_DIR_ . '/' . $override_filename;
            if (is_file($override_file)) {
                $rel_path = $override_filename;
                $file = $override_file;
            }
        }
        return array($setup_rel_path, $setup_amended_rel_path, $rel_path, $file, $override_filename, $orig_rel_path);
    }

    public function insertData($delete = false)
    {
        $db = PSM::getDB();
        if ($delete) {
            $db->delete('pp_template', 'id_pp_template < ' . PProperties::USER_START_ID);
            $db->delete('pp_template_lang', 'id_pp_template < ' . PProperties::USER_START_ID);
            $db->delete('pp_template_property', 'id_pp_template < ' . PProperties::USER_START_ID);
            $db->delete('pp_property', 'id_pp_property < ' . PProperties::USER_START_ID);
            $db->delete('pp_property_lang', 'id_pp_property < ' . PProperties::USER_START_ID);
            $db->delete('pp_template_ext', 'id_pp_template < ' . PProperties::USER_START_ID);
            $db->delete('pp_template_ext_prop', 'id_pp_template < ' . PProperties::USER_START_ID);
        }

        $languages = Language::getLanguages(false);
        $rows = array(
            array('id_pp_template' =>  1, 'version' => 1, 'qty_policy' => 0),
            array('id_pp_template' =>  2, 'version' => 1, 'qty_policy' => 0),
            array('id_pp_template' =>  3, 'version' => 1, 'qty_policy' => 1, 'qty_mode' => 1),
            array('id_pp_template' =>  4, 'version' => 1, 'qty_policy' => 2),
            array('id_pp_template' =>  5, 'version' => 1, 'qty_policy' => 2, 'qty_mode' => 1),
            array(
                'id_pp_template' =>  6, 'version' => 1, 'qty_policy' => 2, 'measurement_system' => 1, 'display_mode' => 9,
                'unit_price_ratio' => 10, 'minimal_quantity' => 0.1, 'default_quantity' => 0.1
            ),
            array('id_pp_template' =>  7, 'version' => 1, 'qty_policy' => 1),
            array(
                'id_pp_template' =>  8, 'version' => 1, 'qty_policy' => 2,
                'minimal_quantity' => 0.1, 'default_quantity' => 1
            ),
            array(
                'id_pp_template' =>  9, 'version' => 1, 'qty_policy' => 2,
                'minimal_quantity' => 0.125, 'default_quantity' => 1
            ),
            array('id_pp_template' => 10, 'version' => 1, 'qty_policy' => 2, 'ext' => 1),
            array('id_pp_template' => 11, 'version' => 1, 'qty_policy' => 2, 'ext' => 1),
        );
        self::dbInsert('pp_template', $rows);

        $rows = array(
            array('id_pp_template' =>  1, 'pp_name' => 'pp_qty_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  2, 'pp_name' => 'pp_qty_text', 'id_pp_property' =>  2),
            array('id_pp_template' =>  3, 'pp_name' => 'pp_qty_text', 'id_pp_property' =>  2),
            array('id_pp_template' =>  4, 'pp_name' => 'pp_qty_text', 'id_pp_property' => 10),
            array('id_pp_template' =>  5, 'pp_name' => 'pp_qty_text', 'id_pp_property' => 10),
            array('id_pp_template' =>  6, 'pp_name' => 'pp_qty_text', 'id_pp_property' => 10),
            array('id_pp_template' =>  7, 'pp_name' => 'pp_qty_text', 'id_pp_property' => 15),
            array('id_pp_template' =>  8, 'pp_name' => 'pp_qty_text', 'id_pp_property' => 15),
            array('id_pp_template' =>  9, 'pp_name' => 'pp_qty_text', 'id_pp_property' => 19),

            array('id_pp_template' =>  1, 'pp_name' => 'pp_price_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  2, 'pp_name' => 'pp_price_text', 'id_pp_property' =>  3),
            array('id_pp_template' =>  3, 'pp_name' => 'pp_price_text', 'id_pp_property' =>  3),
            array('id_pp_template' =>  4, 'pp_name' => 'pp_price_text', 'id_pp_property' => 11),
            array('id_pp_template' =>  5, 'pp_name' => 'pp_price_text', 'id_pp_property' => 11),
            array('id_pp_template' =>  6, 'pp_name' => 'pp_price_text', 'id_pp_property' => 11),
            array('id_pp_template' =>  7, 'pp_name' => 'pp_price_text', 'id_pp_property' => 16),
            array('id_pp_template' =>  8, 'pp_name' => 'pp_price_text', 'id_pp_property' => 16),
            array('id_pp_template' =>  9, 'pp_name' => 'pp_price_text', 'id_pp_property' => 20),
            array('id_pp_template' => 10, 'pp_name' => 'pp_price_text', 'id_pp_property' => 18),
            array('id_pp_template' => 11, 'pp_name' => 'pp_price_text', 'id_pp_property' => 16),

            array('id_pp_template' =>  1, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  2, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  3, 'pp_name' => 'pp_unity_text', 'id_pp_property' => 11),
            array('id_pp_template' =>  4, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  5, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  6, 'pp_name' => 'pp_unity_text', 'id_pp_property' => 12),
            array('id_pp_template' =>  7, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  8, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),
            array('id_pp_template' =>  9, 'pp_name' => 'pp_unity_text', 'id_pp_property' =>  0),

            array('id_pp_template' =>  1, 'pp_name' => 'pp_explanation', 'id_pp_property' =>  0),
            array('id_pp_template' =>  2, 'pp_name' => 'pp_explanation', 'id_pp_property' =>  0),
            array('id_pp_template' =>  3, 'pp_name' => 'pp_explanation', 'id_pp_property' => 51),
            array('id_pp_template' =>  4, 'pp_name' => 'pp_explanation', 'id_pp_property' => 52),
            array('id_pp_template' =>  5, 'pp_name' => 'pp_explanation', 'id_pp_property' => 50),
            array('id_pp_template' =>  6, 'pp_name' => 'pp_explanation', 'id_pp_property' =>  0),
            array('id_pp_template' =>  7, 'pp_name' => 'pp_explanation', 'id_pp_property' =>  0),
            array('id_pp_template' =>  8, 'pp_name' => 'pp_explanation', 'id_pp_property' => 53),
            array('id_pp_template' =>  9, 'pp_name' => 'pp_explanation', 'id_pp_property' => 54),
        );
        self::dbInsertTemplateSpecial('pp_template_property', 'pp_name', $rows);

        $rows = array(
            array(
                'id_pp_template' =>  1, 'name' => 'regular product',
                'description_1' => 'Use this template to enable Product Properties Extension module and plugins dynamic behavior for regular products with no specific assignments',
                'description_2' => 'Use this template to enable Product Properties Extension module and plugins dynamic behavior for regular products with no specific assignments'
            ),
            array(
                'id_pp_template' =>  2, 'name' => 'quantity in pieces',
                'description_1' => 'Product sold in pieces, price as specified',
                'description_2' => 'Product sold in pieces, price as specified'
            ),
            array(
                'id_pp_template' =>  3, 'name' => 'quantity in whole units, sold by weight',
                'description_1' => 'Product sold by weight with quantity in whole units, approximate quantity and price (the exact quantity cannot be ordered)',
                'description_2' => 'Product sold by weight with quantity in pieces, approximate quantity and price (the exact quantity cannot be ordered)'
            ),
            array(
                'id_pp_template' =>  4, 'name' => 'by weight',
                'description_1' => 'Product sold by weight, price as specified (exact weight can be ordered)',
                'description_2' => 'Product sold by weight, price as specified (exact weight can be ordered)'
            ),
            array(
                'id_pp_template' =>  5, 'name' => 'by weight, approximate quantity',
                'description_1' => 'Product sold by weight, approximate quantity and price (exact weight cannot be ordered)',
                'description_2' => 'Product sold by weight, approximate quantity and price (exact weight cannot be ordered)'
            ),
            array(
                'id_pp_template' =>  6, 'name' => 'by weight, price per 100 g',
                'description_1' => 'Product sold by weight (with unit price)',
                'description_2' => 'Product sold by weight (with unit price)'
            ),
            array(
                'id_pp_template' =>  7, 'name' => 'by length (whole units)',
                'description_1' => 'Product sold by length in whole units',
                'description_2' => 'Product sold by length in whole units'
            ),
            array(
                'id_pp_template' =>  8, 'name' => 'by length (fractional units)',
                'description_1' => 'Product sold by length in meters',
                'description_2' => 'Product sold by length in feet'
            ),
            array(
                'id_pp_template' =>  9, 'name' => 'by length (yards)',
                'description_1' => 'Product sold by length in yards',
                'description_2' => 'Product sold by length in yards'
            ),
            array(
                'id_pp_template' => 10, 'name' => 'by length (area)',
                'description_1' => 'Product uses multidimensional feature (height x width)',
                'description_2' => 'Product uses multidimensional feature (height x width)'
            ),
            array(
                'id_pp_template' => 11, 'name' => 'by length (perimeter)',
                'description_1' => 'Product uses multidimensional feature (height + width)',
                'description_2' => 'Product uses multidimensional feature (height + width)'
            ),
        );
        self::dbInsertLang('pp_template_lang', $rows, $languages);

        $rows = array(
            array('id_pp_property' =>  1, 'text_1' => 'pc',                 'text_2' => 'pc'),
            array('id_pp_property' =>  2, 'text_1' => 'pcs',                'text_2' => 'pcs'),
            array('id_pp_property' =>  3, 'text_1' => 'per pc',             'text_2' => 'per pc'),
            array('id_pp_property' =>  4, 'text_1' => 'item',               'text_2' => 'item'),
            array('id_pp_property' =>  5, 'text_1' => 'items',              'text_2' => 'items'),
            array('id_pp_property' =>  6, 'text_1' => 'per item',           'text_2' => 'per item'),
            array('id_pp_property' =>  7, 'text_1' => 'pack',               'text_2' => 'pack'),
            array('id_pp_property' =>  8, 'text_1' => 'packs',              'text_2' => 'packs'),
            array('id_pp_property' =>  9, 'text_1' => 'per pack',           'text_2' => 'per pack'),
            array('id_pp_property' => 10, 'text_1' => 'kg',                 'text_2' => 'lb'),
            array('id_pp_property' => 11, 'text_1' => 'per kg',             'text_2' => 'per lb'),
            array('id_pp_property' => 12, 'text_1' => 'per 100 g',          'text_2' => 'per 100 g'),
            array('id_pp_property' => 13, 'text_1' => 'gram',               'text_2' => 'oz'),
            array('id_pp_property' => 14, 'text_1' => 'per gram',           'text_2' => 'per oz'),
            array('id_pp_property' => 15, 'text_1' => 'm',                  'text_2' => 'ft'),
            array('id_pp_property' => 16, 'text_1' => 'per m',              'text_2' => 'per ft'),
            array('id_pp_property' => 17, 'text_1' => 'm<sup>2</sup>',      'text_2' => 'ft<sup>2</sup>'),
            array('id_pp_property' => 18, 'text_1' => 'per m<sup>2</sup>',  'text_2' => 'per ft<sup>2</sup>'),
            array('id_pp_property' => 19, 'text_1' => 'yd',                 'text_2' => 'yd'),
            array('id_pp_property' => 20, 'text_1' => 'per yd',             'text_2' => 'per yd'),
            array('id_pp_property' => 21, 'text_1' => 'cm',                 'text_2' => 'inch'),
            array('id_pp_property' => 22, 'text_1' => 'per cm',             'text_2' => 'per inch'),
            array('id_pp_property' => 23, 'text_1' => 'cm<sup>2</sup>',     'text_2' => 'inch<sup>2</sup>'),
            array('id_pp_property' => 24, 'text_1' => 'per cm<sup>2</sup>', 'text_2' => 'per inch<sup>2</sup>'),
            array('id_pp_property' => 25, 'text_1' => 'mm',                 'text_2' => 'inch'),
            array('id_pp_property' => 26, 'text_1' => 'per mm',             'text_2' => 'per inch'),
            array('id_pp_property' => 27, 'text_1' => 'mm<sup>2</sup>',     'text_2' => 'inch<sup>2</sup>'),
            array('id_pp_property' => 28, 'text_1' => 'per mm<sup>2</sup>', 'text_2' => 'per inch<sup>2</sup>'),
            array('id_pp_property' => 29, 'text_1' => 'pack(s)',            'text_2' => 'pack(s)'),
        );
        self::dbInsertLang('pp_property_lang', $rows, $languages);

        $types = array();
        foreach ($rows as $row) {
            $types[] = array('id_pp_property' => $row['id_pp_property'], 'type' => PProperties::PROPERTY_TYPE_GENERAL);
        }
        self::dbInsert('pp_property', $types);

        $rows = array(
            array(
                'id_pp_property' => 50,
                'text_1' => 'Product is sold by weight. The exact price will be calculated after the product is weighted.',
                'text_2' => 'Product is sold by weight. The exact price will be calculated after the product is weighted.'
            ),
            array(
                'id_pp_property' => 51,
                'text_1' => 'Product is ordered in items but sold by weight. The exact price will be calculated after the product is weighted.',
                'text_2' => 'Product is ordered in items but sold by weight. The exact price will be calculated after the product is weighted.'
            ),
            array(
                'id_pp_property' => 52,
                'text_1' => 'Ordering part of kg is allowed.',
                'text_2' => 'Ordering part of lb is allowed.'
            ),
            array(
                'id_pp_property' => 53,
                'text_1' => 'Ordering not whole number of meters is allowed.',
                'text_2' => 'Ordering not whole number of ft is allowed.'
            ),
            array(
                'id_pp_property' => 54,
                'text_1' => '<div>to specify part of a yard use:</div><pre>0.0625 for 1/16 yards<br>0.125  for  1/8 yards<br>0.25   for  1/4 yards<br>0.5    for  1/2 yards<br>0.75   for  3/4 yards</pre>',
                'text_2' => '<div>to specify part of a yard use:</div><pre>0.0625 for 1/16 yards<br>0.125  for  1/8 yards<br>0.25   for  1/4 yards<br>0.5    for  1/2 yards<br>0.75   for  3/4 yards</pre>'
            ),
            array(
                'id_pp_property' => 55,
                'text_1' => 'You can order part of the meter.',
                'text_2' => ''
            ),
            array(
                'id_pp_property' => 56,
                'text_1' => 'You cannot order less than {MIN}.',
                'text_2' => 'You cannot order less than {MIN}.'
            ),
            array(
                'id_pp_property' => 57,
                'text_1' => 'You cannot order more than {MAX}.',
                'text_2' => 'You cannot order more than {MAX}.'
            ),
            array(
                'id_pp_property' => 58,
                'text_1' => 'Enter your comment (optional)',
                'text_2' => 'Enter your comment (optional)'
            ),
        );
        self::dbInsertLang('pp_property_lang', $rows, $languages);

        $types = array();
        foreach ($rows as $row) {
            $types[] = array('id_pp_property' => $row['id_pp_property'], 'type' => PProperties::PROPERTY_TYPE_BLOCK_TEXT);
        }
        self::dbInsert('pp_property', $types);

        $rows = array(
            array('id_pp_property' => 70, 'text_1' => 'dimensions', 'text_2' => 'dimensions'),
            array('id_pp_property' => 71, 'text_1' => 'length:',    'text_2' => 'length:'),
            array('id_pp_property' => 72, 'text_1' => 'width:',     'text_2' => 'width:'),
            array('id_pp_property' => 73, 'text_1' => 'height:',    'text_2' => 'height'),
            array('id_pp_property' => 74, 'text_1' => 'depth:',     'text_2' => 'depth:'),
            array('id_pp_property' => 75, 'text_1' => 'square:',    'text_2' => 'square:'),
            array('id_pp_property' => 76, 'text_1' => 'perimeter:', 'text_2' => 'perimeter:'),
            array('id_pp_property' => 77, 'text_1' => 'weight:',    'text_2' => 'weight:'),
            array('id_pp_property' => 78, 'text_1' => 'Square',     'text_2' => 'Square'),
            array('id_pp_property' => 79, 'text_1' => 'Area',       'text_2' => 'Area'),
            array('id_pp_property' => 80, 'text_1' => 'Comment',    'text_2' => 'Comment'),
        );
        self::dbInsertLang('pp_property_lang', $rows, $languages);

        $types = array();
        foreach ($rows as $row) {
            $types[] = array('id_pp_property' => $row['id_pp_property'], 'type' => PProperties::PROPERTY_TYPE_EXT);
        }
        self::dbInsert('pp_property', $types);

        $rows = array(
            array(
                'id_pp_template' => 10, 'type' => 1, 'method' => 1,
                'title' => 70, 'property' => 75, 'text' => 17, 'explanation' => 55, 'minimum_quantity_text' => 56, 'maximum_quantity_text' => 57
            ),
            array(
                'id_pp_template' => 11, 'type' => 1, 'method' => 2,
                'title' => 70, 'property' => 76, 'text' => 15
            ),
        );
        self::dbInsertTemplateSpecial('pp_template_ext', 'method', $rows);

        $rows = array(
            array(
                'id_pp_template' => 10, 'id_ext_prop' => 1, 'position' => 1, 'property' => 71, 'text' => 15,
                'order_text' => 15, 'minimum_quantity' => 0.1, 'default_quantity' => 1
            ),
            array(
                'id_pp_template' => 10, 'id_ext_prop' => 2, 'position' => 2, 'property' => 72, 'text' => 15,
                'order_text' => 15, 'minimum_quantity' => 0.1, 'default_quantity' => 0.5
            ),
            array(
                'id_pp_template' => 11, 'id_ext_prop' => 1, 'position' => 1, 'property' => 71, 'text' => 15,
                'order_text' => 15, 'minimum_quantity' => 0.1, 'default_quantity' => 1
            ),
            array(
                'id_pp_template' => 11, 'id_ext_prop' => 2, 'position' => 2, 'property' => 72, 'text' => 15,
                'order_text' => 15, 'minimum_quantity' => 0.1, 'default_quantity' => 0.5
            ),
        );
        self::dbInsertTemplateSpecial('pp_template_ext_prop', 'position', $rows);
    }

    protected function dbData()
    {
        $data = array();
        $data[] = array(
            'func' => array($this, 'checkCartProductPrimaryKey')
        );
        $data[] = array(
            'func' => array($this, 'functionTrue'),
            'sql' => 'ALTER TABLE ' . _DB_PREFIX_ . 'specific_price CHANGE from_quantity from_quantity decimal(20,6) NOT NULL DEFAULT 0.000000'
        );
        $data[] = array(
            'func' => array($this, 'functionTrue'),
            'sql' => 'ALTER TABLE ' . _DB_PREFIX_ . 'order_detail CHANGE product_quantity_in_stock product_quantity_in_stock decimal(20,6) NOT NULL DEFAULT 0.000000'
        );
        $data[] = array(
            'func' => array($this, 'functionTrue'),
            'sql' => 'ALTER TABLE ' . _DB_PREFIX_ . 'order_detail CHANGE product_quantity_return product_quantity_return decimal(20,6) NOT NULL DEFAULT 0.000000'
        );
        $data[] = array(
            'func' => array($this, 'functionTrue'),
            'sql' => 'ALTER TABLE ' . _DB_PREFIX_ . 'order_detail CHANGE product_quantity_refunded product_quantity_refunded decimal(20,6) NOT NULL DEFAULT 0.000000'
        );
        $data[] = array(
            'func' => array($this, 'functionTrue'),
            'sql' => 'ALTER TABLE ' . _DB_PREFIX_ . 'order_detail CHANGE product_quantity_reinjected product_quantity_reinjected decimal(20,6) NOT NULL DEFAULT 0.000000'
        );
        $data[] = array(
            'func' => array($this, 'functionTrue'),
            'sql' => 'ALTER TABLE ' . _DB_PREFIX_ . 'order_slip_detail CHANGE product_quantity product_quantity decimal(20,6) NOT NULL DEFAULT 0.000000'
        );

        // tables
        $data[] = array(
            'table' => 'pp_template',
            'sql'   => '`id_pp_template` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `version` tinyint unsigned NOT NULL,
                        `qty_policy` tinyint unsigned NOT NULL DEFAULT 0,
                        `qty_mode` tinyint unsigned NOT NULL DEFAULT 0,
                        `measurement_system` tinyint unsigned NOT NULL DEFAULT 0,
                        `display_mode` smallint unsigned NOT NULL DEFAULT 0,
                        `price_display_mode` tinyint unsigned NOT NULL DEFAULT 0,
                        `unit_price_ratio` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `minimal_price_ratio` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `minimal_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `maximum_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `total_maximum_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `default_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `qty_step` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `qty_shift` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `qty_decimals` smallint unsigned NOT NULL DEFAULT 0,
                        `qty_values` varchar(256),
                        `qty_ratio` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `ext` tinyint unsigned NOT NULL DEFAULT 0,
                        `qty_available_display` tinyint unsigned NOT NULL DEFAULT 0,
                        `hidden` tinyint unsigned NOT NULL DEFAULT 0,
                        `customization` tinyint(1) unsigned NOT NULL DEFAULT 0,
                        `css` varchar(256)'
        );
        $data[] = array(
            'table' => 'pp_template_lang',
            'sql'   => '`id_pp_template` int(10) unsigned NOT NULL,
                        `id_lang` int(10) unsigned NOT NULL,
                        `name` varchar(48) NOT NULL,
                        `auto_desc_1` tinyint unsigned NOT NULL DEFAULT 0,
                        `description_1` text,
                        `auto_desc_2` tinyint unsigned NOT NULL DEFAULT 0,
                        `description_2` text,
                        PRIMARY KEY (`id_pp_template`, `id_lang`)'
        );
        $data[] = array(
            'table' => 'pp_template_property',
            'sql'   => '`id_pp_template` int(10) unsigned NOT NULL,
                        `pp_name` varchar(32) NOT NULL,
                        `id_pp_property` int(10) unsigned NOT NULL,
                        INDEX (id_pp_template)'
        );
        $data[] = array(
            'table' => 'pp_property',
            'sql'   => '`id_pp_property` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `type` int(10) NOT NULL',
            'options' => 'AUTO_INCREMENT = ' . PProperties::USER_START_ID
        );
        $data[] = array(
            'table' => 'pp_property_lang',
            'sql'   => '`id_pp_property` int(10) unsigned NOT NULL,
                        `id_lang` int(10) unsigned NOT NULL,
                        `text_1` text NOT NULL,
                        `text_2` text NOT NULL,
                        PRIMARY KEY (`id_pp_property`, `id_lang`)'
        );
        $data[] = array(
            'table' => 'pp_template_ext',
            'sql'   => '`id_pp_template` int(10) unsigned NOT NULL,
                        `type` tinyint unsigned NOT NULL DEFAULT 0,
                        `policy` tinyint unsigned NOT NULL DEFAULT 0,
                        `method` int(10) unsigned NOT NULL DEFAULT 0,
                        `title` int(10) unsigned NOT NULL DEFAULT 0,
                        `property` int(10) unsigned NOT NULL DEFAULT 0,
                        `text` int(10) unsigned NOT NULL DEFAULT 0,
                        `explanation` int(10) unsigned NOT NULL DEFAULT 0,
                        `precision` smallint signed NOT NULL DEFAULT 0,
                        `minimum_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `maximum_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `minimum_quantity_text` int(10) unsigned NOT NULL DEFAULT 0,
                        `maximum_quantity_text` int(10) unsigned NOT NULL DEFAULT 0,
                        `minimal_price_ratio` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        INDEX (id_pp_template)'
        );
        $data[] = array(
            'table' => 'pp_template_ext_prop',
            'sql'   => '`id_pp_template` int(10) unsigned NOT NULL,
                        `id_ext_prop` int(10) unsigned NOT NULL DEFAULT 0,
                        `position` tinyint unsigned NOT NULL DEFAULT 0,
                        `property` int(10) unsigned NOT NULL DEFAULT 0,
                        `text` int(10) unsigned NOT NULL DEFAULT 0,
                        `order_text` int(10) unsigned NOT NULL DEFAULT 0,
                        `minimum_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `maximum_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `default_quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `qty_step` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `qty_ratio` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        INDEX (id_pp_template)'
        );
        $data[] = array(
            'table' => 'pp_order_detail',
            'sql'   => '`id_pp_order_detail` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `id_order` int(10) unsigned NOT NULL,
                        `id_order_detail` int(10) unsigned NOT NULL,
                        `id_shop` int(11) unsigned NOT NULL,
                        `id_cart_product` int(10) unsigned NOT NULL,
                        `id_product` int(10) unsigned NOT NULL,
                        `id_product_attribute` int(10) unsigned DEFAULT NULL,
                        `quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
                        `data_type` varchar(32) NOT NULL,
                        `data` text,
                        INDEX `id_product` (`id_product`),
                        INDEX `id_product_id_product_attribute` (`id_product`, `id_product_attribute`),
                        INDEX `id_product_attribute` (`id_product_attribute`),
                        INDEX `id_order_id_order_detail` (`id_order`, `id_order_detail`)'
        );

        // columns
        $data[] = array(
            'column' => 'id_pp_template',
            'table'  => 'product',
            'sql'    => 'int(10) unsigned AFTER `weight`'
        );
        $data[] = array(
            'column' => 'quantity_remainder',
            'table'  => 'product',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `quantity`'
        );
        $data[] = array(
            'column' => 'minimal_quantity_fractional',
            'table'  => 'product',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `minimal_quantity`'
        );
        $data[] = array(
            'column' => 'minimal_quantity_fractional',
            'table'  => 'product_shop',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `minimal_quantity`'
        );
        $data[] = array(
            'column' => 'quantity_remainder',
            'table'  => 'product_attribute',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `quantity`'
        );
        $data[] = array(
            'column' => 'minimal_quantity_fractional',
            'table'  => 'product_attribute',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `minimal_quantity`'
        );
        $data[] = array(
            'column' => 'minimal_quantity_fractional',
            'table'  => 'product_attribute_shop',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `minimal_quantity`'
        );
        $data[] = array(
            'column' => 'id_cart_product',
            'table'  => 'cart_product',
            'sql'    => 'int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
        );
        $data[] = array(
            'column' => 'quantity_fractional',
            'table'  => 'cart_product',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `quantity`'
        );
        $data[] = array(
            'column' => 'pp_data_type',
            'table'  => 'cart_product',
            'sql'    => 'varchar(32) DEFAULT NULL AFTER `quantity_fractional`'
        );
        $data[] = array(
            'column' => 'pp_data',
            'table'  => 'cart_product',
            'sql'    => 'text AFTER `pp_data_type`'
        );
        $data[] = array(
            'column' => 'id_cart_product',
            'table'  => 'customization',
            'sql'    => 'int(10) unsigned NOT NULL AFTER `id_cart`'
        );
        $data[] = array(
            'column' => 'quantity_fractional',
            'table'  => 'customization',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `quantity`'
        );
        $data[] = array(
            'column' => 'id_cart_product',
            'table'  => 'order_detail',
            'sql'    => 'int(10) unsigned NOT NULL AFTER `id_order`'
        );
        $data[] = array(
            'column' => 'product_quantity_fractional',
            'table'  => 'order_detail',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `product_quantity`'
        );
        $data[] = array(
            'column' => 'pp_data_type',
            'table'  => 'order_detail',
            'sql'    => 'varchar(32) DEFAULT NULL'
        );
        $data[] = array(
            'column' => 'pp_data',
            'table'  => 'order_detail',
            'sql'    => 'text'
        );
        $data[] = array(
            'column' => 'physical_quantity_remainder',
            'table'  => 'stock',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `physical_quantity`'
        );
        $data[] = array(
            'column' => 'usable_quantity_remainder',
            'table'  => 'stock',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `usable_quantity`'
        );
        $data[] = array(
            'column' => 'physical_quantity_remainder',
            'table'  => 'stock_mvt',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `physical_quantity`'
        );
        $data[] = array(
            'column' => 'quantity_remainder',
            'table'  => 'stock_available',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `quantity`'
        );
        $data[] = array(
            'column' => 'physical_quantity_remainder',
            'table'  => 'stock_available',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `physical_quantity`'
        );
        $data[] = array(
            'column' => 'reserved_quantity_remainder',
            'table'  => 'stock_available',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `reserved_quantity`'
        );

        // upgrade
        $data[] = array(
            'column' => 'display_mode',
            'table'  => 'pp_template',
            'sql'    => 'smallint unsigned NOT NULL DEFAULT 0 AFTER `measurement_system`'
        );
        $data[] = array(
            'column' => 'price_display_mode',
            'table'  => 'pp_template',
            'sql'    => 'tinyint unsigned NOT NULL DEFAULT 0 AFTER `display_mode`'
        );
        $data[] = array(
            'column' => 'maximum_quantity',
            'table'  => 'pp_template',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `minimal_quantity`'
        );
        $data[] = array(
            'column' => 'total_maximum_quantity',
            'table'  => 'pp_template',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `maximum_quantity`'
        );
        $data[] = array(
            'column' => 'qty_step',
            'table'  => 'pp_template',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `default_quantity`'
        );
        $data[] = array(
            'column' => 'qty_shift',
            'table'  => 'pp_template',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `qty_step`'
        );
        $data[] = array(
            'column' => 'qty_decimals',
            'table'  => 'pp_template',
            'sql'    => 'smallint unsigned NOT NULL DEFAULT 0 AFTER `qty_shift`'
        );
        $data[] = array(
            'column' => 'qty_values',
            'table'  => 'pp_template',
            'sql'    => 'varchar(256) AFTER `qty_decimals`'
        );
        $data[] = array(
            'column' => 'qty_ratio',
            'table'  => 'pp_template',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `qty_values`'
        );
        $data[] = array(
            'column' => 'ext',
            'table'  => 'pp_template',
            'sql'    => 'tinyint unsigned NOT NULL DEFAULT 0 AFTER `qty_ratio`'
        );
        $data[] = array(
            'column' => 'minimal_price_ratio',
            'table'  => 'pp_template',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `unit_price_ratio`'
        );
        $data[] = array(
            'column' => 'customization',
            'table'  => 'pp_template',
            'sql'    => 'tinyint unsigned NOT NULL DEFAULT 0 AFTER `hidden`'
        );
        $data[] = array(
            'column' => 'css',
            'table'  => 'pp_template',
            'sql'    => 'varchar(64) AFTER `hidden`'
        );
        $data[] = array(
            'column' => 'qty_available_display',
            'table'  => 'pp_template',
            'sql'    => 'tinyint unsigned NOT NULL DEFAULT 0 AFTER `hidden`'
        );
        $data[] = array(
            'column' => 'policy',
            'table'  => 'pp_template_ext',
            'sql'    => 'tinyint unsigned NOT NULL DEFAULT 0 AFTER `type`'
        );
        $data[] = array(
            'column' => 'precision',
            'table'  => 'pp_template_ext',
            'sql'    => 'smallint signed NOT NULL DEFAULT 0 AFTER `explanation`'
        );
        $data[] = array(
            'column' => 'minimum_quantity',
            'table'  => 'pp_template_ext',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `decimals`'
        );
        $data[] = array(
            'column' => 'maximum_quantity',
            'table'  => 'pp_template_ext',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `minimum_quantity`'
        );

        $data[] = array(
            'column' => 'minimum_quantity_text',
            'table'  => 'pp_template_ext',
            'sql'    => 'int(10) unsigned NOT NULL AFTER `maximum_quantity`'
        );
        $data[] = array(
            'column' => 'maximum_quantity_text',
            'table'  => 'pp_template_ext',
            'sql'    => 'int(10) unsigned NOT NULL AFTER `minimum_quantity_text`'
        );
        $data[] = array(
            'column' => 'minimal_price_ratio',
            'table'  => 'pp_template_ext',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `maximum_quantity_text`'
        );
        $data[] = array(
            'column' => 'id_ext_prop',
            'table'  => 'pp_template_ext_prop',
            'sql'    => 'int(10) unsigned NOT NULL DEFAULT 0 AFTER `id_pp_template`'
        );
        $data[] = array(
            'column' => 'qty_ratio',
            'table'  => 'pp_template_ext_prop',
            'sql'    => 'decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `qty_step`'
        );
        $data[] = array(
            'func' => array($this, 'amend')
        );
        return $data;
    }

    public function amend()
    {
        $db = Db::getInstance();
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'pp_template` CHANGE `display_mode` `display_mode` smallint unsigned NOT NULL DEFAULT 0');
        $db->execute('UPDATE `' . _DB_PREFIX_ . 'pp_template_ext_prop` SET `id_ext_prop` = `position` WHERE `id_ext_prop` = 0');
        return false;
    }

    private static function dbInsert($table, $rows)
    {
        $db = PSM::getDB();
        foreach ($rows as $row) {
            $db->insert($table, $row, false, true, Db::INSERT_IGNORE);
        }
    }

    private static function dbInsertLang($table, $rows, $languages)
    {
        $db = PSM::getDB();
        foreach ($rows as $row) {
            foreach ($languages as $lang) {
                $row['id_lang'] = $lang['id_lang'];
                $db->insert($table, $row, false, true, Db::INSERT_IGNORE);
            }
        }
    }

    private static function dbInsertTemplateSpecial($table, $column, $rows)
    {
        $db = PSM::getDB();
        foreach ($rows as $row) {
            $r = $db->getRow('SELECT `id_pp_template` FROM `' . _DB_PREFIX_ . $table . '` WHERE `id_pp_template` = ' . (int) $row['id_pp_template'] . ' AND `' . $column . '`="' . pSQL($row[$column]) . '"');
            if ($r === false) {
                $db->insert($table, $row);
            }
        }
    }

    private function updateDB()
    {
        return $this->setupDB();
    }

    public function checkDbIntegrity()
    {
        $result = parent::checkDbIntegrity();
        if (count($result) > 0) {
            foreach ($result as $data) {
                switch ($data['key']) {
                    case 'table_not_found':
                        $this->module->integration_test_result[$this->l('Database')][] = sprintf($this->l('Missing table "%s"'), _DB_PREFIX_ . $data['table']);
                        break;
                    case 'column_not_found':
                        $this->module->integration_test_result[$this->l('Database')][] = sprintf($this->l('Missing column "%s" in table "%s"'), $data['column'], _DB_PREFIX_ . $data['table']);
                        break;
                    case 'column_definition':
                        $this->module->integration_test_result[$this->l('Database')][] = sprintf($this->l('Column "%s" in table "%s" not properly defined [%s]'), $data['column'], _DB_PREFIX_ . $data['table'], $data['sql']);
                        break;
                    default:
                        break;
                }
            }
            return false;
        }
        return true;
    }

    public function functionTrue()
    {
        return true;
    }

    public function checkCartProductPrimaryKey()
    {
        $db = Db::getInstance();
        $result = $db->getRow(
            'SELECT column_name FROM information_schema.columns
            WHERE table_schema = \'' . _DB_NAME_ . '\'
            AND table_name = \'' . _DB_PREFIX_ . 'cart_product\' AND column_key = \'PRI\''
        );
        // we need to drop cart_product table primary key
        if (is_array($result) && $result['column_name'] != 'id_cart_product') {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'cart_product` DROP PRIMARY KEY');
        }
        return false;
    }

    protected static function directoryListing($source, $skip_dirs = false, &$listing = null)
    {
        if (!is_array($listing)) {
            $listing = array();
        }
        if (is_dir($source)) {
            $exclude_files = self::excludeFiles();
            $directory = dir($source);
            while (false !== ($readdirectory = $directory->read())) {
                if (in_array($readdirectory, $exclude_files)) {
                    continue;
                }
                $path_dir = $source . '/' . $readdirectory;
                if (is_dir($path_dir)) {
                    self::directoryListing($path_dir, $skip_dirs, $listing);
                    continue;
                }
                if ($skip_dirs === false || !self::inString($path_dir, $skip_dirs)) {
                    $listing[] = $path_dir;
                }
            }
            $directory->close();
        } else {
            if (isset($path_dir) && ($skip_dirs === false || !self::inString($path_dir, $skip_dirs))) {
                $listing[] = $path_dir;
            }
        }
        return $listing;
    }

    protected static function inString($str, $find)
    {
        if (is_array($find)) {
            foreach ($find as $s) {
                if (Tools::strpos($str, $find) !== false) {
                    return true;
                }
            }
            return false;
        }
        return Tools::strpos($str, $find) !== false;
    }

    protected static function copyDirectory($source, $destination)
    {
        if (is_dir($source)) {
            mkdir($destination, 0755, true);
            $directory = dir($source);
            while (false !== ($readdirectory = $directory->read())) {
                if ($readdirectory == '.' || $readdirectory == '..') {
                    continue;
                }
                $path_dir = $source . '/' . $readdirectory;
                if (is_dir($path_dir)) {
                    self::copyDirectory($path_dir, $destination . '/' . $readdirectory);
                    continue;
                }
                Tools::copy($path_dir, $destination . '/' . $readdirectory);
            }
            $directory->close();
        } else {
            Tools::copy($source, $destination);
        }
    }

    protected static function removeDirectory($dir)
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            if (count($files) > 2) {
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..' && is_dir($dir . '/' . $file)) {
                        self::removeDirectory($dir . '/' . $file);
                    }
                }
            }
            $exclude_files = self::excludeFiles();
            $can_remove = true;
            $files = scandir($dir);
            foreach ($files as $file) {
                if (!in_array($file, $exclude_files)) {
                    $can_remove = false;
                    break;
                }
            }
            if ($can_remove) {
                $files = scandir($dir);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..' && is_file($dir . '/' . $file)) {
                        Tools::deleteFile($dir . '/' . $file);
                    }
                }
                rmdir($dir);
            }
        }
    }

    /**
     * @return array
     */
    protected static function excludeFiles()
    {
        $file = dirname(__FILE__) . '/ppsetup_exclude.inc';
        if (file_exists($file)) {
            // file format
            // .|..|php.ini|.htaccess|index.php|desktop.ini|.DS_Store|__MACOSX|.gitignore|.gitmodules|.svn
            $content = Tools::file_get_contents($file);
            return explode('|', $content);
        }
        return array('.', '..', 'php.ini', '.htaccess', 'index.php', 'desktop.ini', '.DS_Store', '.gitignore', '.gitmodules', '.svn', '__MACOSX');
    }

    public function l($string)
    {
        return $this->module->l($string, 'ppsetup');
    }

    private function getVersion($file)
    {
        if (is_file($file)) {
            $version = array();
            $content = Tools::file_get_contents($file);
            $version[self::VERSION_INDEX]          = self::getVersionBySignature($content, self::VERSION_SIGNATURE_START);
            $version[self::VERSION_REQUIRED_INDEX] = self::getVersionBySignature($content, self::VERSION_REQUIRED_SIGNATURE_START);
            return $version;
        }
        return false;
    }

    private function getVersionBySignature($content, $signature)
    {
        $start = Tools::strpos($content, $signature);
        if ($start !== false) {
            $end = Tools::strpos($content, self::VERSION_SIGNATURE_END, $start);
            if ($end !== false) {
                $length = Tools::strlen($signature);
                return Tools::substr($content, $start + $length, $end - $start - $length);
            }
        }
        return false;
    }

    private function getVersionSignature()
    {
        return self::VERSION_SIGNATURE_START . $this->module->version . self::VERSION_SIGNATURE_END;
    }

    public function smartyIntegrationString()
    {
        return "if(!file_exists(_PS_MODULE_DIR_.'pproperties/smarty/smarty.config.inc')){@ini_set('display_errors','on');@error_reporting(E_ALL);}" .
            "require_once(_PS_MODULE_DIR_.'pproperties/smarty/smarty.config.inc');";
    }

    private function getXmlMd5File($filename, $node_name, $ps_version = false)
    {
        $filename = dirname(__FILE__) . '/config/' . $filename;
        if (is_file($filename)) {
            $checksum = @simplexml_load_file($filename);
        } else {
            if ($ps_version !== false) {
                $upgrader = new Upgrader();
                libxml_set_streams_context(@stream_context_create(array('http' => array('timeout' => 3))));
                $checksum = @simplexml_load_file($upgrader->rss_md5file_link_dir . $ps_version . '.xml');
                if ($checksum != false) {
                    $checksum->asXML($filename);
                }
            }
        }

        if (isset($checksum) && $checksum != false) {
            $fileslist_md5 = $this->md5FileAsFilesArray($checksum->{$node_name}[0]);
            return $fileslist_md5;
        }
        return false;
    }

    private function md5FileAsFilesArray($node, &$current_path = array(), $level = 1, &$result = null)
    {
        if (!array($result)) {
            $result = array();
        }
        foreach ($node as $child) {
            if (is_object($child) && $child->getName() == 'dir') {
                $current_path[$level] = (string) $child['name'];
                $this->md5FileAsFilesArray($child, $current_path, $level + 1, $result);
            } elseif (is_object($child) && $child->getName() == 'md5file') {
                $relative_path = '';
                for ($i = 1; $i < $level; $i++) {
                    $relative_path .= $current_path[$i] . '/';
                }
                $relative_path .= (string) $child['name'];
                $result[$relative_path] = (string) $child;
            }
        }
        return $result;
    }

    private function backupFile($file, $force = false)
    {
        if (is_file($file)) {
            $dir = self::getBackupDirectory();
            $rel_file = str_replace(_PS_ROOT_DIR_, '', $file);
            $dest_dir = $dir . str_replace(_PS_ROOT_DIR_, '', dirname($file));
            $dest_file = $dir . $rel_file;

            if ($force) {
                Tools::deleteFile($dest_file);
            }
            if (!is_file($dest_file)) {
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                Tools::copy($file, $dest_file);
                $timestamp = filemtime($file);
                if ($timestamp !== false) {
                    touch($dest_file, $timestamp);
                }
            }
        }
    }

    private function restoreFile($file)
    {
        if (is_file($file)) {
            $dir = self::getBackupDirectory();
            $rel_file = str_replace(_PS_ROOT_DIR_, '', $file);
            $backup_file = $dir . $rel_file;
            if (is_file($backup_file)) {
                Tools::copy($backup_file, $file);
                $timestamp = filemtime($backup_file);
                if ($timestamp !== false) {
                    touch($file, $timestamp);
                }
                Tools::deleteFile($backup_file);
            }
        }
    }

    private function cleanup()
    {
        self::removeDirectory(self::getBackupRootDirectory());
    }

    private static function getBackupRootDirectory()
    {
        return _PS_MODULE_DIR_ . 'pproperties-backup';
    }

    private static function getBackupDirectory()
    {
        return self::getBackupRootDirectory() . '/' . _PS_VERSION_;
    }

    private static function truncateString($text, $length = 120)
    {
        if (is_array($text)) {
            $text = implode(' ', $text);
        }
        return Tools::truncateString($text, $length);
    }
}
