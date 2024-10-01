<?php
/**
 * Product Properties Extension
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration
class AdminCartsControllerBase extends AdminController
{
    public function ajaxProcessUpdateQty()
    {
        if ($this->access('edit')) {
            $errors = array();
            if (!$this->context->cart->id) {
                return;
            }
            if ($this->context->cart->OrderExists()) {
                $errors[] = $this->trans('An order has already been placed with this cart.', array(), 'Admin.Catalog.Notification');
            } elseif (!($id_product = (int) Tools::getValue('id_product')) || !($product = new Product((int) $id_product, true, $this->context->language->id))) {
                $errors[] = $this->trans('Invalid product', array(), 'Admin.Catalog.Notification');
            } elseif (!($qty = Tools::getValue('qty')) || $qty == 0) {
                $errors[] = $this->trans('Invalid quantity', array(), 'Admin.Catalog.Notification');
            }

            // Don't try to use a product if not instanciated before due to errors
            if (isset($product) && $product->id) {
                $id_product_attribute = (int) Tools::getValue('id_product_attribute');
                $operator = Tools::getValue('op', (int) $qty < 0 ? $operator = 'down' : 'up');
                $properties = $product->productProperties();
                if (($icp = (int) Tools::getValue('icp', 0)) == 0) { // TODO: if (($icp = Tools::getValue('icp')) == 'add') {
                    $id_cart_product = 0;
                    $minimum_quantity = ($id_product_attribute ? $product->attributeMinQty($id_product_attribute) : $product->minQty());
                    $qty_step = ($id_product_attribute ? $product->attributeQtyStep($id_product_attribute) : $product->qtyStep());
                    $qty = $check_qty = PP::resolveInputQty(abs($qty), $properties, array('minimum_quantity' => $minimum_quantity, 'qty_step' => $qty_step), false);
                } elseif ((int) $icp > 0) {
                    $cart_products = $this->context->cart->getProducts();
                    if (is_array($cart_products)) {
                        foreach ($cart_products as $cart_product) {
                            if ((int) $icp == (int) $cart_product['id_cart_product']) {
                                $id_cart_product = (int) $icp;
                                $behavior = PP::qtyBehavior($product, $cart_product['cart_quantity']);
                                if ($operator == 'update') {
                                    if ($behavior) {
                                        $qty = $check_qty = abs((float) $qty);
                                    } else {
                                        $qty = abs((int) $qty);
                                        $check_qty = PP::resolveQty($qty, $cart_product['cart_quantity_fractional']);
                                    }
                                } else {
                                    $qty = $properties['pp_qty_shift'] ?: 1; // up/down
                                    $sign = $operator == 'down' ? -1 : 1;
                                    if ($behavior) {
                                        $qty = $check_qty = $cart_product['cart_quantity_fractional'] + $qty * $sign;
                                        $operator = 'update';
                                    } else {
                                        $check_qty = PP::resolveQty($cart_product['cart_quantity'] + $qty * $sign, $cart_product['cart_quantity_fractional']);
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
                if (isset($id_cart_product)) {
                    if ($id_product_attribute) {
                        if (!Product::isAvailableWhenOutOfStock($product->out_of_stock) && !Attribute::checkAttributeQty((int) $id_product_attribute, $check_qty)) {
                            $errors[] = $this->trans('There are not enough products in stock.', array(), 'Admin.Catalog.Notification');
                        }
                    } elseif (!$product->checkQty($check_qty)) {
                        $errors[] = $this->trans('There are not enough products in stock.', array(), 'Admin.Catalog.Notification');
                    }
                    if (!($id_customization = (int) Tools::getValue('id_customization', 0)) && !$product->hasAllRequiredCustomizableFields()) {
                        $errors[] = $this->trans('Please fill in all the required fields.', array(), 'Admin.Notifications.Error');
                    }
                    $this->context->cart->save();
                } else {
                    $errors[] = PSM::translate('invalid_cart_product_reference');
                }
            } else {
                $errors[] = $this->trans('This product cannot be added to the cart.', array(), 'Admin.Catalog.Notification');
            }

            if (!count($errors)) {
                if (!isset($operator) || $operator != 'update') {
                    if ((isset($operator) && $operator == 'down') || (int) $qty < 0) {
                        $qty = str_replace('-', '', $qty);
                        $operator = 'down';
                    } else {
                        $operator = 'up';
                    }
                }

                if (!($qty_upd = $this->context->cart->updateQty($qty, $id_product, (int) $id_product_attribute, (int) $id_customization, $operator, 0, null, true, false, true, false, $id_cart_product))) {
                    $errors[] = $this->trans('You already have the maximum quantity available for this product.', array(), 'Admin.Catalog.Notification');
                } elseif ($qty_upd < 0) {
                    $minimal_qty = $id_product_attribute ? $product->attributeMinQty((int) $id_product_attribute) : $product->minQty();
                    $errors[] = sprintf($this->trans('You must add a minimum quantity of %d', array(), 'Admin.Catalog.Notification'), $minimal_qty);
                }
            }

            echo json_encode(array_merge($this->ajaxReturnVars(), array('errors' => $errors)));
        }
    }
}
