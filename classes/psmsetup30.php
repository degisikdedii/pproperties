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
class PSMSetup30
{
    const REGEX_START_SIGNATURE = '/* --- DO NOT REMOVE OR MODIFY THE BLOCK BELOW [modified] %s%s --- */';
    const REGEX_MIDDLE_SIGNATURE = '/* --- [original] %s ---';
    const REGEX_END_SIGNATURE = '--- DO NOT REMOVE OR MODIFY THE BLOCK ABOVE %s --- */';

    protected $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function adminTab($class_name, $enable = null)
    {
        $id_lang = Context::getContext()->language->id;
        $tabs = Tab::getCollectionFromModule($this->module->name, $id_lang);
        if (count($tabs) == 0) {
            if ($enable !== null) {
                $this->installAdminTab($class_name, $enable);
            }
        } else {
            $id_parent = 0;
            foreach ($tabs as $tab) {
                if ($tab->class_name == $class_name && (($tab->active && $enable === null) || (bool) $tab->active != (bool) $enable)) {
                    $id_parent = $tab->id_parent;
                    if (!$id_parent) {
                        $parent = 'AdminPSAndMore';
                        $id_parent = (int) Tab::getIdFromClassName($parent);
                        if ($id_parent) {
                            $tab->id_parent = $id_parent;
                        }
                    }
                    $tab->active = $enable === null ? 1 : ((bool) $enable ? 1 : 0);
                    $tab->name = array($id_lang => $tab->name);
                    $this->normalizeTab($tab);
                    $tab->save();
                }
            }
            if ($id_parent) {
                $tabs = Tab::getTabs((int) Context::getContext()->language->id, $id_parent);
                if (!empty($tabs)) {
                    $active = false;
                    foreach ($tabs as $tab) {
                        $active |= (bool) $tab['active'];
                    }
                    $tab = new Tab($id_parent);
                    if (Validate::isLoadedObject($tab) && (bool) $tab->active != (bool) $active) {
                        $tab->active = ((bool) $active ? 1 : 0);
                        $tab->name = array();
                        $this->normalizeTab($tab, 'PS&More', false);
                        $tab->save();
                    }
                }
            }
        }
    }

    public function installAdminTab($class_name, $active = true, $display_name = null, $parent = null)
    {
        if ($parent == null) {
            $parent = 'AdminPSAndMore';
            $id_tab = (int) Tab::getIdFromClassName($parent);
            if (!$id_tab) {
                $tab = new Tab();
                $tab->class_name = $parent;
                $tab->active = true;
                $tab->name = array();
                $this->normalizeTab($tab, 'PS&More', false);
                $tab->add();
            }
        }

        $id_tab = (int) Tab::getIdFromClassName($class_name);
        if (!$id_tab) {
            $tab = new Tab();
            $tab->class_name = $class_name;
            $tab->active = (bool) $active;
            $tab->name = array();
            $this->normalizeTab($tab, $display_name);
            $tab->id_parent = (int) Tab::getIdFromClassName($parent);
            $tab->module = $this->module->name;
            return $tab->add();
        }
        return true;
    }

    private function normalizeTab($tab, $display_name = null, $translate = true)
    {
        if (empty($display_name)) {
            $display_name = !empty($this->module->adminTabName) ? $this->module->adminTabName : $this->module->displayName;
            if (empty($display_name)) {
                $display_name = $this->module->name;
            }
        }
        foreach (Language::getLanguages() as $lang) {
            if ($translate) {
                if (version_compare(_PS_VERSION_, '1.7.6.4', '>=')) {
                    $name = Translate::getModuleTranslation(
                        $this->module,
                        $display_name,
                        $this->module->name,
                        null,
                        false,
                        !empty($lang['locale']) ? $lang['locale'] : null,
                        false, // set $fallback = false to avoid corrupted translation for some languages
                        false // escape = false
                    );
                } elseif (version_compare(_PS_VERSION_, '1.7.6.0', '<')) {
                    $name = Translate::getModuleTranslation(
                        $this->module,
                        $display_name,
                        $this->module->name,
                        null,
                        false,
                        !empty($lang['locale']) ? $lang['locale'] : null
                    );
                } else {
                    $name = Translate::getModuleTranslation(
                        $this->module,
                        $display_name,
                        $this->module->name,
                        null,
                        false,
                        !empty($lang['locale']) ? $lang['locale'] : null,
                        false // set $fallback = false to avoid corrupted translation for some languages
                    );
                }
            } else {
                $name = $display_name;
            }
            $tab->name[$lang['id_lang']] = !empty($name) ? $name : $display_name;
        }
        // The value for the default must always be set.
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        if (!isset($tab->name[$default_lang])) {
            $tab->name[$default_lang] = $display_name;
        }
    }

