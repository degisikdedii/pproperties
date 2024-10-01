<?php
/**
 * Product Properties Extension
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration
class AdminOrdersControllerBase extends AdminController
{
    public function ajaxProcessEditProductOnOrder()
    {
        // Return value
        $res = true;

        $order = new Order((int) Tools::getValue('id_order'));
        $order_detail = new OrderDetail((int) Tools::getValue('product_id_order_detail'));
        if (Tools::isSubmit('product_invoice')) {
            $order_invoice = new OrderInvoice((int) Tools::getValue('product_invoice'));
        }

        // Check fields validity
        $this->doEditProductValidation($order_detail, $order, isset($order_invoice) ? $order_invoice : null);

        // If multiple product_quantity, the order details concern a product customized
        if (is_array(Tools::getValue('product_quantity'))) {
            $product_quantity = 0;
            foreach (Tools::getValue('product_quantity') as $id_customization => $qty) {
                // Update quantity of each customization
                Db::getInstance()->update('customization', array('quantity' => (float) $qty), 'id_customization = ' . (int) $id_customization);
                // Calculate the real quantity of the product
                $product_quantity += $qty;
            }
            $product_quantity_fractional = $order_detail->product_quantity_fractional;
        } else {
            $properties = PP::getProductProperties($order_detail);
            if (PP::editBothQuantities($properties)) {
                $whole_product_quantity = \PP::getIntNonNegativeValue('whole_product_quantity');
                if ($whole_product_quantity <= 0) {
                    die(json_encode(array(
                        'result' => false,
                        'error' => $this->trans('Invalid quantity', array(), 'Admin.Orderscustomers.Notification'),
                    )));
                }
                $product_quantity = $whole_product_quantity;
                $product_quantity_fractional = round(PP::getFloatNonNegativeValue('product_quantity') / $product_quantity, 6);
            } elseif (PP::qtyBehavior($order_detail, $order_detail->product_quantity, $properties)) {
                $product_quantity = $order_detail->product_quantity;
                $product_quantity_fractional = PP::getFloatNonNegativeValue('product_quantity');
            } else {
                $product_quantity = PP::getIntNonNegativeValue('product_quantity');
                $product_quantity_fractional = $order_detail->product_quantity_fractional;
            }
        }

        $product_price_tax_incl = Tools::ps_round(PP::getFloatNonNegativeValue('product_price_tax_incl'), 2);
        $product_price_tax_excl = Tools::ps_round(PP::getFloatNonNegativeValue('product_price_tax_excl'), 2);
        $total_products_tax_incl = PP::calcPrice($product_price_tax_incl, $product_quantity, $product_quantity_fractional, $order_detail->product_id, true);
        $total_products_tax_excl = PP::calcPrice($product_price_tax_excl, $product_quantity, $product_quantity_fractional, $order_detail->product_id, false);

        // Calculate differences of price (Before / After)
        $diff_price_tax_incl = $total_products_tax_incl - $order_detail->total_price_tax_incl;
        $diff_price_tax_excl = $total_products_tax_excl - $order_detail->total_price_tax_excl;
        // [HOOK ppropertiessmartprice #1]

        // Apply change on OrderInvoice
        if (isset($order_invoice)) {
            // If OrderInvoice to use is different, we update the old invoice and new invoice
            if ($order_detail->id_order_invoice && $order_detail->id_order_invoice != $order_invoice->id) {
                $old_order_invoice = new OrderInvoice($order_detail->id_order_invoice);
                // We remove cost of products
                $old_order_invoice->total_products -= $order_detail->total_price_tax_excl;
                $old_order_invoice->total_products_wt -= $order_detail->total_price_tax_incl;

                $old_order_invoice->total_paid_tax_excl -= $order_detail->total_price_tax_excl;
                $old_order_invoice->total_paid_tax_incl -= $order_detail->total_price_tax_incl;

                $res &= $old_order_invoice->update();

                $order_invoice->total_products += $order_detail->total_price_tax_excl;
                $order_invoice->total_products_wt += $order_detail->total_price_tax_incl;

                $order_invoice->total_paid_tax_excl += $order_detail->total_price_tax_excl;
                $order_invoice->total_paid_tax_incl += $order_detail->total_price_tax_incl;

                $order_detail->id_order_invoice = $order_invoice->id;
            }
        }

        if ($diff_price_tax_incl != 0 && $diff_price_tax_excl != 0) {
            $order_detail->unit_price_tax_excl = $product_price_tax_excl;
            $order_detail->unit_price_tax_incl = $product_price_tax_incl;

            $order_detail->total_price_tax_incl += $diff_price_tax_incl;
            $order_detail->total_price_tax_excl += $diff_price_tax_excl;

            if (isset($order_invoice)) {
                // Apply changes on OrderInvoice
                $order_invoice->total_products += $diff_price_tax_excl;
                $order_invoice->total_products_wt += $diff_price_tax_incl;

                $order_invoice->total_paid_tax_excl += $diff_price_tax_excl;
                $order_invoice->total_paid_tax_incl += $diff_price_tax_incl;
            }

            // Apply changes on Order
            $order = new Order($order_detail->id_order);
            $order->total_products += $diff_price_tax_excl;
            $order->total_products_wt += $diff_price_tax_incl;

            $order->total_paid += $diff_price_tax_incl;
            $order->total_paid_tax_excl += $diff_price_tax_excl;
            $order->total_paid_tax_incl += $diff_price_tax_incl;

            $res &= $order->update();
        }

        $old_quantity = PP::resolveQty($order_detail->product_quantity, $order_detail->product_quantity_fractional);

        $order_detail->product_quantity = $product_quantity;
        $order_detail->product_quantity_fractional = $product_quantity_fractional;
        $order_detail->reduction_percent = 0;

        // update taxes
        $res &= $order_detail->updateTaxAmount($order);

        // Save order detail
        $res &= $order_detail->update();

        // Update weight SUM
        $order_carrier = new OrderCarrier((int) $order->getIdOrderCarrier());
        if (Validate::isLoadedObject($order_carrier)) {
            $order_carrier->weight = (float) $order->getTotalWeight();
            $res &= $order_carrier->update();
            if ($res) {
                $order->weight = sprintf('%.3f ' . Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
            }
        }

        // Save order invoice
        if (isset($order_invoice)) {
            $res &= $order_invoice->update();
        }

        // Update product available quantity
        StockAvailable::updateQuantity(
            $order_detail->product_id,
            $order_detail->product_attribute_id,
            ($old_quantity - PP::resolveQty($order_detail->product_quantity, $order_detail->product_quantity_fractional)),
            $order->id_shop
        );

        $products = $this->getProducts($order);
        // Get the last product
        $product = $products[$order_detail->id];
        $resume = OrderSlip::getProductSlipResume($order_detail->id);
        $product['quantity_refundable'] = $product['product_quantity'] - $resume['product_quantity'];
        if (PP::qtyBehavior($product, $product['product_quantity'])) {
            $product['quantity_refundable'] = PP::resolveQty($product['product_quantity'], $product['product_quantity_fractional']) - $resume['product_quantity'];
        }
        $product['amount_refundable'] = $product['total_price_tax_excl'] - $resume['amount_tax_excl'];
        $product['amount_refund'] = Tools::displayPrice($resume['amount_tax_incl']);
        $product['refund_history'] = OrderSlip::getProductSlipDetail($order_detail->id);
        if ($product['id_warehouse'] != 0) {
            $warehouse = new Warehouse((int) $product['id_warehouse']);
            $product['warehouse_name'] = $warehouse->name;
            $warehouse_location = WarehouseProductLocation::getProductLocation($product['product_id'], $product['product_attribute_id'], $product['id_warehouse']);
            if (!empty($warehouse_location)) {
                $product['warehouse_location'] = $warehouse_location;
            } else {
                $product['warehouse_location'] = false;
            }
        } else {
            $product['warehouse_name'] = '--';
            $product['warehouse_location'] = false;
        }

        // Get invoices collection
        $invoice_collection = $order->getInvoicesCollection();

        $invoice_array = array();
        foreach ($invoice_collection as $invoice) {
            /* @var OrderInvoice $invoice */
            $invoice->name = $invoice->getInvoiceNumberFormatted(Context::getContext()->language->id, (int) $order->id_shop);
            $invoice_array[] = $invoice;
        }

        $order = $order->refreshShippingCost();

        $stockLocationIsAvailable = false;
        foreach ($products as $currentProduct) {
            if (!empty($currentProduct['location'])) {
                $stockLocationIsAvailable = true;
                break;
            }
        }

        // Assign to smarty informations in order to show the new product line
        $this->context->smarty->assign(array(
            'product' => $product,
            'order' => $order,
            'currency' => new Currency($order->id_currency),
            'can_edit' => $this->access('edit'),
            'invoices_collection' => $invoice_collection,
            'current_id_lang' => Context::getContext()->language->id,
            'link' => Context::getContext()->link,
            'current_index' => self::$currentIndex,
            'display_warehouse' => (int) Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT'),
            'stock_location_is_available' => $stockLocationIsAvailable,
        ));

        if (!$res) {
            die(json_encode(array(
                'result' => $res,
                'error' => $this->trans('An error occurred while editing the product line.', array(), 'Admin.Orderscustomers.Notification'),
            )));
        }

        if (is_array(Tools::getValue('product_quantity'))) {
            $view = $this->createTemplate('_customized_data.tpl')->fetch();
        } else {
            $view = $this->createTemplate('_product_line.tpl')->fetch();
        }

        $this->sendChangedNotification($order);

        die(json_encode(array(
            'result' => $res,
            'view' => $view,
            'can_edit' => $this->access('add'),
            'invoices_collection' => $invoice_collection,
            'order' => $order,
            'invoices' => $invoice_array,
            'documents_html' => $this->createTemplate('_documents.tpl')->fetch(),
            'shipping_html' => $this->createTemplate('_shipping.tpl')->fetch(),
            'customized_product' => is_array(Tools::getValue('product_quantity')),
        )));
    }
}
