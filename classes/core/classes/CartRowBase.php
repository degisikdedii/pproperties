<?php
/**
 * Product Properties Extension
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

namespace PrestaShop\PrestaShop\Core\Cart;

use PrestaShop\PrestaShop\Adapter\Tools;

// phpcs:disable Generic.Files.LineLength
class CartRowBase
{
    protected function applyRound()
    {
        // ROUNDING MODE
        $this->finalUnitPrice = clone $this->initialUnitPrice;

        $rowData = $this->getRowData();
        $id_product = (int) $rowData['id_product'];
        $cart_quantity = \PP::obtainQty($rowData);
        $cart_quantity_fractional = \PP::obtainQtyFractional($rowData);
        switch ($this->roundType) {
            case CartRow::ROUND_MODE_TOTAL:
                // do not round the line
                $total_tax_included = \PP::calcPrice($this->initialUnitPrice->getTaxIncluded(), $cart_quantity, $cart_quantity_fractional, $id_product, true, false);
                $total_tax_excluded = \PP::calcPrice($this->initialUnitPrice->getTaxExcluded(), $cart_quantity, $cart_quantity_fractional, $id_product, false, false);
                // [HOOK ppropertiessmartprice #1]
                $this->initialTotalPrice = new AmountImmutable(
                    $total_tax_included,
                    $total_tax_excluded
                );
                $this->finalTotalPrice = new AmountImmutable(
                    $total_tax_included,
                    $total_tax_excluded
                );
                break;

            case CartRow::ROUND_MODE_LINE:
                // round line result
                $total_tax_included = \PP::calcPrice($this->initialUnitPrice->getTaxIncluded(), $cart_quantity, $cart_quantity_fractional, $id_product, true, false);
                $total_tax_excluded = \PP::calcPrice($this->initialUnitPrice->getTaxExcluded(), $cart_quantity, $cart_quantity_fractional, $id_product, false, false);
                // [HOOK ppropertiessmartprice #1]
                $this->initialTotalPrice = new AmountImmutable(
                    $total_tax_included,
                    $total_tax_excluded
                );
                if (\PP::performPricerounding()) {
                    $total_tax_included = \PP::pricerounding($this->finalTotalPrice->getTaxIncluded(), 'total', $id_product, $cart_quantity, $cart_quantity_fractional, true);
                    $total_tax_excluded = \PP::pricerounding($this->finalTotalPrice->getTaxExcluded(), 'total', $id_product, $cart_quantity, $cart_quantity_fractional, false);
                }
                $this->finalTotalPrice = new AmountImmutable(
                    $total_tax_included,
                    $total_tax_excluded
                );
                break;

            case CartRow::ROUND_MODE_ITEM:
            default:
                // round each item
                $price_tax_included = $this->initialUnitPrice->getTaxIncluded();
                $price_tax_excluded = $this->initialUnitPrice->getTaxExcluded();
                // [HOOK ppropertiessmartprice #2]
                $total_tax_included = \PP::calcPrice($price_tax_included, $cart_quantity, $cart_quantity_fractional, $id_product, true, \Order::ROUND_ITEM, $this->precision);
                $total_tax_excluded = \PP::calcPrice($price_tax_excluded, $cart_quantity, $cart_quantity_fractional, $id_product, false, \Order::ROUND_ITEM, $this->precision);
                $this->initialUnitPrice = new AmountImmutable(
                    $total_tax_included,
                    $total_tax_excluded
                );
                if (\PP::performPricerounding()) {
                    $total_tax_included = \PP::pricerounding($this->finalTotalPrice->getTaxIncluded(), 'total', $id_product, $cart_quantity, $cart_quantity_fractional, true);
                    $total_tax_excluded = \PP::pricerounding($this->finalTotalPrice->getTaxExcluded(), 'total', $id_product, $cart_quantity, $cart_quantity_fractional, false);
                }
                $this->finalTotalPrice = new AmountImmutable(
                    $total_tax_included,
                    $total_tax_excluded
                );
                break;
        }
    }
}