    public function uninstallAdminTab($class_name)
    {
        $id_tab = (int) Tab::getIdFromClassName($class_name);
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    public function setupDB()
    {
        $result = true;
        $db = PSM::getDB();
        $db_data = $this->dbData();
        foreach ($db_data as $data) {
            reset($data);
            switch (key($data)) {
                case 'table':
                    $table = $data['table'];
                    if (!$this->dbTableExists($table)) {
                        $sql = 'CREATE TABLE IF NOT EXISTS`' . _DB_PREFIX_ . $table . '` (';
                        $sql .= $data['sql'];
                        $sql .= ') ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
                        if (isset($data['options'])) {
                            $sql .= ' ' . $data['options'];
                        }
                        $sql .= ';';
                        if ($db->execute($sql) === false) {
                            $result = false;
                        }
                    }
                    break;
                case 'column':
                    $table = $data['table'];
                    $column = $data['column'];
                    $sql = $data['sql'];
                    $info = $this->dbColumnInfo($table, $column);
                    $column_exists = ($info && count($info) > 0);
                    if ($column_exists) {
                        if ((stripos($sql, 'PRIMARY') !== false && stripos($info[0]['Key'], 'PRI') === false) ||
                            (stripos($sql, 'AUTO_INCREMENT') !== false && stripos($info[0]['Extra'], 'auto_increment') === false)
                        ) {
                            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` DROP PRIMARY KEY');
                            if ($db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` CHANGE `' . $column . '` `' . $column . '` ' . $sql) === false) {
                                $result = false;
                            }
                        }
                    } else {
                        if (!$this->dbTableExists($table) || $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `' . $column . '` ' . $sql) === false) {
                            $result = false;
                        }
                    }
                    break;
                case 'func':
                    $ret = (isset($data['param']) ? call_user_func($data['func'], $data['param']) : call_user_func($data['func']));
                    if ($ret && ($db->execute($data['sql']) === false)) {
                        $result = false;
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }

    public function checkDbIntegrity()
    {
        $result = array();
        $db_data = $this->dbData();
        foreach ($db_data as $data) {
            reset($data);
            switch (key($data)) {
                case 'table':
                    $table = $data['table'];
                    if (!$this->dbTableExists($table)) {
                        $result[] = array('key' => 'table_not_found', 'table' => $table);
                    }
                    break;
                case 'column':
                    $table = $data['table'];
                    $column = $data['column'];
                    $sql = $data['sql'];
                    $info = $this->dbColumnInfo($table, $column);
                    $column_exists = ($info && count($info) > 0);
                    if ($column_exists) {
                        if (stripos($sql, 'PRIMARY') !== false && stripos($info[0]['Key'], 'PRI') === false) {
                            $result[] = array('key' => 'column_definition', 'table' => $table, 'column' => $column, 'sql' => $sql);
                        } elseif (stripos($sql, 'AUTO_INCREMENT') !== false && stripos($info[0]['Extra'], 'auto_increment') === false) {
                            $result[] = array('key' => 'column_definition', 'table' => $table, 'column' => $column, 'sql' => $sql);
                        }
                    } else {
                        $result[] = array('key' => 'column_not_found', 'table' => $table, 'column' => $column);
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }

    protected function dbData()
    {
        return array();
    }

    public function replaceStrings($filename, $param, $install_mode)
    {
        if (isset($param['ignore']) && $param['ignore'] === true) {
            return '';
        }
        if (!file_exists($filename)) {
            return 'file_not_found';
        }
        if (isset($param['backup'])) {
            if (isset($param['backup']['ext'])) {
                $backup = $filename . $param['backup']['ext'];
                if (!file_exists($backup)) {
                    Tools::copy($filename, $backup);
                    if (!file_exists($backup)) {
                        return 'backup_failed';
                    }
                }
            }
        }
        $count = $duplicates_count = 0;
        $content = $this->convertEol($param, Tools::file_get_contents($filename));
        if (!$this->when($param, $content)) {
            return '';
        }
        $override = Tools::strpos($filename, 'override/') !== false;
        if (isset($param['replace'])) {
            $params = $this->normalizeParams($param['replace'], $override);
            foreach ($params as $choice) {
                $done = false;
                foreach ($choice as $args) {
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        $search = $replace = false;
                        if ($install_mode) {
                            $search = $this->convertEol($param, $args[0]);
                            if (isset($args['include'])) {
                                $include = $this->convertEol($param, Tools::file_get_contents($args['include']));
                                if ($include) {
                                    $replace = str_replace('<?php' . "\n", '', $include);
                                }
                            } else {
                                $replace = $this->convertEol($param, $args[1], $indent);
                            }
                        } else {
                            if (!isset($args['uninstall']) || $args['uninstall'] !== false) {
                                if (isset($args['include'])) {
                                    $include = $this->convertEol($param, Tools::file_get_contents($args['include']));
                                    if ($include) {
                                        $search = str_replace('<?php' . "\n", '', $include);
                                    }
                                } else {
                                    $search = $this->convertEol($param, $args[1], $indent);
                                }
                                $replace = $this->convertEol($param, $args[0]);
                            }
                        }
                        if ($search && $replace) {
                            $content = str_replace($search, $replace, $content, $cnt);
                            if (array_key_exists('fix', $args)) {
                                $this->fix($args, $search, $replace, $content);
                            }
                            if ($cnt == 0) {
                                $search = str_replace(array(' . ', '(int) ', '(float) '), array('.', '(int)', '(float)'), $search, $cnt);
                                $content = str_replace($search, $replace, $content, $cnt);
                            }
                            $done = $cnt > 0;
                            $count += $cnt;
                        }
                    }
                }
            }
        }
        if (isset($param['append'])) {
            $params = $this->normalizeParams($param['append'], $override);
            foreach ($params as $choice) {
                $done = false;
                foreach ($choice as $args) {
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        if ($install_mode) {
                            $search = $this->convertEol($param, $args[0]);
                            $replace = $this->convertEol($param, $args[1], $indent, 'append');
                            // remove occasional duplicates
                            $duplicates_cnt = 0;
                            do {
                                $content = str_replace($replace . $replace, '', $content, $duplicates_cnt);
                            } while ($duplicates_cnt > 0);
                            $duplicates_count += $duplicates_cnt;
                            $replace = $search . $replace;
                            // check if target string already exists
                            $pos = strpos($content, $replace);
                            if ($pos !== false) {
                                unset($search);
                            }
                        } else {
                            if (!isset($args['uninstall']) || $args['uninstall'] !== false) {
                                $replace = $this->convertEol($param, $args[0]);
                                $search = $replace . $this->convertEol($param, $args[1], $indent, 'append');
                            } else {
                                unset($search);
                            }
                        }
                        if (isset($search)) {
                            if (isset($args['limit'])) {
                                if ($args['limit'] == 'first' || $args['limit'] == 'last') {
                                    $pos = ($args['limit'] == 'first' ? strpos($content, $search) : strrpos($content, $search));
                                    if ($pos === false) {
                                        $search = str_replace(array(' . ', '(int) ', '(float) '), array('.', '(int)', '(float)'), $search);
                                        $pos = ($args['limit'] == 'first' ? strpos($content, $search) : strrpos($content, $search));
                                    }
                                    if ($pos !== false) {
                                        $cnt = 1;
                                        $content = substr_replace($content, $replace, $pos, psm_strlen($search));
                                    }
                                }
                            } else {
                                $content = str_replace($search, $replace, $content, $cnt);
                                if ($cnt == 0) {
                                    $search = str_replace(array(' . ', '(int) ', '(float) '), array('.', '(int)', '(float)'), $search);
                                    $content = str_replace($search, $replace, $content, $cnt);
                                }
                            }
                            $done = $cnt > 0;
                            $count += $cnt;
                        }
                    }
                }
            }
        }
        if (isset($param['prepend'])) {
            $params = $this->normalizeParams($param['prepend'], $override);
            foreach ($params as $choice) {
                $done = false;
                foreach ($choice as $args) {
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        if ($install_mode) {
                            $search = $this->convertEol($param, $args[0]);
                            $replace = $this->convertEol($param, $args[1], $indent, 'prepend');
                            // remove occasional duplicates
                            $duplicates_cnt = 0;
                            do {
                                $content = str_replace($replace . $replace, '', $content, $duplicates_cnt);
                            } while ($duplicates_cnt > 0);
                            $duplicates_count += $duplicates_cnt;
                            $replace = $replace . $search;
                            // check if target string already exists
                            $pos = strpos($content, $replace);
                            if ($pos !== false) {
                                unset($search);
                            }
                        } else {
                            if (!isset($args['uninstall']) || $args['uninstall'] !== false) {
                                $replace = $this->convertEol($param, $args[0]);
                                $search = $this->convertEol($param, $args[1], $indent, 'prepend') . $replace;
                            } else {
                                unset($search);
                            }
                        }
                        if (isset($search)) {
                            $cnt = 0;
                            if (isset($args['limit'])) {
                                if ($args['limit'] == 'first' || $args['limit'] == 'last') {
                                    $pos = ($args['limit'] == 'first' ? strpos($content, $search) : strrpos($content, $search));
                                    if ($pos === false) {
                                        $search = str_replace(array(' . ', '(int) ', '(float) '), array('.', '(int)', '(float)'), $search);
                                        $pos = ($args['limit'] == 'first' ? strpos($content, $search) : strrpos($content, $search));
                                    }
                                    if ($pos !== false) {
                                        $cnt = 1;
                                        $content = substr_replace($content, $replace, $pos, psm_strlen($search));
                                    }
                                }
                            } else {
                                $content = str_replace($search, $replace, $content, $cnt);
                                if ($cnt == 0) {
                                    $search = str_replace(array(' . ', '(int) ', '(float) '), array('.', '(int)', '(float)'), $search);
                                    $content = str_replace($search, $replace, $content, $cnt);
                                }
                            }
                            $done = $cnt > 0;
                            $count += $cnt;
                        }
                    }
                }
            }
        }
        if (isset($param['regex'])) {
            $params = $this->normalizeParams($param['regex'], $override);
            foreach ($params as $choice) {
                $done = false;
                foreach ($choice as $args) {
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        $pattern = $args[0];
                        if (isset($args['include'])) {
                            $include = $this->convertEol($param, Tools::file_get_contents($args['include']));
                            if ($include) {
                                $replacement = str_replace('<?php' . "\n", '', $include);
                            } else {
                                $replacement = '';
                            }
                        } else {
                            $replacement = $this->convertEol($param, $args[1], $indent);
                        }
                        $md5 = md5($pattern);
                        if ($install_mode) {
                            if ($replacement) {
                                $cnt = 0;
                                $middle = "\n" . sprintf(self::REGEX_MIDDLE_SIGNATURE, $md5) . "\n";
                                $end = "\n" . sprintf(self::REGEX_END_SIGNATURE, $md5);
                                $_content = PSMHelper::psmsetupReplaceStringsRegexPregReplaceCallback($pattern, $content, $replacement, $cnt, $middle, $end, $md5);
                                // $_content = preg_replace_callback(
                                //     $pattern,
                                //     function ($matches) use (&$pattern, &$replacement, &$cnt, &$middle, &$end, &$md5) {
                                //         $mark = rand(1000, 9999) . ':';
                                //         $start = sprintf(self::REGEX_START_SIGNATURE, $mark, $md5) . "\n";
                                //         $match = reset($matches);
                                //         $replace = preg_replace($pattern, $replacement, $match);
                                //         $cnt++;
                                //         return $start . $replace . $middle . $mark . bin2hex($match) . $end;
                                //     },
                                //     $content
                                // );
                                if ($_content !== null) {
                                    $done = $cnt > 0;
                                    $count += $cnt;
                                    $content = $_content;
                                }
                            }
                        } elseif (!isset($args['uninstall']) || $args['uninstall'] !== false) {
                            $originals = $this->findOriginalsInReplaceStrings($content, $md5);
                            foreach ($originals as $mark => $original) {
                                $expected_string = preg_replace($pattern, $replacement, $original);
                                if ($expected_string !== null) {
                                    $restore_pattern = '#(?P<MATCH>' . preg_quote(sprintf(self::REGEX_START_SIGNATURE, $mark, $md5)) . '(.+?)' . preg_quote(sprintf(self::REGEX_END_SIGNATURE, $md5)) . ')#s';
                                    $_content = preg_replace($restore_pattern, $original, $content, 1, $cnt);
                                    if ($_content !== null && $cnt == 1) {
                                        $content = $_content;
                                        $count += $cnt;
                                    }
                                }
                            }
                            $done = $cnt > 0;
                        }
                    }
                }
            }
        }
        if ($count > 0 || $duplicates_count > 0) {
            file_put_contents($filename, $content);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filename);
            }
        }
        return '';
    }

