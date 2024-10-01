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
class PPSetupEx extends PPSetup
{
    public function setup()
    {
        if (!$this->install_mode) {
            $this->processMailFiles(false);
        }
        return $this->processFiles(true);
    }

    public function checkIntegrity()
    {
        $this->processMailFiles(true);
        return $this->processFiles(false);
    }

    private function processFiles($replace)
    {
        $is_ps_version_1_7_7_or_later = version_compare(_PS_VERSION_, '1.7.7.0', '>=');
        $is_ps_version_1_7_8_or_later = version_compare(_PS_VERSION_, '1.7.8.0', '>=');
        // $data_dir = dirname(__FILE__).'/data/';
        list($prefix_l, $_) = $this->resolveThemeJsStringPrefixSuffix(1, ['.default.on("updatedCart"', '().on("updatedCart"'], 'l');
        list($prefix_e, $suffix_e) = $this->resolveThemeJsStringPrefixSuffix(3, ['.is(":focus")?null:', '.is(":focus")||!'], 'e');
        list($prefix_t, $_) = $this->resolveThemeJsStringPrefixSuffix(1, '.is(":focus"))return null;return ', 't');
        list($prefix_p, $_) = $this->resolveThemeJsStringPrefixSuffix(1, '.checkUpdateOpertation', null, 'pp.CheckUpdateQuantityOperations');
        $theme = (defined('_PARENT_THEME_NAME_') && _PARENT_THEME_NAME_ ? _PARENT_THEME_NAME_ : _THEME_NAME_);
        if (in_array($theme, ['panda', 'transformer'])) {
            $theme = 'sunnytoo'; // https://www.sunnytoo.com
        }
        $themefiles = array(
            array(
                'files'  => array('assets/js/theme.js'),
                'backup' => array('ext' => '.PS' . _PS_VERSION_),
                'append' => array(
                                array(
                                    'choice' => array(
                                        array(
                                            '=".js-cart-line-product-quantity',
                                            '-disabled-by-pp',
                                            'count' => 1,
                                            'ignore' => $theme == 'sunnytoo'
                                        ),
                                        array(
                                            '= ".js-cart-line-product-quantity',
                                            '-disabled-by-pp',
                                            'count' => 1,
                                            'ignore' => $theme == 'sunnytoo'
                                        ),
                                        array(
                                            "='.js-cart-line-product-quantity",
                                            '-disabled-by-pp',
                                            'count' => 1,
                                            'ignore' => $theme == 'sunnytoo'
                                        ),
                                        array(
                                            "= '.js-cart-line-product-quantity",
                                            '-disabled-by-pp',
                                            'count' => 1,
                                            'ignore' => $theme == 'sunnytoo'
                                        ),
                                    ),
                                ),
                            ),
                'replace'=> array(
                                array(
                                    'choice' => array(
                                        array(
                                            "{$prefix_e}.is(\":focus\")?null:{$prefix_e}",
                                            "{$prefix_e}.is(\":focus\")||!{$prefix_e}.length?null:{$prefix_e}",
                                            'uninstall' => false
                                        ),
                                        array(
                                            ".is(\":focus\")?null:{$suffix_e}",
                                            ".is(\":focus\")||!{$suffix_e}.length?null:{$suffix_e}",
                                            'uninstall' => false
                                        ),
                                        array(
                                            "{$prefix_e}.is(\":focus\") ? null : {$prefix_e}",
                                            "{$prefix_e}.is(\":focus\") || !{$prefix_e}.length ? null : {$prefix_e}",
                                            'uninstall' => false
                                        ),
                                        array(
                                            "if({$prefix_t}.is(\":focus\"))return null;return {$prefix_t}",
                                            "if({$prefix_t}.is(\":focus\")||!{$prefix_t}.length)return null;return {$prefix_t}",
                                            'uninstall' => false
                                        ),
                                        array(
                                            'if ($input.is(\':focus\')) {',
                                            'if ($input.is(\':focus\') || !$input.length) {',
                                        ),
                                        array(
                                            'if($input.is(\':focus\')){',
                                            'if($input.is(\':focus\')||!$input.length){',
                                        ),
                                    ),
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    'choice' => array(
                                        array(
                                            "{$prefix_l}.default.on(\"updatedCart\"",
                                            "{$prefix_l}.default.on(\"pp.CheckUpdateQuantityOperations.checkUpdateOpertation\",function(t){{$prefix_p}.checkUpdateOpertation(t)}),",
                                            'ignore' => empty($prefix_p)
                                        ),
                                        array(
                                            "{$prefix_l}().on(\"updatedCart\"",
                                            "{$prefix_l}().on(\"pp.CheckUpdateQuantityOperations.checkUpdateOpertation\",function(t){{$prefix_p}.checkUpdateOpertation(t)}),",
                                            'ignore' => empty($prefix_p)
                                        ),
                                        array(
                                            "prestashop.on(\"updatedCart\"",
                                            "prestashop.on(\"pp.CheckUpdateQuantityOperations.checkUpdateOpertation\",function(t){{$prefix_p}.checkUpdateOpertation(t)}),",
                                            'ignore' => empty($prefix_p)
                                        ),
                                    ),
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/product-add-to-cart.tpl'),
                'prepend'=> array(
                                array(
                                    "{block name='product_minimal_quantity'}",
                                    array('{hook h="displayProductPproperties" product=$product type="explanation"}'),
                                    'indent' => '    '
                                ),
                            ),
                'append' => array(
                                    array(
                                        '$product.minimal_quantity > 1}',
                                        '{if isset($product.minimum_quantity_to_display)}{assign var=minimum_quantity_to_display value=$product.minimum_quantity_to_display}{else}{assign var=minimum_quantity_to_display value=$product.minimal_quantity}{/if}'
                                    ),
                    array(
                                    'value="{$product.quantity_wanted}"',
                                    ' data-value="{$product.quantity_wanted}"',
                                ),
                            ),
                'replace'=> array(
                                array(
                                    'type="number"',
                                    'type=\'text\'',
                                    'count' => -2,
                                ),
                                array(
                                    '=> $product.minimal_quantity',
                                    '=> $minimum_quantity_to_display'
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/product-details.tpl'),
                'replace'=> array(
                                array(
                                    '{$product.quantity} {$product.quantity_label}</span>',
                                    '{if isset($product.quantity_to_display)}{$product.quantity_to_display nofilter}{else}{$product.quantity} {$product.quantity_label}{/if}</span>',
                                    'ignore' => $theme == 'sunnytoo',
                                    'count' => 0
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/product-discounts.tpl'),
                'optional'=> $theme != 'classic',
                'replace'=> array(
                                array(
                                    '<td>{$quantity_discount.quantity}</td>',
                                    '<td>{$quantity_discount.quantity|formatQty}</td>',
                                    'count' => ($theme == 'classic' ? 0 : -1)
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/product-flags.tpl'),
                'optional'=> true,
                'replace'=> array(
                                array(
                                    '{$flag.label}',
                                    '{$flag.label nofilter}',
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/product-prices.tpl'),
                'replace'=> array(
                                array(
                                    '{$product.regular_price}</span>',
                                    '{if isset($product.regular_price_to_display)}{$product.regular_price_to_display nofilter}{else}{$product.regular_price}{/if}</span>',
                                    'count' => ($theme == 'classic' ? 0 : -1)
                                ),
                                array(
                                    '{$product.price}',
                                    '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price }{/if}</span>',
                                    'count' => ($theme == 'classic' ? 0 : -1)
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/miniatures/product.tpl'),
                'replace'=> array(
                                array(
                                    'choice' => array( // avoid replacing js-product-miniature-wrapper in warehouse theme
                                        array(
                                            'js-product-miniature"',
                                            'js-product-miniature{if isset($product.pp_css) && !empty($product.pp_css)} {$product.pp_css}{/if} id_pp_template_{$product.id_pp_template}"',
                                            'ignore' => $theme == 'sunnytoo',
                                            'count' => 1
                                        ),
                                        array(
                                            'js-product-miniature ',
                                            'js-product-miniature{if isset($product.pp_css) && !empty($product.pp_css)} {$product.pp_css}{/if} id_pp_template_{$product.id_pp_template} ',
                                            'ignore' => $theme == 'sunnytoo',
                                            'count' => 1
                                        ),
                                    ),
                                ),
                                array(
                                    '{$product.price}',
                                    '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price }{/if}',
                                    'ignore' => in_array($theme, array('warehouse', 'sunnytoo')),
                                    'count' => ($theme == 'classic' ? 0 : -1)
                                ),
                                array(
                                    '{$product.discount_amount_to_display}',
                                    '{$product.discount_amount_to_display nofilter}',
                                    'count' => -1
                                ),
                                array(
                                    '{$flag.label}',
                                    '{$flag.label nofilter}',
                                    'when' => ['not found' => 'product-flags.tpl'],
                                    'count' => -1
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/_partials/miniatures/_partials/product-miniature-1.tpl', 'templates/catalog/_partials/miniatures/_partials/product-miniature-2.tpl', 'templates/catalog/_partials/miniatures/_partials/product-miniature-3.tpl'),
                'ignore' => $theme != 'warehouse',
                'replace'=> array(
                                array(
                                    '{$product.price}</span>',
                                    '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price}{/if}</span>',
                                    'count' => -1
                                ),
                                array(
                                    "{\$product.price}\n",
                                    "{if isset(\$product.price_to_display)}{\$product.price_to_display nofilter}{else}{\$product.price}{/if}\n",
                                    'count' => -1
                                ),
                                array(
                                    '{$product.regular_price}</span>',
                                    '{if isset($product.regular_price_to_display)}{$product.regular_price_to_display nofilter}{else}{$product.regular_price}{/if}</span>',
                                    'count' => -1
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/product.tpl'),
                'ignore' => $theme == 'sunnytoo',
                'when' => ['not found' => 'product-flags.tpl'],
                'replace'=> array(
                                array(
                                    '{$flag.label}',
                                    '{$flag.label nofilter}',
                                    'count' => -1
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/catalog/product.tpl', 'templates/catalog/_partials/quickview.tpl'),
                'append' => array(
                                array(
                                    'id="add-to-cart-or-refresh">',
                                    array('{hook h="displayProductPproperties" product=$product type="add-to-cart"}'),
                                    'count' => 1,
                                    'indent' => '                '
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/checkout/_partials/cart-detailed-product-line.tpl'),
                'replace'=> array(
                                array(
                                    'type="number"',
                                    'type=\'text\'',
                                    'count' => -2,
                                ),
                                array(
                                    '{$product.price}</span>',
                                    '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price}{/if}</span>'
                                ),
                                array(
                                    '{$product.discount_to_display}',
                                    '{$product.discount_to_display nofilter}',
                                    'count' => -1
                                ),
                                array(
                                    '{$product.unit_price_full}',
                                    '{$product.unit_price_full nofilter}'
                                ),
                                array(
                                    '{$product.total}',
                                    '{if isset($product.total_to_display)}{$product.total_to_display nofilter}{else}{$product.total }{/if}'
                                ),
                                array(
                                    'value="{$product.quantity}"',
                                    'value="{if isset($product.pp_product_quantity)}{$product.pp_product_quantity}{else}{$product.quantity}{/if}" data-value="{if isset($product.pp_product_quantity)}{$product.pp_product_quantity}{else}{$product.quantity}{/if}"'
                                ),
                                array(
                                    '{if $product.customizations|count}',
                                    '{if is_array($product.customizations) && $product.customizations|count}',
                                    'count' => -2,
                                    'uninstall' => false
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    'class="product-line-grid',
                                    '{if isset($product.id_cart_product)}data-icp="{$product.id_cart_product}" {/if}{if isset($product.pp_settings)}data-pp-settings="{$product.pp_settings|json_encode|pp_safeoutput:htmlspecialchars nofilter}" {/if}',
                                    'limit' => 'first',
                                    'count' => 1,
                                ),
                                array(
                                    '{if is_array($product.customizations) && $product.customizations|count}',
                                    array('{hook h="displayProductPproperties" product=$product type="product-name-addional-info"}'),
                                    'indent' => '    ',
                                ),
                            ),
                'append' => array(
                                array(
                                    '<span class="gift-quantity">{$product.quantity}</span>',
                                    array(
                                        '{elseif !empty($product.pp_data_type) and (\'bulk\' === $product.pp_data_type)}',
                                        '  <span class="cart-non-editable-quantity">{$product.quantity}</span>'
                                    ),
                                    'indent' => '            ',
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/checkout/_partials/cart-summary-product-line.tpl'),
                'replace'=> array(
                                array(
                                    'choice' => array(
                                        array(
                                            'x{$product.quantity}</span>',
                                            '{if isset($product.cart_quantity_to_display)}{$product.cart_quantity_to_display nofilter}{else}x{$product.quantity}{/if}</span>',
                                            'ignore' => ($theme == 'sunnytoo')
                                        ),
                                        array(
                                            '>{$product.quantity}<',
                                            '>{if isset($product.cart_quantity_to_display)}{$product.cart_quantity_to_display nofilter}{else}{$product.quantity}{/if}<',
                                            'ignore' => ($theme == 'sunnytoo')
                                        ),
                                    ),
                                ),
                                array(
                                    '{$product.price}</span>',
                                    '{$product.total}</span>',
                                    'uninstall' => false
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/checkout/_partials/footer.tpl'),
                'optional' => true,
                'append' => array(
                                array(
                                    "d='Shop.Theme.Global'}",
                                    ' <span class="powered_by_psandmore_placeholder"></span>',
                                    'count' => -2,
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/checkout/_partials/order-confirmation-table.tpl', $theme == 'warehouse' ? 'templates/checkout/_partials/order-confirmation-table-simple.tpl' : ''),
                'replace'=> array(
                                array(
                                    'choice' => array(
                                        array(
                                            '{$product.price}</div>',
                                            '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price}{/if}</div>'
                                        ),
                                        array(
                                            '{$product.price}</span></div>',
                                            '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price}{/if}</span></div>'
                                        ),
                                        array(
                                            '{$product.price}</strong></div>',
                                            '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price}{/if}</strong></div>'
                                        ),
                                        array(
                                            '{$product.price}',
                                            '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price }{/if}',
                                            'count' => 1
                                        ),
                                    ),
                                ),
                                array(
                                    '{$product.quantity}</div>',
                                    '{if isset($product.cart_quantity_to_display)}{$product.cart_quantity_to_display nofilter}{else}{$product.quantity}{/if}</div>'
                                ),
                                array(
                                    'choice' => array(
                                        array(
                                            '{$product.total}</div>',
                                            '{if isset($product.total_to_display)}{$product.total_to_display nofilter}{else}{$product.total}{/if}</div>'
                                        ),
                                        array(
                                            '{$product.total}</span></div>',
                                            '{if isset($product.total_to_display)}{$product.total_to_display nofilter}{else}{$product.total}{/if}</span></div>'
                                        ),
                                    ),
                                ),
                                array(
                                    '{if $product.customizations|count}',
                                    '{if is_array($product.customizations) && $product.customizations|count}',
                                    'count' => -2,
                                    'uninstall' => false
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    '{if is_array($product.customizations) && $product.customizations|count}',
                                    array('{hook h="displayProductPproperties" product=$product type="_partials/order-confirmation-table"}'),
                                    'indent' => '            '
                                ),
                            ),
            ),
            array(
                'files'  => array('templates/customer/_partials/order-detail-no-return.tpl', 'templates/customer/_partials/order-detail-return.tpl'),
                'replace'=> array(
                                array(
                                    '$product.reference',
                                    '$product.product_reference',
                                    'count' => -2,
                                    'uninstall' => false
                                ),
                                array(
                                    '{$product.price}',
                                    '{if isset($product.price_to_display)}{$product.price_to_display nofilter}{else}{$product.price }{/if}'
                                ),
                                array(
                                    '{$product.total}',
                                    '{if isset($product.total_to_display)}{$product.total_to_display nofilter}{else}{$product.total }{/if}'
                                ),
                                array(
                                    '{$product.quantity}',
                                    '{if isset($product.cart_quantity_to_display)}{$product.cart_quantity_to_display nofilter}{else}{$product.quantity }{/if}'
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    '{if $product.product_reference}',
                                    array('{hook h="displayProductPproperties" product=$product type="product-name-addional-info"}'),
                                    'indent' => '            ',
                                ),
                            ),
            ),
        );

        $smartysysplugins = array(
            array(
                'files' => array('smarty_internal_compile_foreach.php'),
                'replace'=> array(
                    array(
                        "\$output .= '?>';",
                        "\$output .= '?>'".'.PP::smartyCompile(\'foreach\', \'open\', $item, $compiler->smarty);',
                        'count' => 1
                    ),
                    array(
                        '$output .= "?>";',
                        '$output .= "?>".PP::smartyCompile(\'foreach\', \'close\', $itemVar, $compiler->smarty);',
                        'count' => 1
                    ),
                ),
            ),
        );

        $root = array(
            array(
                'files'  => array('classes/Attribute.php'),
                'replace'=> array(
                                array( // getAttributeMinimalQty
                                    'SELECT `minimal_quantity`',
                                    'SELECT `id_product`',
                                    'count' => 1
                                ),
                            ),
                'prepend'=> array(
                                array( // getAttributeMinimalQty
                                    'if ($minimalQuantity > 1) {',
                                    array(
                                        'if ($minimalQuantity > 0) {',
                                        '    $product = (array) new Product($minimalQuantity);',
                                        '    $product[\'id_product_attribute\'] = $idProductAttribute;',
                                        '    \PP::productResolveQuantities($product);',
                                        '    return (isset($product[\'minimum_quantity\']) ? $product[\'minimum_quantity\'] : false);',
                                        '}',
                                    ),
                                    'indent' => '        ',
                                    'count' => 1
                                ),
                            )
            ),
            array(
                'files'  => array('classes/Carrier.php'),
                'replace'=> array(
                                array(
                                    '$cart_quantity += $cart_product[\'cart_quantity\'];',
                                    '$cart_quantity += PP::resolveQty($cart_product);',
                                ),
                                array(
                                    '$cart_weight += ($cart_product[\'weight_attribute\'] * $cart_product[\'cart_quantity\']);',
                                    '$cart_weight += ($cart_product[\'weight_attribute\'] * PP::resolveQty($cart_product));',
                                ),
                                array(
                                    '$cart_weight += ($cart_product[\'weight\'] * $cart_product[\'cart_quantity\']);',
                                    '$cart_weight += ($cart_product[\'weight\'] * PP::resolveQty($cart_product));',
                                )
                            )
            ),
            array(
                'files'  => array('classes/Cart.php'),
                'replace'=> array(
                                array(
                                    'class CartCore extends ObjectModel',
                                    'class CartCore extends CartBase'
                                ),
                                array(
                                    'public function getProducts(',
                                    'public function getProductsCore('
                                ),
                                array(
                                    'protected function applyProductCalculations(',
                                    'protected function applyProductCalculationsCore('
                                ),
                                array(
                                    'private function getCartPrices(',
                                    'private function getCartPricesCore(',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.1', '<')
                                ),
                                array(
                                    'private function getCartPriceFromCatalog(',
                                    'private function getCartPriceFromCatalogCore(',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.1', '<')
                                ),
                                array(
                                    'private function getOrderPrices(',
                                    'private function getOrderPricesCore(',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.1', '<')
                                ),
                                array(
                                    'public function getProductQuantity(',
                                    'public function getProductQuantityCore(',
                                ),
                                array(
                                    'public function updateQty(',
                                    'public function updateQtyCore('
                                ),
                                array(
                                    'protected function _updateCustomizationQuantity(',
                                    'protected function _updateCustomizationQuantityCore('
                                ),
                                array(
                                    'public function deleteProduct(',
                                    'public function deleteProductCore('
                                ),
                                array(
                                    'public function checkQuantities(',
                                    'public function checkQuantitiesCore('
                                ),
                                array(
                                    'public function duplicate(',
                                    'public function duplicateCore('
                                ),
                                array( // getLastProduct
                                    'SELECT `id_product`, `id_product_attribute`, id_shop',
                                    'SELECT `id_cart_product`, `id_product`, `id_product_attribute`, id_shop'
                                ),
                                array( // getLastProduct
                                    'if ($result && isset($result[\'id_product\']) && $result[\'id_product\'])',
                                    'if ($result && isset($result[\'id_product\']) && $result[\'id_product\'] && isset($result[\'id_cart_product\']))',
                                ),
                                array( // getTotalWeight
                                    '$total_weight += $product[\'weight\'] * $product[\'cart_quantity\'];',
                                    '$total_weight += $product[\'weight\'] * \PP::resolveQty($product);'
                                ),
                                array( // getTotalWeight
                                    '$total_weight += $product[\'weight_attribute\'] * $product[\'cart_quantity\'];',
                                    '$total_weight += $product[\'weight_attribute\'] * \PP::resolveQty($product);'
                                ),
                                array( // updateProductWeight
                                    '* cp.`quantity`)',
                                    '* '.PP::sqlQty('quantity', 'cp').')'
                                ),
                                array( // updateProductWeight
                                    '* c.`quantity`)',
                                    '* '.PP::sqlQty('quantity', 'c').')'
                                ),
                                array( // getProductCustomization
                                    'SELECT cu.id_customization, cd.index, cd.value, cd.type, cu.in_cart, cu.quantity',
                                    'SELECT cu.id_customization, cd.index, cd.value, cd.type, cu.in_cart,  cu.quantity, cu.quantity_fractional, cu.id_cart_product'
                                ),
                                array( // setNoMultishipping
                                    'GROUP BY id_product, id_product_attribute',
                                    'GROUP BY id_cart_product'
                                ),
                                array( // setNoMultishipping
                                    "AND id_product_attribute = ' . \$product['id_product_attribute'];",
                                    array(
                                        "AND id_product_attribute = ' . \$product['id_product_attribute'] . '",
                                        "AND id_cart_product = ' . \$product['id_cart_product'];",
                                    ),
                                    'indent' => '                        '
                                ),
                                array( // checkAllProductsAreStillAvailableInThisState
                                    '&& $product[\'id_product_attribute\'] === \'0\'',
                                    '&& (\'bulk\' === PP::getPPDataType($product) ? $product[\'id_product_attribute\'] !== \'0\' : $product[\'id_product_attribute\'] === \'0\')',
                                    'ignore' => !$is_ps_version_1_7_7_or_later
                                ),
                            ),
                'append' => array(
                                array( // setNoMultishipping
                                    'AND (cp1.id_product_attribute = cp2.id_product_attribute)',
                                    array('AND (cp1.id_cart_product = cp2.id_cart_product)'),
                                    'indent' => '                        '
                                ),
                            ),
                'prepend' => array(
                                array(
                                    $is_ps_version_1_7_7_or_later ? 'protected static $_nbProducts = [];' : 'protected static $_nbProducts = array();',
                                    '//'
                                ),
                                array(
                                    $is_ps_version_1_7_7_or_later ? 'protected static $_totalWeight = [];' : 'protected static $_totalWeight = array();',
                                    '//'
                                ),
                                array(
                                    $is_ps_version_1_7_7_or_later ? 'protected static $_attributesLists = [];' : 'protected static $_attributesLists = array();',
                                    '//'
                                ),
                                array( // isAllProductsInStock
                                    '$idProductAttribute = !empty($product[\'id_product_attribute\']) ? $product[\'id_product_attribute\'] : null;',
                                    [
                                        'if (\'bulk\' === PP::getPPDataType($product)) {',
                                        '    return PP::bulkCheckQuantities($product, PP::getProductBulk($product));',
                                        '}',
                                    ],
                                    'indent' => '            ',
                                ),
                            ),
                // 'regex'=> array(
                //                 array(
                //                     '#(\h*public function getProducts\(.*)(\$sql->select\(\'cp.[^)].+?\);)(.+?\})#s',
                //                     '',
                //                     'include' => $data_dir.'classes/Cart_getProducts.inc.php'
                //                 ),
                //             )
            ),
            array(
                'files'  => array('classes/CartRule.php'),
                'replace'=> array(
                                array(
                                    '$price * $product[\'cart_quantity\']',
                                    '$price * \PP::resolveQty($product)'
                                ),
                            )
            ),
            array(
                'files'  => array('classes/Combination.php'),
                'append' => array(
                                array(
                                    'public $minimal_quantity = 1;',
                                    array('public $minimal_quantity_fractional = 0;'),
                                    'indent' => '    '
                                ),
                            )
            ),
            array(
                'files'  => array('classes/ObjectModel.php'),
                'append' => array(
                                array(
                                    '$definition[\'classname\'] = $class;',
                                    array(
                                        'static $pp_exists = null;',
                                        'if ($pp_exists === null) {',
                                        '    $pp_exists = method_exists(\'\PP\', \'objectModelGetDefinition\');',
                                        '}',
                                        'if ($pp_exists) {',
                                        '    \PP::objectModelGetDefinition($class, $definition);',
                                        '}',
                                    ),
                                'indent' => '            '
                                ),
                            )
            ),
            array(
                'files'  => array('classes/PaymentModule.php'),
                'replace'=> array(
                                array( // validateOrder
                                    'true, $product[\'cart_quantity\'], false,',
                                    'true, array($product[\'cart_quantity\'], $product[\'cart_quantity_fractional\']), false,'
                                ),
                                array( // validateOrder
                                    '\'price\' => Tools::',
                                    '\'price\' => isset($presented_product[\'total_to_display\']) ? $presented_product[\'total_to_display\'] : Tools::'
                                ),
                                array( // validateOrder
                                    '\'quantity\' => $product[\'quantity\'],',
                                    '\'quantity\' => isset($presented_product[\'cart_quantity_to_display\']) ? $presented_product[\'cart_quantity_to_display\'] : $product[\'quantity\'],'
                                ),
                                array( // validateOrder
                                    '$product_var_tpl[\'unit_price\'] = Tools::',
                                    '$product_var_tpl[\'unit_price\'] = isset($presented_product[\'price_to_display\']) ? $presented_product[\'price_to_display\'] : Tools::'
                                ),
                                array( // validateOrder
                                    '$product_var_tpl[\'unit_price_full\'] = Tools::',
                                    '$product_var_tpl[\'unit_price_full\'] = $product_var_tpl[\'unit_price\'] // Tools::'
                                ),
                                array( // validateOrder
                                    '$customized_datas = Product::getAllCustomizedDatas((int) $order->id_cart, null, true, null, (int) $product[\'id_customization\']);',
                                    '$customized_datas = PP::filterProductCustomizedDatas($product, Product::getAllCustomizedDatas((int) $order->id_cart, null, true, null, (int) $product[\'id_customization\']));'
                                ),
                            ),
                'prepend'=> array(
                                array( // validateOrder
                                    'foreach ($order->product_list as $product) {',
                                    array('$presented_order = PP::orderPresenterPresent($order);'),
                                    'indent' => '                    ',
                                ),
                                array( // validateOrder
                                    $is_ps_version_1_7_7_or_later ? '$product_var_tpl = [' : '$product_var_tpl = array(',
                                    array('$presented_product = PP::findProduct($presented_order, $product);'),
                                    'indent' => '                        ',
                                ),
                            ),
                'append' => array(
                                array( // validateOrder
                                    '$product[\'name\'] . (isset($product[\'attributes\']) ? \' - \' . $product[\'attributes\'] : \'\')',
                                    ' . Hook::exec(\'displayProductPproperties\', [\'product\' => $presented_product, \'type\' => \'PaymentModule::validateOrder\'])'
                                ),
                                array( // validateOrder
                                    $is_ps_version_1_7_7_or_later ? 'Hook::exec(\'actionValidateOrder\', [' : 'Hook::exec(\'actionValidateOrder\', array(',
                                    array('\'presented_order\' => $presented_order,'),
                                    'indent' => '                        ',
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/PrestaShopAutoload.php'),
                'prepend'=> array(
                                array(
                                    'rename($tmpFile, $filename);',
                                    array(
                                        '// If the file exists "rename" can fail with access denied message, remove it first.',
                                        '@unlink($filename);',
                                        'if (is_file($filename)) {',
                                        '    // File handler is not immediately released, especially in Windows.',
                                        '    // Wait for a while, but not very long.',
                                        '    for ($i = 0; $i < 5; $i++) {',
                                        '        time_nanosleep(0, 100000000); // 0.1 sec',
                                        '        clearstatcache(true, $filename); // essential line, because results of is_file are cached',
                                        '        if (!is_file($filename)) {',
                                        '            break;',
                                        '        }',
                                        '    }',
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                            )
            ),
            array(
                'files'  => array('classes/Product.php'),
                'replace'=> array(
                                array(
                                    'class ProductCore extends ObjectModel',
                                    'class ProductCore extends ProductBase'
                                ),
                                array(
                                    "'-' . (int) \$quantity . '-'",
                                    "'-' . (float) \$quantity . '-'"
                                ),
                                array(
                                    "SUM(`quantity`)",
                                    'SUM(' . PP::sqlQty('quantity') . ')'
                                ),
                                array(
                                    "\$id_cart . '-' . (int) \$real_quantity",
                                    "\$id_cart . '-' . (float) \$real_quantity"
                                ),
                                array( // updateAttribute, addAttribute
                                    '$combination->minimal_quantity = (int) $minimal_quantity;',
                                    '$this->setMinQty($minimal_quantity, $combination);',
                                    'count' => 2
                                ),
                                array( // addAttribute
                                    'SELECT SUM(quantity) as quantity',
                                    'SELECT SUM(quantity + quantity_remainder) as quantity'
                                ),
                                array( // addAttribute
                                    $is_ps_version_1_7_7_or_later ? "['quantity' => 0]" : "array('quantity' => 0)",
                                    $is_ps_version_1_7_7_or_later ? "['quantity' => 0, 'quantity_remainder' => 0]" : "array('quantity' => 0, 'quantity_remainder' => 0)"
                                ),
                                array( // getDefaultAttribute, getNewProducts, getPricesDrop, getAttributesGroups, getAccessories
                                    'IFNULL(stock.quantity, 0)',
                                    '(IFNULL( stock.quantity, 0 ) + IFNULL( stock.quantity_remainder, 0 ))'
                                ),
                                array( // getPriceStatic
                                    '$cart_quantity = (int)',
                                    '$cart_quantity = (float)'
                                ),
                                array( // getPriceStatic
                                    'Cache::retrieve($cache_id) != (int) $quantity)',
                                    'Cache::retrieve($cache_id) != (float) $quantity)'
                                ),
                                array( // getDefaultAttribute
                                    '(int) $minimum_quantity',
                                    '(float) $minimum_quantity'
                                ),
                                array(
                                    'public static function convertPrice(',
                                    'public static function convertPriceCore('
                                ),
                                array(
                                    'public static function convertPriceWithCurrency(',
                                    'public static function convertPriceWithCurrencyCore('
                                ),
                                array(
                                    'public static function displayWtPrice(',
                                    'public static function displayWtPriceCore('
                                ),
                                array(
                                    'public static function displayWtPriceWithCurrency(',
                                    'public static function displayWtPriceWithCurrencyCore('
                                ),
                                array( // getProductProperties -- this statement should be before changing (int) to (float)
                                    '$quantity = (int) $row[\'minimal_quantity\'];',
                                    '$quantity = (isset($row[\'default_quantity\']) ? (float) $row[\'default_quantity\'] : (float) $row[\'minimal_quantity\']);'
                                ),
                                array( // getProductProperties
                                    '(int) $row[\'quantity_wanted\']',
                                    '(float) $row[\'quantity_wanted\']',
                                ),
                                array( // getProductProperties
                                    '(int) $row[\'minimal_quantity\']',
                                    '(float) $row[\'minimal_quantity\']',
                                ),
                                array( // addCustomizationPrice
                                    "\$product_quantity = isset(\$product_update['cart_quantity']) ? (int) \$product_update['cart_quantity'] : (int) \$product_update['product_quantity'];",
                                    '$product_quantity = PP::resolveQty($product_update);',
                                ),
                                array( // addCustomizationPrice
                                    "if ((int) \$product_update['id_customization'] && \$customization['id_customization'] != \$product_update['id_customization']) {",
                                    "if (\$product_update['id_cart_product'] != \$customization['id_cart_product']) {"
                                ),
                                array( // addCustomizationPrice
                                    "\$customization_quantity += (int) \$customization['quantity'];",
                                    array(
                                        "\$customization_quantity += PP::resolveQty(\$customization['quantity'], \$customization['quantity_fractional']);",
                                        "\$customizationQuantityTotal += (int) \$customization['quantity'];",
                                    ),
                                    'indent' => '                        '
                                ),
                                array( // addCustomizationPrice
                                    "\$product_update['customizationQuantityTotal'] = \$customization_quantity;",
                                    "\$product_update['customizationQuantityTotal'] = \$customizationQuantityTotal;"
                                ),
                            ),
                'append' => array(
                                array( // __construct
                                    '$this->tags = Tag::getProductTags((int) $this->id);',
                                    array('$this->amend();'),
                                    'indent' => '            '
                                ),
                                array( // updateAttribute
                                    "'minimal_quantity' => null !== \$minimal_quantity,",
                                    array("'minimal_quantity_fractional' => null !== \$minimal_quantity,"),
                                    'indent' => '                ',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.6.0', '<')
                                ),
                                array( // updateAttribute
                                    "'minimal_quantity' => !is_null(\$minimal_quantity),",
                                    array("'minimal_quantity_fractional' => !is_null(\$minimal_quantity),"),
                                    'indent' => '                ',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.6.0', '>=')
                                ),
                                array( // getAttributesGroups
                                    'product_attribute_shop.`minimal_quantity`,',
                                    ' product_attribute_shop.`minimal_quantity_fractional`,'
                                ),
                                array( // priceCalculation
                                    'static $context = null;',
                                    array(
                                        'if (is_numeric($return = PP::priceCalculation($id_shop, $id_product, $id_product_attribute, $id_country, $id_state, $zipcode, $id_currency, $id_group, $quantity, $use_tax, $decimals, $only_reduc, $use_reduc, $with_ecotax, $specific_price, $use_group_reduction, $id_customer, $use_customer_price, $id_cart, $real_quantity, $id_customization))) {',
                                        '    return (float) $return;',
                                        '}',
                                        'if (is_array($quantity)) {',
                                        '    $quantity = PP::resolveQty($quantity[0], $quantity[1]);',
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                                array( // priceCalculation
                                    '$product_tax_calculator = $tax_manager->getTaxCalculator();',
                                    array('PSMCache::store(\'tax_calculator_\' . $id_product, $product_tax_calculator);'),
                                    'indent' => '        '
                                ),
                                array( // priceCalculation
                                    '$group_reduction = $price * (float) $reduction_from_category;',
                                    array('PSMCache::store(\'group_reduction_\' . $id_product, (float) $reduction_from_category);'),
                                    'indent' => '                '
                                ),
                                array( // priceCalculation
                                    '$group_reduction = (($reduc = Group::getReductionByIdGroup($id_group)) != 0) ? ($price * $reduc / 100) : 0;',
                                    array('PSMCache::store(\'group_reduction_\' . $id_product, $reduc / 100);'),
                                    'indent' => '                '
                                ),
                                array( // getProductProperties
                                    "\$cache_key = \$row['id_product'] . '-' . \$id_product_attribute . '-' . \$id_lang . '-' . (int) \$usetax;",
                                    array(
                                        '\PP::amendProduct($row);',
                                        '\PP::productResolveQuantities($row);',
                                        "if (\$row['id_pp_template'] && isset(\$row['id_cart_product'])) {",
                                        "    \$row['quantity_wanted'] = \PP::resolveQty(\$row);",
                                        "    \$cache_key .= '-id_cart_product' . \$row['id_cart_product'];",
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                                array( // getAllCustomizedDatas
                                    ', c.`id_product_attribute`,',
                                    ' c.`id_cart_product`,'
                                ),
                                array( // getAllCustomizedDatas
                                    ', `quantity`,',
                                    ' `quantity_fractional`, `id_cart_product`,'
                                ),
                                array( // getAllCustomizedDatas
                                    "\$customized_datas[(int) \$row['id_product']][(int) \$row['id_product_attribute']][(int) \$row['id_address_delivery']][(int) \$row['id_customization']]['quantity'] = (int) \$row['quantity'];",
                                    array(
                                        "\$customized_datas[(int) \$row['id_product']][(int) \$row['id_product_attribute']][(int) \$row['id_address_delivery']][(int) \$row['id_customization']]['quantity_fractional'] = (float) \$row['quantity_fractional'];",
                                        "\$customized_datas[(int) \$row['id_product']][(int) \$row['id_product_attribute']][(int) \$row['id_address_delivery']][(int) \$row['id_customization']]['id_cart_product'] = (int) \$row['id_cart_product'];",
                                    ),
                                    'indent' => '            ',
                                ),
                                array( // addCustomizationPrice
                                    '$customization_quantity = 0;',
                                    array('$customizationQuantityTotal = 0;'),
                                    'indent' => '                '
                                ),
                            ),
                'prepend'=> array(
                                array( // getPriceStatic
                                    '$cur_cart = $context->cart;',
                                    array(
                                        'if (is_numeric($return = PP::getPriceStatic($id_product, $usetax, $id_product_attribute, $decimals, $divisor, $only_reduc, $usereduc, $quantity, $force_associated_tax, $id_customer, $id_cart, $id_address, $specific_price_output, $with_ecotax, $use_group_reduction, $context, $use_customer_price, $id_customization))) {',
                                        '    return (float) $return;',
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                                array( // getPriceStatic
                                    '$cart_quantity = 0;',
                                    array(
                                        'if (is_array($quantity)) {',
                                        '    $quantity = PP::resolveQty($quantity[0], $quantity[1]);',
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                                array( // getProductProperties
                                    "\$row['price_tax_exc'] = Product::getPriceStatic(",
                                    array(
                                        'if ($bulk = PP::getProductBulk($row)) {',
                                        '    $quantity = $bulk;',
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                                array( // addCustomizationPrice
                                    '$product_update[\'total_wt\'] = ',
                                    '//',
                                    'count' => 1
                                ),
                                array( // addCustomizationPrice
                                    '$product_update[\'total\'] = ',
                                    '//',
                                    'count' => 1
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/SpecificPrice.php'),
                'replace'=> array(
                                array( // public static $definition
                                    "'from_quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],",
                                    "'from_quantity' => ['type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat', 'required' => true],",
                                    'ignore' => !$is_ps_version_1_7_7_or_later,
                                    'uninstall' => false
                                ),
                                array(
                                    '(int) $quantity',
                                    '(float) $quantity'
                                ),
                                array(
                                    '(int) $real_quantity',
                                    '(float) $real_quantity'
                                ),
                                array(
                                    '(int) $from_quantity',
                                    '(float) $from_quantity'
                                ),
                                array( // getSpecificPrice
                                    '`from_quantity` > 1,',
                                    '`from_quantity` > \'.PP::getSpecificPriceFromQty((int) $id_product).\','
                                ),
                                array( // getSpecificPrice
                                    'max(1,',
                                    'max(PP::getSpecificPriceFromQty((int) $id_product),'
                                ),
                                array( // getQuantityDiscounts
                                    '$specific_price[\'from_quantity\'] > 1',
                                    '$specific_price[\'from_quantity\'] > PP::getSpecificPriceFromQty((int) $id_product)'
                                ),
                                array( // getProductIdByDate
                                    '`from_quantity` = 1 AND',
                                    '1 AND'
                                ),
                                array( // getProductIdByDate
                                    'return $ids_product;',
                                    'return self::getProductIdByDateAmend($results, $with_combination_id);',
                                    'count' => 1
                                ),
                            ),
                'append' => array( // getProductIdByDate
                                array(
                                    'SELECT `id_product`, `id_product_attribute`',
                                    ', `from_quantity`'
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    'public static function exists',
                                    array(
                                        'public static function getProductIdByDateAmend($results, $with_combination_id)',
                                        '{',
                                        '    $ids = [];',
                                        '    foreach ($results as $key => $value) {',
                                        '        $policyLegacy = PP::productQtyPolicyLegacy((int) $value[\'id_product\']);',
                                        '        if (($policyLegacy && (int) $value[\'from_quantity\'] == 1) || (!$policyLegacy && (float) $value[\'from_quantity\'] > PP::getSpecificPriceFromQty((int) $value[\'id_product\']))) {',
                                        '            $ids[(int) $value[\'id_product\']] = $with_combination_id ? array(\'id_product\' => (int) $value[\'id_product\'], \'id_product_attribute\' => (int) $value[\'id_product_attribute\']) : (int) $value[\'id_product\'];',
                                        '        }',
                                        '    }',
                                        '    return $ids;',
                                        '}',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/Tools.php'),
                'replace'=> array(
                                array(
                                    'public static function displayPriceSmarty($params, &$smarty)',
                                    array(
                                        'public static function displayPriceSmarty ($params, &$smarty) {',
                                        '    return PP::smartyDisplayPrice($params, $smarty);',
                                        '}',
                                        'public static function displayPriceSmartyCore($params, &$smarty)',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
                'append' => array(
                                array(
                                    'if (is_string($value)) {',
                                    array(
                                        "if (strpos(\$key, 'quantity') !== false) {",
                                        "    \$value = str_replace(',', '.', \$value);",
                                        '}',
                                    ),
                                    'indent' => '            ',
                                    'count' => 1
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/Validate.php'),
                'prepend'=> array(
                                array(
                                    'public static function isUnsignedFloat',
                                    array(
                                        // Check for Tools::getIsset('id_product') === true is required when duplicating products.
                                        // During the product duplication id_product not yet present.
                                        'public static function validateProductQuantity($value)',
                                        '{',
                                        '    if (is_string($value)) {',
                                        '        $value = str_replace(\',\', \'.\', $value);',
                                        '    }',
                                        '    if (self::isUnsignedFloat($value)) {',
                                        '        if (Tools::getIsset(\'id_product\') === true && PP::productQtyPolicyLegacy((int) Tools::getValue(\'id_product\'))) {',
                                        '            return (self::isUnsignedInt((int) $value) && ((int) $value == (float) $value));',
                                        '        }',
                                        '        return true;',
                                        '    }',
                                        '    return false;',
                                        '}',
                                        '',
                                        'public static function validateSpecificPriceProductQuantity($value, $id_product = 0)',
                                        '{',
                                        '    if (is_string($value)) {',
                                        '        $value = str_replace(\',\', \'.\', $value);',
                                        '    }',
                                        '    if (self::isUnsignedFloat($value)) {',
                                        '        $id_product = (int) $id_product ? (int) $id_product : (Tools::getIsset(\'id_product\') === true ? (int) Tools::getValue(\'id_product\') : false);',
                                        '        if ($id_product !== false && PP::productQtyPolicyLegacy($id_product)) {',
                                        '            return (self::isUnsignedInt((int) $value) && ((int) $value == (float) $value));',
                                        '        }',
                                        '        return true;',
                                        '    }',
                                        '    return false;',
                                        '}',
                                        '',
                                    ),
                                    'indent' => '    ',
                                ),
                            )
            ),
            array(
                'files'  => array('classes/controller/AdminController.php'),
                'replace'=> array(
                    array(
                        'Hook::exec(\'actionAdminControllerSetMedia\');',
                        'Hook::exec(\'actionAdminControllerSetMedia\', [\'isNewTheme\' => $isNewTheme]);',
                        'count' => 1
                    ),
                )
            ),
            array(
                'files'  => array('classes/controller/FrontController.php'),
                'replace'=> array(
                                array(
                                    'DisplayOverrideTemplate',
                                    'displayOverrideTemplate',
                                    'uninstall' => false
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    'return $cssFileList;',
                                    array(
                                        "if (!Configuration::get('PS_CSS_THEME_CACHE')) {",
                                        '    \PSM::amendCSS($cssFileList);',
                                        '}',
                                    ),
                                    'indent' => '        ',
                                ),
                                array(
                                    'return $jsFileList;',
                                    array(
                                        "if (!Configuration::get('PS_JS_THEME_CACHE')) {",
                                        '    \PSM::amendJS($jsFileList);',
                                        '}',
                                    ),
                                    'indent' => '        ',
                                ),
                            )
            ),
            array(
                'files'  => array('classes/module/Module.php'),
                'replace'=> array(
                                array(
                                    '$upgrade[\'number_upgraded\'] += 1;',
                                    '++$upgrade[\'number_upgraded\'];',
                                    'count' => -2,
                                    'uninstall' => false
                                )
                ),
                'prepend' => array(
                                array(
                                    '++$upgrade[\'number_upgraded\'];',
                                    array("Hook::exec('actionModuleUpgradeAfter', array('object' => \$this));"),
                                    'indent' => '                ',
                                ),
                                array(
                                    'if (!$this->uninstallOverrides()) {',
                                    array("Hook::exec('actionModuleUninstallBefore', array('object' => \$this));"),
                                    'indent' => '        ',
                                )
                ),
                'append' => array(
                                array(
                                    'Cache::clean(\'Module::getModuleIdByName_\' . pSQL($this->name));',
                                    array("Hook::exec('actionModuleUninstallAfter', array('object' => \$this));"),
                                    'indent' => '            ',
                                )
                )
            ),
            array(
                'files'  => array('classes/order/Order.php'),
                'replace'=> array(
                                array(
                                    '+= (int) $quantity;',
                                    '+= (float) $quantity;',
                                    'count' => -1
                                ),
                                array(
                                    '(int) $quantity',
                                    '(float) $quantity',
                                    'count' => -1
                                ),
                                array(
                                    'public function getProducts($products = false, $selected_products = false, $selected_qty = false, $fullInfos = true)',
                                    array(
                                        'public function getProducts ($products = false, $selected_products = false, $selected_qty = false, $fullInfos = true)',
                                        '{',
                                        '    $products = $this->getProductsCore($products, $selected_products, $selected_qty, $fullInfos);',
                                        '    return \PP::amendProducts($products);',
                                        '}',
                                        'public function getProductsCore($products = false, $selected_products = false, $selected_qty = false, $fullInfos = true)',
                                    ),
                                    'indent' => '    ',
                                ),
                                array(
                                    'protected function setProductCustomizedDatas(&$product, $customized_datas)',
                                    array(
                                        'protected function setProductCustomizedDatas (&$product, $customized_datas)',
                                        '{',
                                        '    PP::setProductCustomizedDatas($product, $customized_datas);',
                                        '}',
                                        'protected function setProductCustomizedDatasCore(&$product, $customized_datas)',
                                    ),
                                    'indent' => '    ',
                                ),
                                array( // getTotalWeight
                                    'SELECT SUM(product_weight * product_quantity)',
                                    'SELECT SUM(product_weight * '.PP::sqlQty('product_quantity').')'
                                ),
                                array( // getProductTaxesDetails
                                    '$order_detail[\'product_quantity\']',
                                    '\PP::resolveQty($order_detail)',
                                    'count' => 2
                                ),
                                array( // refreshShippingCost
                                    '$product[\'product_quantity\'],',
                                    '\PP::resolveQty($product),',
                                    'count' => 1
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/order/OrderDetail.php'),
                'replace'=> array(
                                array( // public static $definition
                                    "'product_quantity_in_stock' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],",
                                    "'product_quantity_in_stock' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],",
                                    'ignore' => !$is_ps_version_1_7_7_or_later,
                                    'uninstall' => false
                                ),
                                array( // public static $definition
                                    "'product_quantity_return' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],",
                                    "'product_quantity_return' => ['type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'],",
                                    'ignore' => !$is_ps_version_1_7_7_or_later,
                                    'uninstall' => false
                                ),
                                array( // public static $definition
                                    "'product_quantity_refunded' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],",
                                    "'product_quantity_refunded' => ['type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'],",
                                    'ignore' => !$is_ps_version_1_7_7_or_later,
                                    'uninstall' => false
                                ),
                                array( // public static $definition
                                    "'product_quantity_reinjected' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],",
                                    "'product_quantity_reinjected' => ['type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'],",
                                    'ignore' => !$is_ps_version_1_7_7_or_later,
                                    'uninstall' => false
                                ),
                                // @deprecated Functionality moved to Order::updateOrderDetailTax
                                //             because we need the full order object to do a good job here.
                                //             Will no longer be supported after 1.6.1
                                array( // saveTaxCalculator
                                    '$unit_amount * $this->product_quantity',
                                    '$unit_amount * PP::resolveQty($this->product_quantity, $this->product_quantity_fractional)',
                                    'count' => 3
                                ),
                                array( // updateTaxAmount
                                    'public function updateTaxAmount($order)',
                                    'public function updateTaxAmount($order, $tax_calculator = false)'
                                ),
                                array( // updateTaxAmount
                                    'return $this->saveTaxCalculator($order, true);',
                                    'return ($tax_calculator ? $this->tax_calculator : $this->saveTaxCalculator($order, true));'
                                ),
                                array( // checkProductStock
                                    $is_ps_version_1_7_7_or_later ?
                                        '$update_quantity = StockAvailable::updateQuantity($product[\'id_product\'], $product[\'id_product_attribute\'], -(int) $product[\'cart_quantity\'], $product[\'id_shop\'], true);'
                                        :
                                        '$update_quantity = StockAvailable::updateQuantity($product[\'id_product\'], $product[\'id_product_attribute\'], -(int) $product[\'cart_quantity\']);',
                                    'list($update_quantity, $delta_quantity) = PP::orderDetailCheckProductStock($product, $delta_quantity);',
                                    'count' => 1
                                ),
                                array( // checkProductStock
                                    '$product[\'stock_quantity\'] -= $product[\'cart_quantity\'];',
                                    '$product[\'stock_quantity\'] -= $delta_quantity;',
                                    'count' => 1
                                ),
                                array( // setDetailProductPrice
                                    'Product::getPriceStatic((int) $product[\'id_product\'], true, (int) $product[\'id_product_attribute\'], 6, null, false, true, $product[\'cart_quantity\'], false, (int) $order->id_customer, (int) $order->id_cart, (int) $order->{Configuration::get(\'PS_TAX_ADDRESS_TYPE\')}, $specific_price, true, true, $this->context);',
                                    'Product::getPriceStatic((int) $product[\'id_product\'], true, (int) $product[\'id_product_attribute\'], 6, null, false, true, array($product[\'cart_quantity\'], $product[\'cart_quantity_fractional\']), false, (int) $order->id_customer, (int) $order->id_cart, (int) $order->{Configuration::get(\'PS_TAX_ADDRESS_TYPE\')}, $specific_price, true, true, $this->context);',
                                    'count' => 1
                                ),
                                array( // setDetailProductPrice
                                    '(int) $product[\'cart_quantity\'],',
                                    'PP::resolveQty($product),',
                                    'count' => 1
                                ),
                                array( // setDetailProductPrice
                                    '$this->discount_quantity_applied = (($this->specificPrice && $this->specificPrice[\'from_quantity\'] > 1) ? 1 : 0);',
                                    array(
                                        '$this->discount_quantity_applied = (($this->specificPrice && $this->specificPrice[\'from_quantity\'] > PP::getSpecificPriceFromQty((int) $product[\'id_product\'])) ? 1 : 0);',
                                        '$this->id_cart_product = (int) $product[\'id_cart_product\'];',
                                        '$this->product_quantity_fractional = (float) $product[\'cart_quantity_fractional\'];',
                                        '$this->pp_data_type = (!empty($product[\'pp_data_type\']) ? $product[\'pp_data_type\'] : null);',
                                        '$this->pp_data = (!empty($product[\'pp_data\']) ? $product[\'pp_data\'] : null);',
                                        '$this->pp_ms_data = (!empty($product[\'pp_ms_data\']) ? $product[\'pp_ms_data\'] : null);',
                                        '// [HOOK ppropertiessmartprice #2]'
                                    ),
                                    'indent' => '        '
                                ),
                            ),
                'append' => array(
                                array(
                                    'public $product_quantity;',
                                    array(
                                        'public $id_cart_product;',
                                        'public $product_quantity_fractional;',
                                        'public $pp_data_type;',
                                        'public $pp_data;',
                                        'public $pp_ms_data;',
                                        '// [HOOK ppropertiessmartprice #1]',
                                    ),
                                    'indent' => '    ',
                                ),
                                array( // checkProductStock
                                    '$update_quantity = true;',
                                    array(
                                        '$delta_quantity = PP::resolveQty($product);',
                                    ),
                                    'count' => 1,
                                    'indent' => '            ',
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/order/OrderInvoice.php'),
                'replace'=> array(
                                array(
                                    'public function getProducts($products = false, $selected_products = false, $selected_qty = false)',
                                    array(
                                        'public function getProducts ($products = false, $selected_products = false, $selected_qty = false)',
                                        '{',
                                        '    $products = $this->getProductsCore($products, $selected_products, $selected_qty);',
                                        '    return \PP::amendProducts($products);',
                                        '}',
                                        'public function getProductsCore($products = false, $selected_products = false, $selected_qty = false)',
                                    ),
                                    'indent' => '    ',
                                ),
                                array(
                                    'protected function setProductCustomizedDatas(&$product, $customized_datas)',
                                    array(
                                        'protected function setProductCustomizedDatas (&$product, $customized_datas)',
                                        '{',
                                        '    PP::setProductCustomizedDatas($product, $customized_datas);',
                                        '}',
                                        'protected function setProductCustomizedDatasCore(&$product, $customized_datas)',
                                    ),
                                    'indent' => '    ',
                                ),
                            )
            ),
            array(
                'files'  => array('classes/order/OrderHistory.php'),
                'replace'=> array(
                                array(
                                    'StockAvailable::updateQuantity($product[\'product_id\'], $product[\'product_attribute_id\'], -(int) $product[\'product_quantity\'], $order->id_shop);',
                                    'StockAvailable::updateQuantity($product[\'product_id\'], $product[\'product_attribute_id\'], -PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']), $order->id_shop);'
                                ),
                                array(
                                    'StockAvailable::updateQuantity($product[\'product_id\'], $product[\'product_attribute_id\'], (int) $product[\'product_quantity\'], $order->id_shop);',
                                    'StockAvailable::updateQuantity($product[\'product_id\'], $product[\'product_attribute_id\'], PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']), $order->id_shop);'
                                ),
                                array(
                                    '($product[\'product_quantity\'] - $product[\'product_quantity_refunded\'] - $product[\'product_quantity_return\'])',
                                    'PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']) - $product[\'product_quantity_refunded\'] - $product[\'product_quantity_return\']'
                                ),
                                array(
                                    '$mvt[\'physical_quantity\'],',
                                    '$mvt[\'physical_quantity\'] + $mvt[\'physical_quantity_remainder\'],'
                                ),
                                array(
                                    '(int) PP::resolveQty',
                                    '(float) PP::resolveQty',
                                    'count' => -1
                                ),
                                array(
                                    '(int) $product_quantity',
                                    '(float) $product_quantity',
                                    'count' => -1
                                ),
                                array(
                                    '(int) ($product[\'product_quantity\']',
                                    '(float) ($product[\'product_quantity\']',
                                    'count' => -2
                                ),
                            )
            ),
            array(
                'files'  => array('classes/order/OrderSlip.php'),
                'replace'=> array(
                                array(
                                    '(int) $product[\'quantity\']',
                                    '(float) $product[\'quantity\']',
                                ),
                                array(
                                    '(int) $tab[\'quantity\']',
                                    '(float) $tab[\'quantity\']',
                                ),
                                array(
                                    '(int) $value[\'product_quantity\']',
                                    '(float) $value[\'product_quantity\']',
                                ),
                                array(
                                    '(int) ($productQtyList[$key])',
                                    '(float) ($productQtyList[$key])',
                                ),
                            )
            ),
            array(
                'files'  => array('classes/stock/Stock.php'),
                'prepend'=> array(
                                array(
                                    $is_ps_version_1_7_7_or_later ? 'public static $definition = [' : 'public static $definition = array(',
                                    array(
                                        'public function hydrate(array $data, $id_lang = null)',
                                        '{',
                                        '    parent::hydrate($data, $id_lang);',
                                        "    if (!isset(\$data['physical_quantity_remainder'])) {",
                                        "        PP::hydrateQtyO(\$this, 'physical_quantity', \$data['physical_quantity'] + \$this->physical_quantity_remainder);",
                                        '    }',
                                        "    if (!isset(\$data['usable_quantity_remainder'])) {",
                                        "        PP::hydrateQtyO(\$this, 'usable_quantity', \$data['usable_quantity'] + \$this->usable_quantity_remainder);",
                                        '    }',
                                        '}',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
                'append' => array(
                                array(
                                    'public $physical_quantity;',
                                    array(
                                        'public $physical_quantity_remainder = 0;',
                                        'public $usable_quantity_remainder = 0;',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/stock/StockAvailable.php'),
                'replace'=> array(
                                array( // synchronize
                                    'array(\'quantity\' => 0 )',
                                    'array(\'quantity\' => 0)',
                                    'count' => -2,
                                    'uninstall' => false
                                ),
                                array(
                                    $is_ps_version_1_7_7_or_later ? '[\'quantity\' => 0]' : 'array(\'quantity\' => 0)',
                                    $is_ps_version_1_7_7_or_later ? '[\'quantity\' => 0, \'quantity_remainder\' => 0]' : 'array(\'quantity\' => 0, \'quantity_remainder\' => 0)'
                                ),
                                array( // synchronize
                                    $is_ps_version_1_7_7_or_later ? '\'data\' => [\'quantity\' => $quantity],' : '\'data\' => array(\'quantity\' => $quantity),',
                                    '\'data\' =>  PP::hydrateQtyA([], \'quantity\', $quantity),'
                                ),
                                array( // synchronize
                                    $is_ps_version_1_7_7_or_later ? '\'data\' => [\'quantity\' => $product_quantity],' : '\'data\' => array(\'quantity\' => $product_quantity),',
                                    '\'data\' =>  PP::hydrateQtyA([], \'quantity\', $product_quantity),'
                                ),
                                array( // getQuantityAvailableByProduct
                                    '$query->select(\'SUM(quantity)\');',
                                    '$query->select(\'SUM(quantity + quantity_remainder)\');'
                                ),
                                array( // getQuantityAvailableByProduct
                                    '$result = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);',
                                    '$result = (float) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);'
                                ),
                                array( // postSave
                                    '$total_quantity = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(',
                                    '$total_quantity = (float) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
                                ),
                                array( // postSave
                                    'SELECT SUM(quantity) as quantity',
                                    'SELECT (SUM(quantity) + SUM(quantity_remainder)) as quantity'
                                ),
                                array( // setQuantity
                                    '$stock_available->quantity = (int) $quantity;',
                                    'PP::setQtyO($stock_available, $quantity);',
                                    'count' => 2
                                ),
                                array( // setQuantity
                                    'choice' => array(
                                        array(
                                            '$deltaQuantity = -1 * ((int) $stock_available->quantity - (int) $quantity);',
                                            ['$deltaQuantity = -1 * ((int) $stock_available->quantity + $stock_available->quantity_remainder - $quantity);'],
                                            'indent' => '                '
                                        ),
                                        array(
                                            '$deltaQuantity = (int) $quantity - (int) $stock_available->quantity;',
                                            ['$deltaQuantity = $quantity - (int) $stock_available->quantity - (float) $stock_available->quantity_remainder;'],
                                            'indent' => '                '
                                        ),
                                    ),
                                ),
                                array( // setQuantity
                                    '$stockManager->saveMovement($id_product, $id_product_attribute, (int) $quantity);',
                                    '$stockManager->saveMovement($id_product, $id_product_attribute, $quantity);'
                                ),
                                array( // setQuantity
                                    '\'quantity\' => $stock_available->quantity',
                                    '\'quantity\' =>  $stock_available->quantity + $stock_available->quantity_remainder'
                                ),
                            ),
                'append' => array(
                                array(
                                    'public $quantity = 0;',
                                    array('public $quantity_remainder = 0;'),
                                    'indent' => '    ',
                                ),
                            ),
                'regex'=> array(
                                array( // synchronize
                                    $is_ps_version_1_7_7_or_later ?
                                    '#(\h*\$query = \[\s*\'table\' => \'stock_available\',\s*\'data\' =>\s*)(\[[^)].+?\])(,?\s*\];)#s'
                                    :
                                    '#(\h*\$query = array\(\s*\'table\' => \'stock_available\',\s*\'data\' =>\s*)(array\([^)].+?\))(,?\s*\);)#s',
                                    '$1PP::hydrateQtyA($2, \'quantity\')$3',
                                ),
                            )
            ),
            array(
                'files'  => array('classes/stock/StockManager.php'),
                'replace'=> array(
                                array( // removeProduct
                                    '$left_quantity_to_check = $stock->physical_quantity;',
                                    '$left_quantity_to_check = $stock->physical_quantity + $stock->physical_quantity_remainder;'
                                ),
                                array( // removeProduct
                                    '(int) $this->getProductPhysicalQuantities',
                                    '(float) $this->getProductPhysicalQuantities'
                                ),
                                array( // removeProduct
                                    'SELECT sm.`id_stock_mvt`, sm.`date_add`, sm.`physical_quantity`,',
                                    'SELECT sm.`id_stock_mvt`, sm.`date_add`, sm.`physical_quantity_remainder`, sm.`physical_quantity`,'
                                ),
                                array( // removeProduct
                                    'IF ((sm2.`physical_quantity` is null), sm.`physical_quantity`, (sm.`physical_quantity` - SUM(sm2.`physical_quantity`))) as qty',
                                    'IF ((sm2.`physical_quantity` is null), sm.`physical_quantity` + sm.`physical_quantity_remainder`, (sm.`physical_quantity` + sm.`physical_quantity_remainder` - SUM(sm2.`physical_quantity` + sm2.`physical_quantity_remainder`))) as qty'
                                ),
                                array( // removeProduct
                                    '(int) $row[\'qty\']',
                                    '(float) $row[\'qty\']'
                                ),
                                array( // getProductPhysicalQuantities
                                    '$query->select(\'SUM(\' . ($usable ? \'s.usable_quantity\' : \'s.physical_quantity\') . \')\');',
                                    '$query->select(\'SUM(\' . ($usable ? \'s.usable_quantity + s.usable_quantity_remainder\' : \'s.physical_quantity + s.physical_quantity_remainder\') . \')\');'
                                ),
                                array( // getProductPhysicalQuantities
                                    'return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);',
                                    'return (float) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);'
                                ),
                                array( // getProductRealQuantities
                                    '$query->select(\'od.product_quantity, od.product_quantity_refunded',
                                    '$query->select(\'od.product_quantity, od.product_quantity_fractional, od.product_quantity_refunded',
                                    'count' => 2
                                ),
                                array( // getProductRealQuantities
                                    '$client_orders_qty += ($row[\'product_quantity\'] - $row[\'product_quantity_refunded\']);',
                                    '$client_orders_qty += PP::resolveQty($row[\'product_quantity\'] - $row[\'product_quantity_refunded\'], $row[\'product_quantity_fractional\']);'
                                ),
                                array( // getProductCoverage
                                    'SELECT sm.`physical_quantity` as quantity',
                                    'SELECT (sm.`physical_quantity` + sm.`physical_quantity_remainder`) as quantity'
                                ),
                                array( // calculateWA
                                    'return (float) Tools::ps_round(((($stock->physical_quantity * $stock->price_te) + ($quantity * $price_te)) / ($stock->physical_quantity + $quantity)), 6);',
                                    'return (float) Tools::ps_round((((($stock->physical_quantity + $stock->physical_quantity_remainder) * $stock->price_te) + ($quantity * $price_te)) / ($stock->physical_quantity + $stock->physical_quantity_remainder + $quantity)), 6);'
                                ),
                                array( // getStockByCarrier
                                    'SUM(s.`usable_quantity`) as quantity',
                                    'SUM(s.`usable_quantity` + s.`usable_quantity_remainder`) as quantity',
                                    'count' => 2
                                ),
                            ),
                'regex'=> array(
                                array( // addProduct, removeProduct
                                    $is_ps_version_1_7_7_or_later ? '#(\h*\$mvt_params = )(\[[^]].+?\]);#s' : '#(\h*\$mvt_params = )(array\([^)].+?\));#s',
                                    '$1PP::hydrateQtyA($2, \'physical_quantity\');',
                                ),
                                array( // addProduct, removeProduct
                                    $is_ps_version_1_7_7_or_later ? '#(\h*\$stock_params = )(\[[^]].+?\]);#s' : '#(\h*\$stock_params = )(array\([^)].+?\));#s',
                                    '$1PP::hydrateQtyA($2, array(\'physical_quantity\', \'usable_quantity\'), null, $stock);',
                                ),
                            )
            ),
            array(
                'files'  => array('classes/stock/StockMvt.php'),
                'prepend'=> array(
                                array(
                                    $is_ps_version_1_7_7_or_later ? 'public static $definition = [' : 'public static $definition = array(',
                                    array(
                                        'public function hydrate(array $data, $id_lang = null)',
                                        '{',
                                        '    parent::hydrate($data, $id_lang);',
                                        '    if (!isset($data[\'physical_quantity_remainder\'])) {',
                                        '        PP::hydrateQtyO($this, \'physical_quantity\', $data[\'physical_quantity\'] + $this->physical_quantity_remainder);',
                                        '    }',
                                        '}',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
                'replace'=> array(
                                array( // getLastPositiveStockMvt
                                    '(s.usable_quantity = sm.physical_quantity) as is_usable',
                                    '(s.usable_quantity = sm.physical_quantity and s.usable_quantity_remainder = sm.physical_quantity_remainder) as is_usable'
                                ),
                ),
                'append' => array(
                                array(
                                    'public $physical_quantity;',
                                    array('public $physical_quantity_remainder = 0;'),
                                    'indent' => '    ',
                                ),
                                array( // getNegativeStockMvts
                                    "(int) \$row['physical_quantity']",
                                    " + (float) \$row['physical_quantity_remainder']",
                                ),
                            ),
            ),
            array(
                'files'  => array('classes/stock/Warehouse.php'),
                'replace'=> array(
                                array( // isEmpty
                                    '$query->select(\'SUM(s.physical_quantity)\');',
                                    '$query->select(\'SUM(s.`physical_quantity` + s.`physical_quantity_remainder`)\');'
                                ),
                                array( // getQuantitiesOfProducts
                                    'SELECT SUM(s.physical_quantity)',
                                    'SELECT SUM(s.`physical_quantity`+s.`physical_quantity_remainder`)'
                                ),
                                array( // getStockValue
                                    '$query->select(\'SUM(s.`price_te` * s.`physical_quantity`)\');',
                                    '$query->select(\'SUM(s.`price_te` * (s.`physical_quantity` + s.`physical_quantity_remainder`))\');'
                                ),
                            )
            ),
            array(
                'files'  => array('controllers/admin/AdminAttributeGeneratorController.php'),
                'replace'=> array(
                                array( // processGenerate
                                    "\$quantity = (int) Tools::getValue('quantity');",
                                    "\$quantity = Tools::getValue('quantity');"
                                )
                            ),
                'regex'=> array(
                                array( // addAttribute
                                    $is_ps_version_1_7_7_or_later ? '#(\h*return )(\[\s*\'id_product\'[^)].+?\]);#s' : '#(\h*return )(array\(\s*\'id_product\'[^)].+?\));#s',
                                    '$1PP::setQtyA($2, Tools::getValue(\'quantity\'));',
                                    'count' => 1
                                ),
                            )
            ),
            array(
                'files'  => array('controllers/admin/AdminCartsController.php'),
                'replace'=> array(
                                array(
                                    'class AdminCartsControllerCore extends AdminController',
                                    'class AdminCartsControllerCore extends AdminCartsControllerBase'
                                ),
                                array(
                                    'public function ajaxProcessUpdateQty(',
                                    'public function ajaxProcessUpdateQtyCore('
                                ),
                            )
            ),
            array(
                'files'  => array('controllers/admin/AdminImportController.php'),
                'replace'=> array(
                                array( // getMaskedRow
                                    '$res[$type] = isset($row[$nb]) ? $row[$nb] : null;',
                                    '$res[$type] = isset($row[$nb]) ? trim($row[$nb]) : null;',
                                    'count' => -2,
                                    'uninstall' => false
                                ),
                                array(
                                    '(int) $product->quantity',
                                    '(float) $product->quantity'
                                ),
                                array( // attributeImportOne
                                    '(int) $info[\'quantity\']',
                                    '(float) $info[\'quantity\']'
                                ),
                                array( // attributeImportOne
                                    '? (int) $info[\'minimal_quantity\'] : 1;',
                                    '? $product->normalizeQty($info[\'minimal_quantity\']) : 1;'
                                ),
                                array( // attributeImportOne
                                    '(int) $info[\'minimal_quantity\'],',
                                    '(float) $info[\'minimal_quantity\'],'
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    'public static function getPath(',
                                    array(
                                        'public static function ignoreRow($row)',
                                        '{',
                                        '    /* skip empty lines */',
                                        '    if (count($row) == 1 && empty($row[0])) {',
                                        '        return true;',
                                        '    }',
                                        '    return ((isset($row[\'id\']) && is_string($row[\'id\'] && !Validate::isUnsignedInt($row[\'id\'])) && Tools::strtoupper($row[\'id\']) == \'ID\') || (isset($row[\'id_product\']) && is_string($row[\'id_product\'] && !Validate::isUnsignedInt($row[\'id_product\'])) && Tools::strtoupper($row[\'id_product\']) == \'PRODUCT ID*\'));',
                                        '}',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
                'append' => array(
                                array(
                                    $is_ps_version_1_7_7_or_later ? '\'accessories\' => [\'label\' => $this->trans(\'Accessories (x,y,z...)\', [], \'Admin.Advparameters.Feature\')],' : '\'accessories\' => array(\'label\' => $this->trans(\'Accessories (x,y,z...)\', array(), \'Admin.Advparameters.Feature\')),',
                                    array('\'id_pp_template\' => array(\'label\' => PSM::trans(\'Product Properties Extension template ID\')),'),
                                    'indent' => '                    ',
                                ),
                                array(
                                    '$info = AdminImportController::getMaskedRow($line);',
                                    array(
                                        'if (self::ignoreRow($info)) {',
                                        '    continue;',
                                        '}',
                                    ),
                                    'indent' => '            ',
                                ),
                                array( // productImportOne
                                    'AdminImportController::setEntityDefaultValues($product);',
                                    array(
                                        "if (isset(\$info['id_pp_template'])) {",
                                        "    \$product->id_pp_template = (int) \$info['id_pp_template'];",
                                        '}',
                                        "if (isset(\$info['minimal_quantity'])) {",
                                        "    \$product->setMinQty(\$info['minimal_quantity']);",
                                        "    unset(\$info['minimal_quantity']);",
                                        '}',
                                    ),
                                    'indent' => '        ',
                                ),
                            ),
            ),
            array(
                'files'  => array('controllers/admin/AdminOrdersController.php'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'replace'=> array(
                                array(
                                    'class AdminOrdersControllerCore extends AdminController',
                                    'class AdminOrdersControllerCore extends AdminOrdersControllerBase'
                                ),
                                array(
                                    'public function ajaxProcessEditProductOnOrder(',
                                    'public function ajaxProcessEditProductOnOrderCore('
                                ),
                                array( // postProcess
                                    '(int) $quantity[$id_order_detail]',
                                    'PP::toFloat($quantity[$id_order_detail])'
                                ),
                                array( // doEditProductValidation
                                    '!Validate::isUnsignedInt(Tools::getValue(\'product_quantity\'))',
                                    '!(PP::orderEditQtyBehaviorFloat($order_detail, $order_detail->product_quantity) ? Validate::isUnsignedFloat(PP::getFloatNonNegativeValue(\'product_quantity\')) : Validate::isUnsignedInt(Tools::getValue("product_quantity")))'
                                ),
                                array( // reinjectQuantity
                                    '$quantity_to_reinject = $qty_cancel_product > $reinjectable_quantity ? $reinjectable_quantity : $qty_cancel_product;',
                                    '$quantity_to_reinject = PP::resolveQty($qty_cancel_product > $reinjectable_quantity ? $reinjectable_quantity : $qty_cancel_product, $order_detail->product_quantity_fractional);'
                                ),
                                array( // reinjectQuantity
                                    '$movement[\'physical_quantity\']',
                                    '($movement[ \'physical_quantity\' ] + (float) $movement[\'physical_quantity_remainder\'])'
                                ),
                            ),
                'append' => array(
                                array( // renderView
                                    "\n        \$product['quantity_refundable'] = \$product['product_quantity'] - \$resume['product_quantity'];",
                                    array(
                                        'if (PP::qtyBehavior($product, $product[\'product_quantity\'])) {',
                                        '    $product[\'quantity_refundable\'] = PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']) - $resume[\'product_quantity\'];',
                                        '}',
                                    ),
                                    'indent' => '        ',
                                ),
                                array(
                                    "\n            \$product['quantity_refundable'] = \$product['product_quantity'] - \$resume['product_quantity'];",
                                    array(
                                        'if (PP::qtyBehavior($product, $product[\'product_quantity\'])) {',
                                        '    $product[\'quantity_refundable\'] = PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']) - $resume[\'product_quantity\'];',
                                        '}',
                                    ),
                                    'indent' => '            ',
                                ),
                                array(
                                    '$combinations = array();',
                                    array(
                                        '$product[\'pproperties\'] = PP::safeOutput($productObj->productProperties());',
                                        '$product[\'pproperties\'][\'minimal_quantity\'] = $productObj->minimal_quantity;',
                                        '$product[\'pproperties\'][\'minimal_quantity_fractional\'] = $productObj->minimal_quantity_fractional;',
                                        'if (isset($productObj->quantity_step)) {',
                                        '    $product[\'pproperties\'][\'quantity_step\'] = $productObj->quantity_step;',
                                        '}',
                                        'PP::productResolveQuantities($product[\'pproperties\']);',
                                    ),
                                    'indent' => '                ',
                                ),
                            ),
            ),
            array(
                'files'  => array('controllers/admin/AdminProductsController.php'),
                'replace'=> array(
                                array( // processProductAttribute
                                    "'minimal_quantity' => 'isUnsignedInt'",
                                    "'minimal_quantity' => 'validateProductQuantity'"
                                ),
                                array( // processPriceAddition
                                    '$specificPrice->from_quantity = (int) ($from_quantity);',
                                    '$specificPrice->from_quantity = (float) $from_quantity;'
                                ),
                                array( // _validateSpecificPrice
                                    'elseif (!Validate::isUnsignedInt($from_quantity))',
                                    'elseif (!Validate::validateSpecificPriceProductQuantity($from_quantity))'
                                ),
                                array( // ajaxProcessProductQuantity
                                    "if (Tools::getValue('value') === false || (!is_numeric(trim(Tools::getValue('value'))))) {",
                                    "if (Tools::getValue('value') === false || (!is_numeric(str_replace(',', '.', trim(Tools::getValue('value')))))) {"
                                ),
                                array( // ajaxProcessProductQuantity
                                    'StockAvailable::setQuantity($product->id, (int) Tools::getValue(\'id_product_attribute\'), (int) Tools::getValue(\'value\'));',
                                    'StockAvailable::setQuantity($product->id, (int) Tools::getValue(\'id_product_attribute\'), $product->normalizeQty(Tools::getValue(\'value\')));'
                                ),
                            ),
                'prepend'=> array(
                                array( // copyFromPost
                                    "if (Tools::getIsset('unit_price') != null) {",
                                    array(
                                        'if (Tools::getIsset(\'minimal_quantity\')) {',
                                        '    $minimal_quantity = Tools::getValue(\'minimal_quantity\');',
                                        '    $minimal_quantity = (empty($minimal_quantity) ? 0 : str_replace(\',\', \'.\', $minimal_quantity));',
                                        '} else {',
                                        '    $minimal_quantity = \PP::resolveMinQty($object->min_quantity, $object->min_quantity_fractional);',
                                        '}',
                                        '$object->setMinQty($minimal_quantity);',
                                        '$_POST[\'minimal_quantity\'] = (string)$minimal_quantity;',
                                    ),
                                    'indent' => '        '
                                ),
                ),
                'append' => array(
                                array( // ajaxProcessEditProductAttribute
                                    'foreach ($combinations as $key => $combination) {',
                                    array("\$combinations[\$key]['minimal_quantity'] = \$product->boMinQty(\$combination['minimal_quantity'], \$combination['minimal_quantity_fractional']);"),
                                    'indent' => '                    ',
                                    'count' => 1
                                ),
                ),
            ),
            array(
                'files'  => array('controllers/front/CartController.php'),
                'replace'=> array(
                                array(
                                    'class CartControllerCore extends FrontController',
                                    'class CartControllerCore extends CartControllerBase'
                                ),
                                array(
                                    'private $updateOperationError',
                                    'protected $updateOperationError',
                                    'uninstall' => false
                                ),
                                array( // init
                                    '$this->qty = abs(Tools::getValue(\'qty\', 1));',
                                    '$this->qty = abs(PP::normalizeProductQty(Tools::getValue(\'qty\', 1), $this->id_product));'
                                ),
                                array( // displayAjaxProductRefresh
                                    '\'productUrl\' => $url',
                                    '\'productUrl\' => PP::displayAjaxProductRefresh($url, $this->id_product)'
                                ),
                                array(
                                    '(int) $this->qty',
                                    '(float) $this->qty'
                                ),
                                array( // processDeleteProductInCart
                                    'WHERE `id_cart` =',
                                    'WHERE `in_cart` = 1 AND `id_cart` ='
                                ),
                                array( // processDeleteProductInCart
                                    '(int) Attribute::getAttributeMinimalQty',
                                    '$product->attributeMinQty'
                                ),
                                array( // processDeleteProductInCart
                                    '(int) $product->minimal_quantity',
                                    '$product->minQty()'
                                ),
                                array( // processDeleteProductInCart
                                    '$total_quantity += $custom[\'quantity\'];',
                                    '$total_quantity += PP::resolveQty($custom[\'quantity\'], $custom[\'quantity_fractional\']);'
                                ),
                                array(
                                    'protected function processChangeProductInCart(',
                                    'protected function processChangeProductInCartCore('
                                ),
                                array(
                                    'public function productInCartMatchesCriteria($productInCart)',
                                    'public function productInCartMatchesCriteria($productInCart, $ignore_icp = false)'
                                ),
                                array(
                                    'private function shouldAvailabilityErrorBeRaised',
                                    'protected function shouldAvailabilityErrorBeRaised',
                                    'uninstall' => false
                                ),
                                array( // productInCartMatchesCriteria
                                    ') && isset($this->id_product) && $productInCart[\'id_product\'] == $this->id_product;',
                                    ') && isset($this->id_product) && $productInCart[\'id_product\'] == $this->id_product && ($ignore_icp || (Tools::getIsset(\'icp\') ? (int) Tools::getValue(\'icp\') == $productInCart[\'id_cart_product\'] : !empty($this->context->cart->last_icp) && $this->context->cart->last_icp == $productInCart[\'id_cart_product\']));'
                                ),
                            ),
                'append' => array(
                                array( // displayAjaxUpdate
                                    'choice' => array(
                                        array(
                                            '$productQuantity = $updatedProduct[\'quantity\'];',
                                            array(
                                                "if (PP::qtyBehavior(\$updatedProduct, \$updatedProduct['quantity'])) {",
                                                '    $productQuantity = PP::resolveQty($updatedProduct);',
                                                '}',
                                            ),
                                            'indent' => '        '
                                        ),
                                        array(
                                            '$productQuantity = $updatedProduct[\'quantity\'] ?? 0;',
                                            array(
                                                "if (PP::qtyBehavior(\$updatedProduct, \$updatedProduct['quantity'] ?? 0)) {",
                                                '    $productQuantity = PP::resolveQty($updatedProduct);',
                                                '}',
                                            ),
                                            'indent' => '        '
                                        ),
                                    ),
                                ),
                                array( // displayAjaxUpdate
                                    '$presentedCart = $cartPresenter->present($this->context->cart);',
                                    array(
                                        'if (!empty($this->context->cart->last_icp)) {',
                                        "    \$presentedCart['last_icp'] = \$this->context->cart->last_icp;",
                                        '}',
                                    ),
                                    'indent' => '            ',
                                ),
                                array( // processDeleteProductInCart
                                    "'id_cart' => (int) \$this->context->cart->id,",
                                    array("'id_cart_product' => (int) Tools::getValue('icp'),"),
                                    'indent' => '            '
                                ),
                                array( // processDeleteProductInCart
                                    "\$this->customization_id,\n            \$this->id_address_delivery",
                                    ",\n            (int) Tools::getValue('icp')",
                                    'count' => 1
                                ),
                                array( // areProductsAvailable
                                    "\$currentProduct->hasAttributes() && \$product['id_product_attribute'] === '0'",
                                    " && 'bulk' !== PP::getPPDataType(\$product)",
                                    'ignore' => !$is_ps_version_1_7_7_or_later
                                ),
                                array( // areProductsAvailable
                                    "if (\$product['active']) {",
                                    array(
                                        "if (isset(\$product['ppCheckQuantitiesMessage'])) {",
                                        "    return \$product['ppCheckQuantitiesMessage'];",
                                        '}',
                                    ),
                                    'indent' => '            ',
                                ),
                            ),
                'regex'  => array(
                                array( // processDeleteProductInCart
                                    '/\$this->errors\[\] = \$this->trans\(\s*\'You must add %quantity((?!\'Shop.Notifications.Error\').)*\'Shop.Notifications.Error\'\s*\);/s',
                                    '$this->errors[] = \PSM::translate(\'should_add_at_least\', array('.
                                    '\'%quantity%\' => \PP::formatQty($minimal_quantity),'.
                                    '\'%text%\' => \PSM::nvl($product->productProperties()[\'pp_qty_text\'], \PSM::plural($minimal_quantity)),'.
                                    '\'%name%\' => \PSM::amendForTranslation($product->name)));',
                                ),
                            )
            ),
            array(
                'files'  => array('controllers/front/ProductController.php'),
                'replace'=> array(
                                array(
                                    '(int) $row[\'quantity\']',
                                    '(float) $row[\'quantity\']',
                                    'count' => 3
                                ),
                                array( // assignAttributesGroups
                                    "\$row['minimal_quantity'];",
                                    "\PP::productMinQty(\$row['minimal_quantity'], \$row['minimal_quantity_fractional'], \$this->product->productProperties());",
                                    'count' => 1
                                ),
                                array( // getRequiredQuantity
                                    '$requiredQuantity = (int) Tools::getValue(\'quantity_wanted\', $this->getProductMinimalQuantity($product));',
                                    array(
                                        'PP::productResolveQuantities($product);',
                                        '$requiredQuantity = PP::resolveInputQty(Tools::getValue(\'quantity_wanted\'), $product, $product, false);'
                                    ),
                                    'indent' => '        '
                                ),
                                array( // formatQuantityDiscounts
                                    '(int) $specific_prices',
                                    '(float) $specific_prices'
                                ),
                            ),
                'append' => array(
                                array( // displayAjaxRefresh
                                    '$product = $this->getTemplateVarProduct();',
                                    array('PP::productResolveQuantities($product);'),
                                    'indent' => '        '
                                ),
                                array( // displayAjaxRefresh
                                    "'product_images_modal' => \$this->render('catalog/_partials/product-images-modal'),",
                                    array("'product_pproperties' => \$this->render('module:pproperties/_partials/product'),"),
                                    'indent' => '            '
                                ),
                                array( // assignAttributesGroups
                                    '$attributes_groups = $this->product->getAttributesGroups($this->context->language->id);',
                                    array(
                                        "if ('bulk' === PP::getPPDataType(\$product_for_template)) {",
                                        '    $attributes_groups = null;',
                                        '}'
                                    ),
                                    'indent' => '        ',
                                    'count' => 1
                                ),
                            ),
                'prepend'=> array(
                                array( // getTemplateVarProduct
                                    '$product[\'minimal_quantity\'] = $this->getProductMinimalQuantity($product);',
                                    array('PP::productResolveQuantities($product);'),
                                    'indent' => '        '
                                ),
                                array( // getProductMinimalQuantity
                                    '$minimal_quantity = 1;',
                                    array(
                                        'PP::productResolveQuantities($product);',
                                        'return $product[\'minimal_quantity\'];'
                                    ),
                                    'indent' => '        '
                                ),
                                array( // getMinimalProductOrDeclinationQuantity
                                    '$productAttributeId = $product[\'id_product_attribute\'];',
                                    array(
                                        'PP::productResolveQuantities($product);',
                                        'return $product[\'minimal_quantity\'];'
                                    ),
                                    'indent' => '        ',
                                    'ignore' => version_compare(_PS_VERSION_, '1.8', '>=')
                                ),
                            ),
            ),
            array(
                'files'  => array('js/admin/products.js'),
                'replace'=> array(
                                array(
                                    '$(this).parent().attr(\'id\').split(\'_\')[1]',
                                    '($(this).parent().attr(\'id\') == undefined ? $(this).parent().parent() : $(this).parent()).attr(\'id\').split(\'_\')[1]'
                                ),
                            )
            ),
            array(
                'files'  => array('js/admin/orders.js'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'replace'=> array(
                                array(
                                    '= makeTotalProductCaculation(',
                                    '= ppMakeTotalProductCaculation(typeof element == "undefined" ? null : element, '
                                ),
                                array(
                                    'element = element.parent().parent().parent();',
                                    "element = element.closest('tr');"
                                ),
                                array(
                                    'var quantity = parseInt(',
                                    'var quantity = pp.parseFloat('
                                ),
                                array(
                                    'if (quantity < 1',
                                    'if (quantity < 0'
                                ),
                                array(
                                    'var stock_available = parseInt',
                                    'var stock_available = pp.parseFloat'
                                ),
                                array(
                                    'element_list.parent().parent().find(\'td .product_quantity_show\').hide();',
                                    'element_list.find(\'td .product_quantity_show\').hide();',
                                    'count' => -1,
                                    'uninstall' => false
                                ),
                                array(
                                    'element_list.parent().parent().find(\'td .product_quantity_edit\').show();',
                                    'element_list.find(\'td .product_quantity_edit\').show();',
                                    'count' => -1,
                                    'uninstall' => false
                                ),
                            )
            ),
            array(
                'files'  => array('src/Adapter/CombinationDataProvider.php'),
                'replace'=> array(
                                array(
                                    "'attribute_minimal_quantity' => \$combination['minimal_quantity'],",
                                    "'attribute_minimal_quantity' => \$product->boMinQty(\$combination['minimal_quantity'], \$combination['minimal_quantity_fractional']),",
                                )
                            )
            ),
            array(
                'files'  => array('src/Adapter/Presenter/AbstractLazyArray.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                'prepend'=> array(
                                array(
                                    'public function offsetGet($index)',
                                    array(
                                        'public function __clone()',
                                        '{',
                                        '    $this->arrayAccessList = clone $this->arrayAccessList;',
                                        '    $this->arrayAccessIterator = clone $this->arrayAccessIterator;',
                                        '}'
                                    ),
                                    'indent' => '    ',
                                    'uninstall' => false,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.6.0', '>=')
                                ),
                            ),
            ),
            array(
                'files'  => array(version_compare(_PS_VERSION_, '1.7.5.0', '>=') ? 'src/Adapter/Presenter/Object/ObjectPresenter.php' : 'src/Adapter/ObjectPresenter.php'),
                'prepend'=> array(
                                array(
                                    '$fields = $object::$definition[\'fields\'];',
                                    array(
                                        'static $pp_exists = null;',
                                        'if ($pp_exists === null) {',
                                        '    $pp_exists = method_exists(\'\PP\', \'objectModelGetDefinition\');',
                                        '}',
                                        'if ($pp_exists) {',
                                        '    \PP::objectModelGetDefinition(get_class($object), $object::$definition);',
                                        '}',
                                    ),
                                    'indent' => '        ',
                                ),
                            )
            ),
            array(
                'files'  => array(version_compare(_PS_VERSION_, '1.7.5.0', '>=') ? 'src/Adapter/Presenter/Cart/CartPresenter.php' : 'src/Adapter/Cart/CartPresenter.php'),
                'replace'=> array(
                                array( // presentProduct
                                    '$rawProduct[\'quantity_wanted\'] = $rawProduct[\'cart_quantity\'];',
                                    array(
                                        '$quantity = \PP::obtainQty($rawProduct);',
                                        '$quantity_fractional = \PP::obtainQtyFractional($rawProduct);',
                                        'if (\PP::performPricerounding()) {',
                                        '    $rawProduct[ \'total\' ] = $this->priceFormatter->format(',
                                        '        $this->includeTaxes() ?',
                                        '        \PP::pricerounding($rawProduct[\'total_price_tax_incl\'], \'total\', $rawProduct[\'id_product\'], $quantity, $quantity_fractional, true) :',
                                        '        \PP::pricerounding($rawProduct[\'total_price_tax_excl\'], \'total\', $rawProduct[\'id_product\'], $quantity, $quantity_fractional, false)',
                                        '    );',
                                        '}',
                                        '$rawProduct[\'quantity_wanted\'] = \PP::resolveQty($quantity, $quantity_fractional);',
                                        '$rawProduct[\'include_taxes\'] = $this->includeTaxes();'
                                    ),
                                    'indent' => '        '
                                ),
                            ),
                'append' => array(
                                array(
                                    'private $taxConfiguration;',
                                    array("public \$presenter_type = 'cart';"),
                                    'indent' => '    '
                                ),
                                array(
                                    'choice' => array(
                                        array( // presentProduct
                                            '$settings = new ProductPresentationSettings();',
                                            ['$settings->presenter_type = $this->presenter_type;'],
                                            'indent' => '        '
                                        ),
                                        array( // getSettings() - since 1.7.8.0
                                            '$this->settings = new ProductPresentationSettings();',
                                            ['$this->settings->presenter_type = $this->presenter_type;'],
                                            'indent' => '            '
                                        ),
                                    ),
                                ),
                            ),
                'prepend'=> array(
                                array( // presentProduct
                                    '$rawProduct[\'total\'] = $this->priceFormatter->format',
                                    array(
                                        '$rawProduct[\'total_price_tax_excl\'] = $rawProduct[\'total\'];',
                                        '$rawProduct[\'total_price_tax_incl\'] = $rawProduct[\'total_wt\'];'
                                    ),
                                    'indent' => '        '
                                ),
                                array( // addCustomizedData
                                    '$customizations[] = $presentedCustomization;',
                                    array(
                                        '\PP::amendPresentedUrls($product, $product);',
                                        '\PP::amendPresentedUrls($presentedCustomization, $product);'
                                    ),
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                                    'indent' => '                                '
                                ),
                                array( // addCustomizedData
                                    '$product[\'customizations\'][] = $presentedCustomization;',
                                    array(
                                        '\PP::amendPresentedUrls($product, $product);',
                                        '\PP::amendPresentedUrls($presentedCustomization, $product);'
                                    ),
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '>='),
                                    'indent' => '                                '
                                ),
                            ),
            ),
            array(
                'files'  => array(version_compare(_PS_VERSION_, '1.7.5.0', '>=') ? 'src/Adapter/Presenter/Order/OrderLazyArray.php' : 'src/Adapter/Order/OrderPresenter.php'),
                'replace'=> array(
                                array( // getProducts()
                                    '$orderProduct[$totalPrice],',
                                    '\PP::pricerounding($orderProduct[ $totalPrice ], \'total\', $orderProduct[\'product_id\'], $orderProduct[\'product_quantity\'], $orderProduct[\'product_quantity_fractional\'], $includeTaxes),'
                                ),
                            ),
                'append' => array(
                                array( // __construct
                                    '$this->cartPresenter = new CartPresenter();',
                                    array("\$this->cartPresenter->presenter_type = 'order';"),
                                    'indent' => '        ',
                                ),
                                array( // getProducts()
                                    '&& ($cartProduct[\'id_product_attribute\'] === $orderProduct[\'id_product_attribute\'])',
                                    ' && ($cartProduct[\'id_cart_product\'] === $orderProduct[\'id_cart_product\'])',
                                    'count' => 1
                                ),
                            ),
                'prepend' => array(
                                array( // getProducts()
                                    'if (isset($cartProduct[\'attributes\'])) {',
                                    array('\PP::orderPresenterGetProducts($orderProduct, $cartProduct);'),
                                    'indent' => '                    ',
                                    'count' => 1
                                ),
                            ),
            ),
            array(
                'files' => array('src/Adapter/Presenter/Product/ProductLazyArray.php'),
                'ignore'=> version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                'append' => array(
                                array(
                                    '$this->translator = $translator;',
                                    array( // __construct
                                        '\PP::productResolveQuantities($this->product, $language->id);',
                                        '$this->product[\'quantity_wanted\'] = $this->getQuantityWanted();',
                                        '$product = $this->product;',
                                        'if (!empty($product[\'quantity_wanted\'])) {',
                                        '    \PP::productPresenterPresent($this->settings, $product, $this->language);',
                                        '}',
                                    ),
                                    'indent' => '        ',
                                ),
                                array( // shouldEnableAddToCartButton
                                    '$shouldEnable = $shouldEnable && $this->shouldShowAddToCartButton($product);',
                                    array(
                                        'if ($shouldEnable && is_bool($se = \PP::shouldEnableAddToCartButton($product, $settings))) {',
                                        '    return $se;',
                                        '}'
                                    ),
                                    'indent' => '        ',
                                ),
                                array( // addQuantityInformation
                                    '$show_availability = $show_price && $settings->stock_management_enabled;',
                                    array(
                                        'if (($show_availability = \PP::addQuantityInformation($settings, $this->product, $language, $show_availability)) === null) {',
                                        '    return;',
                                        '}'
                                    ),
                                    'indent' => '        ',
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    '$this->appendArray($this->product);',
                                    array( // __construct
                                        '\PP::productPresenterPresent($this->settings, $this->product, $this->language);',
                                    ),
                                    'indent' => '        ',
                                ),
                            ),
                'replace'=> array(
                                array( // shouldEnableAddToCartButton
                                    '- $this->getQuantityWanted() < 0',
                                    '- (array_key_exists(\'pp_quantity_wanted\', $product) ? $product[\'pp_quantity_wanted\'] : $product[\'quantity_wanted\']) < 0'
                                ),
                                array( // getMinimalQuantity
                                    'return (int) $this->product[\'minimal_quantity\'];',
                                    'return (float) $this->product[\'minimal_quantity\'];',
                                    'ignore' => !$is_ps_version_1_7_7_or_later,
                                ),
                                array( // addQuantityInformation
                                    '- $product[\'quantity_wanted\'] >= 0',
                                    '- (array_key_exists(\'pp_quantity_wanted\', $product) ? $product[\'pp_quantity_wanted\'] : $product[\'quantity_wanted\']) >= 0',
                                    'ignore' => $is_ps_version_1_7_8_or_later,
                                ),
                                array( // addQuantityInformation
                                    '- $product[\'quantity_wanted\'];',
                                    '- (array_key_exists(\'pp_quantity_wanted\', $product) ? $product[\'pp_quantity_wanted\'] : $product[\'quantity_wanted\']);',
                                    'ignore' => !$is_ps_version_1_7_8_or_later,
                                ),
                                array( // addQuantityInformation
                                    '($product[\'quantity_wanted\'] > 0',
                                    '((array_key_exists(\'pp_quantity_wanted\', $product) ? $product[\'pp_quantity_wanted\'] : $product[\'quantity_wanted\']) > 0'
                                ),
                            ),
            ),
            array(
                'files'  => array(version_compare(_PS_VERSION_, '1.7.5.0', '>=') ? 'src/Adapter/Presenter/Product/ProductLazyArray.php' : 'src/Core/Product/ProductPresenter.php'),
                'replace'=> array(
                                array(
                                    '$product[\'quantity\']',
                                    '(array_key_exists(\'quantity_avalable\', $product) ? $product[\'quantity_avalable\'] : $product[ \'quantity\' ])',
                                ),
                                array( // addPriceInformation
                                    '$price = $regular_price = $product[\'price\'];',
                                    '$price = $regular_price = (isset($product[\'price_amount\']) ? $product[\'price_amount\'] : $product[\'price\']);',
                                    'uninstall' => false
                                ),
                                array( // addPriceInformation
                                    '$price = $regular_price = $product[\'price_tax_exc\'];',
                                    '$price = $regular_price = (isset($product[\'price_amount\']) ? $product[\'price_amount\'] : $product[\'price_tax_exc\']);',
                                    'uninstall' => false
                                ),
                                array( // addPriceInformation
                                    "(0 != \$product['reduction'])",
                                    "(0 != \$product['reduction'] && (empty(\$product['quantity_wanted']) || (float) (array_key_exists('pp_quantity_wanted', \$product) ? \$product['pp_quantity_wanted'] : \$product['quantity_wanted']) >= (float) \$product['specific_prices']['from_quantity']))",
                                ),
                                array( // getQuantityWanted
                                    "return (int) Tools::getValue('quantity_wanted', 1);",
                                    'return \PP::resolveInputQty(Tools::getValue(\'quantity_wanted\'), $this->product, null, false);',
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Adapter/Presenter/Product/ProductListingLazyArray.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                'prepend'=> array(
                                array(
                                    'return parent::getAddToCartUrl();',
                                    array(
                                        'if (!\PP::allowAddToCartUrlOnProductListing($this->product)) {',
                                        '    return null;',
                                        '}'
                                    ),
                                    'indent' => '        ',
                                    'count' => 1
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Core/Filter/HashMapWhitelistFilter.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                'prepend'=> array(
                                array(
                                    '$subject->intersectKey($this->whitelistItems);',
                                    array('$subject = clone $subject;'),
                                    'indent' => '            ',
                                    'uninstall' => false
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Core/Filter/FrontEndObject/ProductFilter.php'),
                'append' => array(
                                array(
                                    $is_ps_version_1_7_7_or_later ? '$whitelist = [' : '$whitelist = array(',
                                    array(
                                        "'id_cart_product',",
                                        "'pp_settings',",
                                    ),
                                    'indent' => '            '
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Core/Import/EntityField/Provider/ProductFieldsProvider.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.6.0', '<'),
                'append' => array(
                                array(
                                    'new EntityField(\'accessories\', $this->trans(\'Accessories (x,y,z...)\', \'Admin.Advparameters.Feature\')),',
                                    array('new EntityField(\'id_pp_template\', \\PSM::trans(\'Product Properties Extension template ID\')),'),
                                    'indent' => '            ',
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Core/Product/ProductListingPresenter.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '>='),
                'prepend'=> array(
                                array(
                                    'return $presentedProduct;',
                                    array(
                                        'if (\PP::isMultidimensional($product)) {',
                                        '    $presentedProduct[\'add_to_cart_url\'] = null;',
                                        '}'
                                    ),
                                    'indent' => '        ',
                                    'count' => 1
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Core/Product/ProductPresenter.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '>='),
                'replace'=> array(
                                array(
                                    'public function present(',
                                    array(
                                        'public function present (',
                                        '    ProductPresentationSettings $settings,',
                                        '    array $product,',
                                        '    Language $language',
                                        ') {',
                                        '    \PP::productResolveQuantities($product, $language->id);',
                                        '    $this->product = $product;',
                                        '    $product[\'quantity_wanted\'] = $this->getQuantityWanted();',
                                        '    $presentedProduct = $this->presentCore($settings, $product, $language);',
                                        '    \PP::productPresenterPresent($settings, $presentedProduct, $language);',
                                        '    return $presentedProduct;',
                                        '}',
                                        'public function presentCore('
                                    ),
                                    'indent' => '    ',
                                ),
                ),
            ),
            array(
                'files'  => array('src/Adapter/Product/AdminProductWrapper.php'),
                'replace'=> array(
                                array( // processProductSpecificPrice
                                    '$from_quantity = $specificPriceValues[\'sp_from_quantity\'];',
                                    '$from_quantity = \PP::normalizeProductQty($specificPriceValues[\'sp_from_quantity\'], $id_product, true);',
                                ),
                                array( // processProductSpecificPrice
                                    '(int) ($from_quantity)',
                                    '(float) ($from_quantity)',
                                ),
                                array( // validateSpecificPrice
                                    'isUnsignedInt($from_quantity)',
                                    'isUnsignedFloat($from_quantity)',
                                ),
                                array( // getSpecificPricesList
                                    '\'from_quantity\' => $specific_price[\'from_quantity\'],',
                                    '\'from_quantity\' => (float) $specific_price[\'from_quantity\'],',
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Adapter/StockManager.php'),
                'replace'=> array(
                                array( // updatePhysicalProductQuantity
                                    'SET sa.physical_quantity = sa.quantity + sa.reserved_quantity',
                                    'SET sa.physical_quantity = FLOOR(@q := sa.quantity + sa.quantity_remainder + sa.reserved_quantity + sa.reserved_quantity_remainder)'.
                                    ', sa.physical_quantity_remainder = (@q - sa.physical_quantity)',
                                ),
                                array( // updateReservedProductQuantity
                                    'SET sa.reserved_quantity = (',
                                    'SET sa.reserved_quantity = FLOOR(@q := COALESCE((',
                                ),
                                array( // updateReservedProductQuantity
                                    'SUM(od.product_quantity - od.product_quantity_refunded)',
                                    "SUM(IF(od.pp_data > \'\', 0, " . PP::sqlQty('product_quantity', 'od') . ' - od.product_quantity_refunded) + IFNULL(ppod.quantity, 0))',
                                ),
                                array( // updateReservedProductQuantity => bug fix
                                    '(sa.id_product_attribute = od.product_attribute_id OR sa.id_product_attribute = ppod.id_product_attribute)',
                                    'sa.id_product_attribute = od.product_attribute_id',
                                    'fix' => ['replace all' => null],
                                    'uninstall' => false,
                                    'count' => -2
                                ),
                                array( // updateReservedProductQuantity
                                    'sa.id_product_attribute = od.product_attribute_id',
                                    '(sa.id_product_attribute  =  od.product_attribute_id OR sa.id_product_attribute = ppod.id_product_attribute)',
                                ),
                                array( // updateReservedProductQuantity
                                    'GROUP BY od.product_id, od.product_attribute_id',
                                    'GROUP BY sa.id_product, sa.id_product_attribute',
                                ),
                            ),
                'prepend'=> array(
                                array( // updateReservedProductQuantity
                                    'WHERE o.id_shop = :shop_id AND',
                                    array('LEFT JOIN {table_prefix}pp_order_detail ppod ON ppod.id_order = o.id_order AND ppod.data_type = \\\'bulk\\\''),
                                    'indent' => '                '
                                ),
                                array( // updateReservedProductQuantity
                                    'WHERE sa.id_shop = :shop_id',
                                    ', 0)), sa.reserved_quantity_remainder = (@q - sa.reserved_quantity) ',
                                ),
                            ),
            ),
            array(
                'files' => array('src/Core/Cart/CartRow.php'),
                'append' => array(
                                array(
                                    'class CartRow',
                                    ' extends CartRowBase'
                                ),
                            ),
                'prepend'=> array(
                                array( // getProductPrice
                                    version_compare(_PS_VERSION_, '1.7.7.1', '<') ? '$priceTaxIncl = $this->priceCalculator->priceCalculation(' : 'foreach ($productPrices as $productPrice => $computationParameters) {',
                                    array(
                                        'if ($bulk = \PP::getProductBulk($rowData)) {',
                                        '    $quantity = $bulk;',
                                        '}',
                                    ),
                                    'indent' => '        '
                                ),
                            ),
                'replace'=> array(
                                array( // processCalculation, getProductPrice, applyRound, updateFinalUnitPrice
                                    '(int) $rowData[\'cart_quantity\']',
                                    '\PP::resolveQty($rowData)',
                                ),
                                array( // getProductPrice
                                    '(int) $quantity)',
                                    '(float) $quantity)',
                                ),
                                array( // getProductPrice
                                    'SUM(`quantity`)',
                                    'SUM('.PP::sqlQty('quantity').')',
                                ),
                                array(
                                    'protected function applyRound()',
                                    'protected function applyRoundCore()',
                                ),
                            ),
            ),
            array(
                'files'  => array('src/Core/Stock/StockManager.php'),
                'replace'=> array(
                                array( // updateQuantity
                                    '$stockAvailable->quantity = $stockAvailable->quantity + $delta_quantity;',
                                    '\PP::setQtyO($stockAvailable, $stockAvailable->quantity + $stockAvailable->quantity_remainder + $delta_quantity);'
                                ),
                                array( // updateQuantity
                                    '\'quantity\' => $stockAvailable->quantity',
                                    '"quantity" => $stockAvailable->quantity + $stockAvailable->quantity_remainder',
                                ),
                                array( // prepareMovement
                                    '$deltaQuantity >= 1 ?',
                                    '$deltaQuantity >= 0 ?',
                                ),
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Controller/Admin/ProductController.php'),
                'prepend'=> array(
                                array(
                                    '$product = $productAdapter->getProduct',
                                    array('\\ProductBase::$amend = false;'),
                                    'indent' => '        ',
                                )
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Controller/Admin/SpecificPriceController.php'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                'append' => array(
                                array(
                                    '\'sp_reduction_tax\' => $price->reduction_tax,',
                                    array('\'sp_pp_bo_qty_text\' => \PP::getProductProperty($price->id_product, \'pp_bo_qty_text\'),'),
                                    'indent' => '            ',
                                )
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Entity/AdminFilter.php'),
                'replace'=> array(
                                array(
                                    "            'filter_column_sav_quantity' => " . ($is_ps_version_1_7_7_or_later ? "[\n" : "array(\n") .
                                    "                'filter' => FILTER_CALLBACK,\n" .
                                    "                'options' => \$filterMinMax(FILTER_SANITIZE_NUMBER_INT),\n",
                                    "            'filter_column_sav_quantity' => " . ($is_ps_version_1_7_7_or_later ? "[\n" : "array(\n") .
                                    "                'filter' => FILTER_CALLBACK,\n" .
                                    "                'options' => \$filterMinMax(FILTER_SANITIZE_NUMBER_FLOAT),\n",
                                )
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Entity/Repository/NormalizeFieldTrait.php'),
                'replace'=> array(
                                array(
                                    '$columnValue = (int) $columnValue;',
                                    '$columnValue = (float) $columnValue;',
                                )
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Entity/Repository/StockRepository.php'),
                'replace'=> array(
                                array( // updateStock
                                    '$delta >= 1 ?',
                                    '$delta >= 0 ?',
                                ),
                            ),
                'prepend'=> array(
                                array( // selectSql
                                    'AS product_available_quantity,',
                                    '+ sa.quantity_remainder ',
                                ),
                                array( // selectSql
                                    'AS product_physical_quantity,',
                                    '+ sa.physical_quantity_remainder ',
                                ),
                                array( // selectSql
                                    'AS product_reserved_quantity,',
                                    '+ sa.reserved_quantity_remainder ',
                                ),
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Entity/Repository/StockMovementRepository.php'),
                'replace'=> array(
                                array(
                                    'sm.physical_quantity,',
                                    '(sm.physical_quantity + sm.physical_quantity_remainder) as physical_quantity,',
                                )
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Entity/StockMvt.php'),
                'replace'=> array(
                                array( // setPhysicalQuantity
                                    '$this->physicalQuantity = $physicalQuantity;',
                                    'list($this->physicalQuantity, $this->physicalQuantityRemainder) = \PP::explodeQty($physicalQuantity);'
                                ),
                                array( // getPhysicalQuantity
                                    'return $this->physicalQuantity;',
                                    'return ($this->physicalQuantity + $this->physicalQuantityRemainder);'
                                ),
                            ),
                'append' => array(
                                array(
                                    'private $physicalQuantity;',
                                    array(
                                        '',
                                        '/**',
                                        ' * @ORM\Column(name="physical_quantity_remainder", type="decimal", precision=20, scale=6, nullable=false, options={"default":"0.000000"})',
                                        ' */',
                                        'private $physicalQuantityRemainder;',
                                    ),
                                    'indent' => '    ',
                                ),
                            ),
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Model/Product/AdminModelAdapter.php'),
                'replace'=> array(
                                array(
                                    "'minimal_quantity' => \$this->product->minimal_quantity,",
                                    "'minimal_quantity' => \$this->product->boMinQty(\$this->product->minimal_quantity, \$this->product->minimal_quantity_fractional),",
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '>=')
                                ),
                                array(
                                    "'minimal_quantity' => \$product->minimal_quantity,",
                                    "'minimal_quantity' => \$product->boMinQty(\$product->minimal_quantity, \$product->minimal_quantity_fractional),",
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<')
                                ),
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Resources/views/Admin/Product/CatalogPage/catalog.html.twig'),
                'prepend'=> array(
                                array(
                                    "{# Activation product modal #}",
                                    array("{{ include('@Modules/pproperties/views/templates/admin/catalog_manage_templates_modal.html.twig') }}"),
                                    'indent' => '  ',
                                ),
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Resources/views/Admin/Product/CatalogPage/Blocks/filters.html.twig'),
                'prepend'=> array(
                                array(
                                    version_compare(_PS_VERSION_, '1.7.5.0', '>=') ? "{% include '@PrestaShop/Admin/Helpers/dropdown_menu.html.twig'" : "{% include 'PrestaShopBundle:Admin/Helpers:dropdown_menu.html.twig'",
                                    array(
                                        '{% set buttons_action = buttons_action|merge([',
                                        '{',
                                        '    "divider": true',
                                        '}, {',
                                        '    "onclick": "bulkManageTemplatesOpenForm(this);",',
                                        '    "icon": "reorder",',
                                        '    "label": "Manage templates"|psmtrans',
                                        '}',
                                        ']) %}',
                                    ),
                                    'indent' => '          ',
                                ),
                            ),
                'append' => array(
                                array(
                                    '<div id="catalog-actions" class="col order-first">',
                                    array('<div class="row"><div class="col">{{ renderhook("displayAdminBulkManageTemplates", {}) }}</div></div>'),
                                    'indent' => '  ',
                                ),
                            ),
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Resources/views/Admin/Product/CatalogPage/Lists/list.html.twig'),
                'append' => array(
                                array(
                                    '<a href="{{ product.url|default(\'\') }}#tab-step1">{{ product.name|default(\'N/A\'|trans({}, \'Admin.Global\')) }}</a>',
                                    array('{% if product.pp_template is defined %}{{ product.pp_template|raw }}{% endif %}'),
                                    'indent' => '              ',
                                ),
                            ),
                'replace'=> array(
                                array(
                                    '{{ product.sav_quantity }}',
                                    array(
                                        '{% if product.sav_quantity_to_display is defined %}',
                                        '    {{product.sav_quantity_to_display|raw}}',
                                        '{% else %}',
                                        '    {{product.sav_quantity}}',
                                        '{% endif %}',
                                    ),
                                    'indent' => '                        ',
                                ),
                            )
            ),
            array(
                'files'  => array('src/PrestaShopBundle/Resources/views/Admin/Product/ProductPage/Forms/form_specific_price.html.twig'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '<'),
                'replace'=> array(
                                array(
                                    '{{ \'Unit(s)\'|trans({}, \'Admin.Catalog.Feature\') }}',
                                    '{% if form.vars.value.sp_pp_bo_qty_text is defined and form.vars.value.sp_pp_bo_qty_text %}{{ form.vars.value.sp_pp_bo_qty_text }}{% else %}{{\'Unit(s)\'|trans({}, \'Admin.Catalog.Feature\')}}{% endif %}',
                                ),
                            )
            ),
            array(
                'files'  => array('app/config/config.yml'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.5.0', '>='),
                'append' => array(
                                array(
                                    '- { resource: services.yml }',
                                    array('- { resource: ../../modules/pproperties/src/psm.yml }'),
                                    'indent' => '    ',
                                ),
                                array(
                                    "'%admin_page%/Configure/AdvancedParameters': AdvancedParameters",
                                    array("'%kernel.root_dir%/../modules': Modules"),
                                    'indent' => '        ',
                                ),
                            )
            ),
        );
        if (version_compare(_PS_VERSION_, '1.7.6.0', '>=')) {
            $root[] = array(
                'files'  => array('themes/core.js'),
                'backup' => array('ext' => '.PS' . _PS_VERSION_),
                'replace'=> array(
                    array(
                        '(0,r.default)("#quantity_wanted").val(1)',
                        '(0,r.default)("#quantity_wanted").val((0,r.default)("#quantity_wanted").attr("value")||1)',
                        'count' => -1,
                    ),
                ),
            );
        }

        if ($is_ps_version_1_7_7_or_later) {
            $root[] = [
                'files'  => ['src/Adapter/Cart/CommandHandler/AddProductToCartHandler.php'],
                'replace'=> [
                                [ // handle
                                    '(int) $product[\'quantity\']',
                                    '\PP::resolveQty($product)',
                                ],
                                [ // assertQuantityIsPositiveInt
                                    'int $quantity',
                                    'float $quantity',
                                ],
                                [ // handle
                                    '$customizationId' . "\n",
                                    [
                                        '$customizationId,',
                                        "            \$command->psm",
                                        '',
                                    ],
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Cart/CommandHandler/RemoveProductFromCartHandler.php'],
                'replace'=> [
                                [ // handle
                                    '$command->getCustomizationId() ?: 0' . "\n",
                                    [
                                        '$command->getCustomizationId() ?: 0,',
                                        '                0,',
                                        '                true,',
                                        '                $command->psm[\'cartProductId\'] ?? 0',
                                        '',
                                    ],
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Cart/CommandHandler/UpdateProductQuantityInCartHandler.php'],
                'replace' => [
                                [ // updateProductQuantityInCart
                                    'abs($command->getNewQuantity() - $previousQty);',
                                    '\PP::normalizeDbDecimal(abs($command->getNewQuantity() - $previousQty));',
                                ],
                                [ // updateProductQuantityInCart
                                    '($qtyDiff === 0)',
                                    '(($command->psm[\'cartProductId\'] ?? 0) == 0 && $qtyDiff === 0.0)',
                                ],
                                [ // updateProductQuantityInCart
                                    '$qtyDiff,' . "\n",
                                    '$action !== \'update\' ? $qtyDiff : $command->psm[\'quantity\'],' . "\n",
                                    'count' => 1
                                ],
                                [ // updateProductQuantityInCart
                                    '$action' . "\n" . '        );',
                                    [
                                        '$action,',
                                        '    0,',
                                        '    null,',
                                        '    true,',
                                        '    false,',
                                        '    true,',
                                        '    $command->psm[\'cartProductId\'] ?? 0,',
                                        '    $ext_prop_quantities ?? null,',
                                        '    $ext_calculated_quantity ?? 0,',
                                        '    $force_update_qty ?? null',
                                        ');'
                                    ],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                                [ // findPreviousQuantityInCart
                                    'findPreviousQuantityInCart(Cart $cart, UpdateProductQuantityInCartCommand $command): int',
                                    'findPreviousQuantityInCart(Cart $cart, UpdateProductQuantityInCartCommand $command): float',
                                ],
                                // [ // findPreviousQuantityInCart
                                //     '$equalProductId = (int) $cartProduct[\'id_product\'] === $command->getProductId()->getValue();',
                                //     '$equalProductId = (int) $cartProduct[\'id_product\'] === $command->getProductId()->getValue() && (!$hasCartProductId || (int) $cartProduct[\'id_cart_product\'] === $command->cartProductId);',
                                // ],
                                [ // findPreviousQuantityInCart
                                    '                    return (int) $cartProduct[\'quantity\'];',
                                    [
                                        '                    $quantity += \PP::resolveQty($cartProduct);',
                                        // 'if ($hasCartProductId) {',
                                        // '    break;',
                                        // '}',
                                    ],
                                    'indent' => '                    ',
                                ],
                                [ // findPreviousQuantityInCart
                                    '                return (int) $cartProduct[\'quantity\'];',
                                    [
                                        '                $quantity += \PP::resolveQty($cartProduct);',
                                        // 'if ($hasCartProductId) {',
                                        // '    break;',
                                        // '}',
                                    ],
                                    'indent' => '                ',
                                ],
                                [ // findPreviousQuantityInCart
                                    'return 0;',
                                    'return $quantity;',
                                    'count' => 1
                                ],
                            ],
                'prepend'=> [
                                [ // updateProductQuantityInCart
                                    'if ($previousQty < $command->getNewQuantity()) {',
                                    'if (($command->psm[\'cartProductId\'] ?? 0) > 0) {' ."\n" .
                                    '            $action = \'update\';' ."\n" .
                                    '        } else',
                                    'count' => 1
                                ],
                                [ // updateProductQuantityInCart
                                    '$updateResult = $cart->updateQty(',
                                    [
                                        'if (($multidimensional_plugin = \PP::getMultidimensionalPlugin()) && \PP::isMultidimensional($properties = $product->productProperties())) {',
                                        '    if ($action === \'update\') {',
                                        '        if (\PP::editBothQuantities($properties)) {',
                                        '            $product_quantity = $command->psm[\'request\']->request->getInt(\'whole_product_quantity\');',
                                        '            if ($product_quantity <= 0) {',
                                        '                throw new CartException(\'Invalid quantity\');',
                                        '            }',
                                        '            $product_quantity_fractional = round($command->psm[\'quantity\'] / $product_quantity, 6);',
                                        '            $force_update_qty = [$product_quantity, $product_quantity_fractional];',
                                        '        }',
                                        '    } else {',
                                        '        $use_multidimensional_quantity = \PP::isQuantityMultidimensional($properties) && !\PP::isPacksCalculator($properties);',
                                        '        if ($use_multidimensional_quantity) {',
                                        '            list($ext_calculated_quantity, $ext_calculated_total_quantity, $ext_prop_quantities, $errors) = $multidimensional_plugin->processQuantities($properties, $product->id, $combinationIdValue);',
                                        '            if ($errors) {',
                                        '                throw new CartException(json_encode($errors));',
                                        '            }',
                                        '        }',
                                        '    }',
                                        '}',
                                    ],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                                [ // findPreviousQuantityInCart
                                    'foreach ($cart->getProducts() as $cartProduct) {',
                                    [
                                        // '$hasCartProductId = $command->cartProductId > 0;',
                                        '$quantity = 0;'
                                    ],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Cart/Comparator/CartProductsComparator.php'],
                'replace' => [
                                [ // getAllAdditionalProducts
                                    "                \$additionalProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$newProduct['id_product'],\n" .
                                    "                    (int) \$newProduct['id_product_attribute'],\n" .
                                    "                    (int) \$newProduct['cart_quantity'],\n" .
                                    "                    true\n" .
                                    "                );",
                                    "                \$additionalProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$newProduct['id_product'],\n" .
                                    "                    (int) \$newProduct['id_product_attribute'],\n" .
                                    "                    \PP::resolveQty(\$newProduct),\n" .
                                    "                    true,\n" .
                                    "                    (int) \$newProduct['cart_quantity'],\n" .
                                    "                    (int) \$newProduct['cart_quantity_fractional'],\n" .
                                    "                    (int) \$newProduct['id_cart_product']\n" .
                                    "                );",
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '>=')
                                ],
                                [ // getAllAdditionalProducts
                                    "                \$additionalProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$newProduct['id_product'],\n" .
                                    "                    (int) \$newProduct['id_product_attribute'],\n" .
                                    "                    (int) \$newProduct['cart_quantity'],\n" .
                                    "                    true,\n" .
                                    "                    (int) \$newProduct['id_customization']\n" .
                                    "                );",
                                    "                \$additionalProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$newProduct['id_product'],\n" .
                                    "                    (int) \$newProduct['id_product_attribute'],\n" .
                                    "                    \PP::resolveQty(\$newProduct),\n" .
                                    "                    true,\n" .
                                    "                    (int) \$newProduct['id_customization'],\n" .
                                    "                    (int) \$newProduct['cart_quantity'],\n" .
                                    "                    (int) \$newProduct['cart_quantity_fractional'],\n" .
                                    "                    (int) \$newProduct['id_cart_product']\n" .
                                    "                );",
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '<')
                                ],
                                [ // getAllUpdatedProducts
                                    '$deltaQuantity = -(int) $oldProduct[\'cart_quantity\'];',
                                    '$deltaQuantity = -\PP::normalizeDbDecimal(\PP::resolveQty($oldProduct));',
                                ],
                                [ // getAllUpdatedProducts
                                    '$deltaQuantity = (int) $newProduct[\'cart_quantity\'] - (int) $oldProduct[\'cart_quantity\'];',
                                    '$deltaQuantity = \PP::normalizeDbDecimal(\PP::resolveQty($newProduct) - \PP::resolveQty($oldProduct));',
                                ],
                                [ // getAllUpdatedProducts
                                    'if ($deltaQuantity) {',
                                    'if ($deltaQuantity || (int) $newProduct[\'cart_quantity\'] != (int) $oldProduct[\'cart_quantity\'] || (float) $newProduct[\'cart_quantity_fractional\'] != (float) $oldProduct[\'cart_quantity_fractional\']) {',
                                ],
                                [ // getAllUpdatedProducts
                                    "                \$updatedProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$oldProduct['id_product'],\n" .
                                    "                    (int) \$oldProduct['id_product_attribute'],\n" .
                                    "                    \$deltaQuantity,\n" .
                                    "                    false\n" .
                                    "                );",
                                    "                \$additionalProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$oldProduct['id_product'],\n" .
                                    "                    (int) \$oldProduct['id_product_attribute'],\n" .
                                    "                    \$deltaQuantity,\n" .
                                    "                    false,\n" .
                                    "                    (int) \$oldProduct['cart_quantity'],\n" .
                                    "                    (int) \$oldProduct['cart_quantity_fractional'],\n" .
                                    "                    (int) \$oldProduct['id_cart_product']\n" .
                                    "                );",
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '>=')
                                ],
                                [ // getAllUpdatedProducts
                                    "                \$updatedProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$oldProduct['id_product'],\n" .
                                    "                    (int) \$oldProduct['id_product_attribute'],\n" .
                                    "                    \$deltaQuantity,\n" .
                                    "                    false,\n" .
                                    "                    (int) \$oldProduct['id_customization']\n" .
                                    "                );",
                                    "                \$additionalProducts[] = new CartProductUpdate(\n" .
                                    "                    (int) \$oldProduct['id_product'],\n" .
                                    "                    (int) \$oldProduct['id_product_attribute'],\n" .
                                    "                    \$deltaQuantity,\n" .
                                    "                    false,\n" .
                                    "                    (int) \$oldProduct['id_customization'],\n" .
                                    "                    (int) \$oldProduct['cart_quantity'],\n" .
                                    "                    (int) \$oldProduct['cart_quantity_fractional'],\n" .
                                    "                    (int) \$oldProduct['id_cart_product']\n" .
                                    "                );",
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '<')
                                ],
                                [ // filterKnownUpdates
                                    "\$updateProduct->getDeltaQuantity() - \$knownUpdate->getDeltaQuantity()\n",
                                    "\PP::normalizeDbDecimal(\$updateProduct->getDeltaQuantity() - \$knownUpdate->getDeltaQuantity())\n",
                                ],
                                [ // filterKnownUpdates
                                    '0 !== $updateProduct->getDeltaQuantity()',
                                    '$updateProduct->getDeltaQuantity() !== 0.0 || $updateProduct->quantityChanged === true',
                                ],
                            ],
                'append' => [
                                [ // getMatchingProduct
                                    'return $productMatch && $combinationMatch',
                                    ' && ((int) $item[\'id_cart_product\'] == (int) $searchedProduct[\'id_cart_product\'])',
                                    'count' => 1
                                ],
                            ],
                'prepend'=> [
                                [ // filterKnownUpdates
                                    '$updateProduct->setDeltaQuantity(',
                                    ['$updateProduct->quantityChanged = (int) $knownUpdate->quantity != (int) $updateProduct->quantity || (float) $knownUpdate->quantityFractional != (float) $updateProduct->quantityFractional;'],
                                    'indent' => '                    ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Cart/Comparator/CartProductUpdate.php'],
                'replace'=> [
                                [
                                    'int $deltaQuantity',
                                    'float $deltaQuantity',
                                ],
                                [
                                    'public function getDeltaQuantity(): int',
                                    'public function getDeltaQuantity(): float',
                                ],
                                [ // __construct
                                    version_compare(_PS_VERSION_, '1.7.7.5', '<') ? 'bool $created)' : 'bool $created, int $customizationId = 0)',
                                    version_compare(_PS_VERSION_, '1.7.7.5', '<') ? 'bool $created, int $quantity = 0, float $quantityFractional = 0, int $cartProductId = 0)' : 'bool $created, int $customizationId = 0, int $quantity = 0, float $quantityFractional = 0, int $cartProductId = 0)',
                                ],
                                [ // productMatches
                                    'if ($this->getProductId()->getValue() !== $cartProductUpdate->getProductId()->getValue()) {',
                                    'if ($this->getProductId()->getValue() !== $cartProductUpdate->getProductId()->getValue() || $this->cartProductId !== $cartProductUpdate->cartProductId) {',
                                ],
                            ],
                'append' => [
                                [
                                    'private $created;',
                                    [
                                        'public $quantity;',
                                        'public $quantityFractional;',
                                        'public $cartProductId;',
                                        'public $quantityChanged;',
                                    ],
                                    'indent' => '    '
                                ],
                                [ // __construct
                                    '$this->created = $created;',
                                    [
                                        '$this->quantity = $quantity;',
                                        '$this->quantityFractional = $quantityFractional;',
                                        '$this->cartProductId = $cartProductId;',
                                    ],
                                    'indent' => '        '
                                ],
                                [ // toArray
                                    '\'created\' => $this->created,',
                                    [
                                        '\'quantity\' => $this->quantity,',
                                        '\'quantity_fractional\' => $this->quantityFractional,',
                                        '\'id_cart_product\' => $this->cartProductId,',
                                    ],
                                    'indent' => '            '
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/CommandHandler/AbstractOrderCommandHandler.php'],
                'replace'=> [
                                [ // reinjectQuantity
                                    '$movement[\'physical_quantity\']',
                                    '\PP::resolveQty($movement[ \'physical_quantity\' ], $movement[\'product_quantity_fractional\'])',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/CommandHandler/AddProductToOrderHandler.php'],
                'append' => [
                                [ // getCreatedCartProducts
                                    version_compare(_PS_VERSION_, '1.7.7.1', '<') ?
                                        '\'id_product_attribute\' => null !== $additionalUpdate->getCombinationId() ? $additionalUpdate->getCombinationId()->getValue() : 0,'
                                        :
                                        '\'id_product_attribute\' => $updateCombinationId,',
                                    ['\'id_cart_product\' => $additionalUpdate->cartProductId,'],
                                    'indent' => '                ',
                                    'count' => 1
                                ],
                                [ // getMatchingProduct
                                    'return $productMatch && $combinationMatch',
                                    ' && ((int) $item[\'id_cart_product\'] == (int) $searchedProduct[\'id_cart_product\'])',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/CommandHandler/ChangeOrderDeliveryAddressHandler.php'],
                'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '<'),
                'replace'=> [
                                [ // synchronizeOrderWithCart
                                    ': $orderDetail->product_quantity + $productUpdate->getDeltaQuantity();',
                                    ': \PP::normalizeDbDecimal(\PP::resolveQty($orderDetail->product_quantity, $orderDetail->product_quantity_fractional) + $productUpdate->getDeltaQuantity());',
                                    'count' => 1
                                ],
                            ],
                'append' => [
                                [ // getOrderDetail
                                    '] === $combinationId',
                                    ' && (int) $product[\'id_cart_product\'] === $productUpdate->cartProductId',
                                    'count' => 2
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/CommandHandler/UpdateProductInOrderHandler.php'],
                'replace'=> [
                                [ // assertProductCanBeUpdated
                                    '!Validate::isUnsignedInt($command->getQuantity()',
                                    '!(\PP::orderEditQtyBehaviorFloat($orderDetail, $orderDetail->product_quantity) ? Validate::isUnsignedFloat($command->getQuantity()) : Validate::isUnsignedInt($command->getQuantity())',
                                    'count' => 1
                                ],
                                [
                                    '(int) $orderDetail->product_quantity',
                                    '\PP::resolveQty($orderDetail->product_quantity, $orderDetail->product_quantity_fractional)',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/QueryHandler/GetOrderPreviewHandler.php'],
                'replace'=> [
                                [
                                    '(int) $detail[\'product_quantity\'],',
                                    '\PP::convertQty([\'order\' => $detail, \'m\' => \'edit\']),',
                                ],
                            ],
                'append' => [
                                [ // getProductDetails
                                    '$locale->formatPrice((string) $totalTaxAmount, $currency->iso_code)',
                                    ",\n                \$detail",
                                    'count' => 1
                                ],
                            ],
                'prepend'=> [
                                [ // getProductDetails
                                    '$productDetails[] = new OrderPreviewProductDetail(',
                                    [
                                        '$detail[\'price_amount\'] = (float) $unitPrice;',
                                        '$detail[\'total_amount\'] = (float) $totalPrice;',
                                        '\PP::orderProductPresent($detail, PS_TAX_INC === $taxCalculationMethod);',
                                    ],
                                    'indent' => '            ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/OrderAmountUpdater.php'],
                'replace'=> [
                                [ // updateOrderDetails
                                    '$cartProduct = $this->getProductFromCart($cartProducts, (int) $orderDetail->product_id, (int) $orderDetail->product_attribute_id);',
                                    '$cartProduct = $this->getProductFromCart($cartProducts, (int) $orderDetail->product_id, (int) $orderDetail->product_attribute_id, (int) $orderDetail->id_cart_product);',
                                ],
                                [ // updateOrderDetails
                                    '$unitPriceTaxExcl * $orderDetail->product_quantity',
                                    '\PP::calcPrice($orderDetail->unit_price_tax_excl, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, false, $roundType)',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.0', '>'),
                                    'count' => 2
                                ],
                                [ // updateOrderDetails
                                    '$unitPriceTaxIncl * $orderDetail->product_quantity',
                                    '\PP::calcPrice($orderDetail->unit_price_tax_incl, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, true, $roundType)',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.0', '>'),
                                    'count' => 2
                                ],
                                [ // updateOrderDetails
                                    '$orderDetail->unit_price_tax_excl * $orderDetail->product_quantity',
                                    '\PP::calcPrice($unitPriceTaxExcl, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, false, $roundType, $computingPrecision)',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.0', '>'),
                                    'count' => 1
                                ],
                                [ // updateOrderDetails
                                    '$orderDetail->total_price_tax_incl * $orderDetail->product_quantity',
                                    '\PP::calcPrice($unitPriceTaxIncl, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, true, $roundType, $computingPrecision)',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.0', '>'),
                                    'count' => 1
                                ],
                                [ // getProductFromCart
                                    'private function getProductFromCart(array $cartProducts, int $productId, int $productAttributeId): array',
                                    'private function getProductFromCart(array $cartProducts, int $productId, int $productAttributeId, int $cartProductId): array',
                                ],
                                [ // getProductFromCart
                                    'use ($productId, $productAttributeId)',
                                    'use ($productId, $productAttributeId, $cartProductId)',
                                ],
                            ],
                'append' => [
                                [ // getProductFromCart
                                    'return $productMatch && $combinationMatch',
                                    ' && ((int) $item[\'id_cart_product\'] == $cartProductId)',
                                    'count' => 1
                                ],
                            ],
            ];
            if (version_compare(_PS_VERSION_, '1.7.7.1', '>=')) {
                $root[] = [
                    'files'  => ['src/Adapter/Order/OrderDetailUpdater.php'],
                    'replace'=> [
                                    [ // applyOrderDetailPriceUpdate
                                        '$floatPriceTaxExcluded * $orderDetail->product_quantity',
                                        '\PP::calcPrice($orderDetail->unit_price_tax_excl, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, false, $roundType)',
                                        'count' => 2
                                    ],
                                    [ // applyOrderDetailPriceUpdate
                                        '$floatPriceTaxIncluded * $orderDetail->product_quantity',
                                        '\PP::calcPrice($orderDetail->unit_price_tax_incl, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, true, $roundType)',
                                        'count' => 2
                                    ],
                                    [ // applyOrderDetailPriceUpdate
                                        '$orderDetail->unit_price_tax_excl * $orderDetail->product_quantity',
                                        '\PP::calcPrice($floatPriceTaxExcluded, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, false, $roundType, $computingPrecision)',
                                        'count' => 1
                                    ],
                                    [ // applyOrderDetailPriceUpdate
                                        '$orderDetail->unit_price_tax_incl * $orderDetail->product_quantity',
                                        '\PP::calcPrice($floatPriceTaxIncluded, $orderDetail->product_quantity, $orderDetail->product_quantity_fractional, $orderDetail->product_id, true, $roundType, $computingPrecision)',
                                        'count' => 1
                                    ],
                                ],
                ];
            }
            $root[] = [
                'files'  => ['src/Adapter/Order/OrderProductQuantityUpdater.php'],
                'replace'=> [
                                [
                                    'int $oldQuantity',
                                    'float $oldQuantity',
                                ],
                                [
                                    'int $newQuantity',
                                    'float $newQuantity',
                                ],
                                [ // updateOrderDetail
                                    '0 === $newQuantity',
                                    '$newQuantity === 0.0',
                                ],
                                [ // updateOrderDetail
                                    '$orderDetail->product_quantity = $newQuantity;',
                                    [
                                        '$properties = \PP::getProductProperties($orderDetail);',
                                        'if (\PP::editBothQuantities($properties)) {',
                                        '    $product_quantity = \PP::getIntNonNegativeValue(\'whole_product_quantity\');',
                                        '    if ($product_quantity <= 0) {',
                                        '        throw new OrderException(\'Invalid quantity\');',
                                        '    }',
                                        '    $orderDetail->editBothQuantities = true;',
                                        '    $product_quantity_fractional = \PP::normalizeDbDecimal($newQuantity / $product_quantity);',
                                        '    $orderDetail->quantityChanged = (int) $orderDetail->product_quantity != $product_quantity || (float) $orderDetail->product_quantity_fractional != $product_quantity_fractional;',
                                        '    $orderDetail->product_quantity = $product_quantity;',
                                        '    $orderDetail->product_quantity_fractional = $product_quantity_fractional;',
                                        '} elseif (\PP::qtyBehavior($orderDetail, $orderDetail->product_quantity, $properties)) {',
                                        '    $orderDetail->product_quantity_fractional = $newQuantity;',
                                        '} else {',
                                        '    $orderDetail->product_quantity  =  $newQuantity;',
                                        '}',
                                    ],
                                    'indent' => '            ',
                                ],
                                [ // updateOrderDetail
                                    '$customization->quantity = $newQuantity;',
                                    [
                                        '$customization->quantity = $orderDetail->product_quantity;',
                                        '$customization->quantity_fractional = $orderDetail->product_quantity_fractional;',
                                    ],
                                    'indent' => '                ',
                                    'count' => 1
                                ],
                                [ // applyOtherProductUpdates
                                    '$newUpdatedQuantity = (int) $updatedOrderDetail->product_quantity + $updatedProduct->getDeltaQuantity();',
                                    '$newUpdatedQuantity = \PP::normalizeDbDecimal(\PP::resolveQty($updatedOrderDetail->product_quantity, $updatedOrderDetail->product_quantity_fractional) + $updatedProduct->getDeltaQuantity());',
                                ],
                                [ // updateProductQuantity
                                    '$newQuantity - $oldQuantity;',
                                    '\PP::normalizeDbDecimal($newQuantity - $oldQuantity);',
                                ],
                                [ // updateProductQuantity
                                    '0 === $deltaQuantity',
                                    '$deltaQuantity === 0.0 && empty($orderDetail->quantityChanged)',
                                ],
                                [ // updateProductQuantity
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                $deltaQuantity,' . "\n" .
                                    '                false' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                $deltaQuantity,' . "\n" .
                                    '                false,' . "\n" .
                                    '                (int) $orderDetail->product_quantity,' . "\n" .
                                    '                (float) $orderDetail->product_quantity_fractional,' . "\n" .
                                    '                (int) $orderDetail->id_cart_product' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '>=')
                                ],
                                [
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                $deltaQuantity,' . "\n" .
                                    '                false,' . "\n" .
                                    '                (int) $orderDetail->id_customization' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                $deltaQuantity,' . "\n" .
                                    '                false,' . "\n" .
                                    '                (int) $orderDetail->id_customization,' . "\n" .
                                    '                (int) $orderDetail->product_quantity,' . "\n" .
                                    '                (float) $orderDetail->product_quantity_fractional,' . "\n" .
                                    '                (int) $orderDetail->id_cart_product' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '<')
                                ],
                                [ // updateProductQuantity
                                    "\$updateQuantityResult = \$cart->updateQty(\n            abs(\$deltaQuantity),",
                                    "\$updateQuantityResult = \$cart->updateQty(\n            empty(\$orderDetail->editBothQuantities) ? \$newQuantity : \$orderDetail->product_quantity,",
                                ],
                                [ // updateProductQuantity
                                    '$deltaQuantity < 0 ? \'down\' : \'up\',',
                                    '\'update\',',
                                ],
                                [ // updateStocks
                                    '$oldQuantity - $newQuantity;',
                                    '\PP::normalizeDbDecimal($oldQuantity - $newQuantity);',
                                ],
                                [ // reinjectQuantity
                                    '$movement[\'physical_quantity\']',
                                    '\PP::resolveQty($movement[ \'physical_quantity\' ], $movement[\'product_quantity_fractional\'])',
                                ],
                                [ // assertValidProductQuantity
                                    '$quantityDiff = $newQuantity - (int) $orderDetail->product_quantity;',
                                    '$quantityDiff = $newQuantity - \PP::resolveQty($orderDetail->product_quantity, $orderDetail->product_quantity_fractional);',
                                ],
                                [ // assertValidProductQuantity
                                    'if ($quantityDiff > $availableQuantity) {',
                                    'if (\PP::normalizeDbDecimal($quantityDiff) > \PP::normalizeDbDecimal($availableQuantity)) {',
                                ],
                            ],
                'append' => [
                                [ // applyOtherProductUpdates
                                    '&& (int) $orderDetailData[\'product_attribute_id\'] === $updatedCombinationId',
                                    ['&& (int) $orderDetailData[\'id_cart_product\'] === $updatedProduct->cartProductId'],
                                    'indent' => '                    ',
                                    'count' => 1
                                ],
                                [ // applyOtherProductCreation
                                    '&& (int) $product[\'id_product_attribute\'] === $updatedCombinationId',
                                    ['&& (int) $product[\'id_cart_product\'] === $createdProduct->cartProductId'],
                                    'indent' => '                    ',
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '<')
                                ],
                                [ // updateProductQuantity
                                    "new Shop(\$cart->id_shop),\n            true,\n            true",
                                    ",\n            true,\n            false,\n            \$orderDetail->id_cart_product,\n            null,\n            0,\n            [\$orderDetail->product_quantity, \$orderDetail->product_quantity_fractional]",
                                    'count' => 1
                                ],
                                [ // updateCustomizationOnProductDelete
                                    '`id_cart` = \' . (int) $order->id_cart',
                                    ' . \' AND `id_cart_product` = \' . (int) $orderDetail->id_cart_product',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/Refund/OrderProductRemover.php'],
                'replace'=> [
                                [ // updateCart
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                -$orderDetail->product_quantity,' . "\n" .
                                    '                false' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                -\PP::resolveQty($orderDetail->product_quantity, $orderDetail->product_quantity_fractional),' . "\n" .
                                    '                false,' . "\n" .
                                    '                (int) $orderDetail->product_quantity,' . "\n" .
                                    '                (float) $orderDetail->product_quantity_fractional,' . "\n" .
                                    '                (int) $orderDetail->id_cart_product' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '>=')
                                ],
                                [ // updateCart
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                -$orderDetail->product_quantity,' . "\n" .
                                    '                false,' . "\n" .
                                    '                (int) $orderDetail->id_customization' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    '        $knownUpdates = [' . "\n" .
                                    '            new CartProductUpdate(' . "\n" .
                                    '                (int) $orderDetail->product_id,' . "\n" .
                                    '                (int) $orderDetail->product_attribute_id,' . "\n" .
                                    '                -\PP::resolveQty($orderDetail->product_quantity, $orderDetail->product_quantity_fractional),' . "\n" .
                                    '                false,' . "\n" .
                                    '                (int) $orderDetail->id_customization,' . "\n" .
                                    '                (int) $orderDetail->product_quantity,' . "\n" .
                                    '                (float) $orderDetail->product_quantity_fractional,' . "\n" .
                                    '                (int) $orderDetail->id_cart_product' . "\n" .
                                    '            ),' . "\n" .
                                    '        ];',
                                    'count' => 1,
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.5', '<')
                                ],
                                [ // updateCart
                                    'false // Do not preserve gift removal',
                                    "false, // Do not preserve gift removal\n            \$orderDetail->id_cart_product",
                                    'count' => 1
                                ],
                                [ // deleteSpecificPrice
                                    '(int) $productQuantity[\'quantity\']',
                                    '(float) $productQuantity[\'quantity\']'
                                ],
                            ],
                'append' => [
                                [ // deleteCustomization
                                    '`id_customization` = \' . (int) $orderDetail->id_customization',
                                    ' . \' AND `id_cart_product` = \' . (int) $orderDetail->id_cart_product',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Cart/Command/AddProductToCartCommand.php'],
                'replace'=> [
                                [
                                    'int $quantity',
                                    'float $quantity',
                                ],
                                [
                                    'int $qty',
                                    'float $qty',
                                ],
                                [
                                    'array $customizationsByFieldIds = []' . "\n",
                                    [
                                        'array $customizationsByFieldIds = [],',
                                        '        $psm = null',
                                        '',
                                    ],
                                    'count' => 1
                                ],
                                [
                                    'function getQuantity(): int',
                                    'function getQuantity(): float',
                                ],
                            ],
                'append' => [
                                [
                                    'private $cartId;',
                                    ['public $psm;'],
                                    'indent' => '    ',
                                ],
                                [
                                    '$this->customizationsByFieldIds = $customizationsByFieldIds;',
                                    ['$this->psm = $psm;'],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Cart/Command/RemoveProductFromCartCommand.php'],
                'replace'=> [
                                [
                                    '$customizationId = null' . "\n",
                                    [
                                        '$customizationId = null,',
                                        '        $psm = null',
                                        '',
                                    ],
                                    'count' => 1
                                ],
                            ],
                'append' => [
                                [
                                    'private $cartId;',
                                    ['public $psm;'],
                                    'indent' => '    ',
                                ],
                                [
                                    '$this->customizationId = $customizationId;',
                                    ['$this->psm = $psm;'],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Cart/Command/UpdateProductQuantityInCartCommand.php'],
                'replace'=> [
                                [
                                    'int $qty',
                                    'float $qty',
                                ],
                                [
                                    '$customizationId = null' . "\n",
                                    [
                                        '$customizationId = null,',
                                        '        $psm = null',
                                        '',
                                    ],
                                    'count' => 1
                                ],
                            ],
                'append' => [
                                [
                                    'private $cartId;',
                                    ['public $psm;'],
                                    'indent' => '    ',
                                ],
                                [
                                    '$this->newQuantity = $quantity;',
                                    ['$this->psm = $psm;'],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Product/QueryResult/FoundProduct.php'],
                'replace'=> [
                                [
                                    'int $stock',
                                    'float $stock',
                                ],
                                [
                                    'public function getStock(): int',
                                    'public function getStock(): float',
                                ],
                            ],
                'append' => [
                                [
                                    'namespace PrestaShop\PrestaShop\Core\Domain\Product\QueryResult;',
                                    [
                                        '',
                                        'use Hook;',
                                        'use Product;',
                                    ],
                                ],
                                [
                                    'private $productId;',
                                    ['public $ppsettings;'],
                                    'indent' => '    ',
                                ],
                                [
                                    '$this->customizationFields = $customizationFields;',
                                    [
                                        'if (\PP::getProductTemplateId($productId)) {',
                                            '    $product = (array) new Product($productId);',
                                            '    $product[\'id_product\'] = $productId;',
                                            '    $product[\'id_product_attribute\'] = (int) Product::getDefaultAttribute($productId);',
                                            '    \PP::productProductPresent($product);',
                                            '    $multidimensional = \PP::isMultidimensional($product);',
                                            '    $this->ppsettings = [',
                                            '        \'id_pp_template\' => $product[\'id_pp_template\'],',
                                            '        \'qty_policy\' => $product[\'qty_policy\'],',
                                            '        \'qty_text\' => $product[\'pp_bo_qty_text\'],',
                                            '        \'default_quantity\' => $product[\'default_quantity\'],',
                                            '        \'multidimensional\' => $multidimensional,',
                                            '        \'qty_text_for_edit\' => $multidimensional ? \'\' : $product[\'pp_bo_qty_text\'],',
                                            '        \'disable_add_to_cart\' => !\PP::orderCreateProductAllowAddToCart($product),',
                                            '        \'html\' => Hook::exec(\'displayAdminProductPproperties\', [\'type\' => \'orderCreateProductFoundProduct\', \'product\' => $product]),',
                                            '    ];',
                                                '} else {',
                                            '    $this->ppsettings = [\'id_pp_template\' => 0];',
                                            '}',
                                    ],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Product/QueryResult/ProductCombination.php'],
                'replace'=> [
                                [
                                    'int $stock',
                                    'float $stock',
                                ],
                                [
                                    'public function getStock(): int',
                                    'public function getStock(): float',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Order/Product/Command/AddProductToOrderCommand.php'],
                'replace'=> [
                                [
                                    'int $productQuantity',
                                    'float $productQuantity',
                                ],
                                [
                                    'public function getProductQuantity(): int',
                                    'public function getProductQuantity(): float',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Order/Product/Command/UpdateProductInOrderCommand.php'],
                'replace'=> [
                                [
                                    'int $quantity',
                                    'float $quantity',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Order/QueryResult/OrderPreviewProductDetail.php'],
                'replace'=> [
                                [
                                    'int $quantity',
                                    'float $quantity',
                                ],
                                [
                                    'function getUnitPrice(): string',
                                    'function getUnitPrice()',
                                ],
                                [
                                    'return $this->unitPrice;',
                                    'return $this->product[\'pp_price_formatted\'] ?? $this->unitPrice;',
                                ],
                                [
                                    'function getTotalPrice(): string',
                                    'function getTotalPrice()',
                                ],
                                [
                                    'return $this->totalPrice;',
                                    'return $this->product[\'pp_total_formatted\'] ?? $this->totalPrice;',
                                ],
                                [
                                    'function getQuantity(): int',
                                    'function getQuantity(): float',
                                ],
                            ],
                'append' => [
                                [
                                    'private $location;',
                                    ['public $product;'],
                                    'indent' => '    ',
                                ],
                                [
                                    '        string $totalTax',
                                    ",\n        ?array \$product = null",
                                ],
                                [
                                    '$this->location = $location;',
                                    ['$this->product = $product;'],
                                    'indent' => '        ',
                                ],
                            ],
                'prepend'=> [
                                [
                                    'public function getTotalTax(): string',
                                    [
                                        'public function getQuantityFormatted()',
                                        '{',
                                        '    return $this->product[\'pp_quantity_formatted\'] ?? $this->quantity;',
                                        '}',
                                        '',
                                    ],
                                    'indent' => '    '
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Cart/QueryHandler/GetCartForOrderCreationHandler.php'],
                'replace'=> [
                                [ // generateUniqueProductKey
                                    '\'%s_%s_%s\',',
                                    '\'%s_%s_%s_%s\',' . "\n            (int) \$product['id_cart_product'],",
                                ],
                                [ // buildCartProduct
                                    '$product[\'quantity\'],',
                                    '\PP::convertQty([\'cart\' => $product, \'m\' => \'edit\']),',
                                ],
                            ],
                'append' => [
                                [
                                    '$product[\'name\'],',
                                    ['$product,'],
                                    'indent' => '            ',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Cart/QueryHandler/GetCartForViewingHandler.php'],
                'replace'=> [
                                [ // prepareProductForView
                                    '=> $product[\'qty_in_stock\'],',
                                    [
                                        '=> \PP::twigDisplayQty($product, $product[\'qty_in_stock\'], \'inline\'),',
                                        '\'cart_quantity_formatted\' => $product[\'pp_quantity_formatted\'] ?? $product[\'cart_quantity\'],',
                                        '\'product\' => $product,',
                                    ],
                                    'indent' => '                ',
                                    'count' => 1
                                ],
                                [ // prepareProductForView
                                    '$formattedProduct[\'quantity\'] = $product[\'customizationQuantityTotal\'];',
                                    [
                                        '$formattedProduct[\'total_price_formatted\'] = $product[\'pp_total_formatted\'] ?? $formattedProduct[\'total_price_formatted\'];',
                                        '$formattedProduct[\'quantity\'] = $product[\'pp_quantity_formatted\'] ?? $product[\'customizationQuantityTotal\'];'
                                    ],
                                    'indent' => '                ',
                                    'count' => 1
                                ],
                            ],
                'prepend'=> [
                                [ // handle
                                    'Validate::isLoadedObject($order)) {',
                                    '$is_order = ',
                                ],
                                [ // handle
                                    '$product[\'product_price\'] = ',
                                    '$product[\'price_amount\'] = ',
                                    'count' => 2
                                ],
                                [ // handle
                                    '$product[\'product_total\'] = ',
                                    '$product[\'total_amount\'] = ',
                                    'count' => 2
                                ],
                                [ // handle
                                    '$customized_datas = Product::getAllCustomizedDatas(',
                                    ['$is_order ? \PP::orderProductPresent($product, $tax_calculation_method != PS_TAX_EXC) : \PP::cartProductPresent($product, $tax_calculation_method != PS_TAX_EXC);'],
                                    'indent' => '            ',
                                ],
                                [ // prepareProductForView
                                    '$this->locale->formatPrice($product[\'product_total\'], $currency->iso_code),',
                                    '$product[\'pp_total_formatted\'] ?? ',
                                    'count' => 1
                                ],
                                [ // prepareProductForView
                                    '$this->locale->formatPrice($product[\'product_price\'], $currency->iso_code),',
                                    '$product[\'pp_price_formatted\'] ?? ',
                                    'count' => 1
                                ],
                                [ // prepareProductForView
                                    '$this->locale->formatPrice($product[\'price_wt\'], $currency->iso_code);',
                                    '$product[\'pp_price_formatted\'] ?? ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Adapter/Order/QueryHandler/GetOrderProductsForViewingHandler.php'],
                'replace'=> [
                                [ // handle
                                    "\$product['product_quantity'],\n",
                                    "\PP::convertQty(['order' => \$product, 'm' => 'edit']),\n",
                                    'count' => 1
                                ],
                                [ // handle
                                    "\$product['customizations']\n",
                                    "\$product['customizations'],\n                \$product\n",
                                    'count' => 1
                                ],
                            ],
                'append' => [
                                [ // handle
                                    "\$product['quantity_refundable'] = \$product['product_quantity'] - \$product['product_quantity_return'] - \$product['product_quantity_refunded'];",
                                    [
                                        'if (\PP::qtyBehavior($product, $product[\'product_quantity\'])) {',
                                        '    $product[\'quantity_refundable\'] += \PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']) - $product[\'product_quantity\'];',
                                        '}',
                                    ],
                                    'indent' => '            ',
                                ],
                            ],
                'prepend'=> [
                                [ // handle
                                    '$unitPriceFormatted = ',
                                    [
                                        '$product[\'price_amount\'] = $unitPrice;',
                                        '$product[\'total_amount\'] = $unitPrice * \PP::resolveQty($product[\'product_quantity\'], $product[\'product_quantity_fractional\']) + (float) ($isOrderTaxExcluded ? $product[\'smartprice_tax_excl\'] ?? 0 : $product[\'smartprice_tax_incl\'] ?? 0);',
                                        '\PP::orderProductPresent($product, !$isOrderTaxExcluded);',
                                    ],
                                    'indent' => '            ',
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Cart/QueryResult/CartForOrderCreation/CartProduct.php'],
                'replace'=> [
                                [
                                    'int $quantity',
                                    'float $quantity',
                                ],
                                [
                                    'public function getQuantity(): int',
                                    'public function getQuantity(): float',
                                ],
                            ],
                'append' => [
                                [
                                    'private $quantity;',
                                    [
                                        'public $quantityFractional;',
                                        'public $cartProductId;',
                                        'public $ppsettings;',
                                    ],
                                    'indent' => '    ',
                                ],
                                [
                                    'string $name,',
                                    ['array $product,'],
                                    'indent' => '        ',
                                    'count' => 1
                                ],
                                [
                                    '$this->quantity = $quantity;',
                                    [
                                        '$this->quantityFractional = (float) $product[\'cart_quantity_fractional\'];',
                                        '$this->cartProductId = (int) $product[\'id_cart_product\'];',
                                        'if ($product[\'id_pp_template\']) {',
                                        '    \PP::cartProductPresent($product);',
                                        '    if (!empty($n = \Hook::exec(\'displayProductPproperties\', [\'product\' => $product, \'type\' => \'product-name-addional-info\']))) {',
                                        '        $this->name .= $n;',
                                        '    }',
                                        '    $this->ppsettings = [',
                                        '        \'id_pp_template\' => $product[\'id_pp_template\'],',
                                        '        \'id_cart_product\' => $product[\'id_cart_product\'],',
                                        '        \'qty_policy\' => $product[\'qty_policy\'],',
                                        '        \'pp_bo_qty_text\' => $product[\'pp_bo_qty_text\'],',
                                        '        \'cart_quantity_details_to_display\' => $product[\'pp_settings\'][\'cart_quantity_details_to_display\'] ?? \'\',',
                                        '        \'pp_qty_text_for_edit\' => (string) \PP::twigDisplayQty($product, $product[\'cart_quantity\'], \'edit\'),',
                                        '        \'product_quantity_factor\' => \PP::isMultidimensional($product) ? (float) $product[\'cart_quantity_fractional\'] : null,',
                                        '        \'whole_product_quantity\' => \PP::editBothQuantities($product) ? (int) $product[\'cart_quantity\'] : null,',
                                        '    ];',
                                        '} else {',
                                        '    $this->ppsettings = [\'id_pp_template\' => 0, \'id_cart_product\' => $product[\'id_cart_product\']];',
                                        '}',
                                        ],
                                    'indent' => '        ',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/Core/Domain/Order/QueryResult/OrderProductForViewing.php'],
                'replace'=> [
                                [
                                    'int $quantity',
                                    'float $quantity',
                                ],
                                [
                                    'int $availableQuantity',
                                    'float $availableQuantity',
                                ],
                                [
                                    'function getQuantity(): int',
                                    'function getQuantity(): float',
                                ],
                                [
                                    'function getUnitPrice(): string',
                                    'function getUnitPrice()',
                                ],
                                [
                                    'return $this->unitPrice;',
                                    'return $this->product[\'pp_price_formatted\'] ?? $this->unitPrice;',
                                ],
                                [
                                    'function getTotalPrice(): string',
                                    'function getTotalPrice()',
                                ],
                                [
                                    'return $this->totalPrice;',
                                    'return $this->product[\'pp_total_formatted\'] ?? $this->totalPrice;',
                                ],
                                [
                                    'public function getAvailableQuantity(): int',
                                    'public function getAvailableQuantity(): float'
                                ],
                            ],
                'append' => [
                                [
                                    'private $customizations;',
                                    ['public $product;'],
                                    'indent' => '    ',
                                ],
                                [
                                    '?OrderProductCustomizationsForViewing $customizations = null',
                                    ",\n        ?array \$product = null",
                                ],
                                [
                                    '$this->customizations = $customizations;',
                                    ['$this->product = $product;'],
                                    'indent' => '        ',
                                ],
                            ],
                'prepend'=> [
                                [
                                    'public function jsonSerialize(): array',
                                    [
                                        'public function getQuantityFormatted()',
                                        '{',
                                        '    return $this->product[\'pp_quantity_formatted_order_view\'] ?? $this->quantity;',
                                        '}',
                                        '',
                                        'public function getAvailableQuantityFormatted()',
                                        '{',
                                        '    return \PP::twigDisplayQty($this->product, $this->getAvailableQuantity(), \'inline\');',
                                        '}',
                                        '',
                                        'public function ppsettings(): array',
                                        '{',
                                        '    if ($this->product[\'id_pp_template\']) {',
                                        '        return [',
                                        '            \'id_pp_template\' => $this->product[\'id_pp_template\'],',
                                        '            \'id_cart_product\' => $this->product[\'id_cart_product\'],',
                                        '            \'qty_policy\' => $this->product[\'qty_policy\'],',
                                        '            \'pp_bo_qty_text\' => $this->product[\'pp_bo_qty_text\'],',
                                        '            \'cart_quantity_details_to_display\' => $this->product[\'pp_settings\'][\'cart_quantity_details_to_display\'] ?? \'\',',
                                        '            \'pp_qty_text_for_edit\' => (string) \PP::twigDisplayQty($this->product, $this->product[\'product_quantity\'], \'edit\'),',
                                        '            \'product_quantity_factor\' => \PP::isMultidimensional($this->product) ? (float) $this->product[\'product_quantity_fractional\'] : null,',
                                        '            \'whole_product_quantity\' => \PP::editBothQuantities($this->product) ? (int) $this->product[\'product_quantity\'] : null,',
                                        '        ];',
                                        '    }',
                                        '    return [\'id_pp_template\' => 0, \'id_cart_product\' => $this->product[\'id_cart_product\']];',
                                        '}',
                                        '',
                                    ],
                                    'indent' => '    '
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/PrestaShopBundle/Controller/Admin/Sell/Order/CartController.php'],
                'replace'=> [
                                [ // addProductAction
                                    '$request->request->getInt(\'product_quantity\')',
                                    '\PP::toFloat($request->request->get(\'product_quantity\'))',
                                ],
                                [ // addProductAction
                                    '$customizations' . "\n",
                                    [
                                        '$customizations,',
                                        "                ['request' => \$request, 'quantity' => \$quantity]",
                                        '',
                                    ],
                                    'count' => 1
                                ],
                                [ // editProductQuantityAction
                                    '$request->request->getInt(\'newQty\')',
                                    '\PP::toFloat($request->request->get(\'newQty\'))',
                                ],
                                [ // editProductQuantityAction
                                    '$request->request->getInt(\'customizationId\') ?: null' . "\n",
                                    [
                                        '$request->request->getInt(\'customizationId\') ?: null,',
                                        "                ['request' => \$request, 'cartProductId' => \$request->request->getInt('cartProductId'), 'quantity' => \$newQty]",
                                        '',
                                    ],
                                    'count' => 1
                                ],
                                [ // editProductPriceAction
                                    '1' . "\n",
                                    '\PP::getSpecificPriceFromQty($productId)' . "\n",
                                    'count' => 1
                                ],
                                [ // deleteProductAction
                                    '$customizationId ?: null' . "\n",
                                    [
                                        '$customizationId ?: null,',
                                        "                ['cartProductId' => \$request->request->getInt('cartProductId')]",
                                        '',
                                    ],
                                    'count' => 1
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/PrestaShopBundle/Controller/Admin/Sell/Order/OrderController.php'],
                'replace'=> [
                                [ // getProductPricesAction
                                    '=> $product->getUnitPrice(),',
                                    '=> (string) $product->getUnitPrice(),',
                                ],
                                [ // getProductPricesAction
                                    '=> $product->getAvailableQuantity(),',
                                    '=> (string) $product->getAvailableQuantity(),',
                                ],
                                [ // getProductPricesAction
                                    '=> $product->getTotalPrice(),',
                                    '=> (string) $product->getTotalPrice(),',
                                ],
                                [ // updateProductAction
                                    '(int) $request->get(\'quantity\')',
                                    '\PP::toFloat($request->get(\'quantity\'))',
                                ],
                            ],
                'append' => [
                                [ // getProductPricesAction
                                    '\'quantity\' => $product->getQuantity(),',
                                    [
                                        '\'quantityFormatted\' => (string) $product->getQuantityFormatted(),',
                                        '\'availableQuantityFormatted\' => (string) $product->getavailableQuantityFormatted(),',
                                    ],
                                    'indent' => '                    ',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/PrestaShopBundle/Resources/views/Admin/Sell/Order/Cart/Blocks/View/cart_summary.html.twig'],
                'replace'=> [
                                [
                                    '{{ product.cart_quantity }}',
                                    '{{ product.cart_quantity_formatted }}',
                                ],
                            ],
                'prepend'=> [
                                [
                                    "</td>\n              <td>{{ product.unit_price_formatted }}</td>",
                                    ['  {{ renderhook("displayAdminProductPproperties", {"product": product.product, "type": "orders/_product_line"}) }}'],
                                    'indent' => '              ',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/PrestaShopBundle/Resources/views/Admin/Sell/Order/Order/preview.html.twig'],
                'replace'=> [
                                [
                                    '{{ productDetail.name }}</td>',
                                    '{{ productDetail.name }}{{ renderhook("displayAdminProductPproperties", {"product": productDetail.product, "type": "orders/_product_line"}) }}</td>',
                                ],
                                [
                                    '{{ productDetail.quantity }}',
                                    '{{ productDetail.quantityFormatted }}',
                                ],
                            ],
            ];
            $root[] = [
                'files'  => ['src/PrestaShopBundle/Resources/views/Admin/Sell/Order/Order/Blocks/View/product.html.twig'],
                'prepend'=> [
                                [
                                    "{% if product.type == constant('PrestaShop\\\\PrestaShop\\\\Core\\\\Domain\\\\Order\\\\QueryResult\\\\OrderProductForViewing::TYPE_PACK') and product.customizations is null %}",
                                    ['{{ renderhook("displayAdminProductPproperties", {"product": product.product, "type": "orders/_product_line"}) }}'],
                                    'indent' => '    ',
                                    'count' => 1
                                ],
                                [
                                    'data-available-out-of-stock="{{ product.availableOutOfStock }}"',
                                    ['data-order-pp-settings="{{ product.ppsettings|json_encode|pp_safeoutput(\'htmlspecialchars\') }}"'],
                                    'indent' => '        ',
                                ],
                            ],
                'replace'=> [
                                [
                                    "{% if product.quantity > 1 %}\n",
                                    "{% if product.quantity > 1 and not (product.product.pp_settings.id_pp_template ?? false) %}\n",
                                    'count' => 1
                                ],
                                [
                                    "{{ product.quantity }}\n",
                                    "{{ product.quantityFormatted }}\n",
                                    'count' => 1
                                ],
                                [
                                    '{{ product.availableQuantity }}</td>',
                                    '{{ product.availableQuantityFormatted }}</td>',
                                    'count' => 1
                                ],
                            ],
            ];
        }

        $files = [];
        foreach (Tools::scandir(_PS_ROOT_DIR_ . '/', 'tpl', 'mails', true) as $filename) {
            if (strpos($filename, '/order_conf_product_list.tpl') !== false) {
                $files[] = $filename;
            }
        }
        $root[] = [
            'files'  => $files,
            'replace'=> [
                            [
                                '{$product[\'name\']}',
                                '{$product[\'name\'] nofilter}'
                            ],
                            [
                                '{$product[\'unit_price\']}',
                                '{$product[\'unit_price\'] nofilter}'
                            ],
                            [
                                '{$product[\'quantity\']}',
                                '{$product[\'quantity\'] nofilter}'
                            ],
                            [
                                '{$product[\'price\']}',
                                '{$product[\'price\'] nofilter}'
                            ],
                        ],
        ];

        $root[] = array(
            'files'  => array('pdf/invoice.product-tab.tpl'),
            'replace'=> array(
                            array(
                                '{displayPrice currency=$order->id_currency price=$order_detail.unit_price_tax_excl_including_ecotax}',
                                '{displayPrice currency=$order->id_currency price=$order_detail.unit_price_tax_excl_including_ecotax usetax=false m="smart_product_price"}'
                            ),
                            array(
                                '{$order_detail.product_quantity}',
                                '{$order_detail.product_quantity|displayQty:"total"}'
                            ),
                            array(
                                '{displayPrice currency=$order->id_currency price=$order_detail.total_price_tax_excl_including_ecotax}',
                                '{displayPrice currency=$order->id_currency price=$order_detail.total_price_tax_excl_including_ecotax m="total"}'
                            ),
                        ),
            'append' => array(
                            array(
                                '{$order_detail.product_name}',
                                '{hook h="displayAdminProductPproperties" product=$order_detail type="pdf"}'
                            ),
                        )
        );
        $root[] = array(
            'files'  => array('pdf/delivery-slip.product-tab.tpl', 'pdf/order-slip.product-tab.tpl'),
            'replace'=> array(
                            array(
                                '{$order_detail.product_quantity}',
                                '{$order_detail.product_quantity|displayQty}'
                            ),
                        ),
            'append' => array(
                            array(
                                '{$order_detail.product_name}',
                                '{hook h="displayAdminProductPproperties" product=$order_detail type="pdf"}'
                            ),
                        )
        );

        $adminthemefiles = array(
            array(
                'files'  => array('/themes/default/template/controllers/carts/helpers/view/view.tpl'),
                'replace'=> array(
                                array(
                                    '{$product.customizationQuantityTotal}',
                                    '{$product.customizationQuantityTotal|displayQty}',
                                ),
                                array(
                                    '{if isset($product.customizationQuantityTotal)}{math equation=\'x - y\' x=$product.cart_quantity y=$product.customizationQuantityTotal|intval}{else}{math equation=\'x - y\' x=$product.cart_quantity y=$product.customization_quantity|intval}{/if}',
                                    '{if isset($product.customizationQuantityTotal)}{displayQty quantity=($product|psm:qty:cart_quantity-$product.customizationQuantityTotal)}{else}{displayQty quantity=($product|psm:qty:cart_quantity-$product.customization_quantity)}{/if}',
                                ),
                                array(
                                    '{$product.qty_in_stock}',
                                    '{$product.qty_in_stock|displayQty:inline}',
                                ),
                                array(
                                    '{displayWtPriceWithCurrency price=$product.price_wt currency=$currency}',
                                    '{displayWtPriceWithCurrency price=$product.price_wt currency=$currency usetax=($tax_calculation_method != $smarty.const.PS_TAX_EXC) m="smart_product_price"}',
                                ),
                                array(
                                    '{displayWtPriceWithCurrency price=$product.product_price currency=$currency}',
                                    '{displayWtPriceWithCurrency price=$product.product_price currency=$currency usetax=($tax_calculation_method != $smarty.const.PS_TAX_EXC) m="smart_product_price"}',
                                ),
                                array(
                                    '{displayWtPriceWithCurrency price=$product.product_total currency=$currency}',
                                    '{displayWtPriceWithCurrency price=$product.product_total currency=$currency m="total"}',
                                ),
                            )
            ),
            array(
                'files'  => array('/themes/default/template/controllers/orders/_customized_data.tpl'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'replace'=> array(
                                array(
                                    '{$product[\'customizationQuantityTotal\']}',
                                    '{$product.customizationQuantityTotal|displayQty}',
                                ),
                                array(
                                    '{displayPrice price=$product_price currency=$currency->id|intval}',
                                    '{if isset($presented_product.price_to_display)}{$presented_product.price_to_display nofilter}{else}{displayPrice price=$product_price currency=$currency->id|intval }{/if}',
                                ),
                                array(
                                    '{$product[\'current_stock\']}',
                                    '{$product.current_stock|displayQty:inline}',
                                ),
                            ),
                'append' => array(
                                array(
                                    '$product[\'customizationQuantityTotal\'], 2)',
                                    ' m="total"',
                                ),
                            )
            ),
            array(
                'files'  => array('/themes/default/template/controllers/orders/_new_product.tpl'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'replace'=> array(
                                array(
                                    'type="number"',
                                    'type=\'text\'',
                                    'count' => -1,
                                    'uninstall' => false
                                ),
                            )
            ),
            array(
                'files'  => array('/themes/default/template/controllers/orders/_product_line.tpl'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'replace'=> array(
                                array(
                                    '{(int) $product[\'product_quantity\'] - (int) $product[\'customized_product_quantity\']}</span>',
                                    '{($product|psm:qty:product_quantity - (int) $product["customized_product_quantity"])|formatQty}</span>' .
                                    "\n" . '		<span class="product_quantity_show">{displayQty quantity=($product|psm:qty:product_quantity - (int) $product["customized_product_quantity"]) m="unit"}</span>',
                                ),
                                array(
                                    '<input type="text" name="product_quantity" class="edit_product_quantity" value="{$product[\'product_quantity\']|htmlentities}"/>',
                                    array(
                                        '<div class="input-group">',
                                        '    <input type="text" name="product_quantity" class="edit_product_quantity" value="{convertQty quantity=$product["product_quantity"] m="edit"}"/>',
                                        '    {displayQty quantity=$product["product_quantity"] m="edit" wrap="input-group-addon" bo=true}',
                                        '</div>',
                                        '{if PP::editBothQuantities($product)}',
                                        '	<br>',
                                        '	<div class="input-group">',
                                        '	    <input type="text" name="whole_product_quantity" value="{(int)$product[\'product_quantity\']}"/>',
                                        '	    {displayQty quantity=$product["product_quantity"] m="unit" wrap="input-group-addon" bo=true}',
                                        '	</div>',
                                        '{/if}',
                                        '{if $product.pp_qty_step > 0}<div class="pp_qty_step">{"quantity step"|psmtrans} {$product.qty_step|formatQty}</div>{/if}',
                                        '{if $product.specific_values}<div class="pp_specific_values">{"s_pp_qty_values"|psmtrans} {$product.specific_values}</div>{/if}',
                                    ),
                                    'indent' => '			',
                                ),
                                array(
                                    '{$product[\'current_stock\']}',
                                    '{$product.current_stock|displayQty:inline}',
                                ),
                                array(
                                    '{$product[\'quantity_refundable\']}</div>',
                                    '{$product.quantity_refundable|displayQty:overall}</div>',
                                ),
                                array(
                                    '=> $product[\'product_quantity_refunded\']',
                                    '=> {$product.product_quantity_refunded|displayQty:overall}',
                                ),
                                array(
                                    '{displayPrice price=$product_price currency=$currency->id}',
                                    '{if isset($presented_product.price_to_display)}{$presented_product.price_to_display nofilter}{else}{displayPrice price=$product_price currency=$currency->id }{/if}',
                                ),
                                array(
                                    '{displayPrice price=(Tools::ps_round($product_price, 2) * ($product[\'product_quantity\'] - $product[\'customizationQuantityTotal\'])) currency=$currency->id}',
                                    '{if isset($presented_product.total_to_display)}{$presented_product.total_to_display nofilter}{else}{displayPrice price=(Tools::ps_round($product_price, 2) * ($product[\'product_quantity\'] - $product[\'customizationQuantityTotal\'])) currency=$currency->id }{/if}',
                                ),
                            ),
                'prepend'=> array(
                                array(
                                    '$product[\'product_quantity_return\']',
                                    '(float)',
                                ),
                                array(
                                    '<div class="row-editing-warning" style="display:none;">',
                                    array('{hook h="displayAdminProductPproperties" product=$presented_product type="orders/_product_line"}'),
                                    'indent' => '        '
                                ),
                            ),
            ),
            array(
                'files'  => array('/themes/default/template/controllers/orders/form.tpl'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'append' => array(
                                array(
                                    'to_delete[3]',
                                    ", to_delete[4]",
                                ),
                                array(
                                    'product[2]',
                                    ", product[3]",
                                ),
                                array( // displayQtyInStock
                                    "$('#qty_in_stock').html(stock[id_product][id_product_attribute]);",
                                    array(
                                        "window.ppAdminTemplates.toggle_bo_qty_text(pproperties[id_product]['pp_bo_qty_text']);",
                                        "$('#qty').val(pproperties[id_product]['default_quantity'] || 1);",
                                    ),
                                    'indent' => '        ',
                                ),
                                array( // searchProducts
                                    'stock = {};',
                                    array('pproperties = {};'),
                                    'indent' => '                ',
                                ),
                                array( // searchProducts
                                    'stock[this.id_product][0] = this.stock[0];',
                                    array('pproperties[this.id_product] = this.pproperties;'),
                                    'indent' => '                        ',
                                ),
                                array(
                                    '<span id="qty_in_stock"></span>',
                                    ' <span class="pp_bo_qty_text"></span>',
                                ),
                                array( // updateCartProducts
                                    'Number(this.id_customization)',
                                    "+'_'+Number(this.id_cart_product)",
                                    'count' => 1
                                ),
                                array( // updateCartProducts
                                    "this.id_customization : 0)+",
                                    "'_'+this.id_cart_product+",
                                    'count' => 4
                                ),
                                array( // deleteProduct, updateQty
                                    'id_customization: id_customization,',
                                    array(
                                        'icp: id_cart_product,',
                                        'op: op,',
                                    ),
                                    'indent' => '                ',
                                    'count' => 2
                                ),
                            ),
                'replace'=> array(
                                array( // addProductProcess
                                    "updateQty($('#id_product').val(), $('#ipa_'+$('#id_product').val()+' option:selected').val(), 0, $('#qty').val());",
                                    "updateQty($('#id_product').val(), $('#ipa_'+$('#id_product').val()+' option:selected').val(), 0, 0, $('#qty').val());",
                                ),
                                array(
                                    ', $(this).val() - cart_quantity[$(this).attr(\'rel\')]',
                                    ', $(this).val(), \'update\''
                                ),
                                array(
                                    'function deleteProduct(id_product, id_product_attribute, id_customization)',
                                    'function deleteProduct(id_product, id_product_attribute, id_customization, id_cart_product, op)',
                                ),
                                array(
                                    'function updateQty(id_product, id_product_attribute, id_customization, qty)',
                                    'function updateQty(id_product, id_product_attribute, id_customization, id_cart_product, qty, op)',
                                ),
                                array(
                                    '<input type="text" name="qty" id="qty" class="form-control fixed-width-sm" value="1" />',
                                    array(
                                        '<div class="fixed-width-md">',
                                        '    <input type="text" name="qty" id="qty" class="form-control" value="1"/>',
                                        '    <span class="input-group-addon input-group-text input-group-append no-input-group-when-empty pp_bo_qty_text"></span>',
                                        '</div>',
                                    ),
                                    'indent' => '                    ',
                                ),
                            ),
            ),
            array(
                'files'  => array('/themes/default/template/controllers/orders/_customized_data.tpl', '/themes/default/template/controllers/orders/_product_line.tpl'),
                'ignore' => $is_ps_version_1_7_7_or_later,
                'prepend'=> array(
                                array(
                                    '{assign var="currencySymbolBeforeAmount" value=$currency->format[0] ===',
                                    array(
                                        '{if !isset($presented_order)}',
                                        '    {assign var=presented_order value=PP::orderPresenterPresent($order) scope="parent"}',
                                        '{/if}',
                                        '{if !isset($presented_product)}',
                                        '    {assign var=presented_product value=PP::findProduct($presented_order, $product)}',
                                        '{/if}',
                                        '{ppAssign order=$product currency=$currency bo=true}'
                                    ),
                                ),
                            ),
                'append' => array(
                                array(
                                    'edit_product_change_link"',
                                    '{if "bulk" == PP::getPPDataType($product)} disabled style="opacity: 0.5; background-color: transparent;"{/if}'
                                ),
                            ),
            ),
            array(
                'files'  => array('/themes/default/template/helpers/form/form.tpl'),
                'ignore' => version_compare(_PS_VERSION_, '1.7.8.0', '<'),
                'replace'=> array(
                                array(
                                    '<label class="control-label col-lg-4',
                                    '<label class="control-label col-lg-{if isset($input.label_col)}{$input.label_col|intval}{else}4{/if}',
                                    'count' => -2
                                ),
                            ),
            ),
            array(
                'files'  => ['/themes/new-theme/public/order_create.bundle.js'],
                'ignore' => !$is_ps_version_1_7_7_or_later,
                'backup' => ['ext' => '.PS' . _PS_VERSION_],
                'replace'=> [
                                [ // admin/themes/new-theme/js/pages/order/create/product-renderer.js => ProductRenderer.renderList
                                    '.listedProductNameField).text',
                                    '.listedProductNameField).html',
                                ],
                            ],
                'append' => [
                                [ // admin/themes/new-theme/js/pages/order/create/product-renderer.js => ProductRenderer.renderList
                                    $is_ps_version_1_7_8_or_later ? 'r.find(a.default.productRemoveBtn).data("attribute-id",e.attributeId),' : 'n.find(l.default.productRemoveBtn).data("attribute-id",r.attributeId),',
                                    $is_ps_version_1_7_8_or_later ? 'window.orderCreateProductRendererRenderList(r,e),' : 'window.orderCreateProductRendererRenderList(n,r),',
                                    'count' => 1
                                ],
                                [ // admin/themes/new-theme/js/pages/order/create/product-renderer.js => ProductRenderer.renderProductMetadata
                                    $is_ps_version_1_7_8_or_later ? ',this.renderCustomizations(e.customizationFields)' : ',this._renderCustomizations(e.customizationFields)',
                                    $is_ps_version_1_7_8_or_later ? ',window.orderCreateProductRendererRenderProductMetadata(e)' : ',window.orderCreateProductRendererRenderProductMetadata(e)',
                                    'count' => 1
                                ],
                                [ // admin/themes/new-theme/js/pages/order/create/create-order-page.js => _initProductRemoveFromCart, _initProductChangePrice, _initProductChangeQty
                                    $is_ps_version_1_7_8_or_later ? 'productId:S(e.currentTarget).data("product-id"),' : 'productId:z(e.currentTarget).data("product-id"),',
                                    $is_ps_version_1_7_8_or_later ? 'cartProductId:S(e.currentTarget).data("id_cart_product"),' : 'cartProductId:z(e.currentTarget).data("id_cart_product"),',
                                    'count' => 3
                                ],
                                [ // admin/themes/new-theme/js/pages/order/create/create-order-page.js => _initProductChangeQty
                                    $is_ps_version_1_7_8_or_later ? ',newQty:S(e.currentTarget).val()' : ',newQty:z(e.currentTarget).val()',
                                    $is_ps_version_1_7_8_or_later ? ',whole_product_quantity:S(e.currentTarget).data("whole_product_quantity")' : ',whole_product_quantity:z(e.currentTarget).data("whole_product_quantity")',
                                    'count' => 1
                                ],
                                [ // admin/themes/new-theme/js/pages/order/create/cart-editor.js => CartEditor.removeProductFromCart
                                    '{productId:t.productId,',
                                    'cartProductId:t.cartProductId,',
                                    'count' => 1
                                ],
                                [ // admin/themes/new-theme/js/pages/order/create/cart-editor.js => CartEditor.changeProductQty
                                    '{newQty:t.newQty,',
                                    'cartProductId:t.cartProductId,whole_product_quantity:t.whole_product_quantity,',
                                    'count' => 1
                                ],
                            ],
            ),
            array(
                'files'  => ['/themes/new-theme/public/order_view.bundle.js'],
                'ignore' => !$is_ps_version_1_7_7_or_later,
                'backup' => ['ext' => '.PS' . _PS_VERSION_],
                'replace'=> [
                                [
                                    '.productEditUnitPrice).text',
                                    '.productEditUnitPrice).html',
                                ],
                                [
                                    '.productEditQuantity).text(e.quantity)',
                                    '.productEditQuantity).html(e.quantityFormatted)',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.3', '>=')
                                ],
                                [
                                    '.productEditQuantity).html(r.html())',
                                    '.productEditQuantity).html(e.quantityFormatted)',
                                    'ignore' => version_compare(_PS_VERSION_, '1.7.7.3', '<')
                                ],
                                [
                                    '.productEditAvailableQuantity).text(e.availableQuantity)',
                                    '.productEditAvailableQuantity).html(e.availableQuantityFormatted)',
                                ],
                                [
                                    '.productEditTotalPrice).text',
                                    '.productEditTotalPrice).html',
                                ],
                                [
                                    $is_ps_version_1_7_8_or_later ? '=parseInt(f(t.currentTarget).data("availableQuantity"),10)' : '=parseInt(x(t.currentTarget).data("availableQuantity"),10)',
                                    $is_ps_version_1_7_8_or_later ? '=pp.parseFloat(f(t.currentTarget).data("availableQuantity"))' : '=pp.parseFloat(x(t.currentTarget).data("availableQuantity"))',
                                ],
                                [
                                    '=parseInt(e.quantityInput.data("previousQuantity"),10)',
                                    '=pp.parseFloat(x(t.currentTarget).data("previousQuantity"))',
                                    'ignore' => $is_ps_version_1_7_8_or_later,
                                ],
                                [
                                    '=n-(r-o),',
                                    '=n-(r-o)*pp.parseFloat(x(t.currentTarget).data("product_quantity_factor"),1),',
                                    'ignore' => $is_ps_version_1_7_8_or_later,
                                    'count' => 1
                                ],
                                [
                                    '(r-parseInt(e.quantityInput.data("previousQuantity"),10)),',
                                    '(r-pp.parseFloat(f(t.currentTarget).data("previousQuantity")))*pp.parseFloat(f(t.currentTarget).data("product_quantity_factor"),1),',
                                    'ignore' => !$is_ps_version_1_7_8_or_later,
                                ],
                                [
                                    '=this.priceTaxCalculator.calculateTotalPrice(this.quantity,',
                                    '=this.priceTaxCalculator.calculateTotalPrice(this.quantityInput.val()*pp.parseFloat(this.quantityInput.data("product_quantity_factor"),1),',
                                ],
                                [
                                    $is_ps_version_1_7_8_or_later ? 'this.productEditInvoiceSelect.val()};f.ajax' : 'this.productEditInvoiceSelect.val()};x.ajax',
                                    $is_ps_version_1_7_8_or_later ? 'this.productEditInvoiceSelect.val(),whole_product_quantity:f("input.editProductWholeQuantity").val()};f.ajax' : 'this.productEditInvoiceSelect.val(),whole_product_quantity:x("input.editProductWholeQuantity").val()};x.ajax',
                                    'count' => 1
                                ],
                            ],
            ),
        );

        return $this->processReplaceInFiles($themefiles, $replace, _PS_THEME_DIR_)
                && $this->processReplaceInFiles($smartysysplugins, $replace, SMARTY_SYSPLUGINS_DIR)
                && $this->processReplaceInFiles($root, $replace, _PS_ROOT_DIR_.'/')
                && $this->processReplaceInFiles($adminthemefiles, $replace, _PS_ADMIN_DIR_);
    }

    private function resolveThemeJsStringPrefixSuffix($mode, $search, $default = null, $avoid = null)
    {
        $output = [$default, $default];
        $theme_js = 'assets/js/theme.js';
        $filename = null;
        if (is_file(_PS_THEME_DIR_ . $theme_js)) {
            $filename = _PS_THEME_DIR_ . $theme_js;
        } elseif (defined('_PS_PARENT_THEME_DIR_') && is_file(_PS_PARENT_THEME_DIR_ . $theme_js)) {
            $filename = _PS_PARENT_THEME_DIR_ . $theme_js;
        }
        if ($filename) {
            $content = Tools::file_get_contents($filename);
            if (!is_array($search)) {
                $search = array($search);
            }
            foreach ($search as $string) {
                $offset = 0;
                while ($pos = Tools::strpos($content, $string, $offset)) {
                    if (!is_string($avoid) || ($avoid != Tools::substr($content, $pos - Tools::strlen($avoid), Tools::strlen($avoid)))) {
                        $preceding = Tools::substr($content, $pos - 2, 1);
                        $succeeding = Tools::substr($content, $pos + Tools::strlen($string) + 1, 1);
                        if (((($mode & 1) != 1) || !ctype_alnum($preceding)) && ((($mode & 2) != 2) || !ctype_alnum($succeeding))) {
                            $prefix = Tools::substr($content, $pos - 1, 1);
                            $suffix = Tools::substr($content, $pos + Tools::strlen($string), 1);
                            $output = [$prefix, $suffix];
                            break 2;
                        }
                    }
                    $offset = $pos + Tools::strlen($string);
                }
            }
        }
        return $output;
    }

    private function processMailFiles($install)
    {
        $params = array(
            array(
                'files' => array(
                    'account.html', 'bankwire.html', 'cheque.html', 'download_product.html', 'newsletter.html',
                    'order_conf.html', 'order_changed.html', 'order_canceled.html', 'payment.html', 'preparation.html', 'shipped.html'
                ),
                'condition' => '://www.prestashop.com',
                'delimiter' => '</a>',
                'target' => PSM::authorDomain(),
                'replace'=> ' &amp; <a style="text-decoration: none; color: #337ff1;" href="'.PSM::authorUrl().'">PS&amp;More&trade;</a>'
            )
        );
        $this->setupMail($params, $install);
    }
}
