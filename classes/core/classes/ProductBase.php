<?php
/**
 * Product Properties Extension
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration
class ProductBase extends ObjectModel
{
    public $minimal_quantity_fractional = 0;
    public $id_pp_template = 0;
    protected $pproperties = null;
    public static $amend = true;

    public static function hasCustomizedDatas($product, $customized_datas)
    {
        if ($customized_datas) {
            /* Compatibility */
            $id_product = (isset($product['id_product']) ? 'id_product' : 'product_id');
            $id_product_attribute = (isset($product['id_product_attribute']) ? 'id_product_attribute' : 'product_attribute_id');
            if (isset($customized_datas[(int)$product[$id_product]][(int)$product[$id_product_attribute]])) {
                foreach ($customized_datas[(int)$product[$id_product]][(int)$product[$id_product_attribute]] as $addresses) {
                    foreach ($addresses as $customization) {
                        if ($product['id_cart_product'] == $customization['id_cart_product']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    protected function amend()
    {
        if (self::$amend) {
            $this->productProperties();
            if ($this->pproperties['pp_unit_price_ratio'] > 0) {
                $this->unit_price_ratio = $this->pproperties['pp_unit_price_ratio'];
            }
            if ((float)$this->unit_price_ratio != 0) {
                $this->unit_price = ($this->price / $this->unit_price_ratio); // recalculate unit_price
            }
            if (!empty($this->pproperties['pp_unity_text'])) {
                $this->unity = $this->pproperties['pp_unity_text'];
            }
            if ((int)(($this->pproperties['pp_display_mode'] & 2) == 2)) { // display retail price as unit price
                if (empty($this->unity)) {
                    $this->unity = ' ';
                }
                if ((float)$this->unit_price_ratio == 0) {
                    $this->unit_price_ratio = 1.0;
                }
            }
            $this->qty_step = $this->pproperties['id_pp_template'] > 0 ? $this->qtyStep() : 0;
        }
    }

    public function productProperties()
    {
        if ($this->pproperties === null) {
            $this->pproperties = \PP::getProductProperties($this);
        }
        return $this->pproperties;
    }

    public function normalizeQty($qty)
    {
        return \PP::normalizeQty($qty, \PP::productQtyPolicy($this));
    }

    public function setMinQty($min_quantity, $object = false)
    {
        if ($object === false) {
            $object = $this;
        }
        $qty_policy = \PP::productQtyPolicy($object);
        $min_quantity = \PP::normalizeQty($min_quantity, $qty_policy);
        if (\PP::qtyPolicyLegacy($qty_policy)) {
            $object->minimal_quantity = ($min_quantity > 1 ? $min_quantity : 1);
            $object->minimal_quantity_fractional = 0;
        } else {
            $object->minimal_quantity = 1;
            $object->minimal_quantity_fractional = ($min_quantity > 0 ? $min_quantity : 0);
        }
    }

    public function minQty()
    {
        return \PP::productMinQty($this->minimal_quantity, $this->minimal_quantity_fractional, $this->productProperties());
    }

    public function boMinQty($min_qty, $min_qty_fractional)
    {
        return \PP::productBoMinQty($min_qty, $min_qty_fractional, $this->productProperties());
    }

    public function attributeMinQty($id_product_attribute)
    {
        return \PP::productAttributeMinQty($id_product_attribute, $this->productProperties());
    }

    public function maxQty()
    {
        return \PP::productMaxQty(isset($this->maximum_quantity) ? $this->maximum_quantity : 0, $this->productProperties());
    }

    public function attributeMaxQty($id_product_attribute)
    {
        return \PP::productAttributeMaxQty($id_product_attribute, $this->productProperties());
    }

    public function defaultQty()
    {
        return \PP::calcDefaultQty($this->minQty(), $this->pproperties);
    }

    public function attributeDefaultQty($id_product_attribute)
    {
        return \PP::calcDefaultQty($this->attributeMinQty($id_product_attribute), $this->pproperties);
    }

    public function qtyStep()
    {
        return \PP::productQtyStep(isset($this->quantity_step) ? $this->quantity_step : 0, $this->productProperties());
    }

    public function attributeQtyStep($id_product_attribute)
    {
        return \PP::productAttributeQtyStep($id_product_attribute, $this->productProperties());
    }

    public static function convertPrice($params, &$smarty)
    {
        return \PP::smartyConvertPrice($params, $smarty);
    }

    public static function convertPriceWithCurrency($params, &$smarty)
    {
        return \PP::smartyConvertPrice($params, $smarty);
    }

    public static function displayWtPrice($params, &$smarty)
    {
        return \PP::smartyDisplayPrice($params, $smarty);
    }

    public static function displayWtPriceWithCurrency($params, &$smarty)
    {
        return \PP::smartyDisplayPrice($params, $smarty);
    }
}