    public function checkReplacedStrings($filename, $param)
    {
        $result = array();
        if (isset($param['ignore']) && $param['ignore'] === true) {
            return $result;
        }
        if (!file_exists($filename)) {
            if (!isset($param['optional']) || $param['optional'] !== true) {
                $result[] = array('file_not_found' => $filename);
            }
            return $result;
        }
        if (isset($param['backup'])) {
            if (isset($param['backup']['ext'])) {
                $backup = $filename . $param['backup']['ext'];
                if (!file_exists($backup)) {
                    $result[] = array('backup_failed' => $backup);
                    return $result;
                }
            }
        }
        $content = $this->convertEol($param, Tools::file_get_contents($filename));
        if (!$this->when($param, $content)) {
            return $result;
        }
        $override = Tools::strpos($filename, 'override/') !== false;
        if (isset($param['replace'])) {
            $params = $this->normalizeParams($param['replace'], $override);
            foreach ($params as $choice) {
                $done = false;
                $x = count($choice);
                while ($x--) {
                    $args = $choice[$x];
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        $count = (isset($args['count']) ? (int) $args['count'] : 0);
                        if ($count >= 0 || _PS_MODE_DEV_ === true) {
                            $replacement = '';
                            if (isset($args['include'])) {
                                $include = $this->convertEol($param, Tools::file_get_contents($args['include']));
                                if ($include) {
                                    $replacement = str_replace('<?php' . "\n", '', $include);
                                }
                            } else {
                                $replacement = $this->convertEol($param, $args[1], $indent);
                            }
                            if ($replacement) {
                                str_replace($replacement, '', $content, $cnt);
                            } else {
                                $cnt = 0;
                            }
                            $done = $cnt > 0;
                            if ($x == 0) { // last iteration
                                if ($count == 0) {
                                    if ($cnt == 0) {
                                        $result[] = array('string_not_found' => array($args[0], $replacement));
                                    }
                                } elseif ($count > 0) {
                                    if ($count != $cnt) {
                                        $result[] = array('string_count' => array($args[0], $replacement, $count, $cnt));
                                    }
                                } elseif ($cnt == 0 && _PS_MODE_DEV_ === true && $count != -2) {
                                    $result[] = array('string_not_found_note' => array($args[0], $replacement));
                                }
                            }
                        }
                    }
                }
            }
        }
        if (isset($param['append'])) {
            $params = $this->normalizeParams($param['append'], $override);
            foreach ($params as $choice) {
                $done = false;
                $x = count($choice);
                while ($x--) {
                    $args = $choice[$x];
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        $count = (isset($args['count']) ? (int) $args['count'] : 0);
                        if ($count >= 0 || _PS_MODE_DEV_ === true) {
                            $replace = $this->convertEol($param, $args[1], $indent, 'append');
                            $replacement = $this->convertEol($param, $args[0]) . $replace;
                            // check for occasional duplicates
                            str_replace($replacement . $replace, '', $content, $duplicates_cnt);
                            if ($duplicates_cnt > 0) {
                                $result[] = array('duplicate_string_found' => $replace . $replace);
                            } else {
                                str_replace($replacement, '', $content, $cnt);
                                $done = $cnt > 0;
                                if ($x == 0) { // last iteration
                                    if ($count == 0) {
                                        if ($cnt == 0) {
                                            $result[] = array('string_not_found' => array($args[0], $replacement));
                                        }
                                    } elseif ($count > 0) {
                                        if ($count != $cnt) {
                                            $result[] = array('string_count' => array($args[0], $replacement, $count, $cnt));
                                        }
                                    } elseif ($cnt == 0 && _PS_MODE_DEV_ === true && $count != -2) {
                                        $result[] = array('string_not_found_note' => array($args[0], $replacement));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (isset($param['prepend'])) {
            $params = $this->normalizeParams($param['prepend'], $override);
            foreach ($params as $choice) {
                $done = false;
                $x = count($choice);
                while ($x--) {
                    $args = $choice[$x];
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        $count = (isset($args['count']) ? (int) $args['count'] : 0);
                        if ($count >= 0 || _PS_MODE_DEV_ === true) {
                            $replace = $this->convertEol($param, $args[1], $indent, 'prepend');
                            $replacement = $replace . $this->convertEol($param, $args[0]);
                            // check for occasional duplicates
                            str_replace($replace . $replacement, '', $content, $duplicates_cnt);
                            if ($duplicates_cnt > 0) {
                                $result[] = array('duplicate_string_found' => $replace . $replace);
                            } else {
                                str_replace($replacement, '', $content, $cnt);
                                $done = $cnt > 0;
                                if ($x == 0) { // last iteration
                                    if ($count == 0) {
                                        if ($cnt == 0) {
                                            $result[] = array('string_not_found' => array($args[0], $replacement));
                                        }
                                    } elseif ($count > 0) {
                                        if ($count != $cnt) {
                                            $result[] = array('string_count' => array($args[0], $replacement, $count, $cnt));
                                        }
                                    } elseif ($cnt == 0 && _PS_MODE_DEV_ === true && $count != -2) {
                                        $result[] = array('string_not_found_note' => array($args[0], $replacement));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (isset($param['regex'])) {
            $params = $this->normalizeParams($param['regex'], $override);
            foreach ($params as $choice) {
                $done = false;
                $x = count($choice);
                while ($x--) {
                    $args = $choice[$x];
                    if (!$done && $this->when($args, $content)) {
                        $indent = $this->indent($args);
                        $count = (isset($args['count']) ? (int) $args['count'] : 0);
                        if ($count >= 0 || _PS_MODE_DEV_ === true) {
                            $cnt = 0;
                            $pattern = $args[0];
                            $replacement = $this->convertEol($param, $args[1], $indent);
                            $md5 = md5($pattern);
                            $originals = $this->findOriginalsInReplaceStrings($content, $md5);
                            foreach ($originals as $mark => $original) {
                                $expected_string = preg_replace($pattern, $replacement, $original);
                                if ($expected_string !== null) {
                                    $modified_pattern = '#' . preg_quote(sprintf(self::REGEX_START_SIGNATURE, $mark, $md5)) . '\s*(?P<MATCH>.+?)\s*' . preg_quote(sprintf(self::REGEX_MIDDLE_SIGNATURE, $md5)) . '#s';
                                    preg_match($modified_pattern, $content, $matches);
                                    if (PREG_NO_ERROR === preg_last_error() && isset($matches['MATCH'])) {
                                        $found_string = $matches['MATCH'];
                                        if (strcmp(preg_replace('/\s+/', '', $expected_string), preg_replace('/\s+/', '', $found_string)) === 0) {
                                            $cnt++;
                                        }
                                    }
                                }
                            }
                            $done = $cnt > 0;
                            if ($x == 0) { // last iteration
                                if ($count == 0) {
                                    if ($cnt == 0) {
                                        $result[] = array('string_not_found' => array($args[0], $replacement));
                                    }
                                } elseif ($count > 0) {
                                    if ($count != $cnt) {
                                        $result[] = array('string_count' => array($args[0], $replacement, $count, $cnt));
                                    }
                                } elseif ($cnt == 0 && _PS_MODE_DEV_ === true && $count != -2) {
                                    $result[] = array('string_not_found_note' => array($args[0], $replacement));
                                }
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    private function findOriginalsInReplaceStrings(&$content, $md5)
    {
        $originals = array();
        $pattern = '#' . preg_quote(sprintf(self::REGEX_MIDDLE_SIGNATURE, $md5)) . '\s*(?P<MATCH>[^\-]+?)\s*' . preg_quote(sprintf(self::REGEX_END_SIGNATURE, $md5)) . '#s';
        preg_match_all($pattern, $content, $matches);
        if (PREG_NO_ERROR === preg_last_error() && isset($matches['MATCH'])) {
            if (isset($matches['MATCH'])) {
                foreach ($matches['MATCH'] as $original) {
                    $originals[Tools::substr($original, 0, 5)] = hex2bin(Tools::substr($original, 5));
                }
            }
        }
        return $originals;
    }

    public function dbTableExists($table)
    {
        $result = PSM::getDB()->executeS('SHOW TABLE STATUS FROM `' . _DB_NAME_ . '` like \'' . _DB_PREFIX_ . $table . '\'');
        return ($result && count($result) > 0);
    }

    public function dbColumnExists($table, $column)
    {
        $result = $this->dbColumnInfo($table, $column);
        return ($result && count($result) > 0);
    }

    public function dbColumnInfo($table, $column)
    {
        return PSM::getDB()->executeS('SHOW COLUMNS FROM `' . _DB_NAME_ . '`.`' . _DB_PREFIX_ . $table . '` like \'' . $column . '\'');
    }

    public function setupSmarty($install)
    {
        $line = $this->smartyIntegrationString();
        if ($line !== false) {
            $file = $this->smartyConfigFile();
            if ($install) {
                PSM::appendLine($file, $line);
            } else {
                PSM::removeLine($file, $line);
            }
        }
    }

    public function checkSmartyIntegrity()
    {
        $line = $this->smartyIntegrationString();
        if ($line !== false) {
            $file = $this->smartyConfigFile();
            return (strpos(Tools::file_get_contents($file), $line) !== false);
        }
        return true;
    }

    public function smartyIntegrationString()
    {
        return false;
    }

    public function smartyConfigFile($fullpath = true)
    {
        return (($fullpath ? _PS_ROOT_DIR_ . '/' : '') . 'config/smarty.config.inc.php');
    }

    protected function appendInitFunctionLine($source_file, $target_file, $func, $signature)
    {
        if (file_exists($source_file) && file_exists($target_file)) {
            $content = Tools::file_get_contents($source_file);
            if (strpos($content, 'function ' . $func) !== false) {
                $class = basename($target_file, '.php');
                $str = $class . '::' . $func . ';';
                $content = Tools::file_get_contents($target_file);
                if (strpos($content, $str) === false) {
                    if ($signature) {
                        $str .= ' // ' . $signature;
                    }
                    file_put_contents($target_file, "\n" . $str . "\n", FILE_APPEND);
                    if (function_exists('opcache_invalidate')) {
                        opcache_invalidate($target_file);
                    }
                }
            }
        }
    }

    protected function convertEol(array $param, $str, $indent = '', $mode = null)
    {
        if (array_key_exists('eol', $param) && $param['eol'] === false) {
            return $str;
        }
        if (is_array($str)) {
            $str = implode("\n", $str);
            if ($mode == 'append') {
                $str = "\n" . $str;
            } elseif ($mode == 'prepend') {
                $str = $str . "\n";
            }
        }
        $str = preg_replace('#(\r\n|\r)#ism', "\n", $str);
        if ($indent) {
            $str = str_replace("\n", "\n{$indent}", $str);
            $str = str_replace("\n{$indent}\n", "\n\n", $str);
        }
        return $str;
    }

    protected function indent($args)
    {
        if (array_key_exists('indent', $args)) {
            if (!is_array($args[1])) {
                trigger_error(sprintf('ppsetup: "indent" specified, but corresponding argument %s is not an array.', $args[1]));
            }
            return $args['indent'];
        }
        return '';
    }

    protected function when($args, $content)
    {
        if (isset($args['when'])) {
            foreach ($args['when'] as $condition => $value) {
                switch ($condition) {
                    case 'found':
                        return strpos($content, $value) !== false;
                    case 'not found':
                        return strpos($content, $value) === false;
                    default:
                        trigger_error(sprintf('ppsetup: "when" specified, but condition "%s" not recognized.', $condition));
                        break;
                }
            }
        }
        return true;
    }

    protected function fix($args, $search, $replace, &$content)
    {
        if (isset($args['fix'])) {
            foreach ($args['fix'] as $condition => $value) {
                switch ($condition) {
                    case 'replace all':
                        do {
                            $content = str_replace($search, $replace, $content, $cnt);
                        } while ($cnt > 0);
                        break;
                    default:
                        trigger_error(sprintf('ppsetup: "fix" specified, but condition "%s" not recognized.', $condition));
                        break;
                }
            }
        }
    }

    private function normalizeParams($params, $override)
    {
        $normalized = array();
        foreach ($params as $args) {
            if (!isset($args['ignore']) || $args['ignore'] !== true) {
                if (isset($args['choice'])) {
                    $choice = array();
                    foreach ($args['choice'] as $key => $arg) {
                        if (!isset($arg['ignore']) || $arg['ignore'] !== true) {
                            if (!$override || !PSM::endsWith($arg[1], 'Core(', false)) {
                                $choice[] = $arg;
                            }
                        }
                    }
                } else {
                    if (!$override || !PSM::endsWith($args[1], 'Core(', false)) {
                        $choice = array($args);
                    }
                }
                if (!empty($choice)) {
                    $normalized[] = $choice;
                }
            }
        }
        return $normalized;
    }
}
