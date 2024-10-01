<?php
/**
 * Product Properties Extension
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration, PSR2.Methods.MethodDeclaration
class CartControllerBase extends FrontController
{
    protected function processChangeProductInCart()
    {
        $id_cart_product = (int) Tools::getValue('icp', 0);
        $mode = (Tools::getIsset('update') && $this->id_product && $id_cart_product) ? 'update' : 'add';
        $operator = Tools::getValue('op', 'up');
        $ErrorKey = ('update' === $mode) ? 'updateOperationError' : 'errors';

        if (Tools::getIsset('group')) {
            $this->id_product_attribute = (int) Product::getIdProductAttributeByIdAttributes(
                $this->id_product,
                Tools::getValue('group')
            );
        }

        if (!$this->id_product) {
            $this->{$ErrorKey}[] = $this->trans(
                'Product not found',
                array(),
                'Shop.Notifications.Error'
            );
            return;
        }
        $product = new Product($this->id_product, true, $this->context->language->id);
        if (!$product->id || !$product->active || !$product->checkAccess($this->context->cart->id_customer)) {
            $this->{$ErrorKey}[] = $this->trans(
                'This product (%product%) is no longer available.',
                array('%product%' => $product->name),
                'Shop.Notifications.Error'
            );
            return;
        }

        $properties = $product->productProperties();
        if (PP::isMultidimensional($properties)) {
            $multidimensional_plugin = PP::getMultidimensionalPlugin();
            if (!$multidimensional_plugin) {
                $this->{$ErrorKey}[] = PSM::translate('multidimensional_plugin_not_installed');
                return;
            }
        } else {
            $multidimensional_plugin = null;
        }
        if ('bulk' === PP::getPPDataType($properties)) {
            if ('update' === $mode || 'update' === $operator) {
                $this->{$ErrorKey}[] = PSM::translate('quantity_update_not_supported');
                return;
            }
            $this->id_product_attribute = 0;
            $bulk = PP::getProductBulk($properties);
        } else {
            if (!$this->id_product_attribute && $product->hasAttributes()) {
                $minimum_quantity = ($product->out_of_stock == 2)
                    ? !Configuration::get('PS_ORDER_OUT_OF_STOCK')
                    : !$product->out_of_stock;
                $this->id_product_attribute = Product::getDefaultAttribute($product->id, $minimum_quantity);
                // @todo do something better than a redirect admin !!
                if (!$this->id_product_attribute) {
                    Tools::redirectAdmin($this->context->link->getProductLink($product));
                }
            }
        }

        if ('update' !== $mode && PP::isQuantityCalculator($properties)) {
            list($ext_calculated_quantity, $ext_calculated_total_quantity, $ext_prop_quantities, $errors) = $multidimensional_plugin->processQuantities($properties, $this->id_product, $this->id_product_attribute);
            $this->qty = $ext_calculated_quantity;
            unset($ext_calculated_quantity);
            if (is_array($errors)) {
                $this->{$ErrorKey} = array_merge($this->{$ErrorKey}, $errors);
            }
        } else {
            $this->qty = Tools::getValue('qty', false);
        }
        if ($this->qty !== false || 'update' === $operator) {
            $this->qty = PP::normalizeQty(abs($this->qty), $properties['qty_policy']);
            if ($this->qty <= 0) {
                $this->{$ErrorKey}[] = $this->trans('Quantity not specified.', array(), 'Shop.Notifications.Error');
                return;
            }
        }
        if (!$this->{$ErrorKey}) {
            if ($this->qty === false) {
                if ('update' !== $mode || 'update' === $operator) {
                    $this->qty = ($this->id_product_attribute ? $product->attributeDefaultQty($this->id_product_attribute) : $product->defaultQty());
                } else {
                    $this->qty = $properties['pp_qty_shift'] ?: 1; // add to cart or up/down
                }
            }
            if ('update' === $mode) {
                if (isset($bulk)) {
                    // should never happen
                    throw new PrestaShopException('Update mode for product bulk operations is not supported');
                }
                // in 'update' mode we use the cart to find the product details
                $cart_products = $this->context->cart->getProducts();
                if (is_array($cart_products)) {
                    foreach ($cart_products as $_cart_product) {
                        if ($id_cart_product == (int) $_cart_product['id_cart_product']) {
                            $cart_product = $_cart_product;
                            if ('update' !== $operator) {
                                $cart_quantity = (PP::qtyPolicyFractional($properties['qty_policy']) ? PP::resolveQty($cart_product) : $cart_product['cart_quantity']);
                                if ('down' === $operator) {
                                    $this->qty = $cart_quantity - $this->qty;
                                } else {
                                    $this->qty = $cart_quantity + $this->qty;
                                }
                                $operator = 'update';
                            }
                            break;
                        }
                    }
                }
                if (!isset($cart_product)) {
                    $this->{$ErrorKey}[] = PSM::translate('invalid_cart_product_reference');
                    return;
                }
            }
            $minimum_quantity = ($this->id_product_attribute ? $product->attributeMinQty($this->id_product_attribute) : $product->minQty());
            $qty_step = ($this->id_product_attribute ? $product->attributeQtyStep($this->id_product_attribute) : $product->qtyStep());
            $qty = PP::resolveInputQty($this->qty, $properties, array('minimum_quantity' => $minimum_quantity, 'qty_step' => $qty_step), false);
            if (round($this->qty, 8) < round($qty, 8)) {
                $this->{$ErrorKey}[] = PSM::translate(
                    'should_add_at_least',
                    array(
                        '%quantity%' => PP::formatQty($qty),
                        '%text%' => PSM::nvl($properties['pp_qty_text'], PSM::plural($qty)),
                        '%name%' => PSM::amendForTranslation($product->name),
                    )
                );
                return;
            }
            $this->qty = $qty;
            if (isset($bulk)) {
                if ($this->qty != $bulk['quantity']) {
                    // should not happen
                    $this->{$ErrorKey}[] = $this->trans('Quantity not specified.', array(), 'Shop.Notifications.Error');
                    return;
                }
            }
        }
        if (!$this->{$ErrorKey}) {
            $use_multidimensional_quantity = PP::isQuantityMultidimensional($properties) && !PP::isPacksCalculator($properties);
            $qty_to_check = $this->qty;
            if ('update' === $mode) {
                if (isset($bulk)) {
                    // should never happen
                    throw new PrestaShopException('Update mode for product bulk operations is not supported');
                }
                if ($use_multidimensional_quantity || PP::qtyPolicyWholeUnits($properties['qty_policy'])) {
                    $qty_to_check = $this->qty * (float) $cart_product['cart_quantity_fractional'];
                }
            } else {
                $id_cart_product = 0;
                if ($use_multidimensional_quantity) {
                    list($ext_calculated_quantity, $ext_calculated_total_quantity, $ext_prop_quantities, $errors) = $multidimensional_plugin->processQuantities($properties, $this->id_product, $this->id_product_attribute);
                    if (is_numeric($ext_calculated_quantity)) {
                        $qty_to_check = $this->qty * $ext_calculated_quantity;
                    }
                    if ($errors) {
                        $this->{$ErrorKey} = array_merge($this->{$ErrorKey}, $errors);
                    }
                }
                if (isset($bulk)) {
                    $qty_to_check = $bulk;
                }
            }
        }
        if (!$this->{$ErrorKey}) {
            // pp_maximum_quantity refers to the quantity entered by user (in multidimensional template the quantity refers to the number of items)
            if ($properties['pp_maximum_quantity'] > 0 && $this->qty > $properties['pp_maximum_quantity']) {
                $this->{$ErrorKey}[] = PSM::translate(
                    'cannot_order_more_than',
                    array(
                        '%quantity%' => PP::formatQty($properties['pp_maximum_quantity']),
                        '%text%' => PSM::nvl($properties['pp_qty_text'], PSM::plural($properties['pp_maximum_quantity'])),
                        '%name%' => $product->name
                    )
                );
            }
            // Check product quantity availability
            if ('update' !== $mode && $this->_shouldAvailabilityErrorBeRaised($product, $qty_to_check)) {
                $this->{$ErrorKey}[] = $this->trans(
                    'The item %product% in your cart is no longer available in this quantity. You cannot proceed with your order until the quantity is adjusted.',
                    array('%product%' => $product->name),
                    'Shop.Notifications.Error'
                );
            }
        }

        // If no errors, process product addition
        if (!$this->{$ErrorKey}) {
            // Add cart if no cart found
            if (!$this->context->cart->id) {
                if (Context::getContext()->cookie->id_guest) {
                    $guest = new Guest(Context::getContext()->cookie->id_guest);
                    $this->context->cart->mobile_theme = $guest->mobile_theme;
                }
                $this->context->cart->add();
                if ($this->context->cart->id) {
                    $this->context->cookie->id_cart = (int) $this->context->cart->id;
                }
            }

            // Check customizable fields
            if (!$product->hasAllRequiredCustomizableFields() && !$this->customization_id) {
                $this->{$ErrorKey}[] = $this->trans(
                    'Please fill in all of the required fields, and then save your customizations.',
                    array(),
                    'Shop.Notifications.Error'
                );
            }

            if (!$this->{$ErrorKey}) {
                $update_quantity = $this->context->cart->updateQty(
                    isset($bulk) ? $bulk : $this->qty,
                    $this->id_product,
                    $this->id_product_attribute,
                    $this->customization_id,
                    $operator,
                    $this->id_address_delivery,
                    null,
                    true,
                    true,
                    true,
                    false,
                    $id_cart_product,
                    $ext_prop_quantities ?? null,
                    isset($ext_calculated_quantity) && is_numeric($ext_calculated_quantity) ? $ext_calculated_quantity : 0
                );
                if ($update_quantity === -2) {
                    $this->{$ErrorKey}[] = PSM::translate(
                        'cannot_order_more_than',
                        array(
                            '%quantity%' => PP::formatQty($properties['pp_maximum_quantity']),
                            '%text%' => PSM::nvl($properties['pp_qty_text'], PSM::plural($properties['pp_maximum_quantity'])),
                            '%name%' => $product->name
                        )
                    );
                } elseif ($update_quantity < 0) {
                    $this->{$ErrorKey}[] = PSM::translate(
                        'should_add_at_least',
                        array(
                            '%quantity%' => PP::formatQty($minimum_quantity),
                            '%text%' => PSM::nvl($properties['pp_qty_text'], PSM::plural($minimum_quantity)),
                            '%name%' => PSM::amendForTranslation($product->name)
                        )
                    );
                } elseif (!$update_quantity) {
                    $this->{$ErrorKey}[] = $this->trans(
                        'You already have the maximum quantity available for this product.',
                        array(),
                        'Shop.Notifications.Error'
                    );
                } elseif ($this->_shouldAvailabilityErrorBeRaised($product, $qty_to_check)) {
                    // check quantity after cart quantity update
                    $this->{$ErrorKey}[] = $this->trans(
                        'The item %product% in your cart is no longer available in this quantity. You cannot proceed with your order until the quantity is adjusted.',
                        array('%product%' => $product->name),
                        'Shop.Notifications.Error'
                    );
                }
                if (!$this->{$ErrorKey} && 'update' === $mode) {
                    $p = $this->context->cart->checkQuantities(true);
                    if (isset($p['ppCheckQuantitiesMessage'])) {
                        $this->{$ErrorKey}[] = $p['ppCheckQuantitiesMessage'];
                    }
                }
            }
        }

        $removed = CartRule::autoRemoveFromCart();
        CartRule::autoAddToCart();
    }

    private function _shouldAvailabilityErrorBeRaised($product, $qtyToCheck)
    {
        if (!empty($qtyToCheck['bulk'])) {
            $return = false;
            $id_product = $qtyToCheck['id_product'];
            if ($product->id != $id_product) {
                // TODO cache products
                $productToCheck = new Product($id_product, true, $this->context->language->id);
            } else {
                $productToCheck = $product;
            }
            foreach ($qtyToCheck['bulk'] as $id_product_attribute => $data) {
                $this->id_product_attribute = $id_product_attribute;
                $return = $this->shouldAvailabilityErrorBeRaised($productToCheck, $data['quantity']);
                if ($return) {
                    break;
                }
            }
            $this->id_product_attribute = 0;
            return $return;
        }
        return $this->shouldAvailabilityErrorBeRaised($product, $qtyToCheck);
    }
}
