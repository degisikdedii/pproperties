<?php
/**
 * Product Properties Extension
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength, PSR1.Classes.ClassDeclaration, PSR2.Methods.MethodDeclaration, PSR2.Classes.PropertyDeclaration
class CartBase extends ObjectModel
{
    protected static $_nbProducts = [];
    protected static $_totalWeight = [];
    protected static $_attributesLists = [];

    /**
     * Return cart products.
     *
     * @param bool $refresh
     * @param bool $id_product
     * @param int $id_country
     * @param bool $fullInfos
     * @param bool $keepOrderPrices When true use the Order saved prices instead of the most recent ones from catalog (if Order exists)
     *
     * @return array Products
     */
    public function getProducts($refresh = false, $id_product = false, $id_country = null, $fullInfos = true, bool $keepOrderPrices = false)
    {
        if (!$this->id) {
            return [];
        }
        // Product cache must be strictly compared to NULL, or else an empty cart will add dozens of queries
        if ($this->_products !== null && !$refresh) {
            // Return product row with specified ID if it exists
            if (is_int($id_product)) {
                foreach ($this->_products as $product) {
                    if ($product['id_product'] == $id_product) {
                        return [$product];
                    }
                }
                return [];
            }
            return $this->_products;
        }

        // Build query
        $sql = new DbQuery();

        // Build SELECT
        $sql->select('cp.`id_product_attribute`, cp.`id_product`, cp.`quantity` AS cart_quantity, cp.id_shop, cp.`id_customization`, pl.`name`, p.`is_virtual`,
                      cp.`id_cart_product`, cp.`quantity_fractional` AS cart_quantity_fractional, cp.`pp_data_type`, cp.`pp_data`, p.id_pp_template,
                      pl.`description_short`, pl.`available_now`, pl.`available_later`, product_shop.`id_category_default`, p.`id_supplier`,
                      p.`id_manufacturer`, m.`name` AS manufacturer_name, product_shop.`on_sale`, product_shop.`ecotax`, product_shop.`additional_shipping_cost`,
                      product_shop.`available_for_order`, product_shop.`show_price`, product_shop.`price`, product_shop.`active`, product_shop.`unity`, product_shop.`unit_price_ratio`,
                      IFNULL(stock.quantity, 0) + IFNULL(stock.quantity_remainder, 0) as quantity_available, p.`unit_price_ratio`, p.`width`, p.`height`, p.`depth`, stock.`out_of_stock`, p.`weight`,
                      p.`available_date`, p.`date_add`, p.`date_upd`, IFNULL(stock.quantity, 0) + IFNULL(stock.quantity_remainder, 0) as quantity, pl.`link_rewrite`, cl.`link_rewrite` AS category,
                      cp.`id_cart_product` AS unique_id, cp.id_address_delivery,
                      product_shop.advanced_stock_management, ps.product_supplier_reference supplier_reference');

        // Build FROM
        $sql->from('cart_product', 'cp');

        // Build JOIN
        $sql->leftJoin('product', 'p', 'p.`id_product` = cp.`id_product`');
        $sql->innerJoin('product_shop', 'product_shop', '(product_shop.`id_shop` = cp.`id_shop` AND product_shop.`id_product` = p.`id_product`)');
        $sql->leftJoin(
            'product_lang',
            'pl',
            'p.`id_product` = pl.`id_product`
            AND pl.`id_lang` = ' . (int) $this->getAssociatedLanguageId() . Shop::addSqlRestrictionOnLang('pl', 'cp.id_shop')
        );

        $sql->leftJoin(
            'category_lang',
            'cl',
            'product_shop.`id_category_default` = cl.`id_category`
            AND cl.`id_lang` = ' . (int) $this->getAssociatedLanguageId() . Shop::addSqlRestrictionOnLang('cl', 'cp.id_shop')
        );

        $sql->leftJoin('product_supplier', 'ps', 'ps.`id_product` = cp.`id_product` AND ps.`id_product_attribute` = cp.`id_product_attribute` AND ps.`id_supplier` = p.`id_supplier`');
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');

        // @todo test if everything is ok, then refactorise call of this method
        $sql->join(Product::sqlStock('cp', 'cp'));

        // Build WHERE clauses
        $sql->where('cp.`id_cart` = ' . (int) $this->id);
        if ($id_product) {
            $sql->where('cp.`id_product` = ' . (int) $id_product);
        }
        $sql->where('p.`id_product` IS NOT NULL');

        // Build ORDER BY
        $sql->orderBy('cp.`id_cart_product`, cp.`date_add`, cp.`id_product`, cp.`id_product_attribute` ASC');

        if (Customization::isFeatureActive()) {
            $sql->select('cu.`id_customization`, cu.`quantity` AS customization_quantity, cu.`quantity_fractional` AS customization_quantity_fractional');
            $sql->leftJoin(
                'customization',
                'cu',
                //'p.`id_product` = cu.`id_product` AND cp.`id_product_attribute` = cu.`id_product_attribute` AND cp.`id_customization` = cu.`id_customization` AND cu.`id_cart` = ' . (int) $this->id
                'cp.`id_cart_product` = cu.`id_cart_product`'
            );
            $sql->groupBy('unique_id');
        } else {
            $sql->select('NULL AS customization_quantity, NULL AS customization_quantity_fractional, NULL AS id_customization');
        }

        if (Combination::isFeatureActive()) {
            $sql->select('
                product_attribute_shop.`price` AS price_attribute, product_attribute_shop.`ecotax` AS ecotax_attr,
                IF (IFNULL(pa.`reference`, \'\') = \'\', p.`reference`, pa.`reference`) AS reference,
                (p.`weight`+ pa.`weight`) weight_attribute,
                IF (IFNULL(pa.`ean13`, \'\') = \'\', p.`ean13`, pa.`ean13`) AS ean13,
                IF (IFNULL(pa.`isbn`, \'\') = \'\', p.`isbn`, pa.`isbn`) AS isbn,
                IF (IFNULL(pa.`upc`, \'\') = \'\', p.`upc`, pa.`upc`) AS upc,
                ' . (version_compare(_PS_VERSION_, '1.7.7', '<') ? '' : 'IF (IFNULL(pa.`mpn`, \'\') = \'\', p.`mpn`, pa.`mpn`) AS mpn,') . '
                IFNULL(product_attribute_shop.`minimal_quantity`, product_shop.`minimal_quantity`) as minimal_quantity,
                IFNULL(product_attribute_shop.`minimal_quantity_fractional`, product_shop.`minimal_quantity_fractional`) as minimal_quantity_fractional,
                IF(product_attribute_shop.wholesale_price > 0,  product_attribute_shop.wholesale_price, product_shop.`wholesale_price`) wholesale_price
            ');

            $sql->leftJoin('product_attribute', 'pa', 'pa.`id_product_attribute` = cp.`id_product_attribute`');
            $sql->leftJoin('product_attribute_shop', 'product_attribute_shop', '(product_attribute_shop.`id_shop` = cp.`id_shop` AND product_attribute_shop.`id_product_attribute` = pa.`id_product_attribute`)');
        } else {
            $sql->select(
                'p.`reference` AS reference, p.`ean13`, p.`isbn`,
                p.`upc` AS upc,
                ' . (version_compare(_PS_VERSION_, '1.7.7', '<') ? '' : 'p.`mpn` AS mpn,') . '
                product_shop.`minimal_quantity` AS minimal_quantity, product_shop.`minimal_quantity_fractional` as minimal_quantity_fractional, product_shop.`wholesale_price` wholesale_price'
            );
        }

        $sql->select('image_shop.`id_image` id_image, il.`legend`');
        $sql->leftJoin('image_shop', 'image_shop', 'image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $this->id_shop);
        $sql->leftJoin('image_lang', 'il', 'il.`id_image` = image_shop.`id_image` AND il.`id_lang` = ' . (int) $this->getAssociatedLanguageId());

        $result = Db::getInstance()->executeS($sql);

        // Reset the cache before the following return, or else an empty cart will add dozens of queries
        $products_ids = [];
        $pa_ids = [];
        if ($result) {
            foreach ($result as $key => $row) {
                $products_ids[] = $row['id_product'];
                $pa_ids[] = $row['id_product_attribute'];
                $specific_price = SpecificPrice::getSpecificPrice($row['id_product'], $this->id_shop, $this->id_currency, $id_country, $this->id_shop_group, $row['cart_quantity'], $row['id_product_attribute'], $this->id_customer, $this->id);
                if ($specific_price) {
                    $reduction_type_row = ['reduction_type' => $specific_price['reduction_type']];
                } else {
                    $reduction_type_row = ['reduction_type' => 0];
                }

                $result[$key] = array_merge($row, $reduction_type_row);
            }
        }
        // Thus you can avoid one query per product, because there will be only one query for all the products of the cart
        Product::cacheProductsFeatures($products_ids);
        Cart::cacheSomeAttributesLists($pa_ids, $this->getAssociatedLanguageId());

        if (empty($result)) {
            $this->_products = [];
            return [];
        }

        \PP::amendProducts($result);
        if ($fullInfos) {
            $cart_shop_context = Context::getContext()->cloneContext();

            $givenAwayProductsIds = [];

            if ($this->shouldSplitGiftProductsQuantity && $refresh) {
                $gifts = $this->getCartRules(CartRule::FILTER_ACTION_GIFT, false);
                if (count($gifts) > 0) {
                    foreach ($gifts as $gift) {
                        foreach ($result as $rowIndex => $row) {
                            if (!array_key_exists('is_gift', $result[$rowIndex])) {
                                $result[$rowIndex]['is_gift'] = false;
                            }

                            if ($row['id_product'] == $gift['gift_product'] && $row['id_product_attribute'] == $gift['gift_product_attribute']) {
                                $row['is_gift'] = true;
                                $result[$rowIndex] = $row;
                            }
                        }

                        $index = $gift['gift_product'] . '-' . $gift['gift_product_attribute'];
                        if (!array_key_exists($index, $givenAwayProductsIds)) {
                            $givenAwayProductsIds[$index] = 1;
                        } else {
                            ++$givenAwayProductsIds[$index];
                        }
                    }
                }
            }

            $has_reduction_without_tax = (version_compare(_PS_VERSION_, '1.7.6.0', '>='));
            $this->_products = [];
            foreach ($result as &$row) {
                if (!array_key_exists('is_gift', $row)) {
                    $row['is_gift'] = false;
                }

                $additionalRow = Product::getProductProperties((int) $this->getAssociatedLanguageId(), $row);
                $row['reduction'] = $additionalRow['reduction'];
                if ($has_reduction_without_tax) {
                    $row['reduction_without_tax'] = $additionalRow['reduction_without_tax'];
                }
                $row['price_without_reduction'] = $additionalRow['price_without_reduction'];
                $row['specific_prices'] = $additionalRow['specific_prices'];
                unset($additionalRow);

                $givenAwayQuantity = 0;
                $giftIndex = $row['id_product'] . '-' . $row['id_product_attribute'];
                if ($row['is_gift'] && array_key_exists($giftIndex, $givenAwayProductsIds)) {
                    $givenAwayQuantity = $givenAwayProductsIds[$giftIndex];
                }

                if (!$row['is_gift'] || (int) $row['cart_quantity'] === $givenAwayQuantity) {
                    $row = $this->applyProductCalculations($row, $cart_shop_context, null, $keepOrderPrices);
                } else {
                    // Separate products given away from those manually added to cart
                    $this->_products[] = $this->applyProductCalculations($row, $cart_shop_context, $givenAwayQuantity, $keepOrderPrices);
                    unset($row['is_gift']);
                    $row = $this->applyProductCalculations(
                        $row,
                        $cart_shop_context,
                        $row['cart_quantity'] - $givenAwayQuantity,
                        $keepOrderPrices
                    );
                }

                $this->_products[] = $row;
            }
        } else {
            $this->_products = $result;
        }

        return $this->_products;
    }

    /**
     * @param $row
     * @param $shopContext
     * @param $productQuantity
     * @param bool $keepOrderPrices When true use the Order saved prices instead of the most recent ones from catalog (if Order exists)
     *
     * @return mixed
     */
    protected function applyProductCalculations($row, $shopContext, $productQuantity = null, bool $keepOrderPrices = false)
    {
        if (null === $productQuantity) {
            $productQuantity = (int) $row['cart_quantity'];
        }
        $cart_quantity_fractional = (float) $row['cart_quantity_fractional'];

        if (isset($row['ecotax_attr']) && $row['ecotax_attr'] > 0) {
            $row['ecotax'] = (float) $row['ecotax_attr'];
        }

        $row['stock_quantity'] = (float) $row['quantity'];
        // for compatibility with 1.2 themes
        $row['quantity'] = $productQuantity;

        // get the customization weight impact
        $customization_weight = Customization::getCustomizationWeight($row['id_customization']);

        if (isset($row['id_product_attribute']) && (int) $row['id_product_attribute'] && isset($row['weight_attribute'])) {
            $row['weight_attribute'] += $customization_weight;
            $row['weight'] = (float) $row['weight_attribute'];
        } else {
            $row['weight'] += $customization_weight;
        }

        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
            $address_id = (int) $this->id_address_invoice;
        } else {
            $address_id = (int) $row['id_address_delivery'];
        }
        if (!Address::addressExists($address_id)) {
            $address_id = null;
        }

        if ($shopContext->shop->id != $row['id_shop']) {
            $shopContext->shop = new Shop((int) $row['id_shop']);
        }

        $id_product = (int) $row['id_product'];
        $address = Address::initialize($address_id, true);
        $id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct($id_product, $shopContext);
        $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();

        $specific_price_output = null;
        // Specify the orderId if needed so that Product::getPriceStatic returns the prices saved in OrderDetails
        $orderId = null;
        if ($keepOrderPrices) {
            $orderId = Order::getIdByCartId($this->id);
            $orderId = (int) $orderId ?: null;
        }

        if (!empty($orderId)) {
            $orderPrices = $this->getOrderPrices($row, $orderId, $productQuantity, $address_id, $shopContext, $specific_price_output);
            $row = array_merge($row, $orderPrices);
        } else {
            $cartPrices = $this->getCartPrices($row, $productQuantity, $address_id, $shopContext, $specific_price_output);
            $row = array_merge($row, $cartPrices);
        }

        switch (Configuration::get('PS_ROUND_TYPE')) {
            case Order::ROUND_TOTAL:
                $row['total'] = \PP::calcPrice($row['price_with_reduction_without_tax'], $productQuantity, $cart_quantity_fractional, $id_product, false, false);
                $row['total_wt'] = \PP::calcPrice($row['price_with_reduction'], $productQuantity, $cart_quantity_fractional, $id_product, true, false);
                // [HOOK ppropertiessmartprice #1]
                break;
            case Order::ROUND_LINE:
                $row['total'] = \PP::calcPrice($row['price_with_reduction_without_tax'], $productQuantity, $cart_quantity_fractional, $id_product, false, false);
                $row['total_wt'] = \PP::calcPrice($row['price_with_reduction'], $productQuantity, $cart_quantity_fractional, $id_product, true, false);
                // [HOOK ppropertiessmartprice #1]
                $row['total'] = Tools::ps_round($row['total'], PP::getComputingPrecision());
                $row['total_wt'] = Tools::ps_round($row['total_wt'], PP::getComputingPrecision());
                break;
            case Order::ROUND_ITEM:
            default:
                $price_tax_excluded = $row['price_with_reduction_without_tax'];
                $price_tax_included = $row['price_with_reduction'];
                // [HOOK ppropertiessmartprice #2]
                $row['total'] = \PP::calcPrice($price_tax_excluded, $productQuantity, $cart_quantity_fractional, $id_product, false, Order::ROUND_ITEM);
                $row['total_wt'] = \PP::calcPrice($price_tax_included, $productQuantity, $cart_quantity_fractional, $id_product, true, Order::ROUND_ITEM);
                break;
        }

        $row['price'] = \PP::calcPrice($row['price_with_reduction_without_tax'], 1, 0, $id_product, false, false);
        $row['price_wt'] = \PP::calcPrice($row['price_with_reduction'], 1, 0, $id_product, true, false);
        $row['description_short'] = Tools::nl2br($row['description_short']);

        // check if a image associated with the attribute exists
        if ($row['id_product_attribute']) {
            $row2 = Image::getBestImageAttribute($row['id_shop'], $this->getAssociatedLanguageId(), $row['id_product'], $row['id_product_attribute']);
            if ($row2) {
                $row = array_merge($row, $row2);
            }
        }

        $row['reduction_applies'] = ($specific_price_output && (float) $specific_price_output['reduction']);
        $row['quantity_discount_applies'] = ($specific_price_output && \PP::resolveQty($productQuantity, $row['cart_quantity_fractional']) >= (float) $specific_price_output['from_quantity']);
        $row['id_image'] = Product::defineProductImage($row, $this->getAssociatedLanguageId());
        $row['allow_oosp'] = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
        $row['features'] = Product::getFeaturesStatic($id_product);

        $productAttributeKey = $row['id_product_attribute'] . '-' . $this->getAssociatedLanguageId();
        $row = array_merge(
            $row,
            self::$_attributesLists[$productAttributeKey] ?? ['attributes' => '', 'attributes_small' => '']
        );

        return Product::getTaxesInformations($row, $shopContext);
    }

    /**
     * @param array $productRow
     * @param array|float $productQuantity
     * @param int|null $addressId
     * @param Context $shopContext
     * @param array|false|null $specificPriceOutput
     *
     * @return array
     */
    private function getCartPrices(
        array $productRow,
        $productQuantity,
        ?int $addressId,
        Context $shopContext,
        &$specificPriceOutput
    ): array {
        $cartPrices = [];
        $cartPrices['price_without_reduction'] = $this->getCartPriceFromCatalog(
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : null,
            (int) $productRow['id_customization'],
            true,
            false,
            true,
            $productQuantity,
            $addressId,
            $shopContext,
            $specificPriceOutput
        );

        $cartPrices['price_without_reduction_without_tax'] = $this->getCartPriceFromCatalog(
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : null,
            (int) $productRow['id_customization'],
            false,
            false,
            true,
            $productQuantity,
            $addressId,
            $shopContext,
            $specificPriceOutput
        );

        $cartPrices['price_with_reduction'] = $this->getCartPriceFromCatalog(
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : null,
            (int) $productRow['id_customization'],
            true,
            true,
            true,
            $productQuantity,
            $addressId,
            $shopContext,
            $specificPriceOutput
        );

        $cartPrices['price'] = $cartPrices['price_with_reduction_without_tax'] = $this->getCartPriceFromCatalog(
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : null,
            (int) $productRow['id_customization'],
            false,
            true,
            true,
            $productQuantity,
            $addressId,
            $shopContext,
            $specificPriceOutput
        );

        return $cartPrices;
    }

    /**
     * @param int $productId
     * @param int $combinationId
     * @param int $customizationId
     * @param bool $withTaxes
     * @param bool $useReduction
     * @param bool $withEcoTax
     * @param array|float $productQuantity
     * @param int|null $addressId
     * @param Context $shopContext
     * @param array|false|null $specificPriceOutput
     *
     * @return float
     */
    private function getCartPriceFromCatalog(
        int $productId,
        int $combinationId,
        int $customizationId,
        bool $withTaxes,
        bool $useReduction,
        bool $withEcoTax,
        $productQuantity,
        ?int $addressId,
        Context $shopContext,
        &$specificPriceOutput
    ): float {
        return Product::getPriceStatic(
            $productId,
            $withTaxes,
            $combinationId,
            6,
            null,
            false,
            $useReduction,
            $productQuantity,
            false,
            (int) $this->id_customer ? (int) $this->id_customer : null,
            (int) $this->id,
            $addressId,
            $specificPriceOutput,
            $withEcoTax,
            true,
            $shopContext,
            true,
            $customizationId
        );
    }

    /**
     * @param array $productRow
     * @param int $orderId
     * @param array|float $productQuantity
     * @param int|null $addressId
     * @param Context $shopContext
     * @param array|false|null $specificPriceOutput
     *
     * @return array
     */
    private function getOrderPrices(
        array $productRow,
        int $orderId,
        $productQuantity,
        ?int $addressId,
        Context $shopContext,
        &$specificPriceOutput
    ): array {
        $orderPrices = [];
        $orderPrices['price_without_reduction'] = Product::getPriceFromOrder(
            $orderId,
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : 0,
            true,
            false,
            true
        );

        $orderPrices['price_without_reduction_without_tax'] = Product::getPriceFromOrder(
            $orderId,
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : 0,
            false,
            false,
            true
        );

        $orderPrices['price_with_reduction'] = Product::getPriceFromOrder(
            $orderId,
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : 0,
            true,
            true,
            true
        );

        $orderPrices['price'] = $orderPrices['price_with_reduction_without_tax'] = Product::getPriceFromOrder(
            $orderId,
            (int) $productRow['id_product'],
            isset($productRow['id_product_attribute']) ? (int) $productRow['id_product_attribute'] : 0,
            false,
            true,
            true
        );

        // If the product price was not found in the order, use cart prices as fallback
        if (false !== array_search(null, $orderPrices)) {
            $cartPrices = $this->getCartPrices(
                $productRow,
                $productQuantity,
                $addressId,
                $shopContext,
                $specificPriceOutput
            );
            foreach ($orderPrices as $orderPrice => $value) {
                if (null === $value) {
                    $orderPrices[$orderPrice] = $cartPrices[$orderPrice];
                }
            }
        }

        return $orderPrices;
    }

    /**
     * Check if the Cart contains the given Product (Attribute).
     *
     * @param int $idProduct Product ID
     * @param int $idProductAttribute ProductAttribute ID
     * @param int $idCustomization Customization ID
     * @param int $idAddressDelivery Delivery Address ID
     *
     * @return array quantity index     : number of product in cart without counting those of pack in cart
     *               deep_quantity index: number of product in cart counting those of pack in cart
     */
    public function getProductQuantity($idProduct, $idProductAttribute = 0, $idCustomization = 0, $idAddressDelivery = 0, $quantity = 0, $id_cart_product = 0)
    {
        $defaultPackStockType = Configuration::get('PS_PACK_STOCK_TYPE');
        $packStockTypesAllowed = [
            Pack::STOCK_TYPE_PRODUCTS_ONLY,
            Pack::STOCK_TYPE_PACK_BOTH,
        ];
        $packStockTypesDefaultSupported = (int) in_array($defaultPackStockType, $packStockTypesAllowed);
        $firstUnionSql = 'SELECT ' . \PP::sqlQty('quantity', 'cp') . ' as first_level_quantity, 0 as pack_quantity
            , cp.`quantity` as cart_quantity, cp.`quantity_fractional` as cart_quantity_fractional, cp.`id_cart_product` as id_cart_product
          FROM `'._DB_PREFIX_.'cart_product` cp';
        $secondUnionSql = 'SELECT 0 as first_level_quantity, ' . \PP::sqlQty('quantity', 'cp') . ' * p.`quantity` as pack_quantity
            , 0 as cart_quantity, 0 as cart_quantity_fractional, 0 as id_cart_product
          FROM `' . _DB_PREFIX_ . 'cart_product` cp' .
            ' JOIN `' . _DB_PREFIX_ . 'pack` p ON cp.`id_product` = p.`id_product_pack`' .
            ' JOIN `' . _DB_PREFIX_ . 'product` pr ON p.`id_product_pack` = pr.`id_product`';

        if ((int) $idCustomization > 0) {
            if ((int) $id_cart_product > 0) {
                $customizationJoin = '
                    LEFT JOIN `' . _DB_PREFIX_ . 'customization` c ON (
                            c.`id_cart_product` = cp.`id_cart_product`
                        )';
            } else {
                $customizationJoin = '
                    LEFT JOIN `' . _DB_PREFIX_ . 'customization` c ON (
                        c.`id_product` = cp.`id_product`
                        AND c.`id_product_attribute` = cp.`id_product_attribute`
                    )';
            }
            $firstUnionSql .= $customizationJoin;
            $secondUnionSql .= $customizationJoin;
        }
        if ((int) $id_cart_product > 0) {
            $commonWhere = ' WHERE cp.`id_cart_product` = ' . (int) $id_cart_product;
        } else {
            $commonWhere = '
                WHERE cp.`id_product_attribute` = ' . (int) $idProductAttribute . '
                AND cp.`id_customization` = ' . (int) $idCustomization . '
                AND cp.`id_cart` = ' . (int) $this->id;

            if (Configuration::get('PS_ALLOW_MULTISHIPPING') && $this->isMultiAddressDelivery()) {
                $commonWhere .= ' AND cp.`id_address_delivery` = '. (int) $idAddressDelivery;
            }
        }
        if ((int) $idCustomization > 0) {
            $commonWhere .= ' AND c.`id_customization` = ' . (int) $idCustomization;
        }
        $firstUnionSql .=  $commonWhere;
        $firstUnionSql .= ' AND cp.`id_product` = ' . (int) $idProduct;
        if ($quantity > 0 && (int) $id_cart_product == 0) {
            $firstUnionSql .= ' AND cp.`quantity_fractional` = ' . number_format((float) $quantity, 6, '.', '');
        }
        $secondUnionSql .= $commonWhere;
        $secondUnionSql .= ' AND p.`id_product_item` = ' . (int) $idProduct;
        $secondUnionSql .= ' AND (pr.`pack_stock_type` IN (' . implode(',', $packStockTypesAllowed) . ') OR (
            pr.`pack_stock_type` = ' . Pack::STOCK_TYPE_DEFAULT . '
            AND ' . $packStockTypesDefaultSupported . ' = 1
        ))';
        $parentSql = 'SELECT
            COALESCE(cart_quantity, 0) as cart_quantity,
            COALESCE(cart_quantity_fractional, 0) as cart_quantity_fractional,
            COALESCE(id_cart_product, 0) as id_cart_product,
            COALESCE(SUM(first_level_quantity) + SUM(pack_quantity), 0) as deep_quantity,
            COALESCE(SUM(first_level_quantity), 0) as quantity
          FROM (' . $firstUnionSql . ' UNION ' . $secondUnionSql . ') as q';

        return Db::getInstance()->getRow($parentSql);
    }

    public function updateQty(
        $quantity,
        $id_product,
        $id_product_attribute = null,
        $id_customization = false,
        $operator = 'up',
        $id_address_delivery = 0,
        Shop $shop = null,
        $auto_add_cart_rule = true,
        $skipAvailabilityCheckOutOfStock = false,
        bool $preserveGiftRemoval = true,
        bool $useOrderPrices = false,
        $id_cart_product = 0,
        $ext_prop_quantities = null,
        $ext_calculated_quantity = 0,
        $force_update_qty = null
    ) {
        $quantity_param = $quantity;
        $is_bulk = !empty($quantity_param['bulk']);
        if (!$shop) {
            $shop = Context::getContext()->shop;
        }

        if (Validate::isLoadedObject(Context::getContext()->customer)) {
            if ($id_address_delivery == 0 && (int) $this->id_address_delivery) {
                // The $id_address_delivery is null, use the cart delivery address
                $id_address_delivery = $this->id_address_delivery;
            } elseif ($id_address_delivery == 0) {
                // The $id_address_delivery is null, get the default customer address
                $id_address_delivery = (int) Address::getFirstCustomerAddressId(
                    (int) Context::getContext()->customer->id
                );
            } elseif (!Customer::customerHasAddress(Context::getContext()->customer->id, $id_address_delivery)) {
                // The $id_address_delivery must be linked with customer
                $id_address_delivery = 0;
            }
        } else {
            $id_address_delivery = 0;
        }

        $id_product = (int) $id_product;
        $id_product_attribute = $is_bulk ? 0 : (int) $id_product_attribute;
        $product = new Product($id_product, false, Configuration::get('PS_LANG_DEFAULT'), $shop->id);

        if ($id_product_attribute) {
            $combination = new Combination((int) $id_product_attribute);
            if ($combination->id_product != $id_product) {
                return false;
            }
        }

        $properties = $product->productProperties();
        $quantity = $is_bulk ? $quantity_param['quantity'] : $product->normalizeQty($quantity);
        $quantity_calculator = PP::isQuantityCalculator($properties);
        $quantity_multidimensional = PP::isQuantityMultidimensional($properties);
        /* If we have a product combination, the minimal quantity is set with the one of this combination */
        if (!empty($id_product_attribute)) {
            $minimal_quantity = $product->attributeMinQty($id_product_attribute);
        } else {
            $minimal_quantity = $product->minQty();
        }

        if (!Validate::isLoadedObject($product)) {
            die(Tools::displayError());
        }

        if (isset(self::$_nbProducts[$this->id])) {
            unset(self::$_nbProducts[$this->id]);
        }

        if (isset(self::$_totalWeight[$this->id])) {
            unset(self::$_totalWeight[$this->id]);
        }

        $data = [
            'cart' => $this,
            'product' => $product,
            'id_product_attribute' => $id_product_attribute,
            'id_customization' => $id_customization,
            'id_cart_product' => $id_cart_product,
            'quantity' => $quantity,
            'operator' => $operator,
            'id_address_delivery' => $id_address_delivery,
            'shop' => $shop,
            'auto_add_cart_rule' => $auto_add_cart_rule,
        ];

        /* @deprecated deprecated since 1.6.1.1 */
        // Hook::exec('actionBeforeCartUpdateQty', $data);
        Hook::exec('actionCartUpdateQuantityBefore', $data);

        if ($quantity <= 0) {
            return $this->deleteProduct($id_product, $id_product_attribute, (int) $id_customization, (int) $id_address_delivery, $preserveGiftRemoval, $useOrderPrices, $id_cart_product);
        }

        if (!$product->available_for_order
            || (
                Configuration::isCatalogMode()
                && !defined('_PS_ADMIN_DIR_')
            )
        ) {
            return false;
        }

        $qty_policy_legacy = \PP::qtyPolicyLegacy($properties['qty_policy']);
        if (empty($force_update_qty)) {
            $ppQty = array(
                'quantity' => $qty_policy_legacy ? $quantity : 1,
                'quantity_fractional' => $qty_policy_legacy || $quantity_multidimensional ? ($properties['pp_ext'] == 1 ? (float) $ext_calculated_quantity : ($properties['pp_ext'] == 2 ? 1 : 0)) : $quantity
            );
        } else {
            $ppQty = array(
                'quantity' => $force_update_qty[0],
                'quantity_fractional' => $force_update_qty[1]
            );
        }
        $aggregate_in_cart = !$qty_policy_legacy && \PP::qtyModeAggregate($properties) && !$is_bulk && !Pack::isPack($id_product);
        if ($id_cart_product == 0 && !$aggregate_in_cart && (!$qty_policy_legacy || $quantity_multidimensional || $is_bulk)) {
            $id_cart_product = 0;
            $result = false; // simulate "add to cart"
        } else {
            /* Check if the product is already in the cart */
            $cartProductQuantity = $this->getProductQuantity(
                $id_product,
                $id_product_attribute,
                (int) $id_customization,
                (int) $id_address_delivery,
                $aggregate_in_cart || ($qty_policy_legacy && ($properties['pp_ext'] == 0) || $quantity_calculator) ? 0 : $ppQty['quantity_fractional'],
                $id_cart_product
            );
            $id_cart_product = (int) $cartProductQuantity['id_cart_product'];
            $result = ($id_cart_product != 0);
        }
        if ($result) {
            if ($operator == 'up' || $operator == 'update') {
                $sql = 'SELECT stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, IFNULL(stock.quantity_remainder, 0) as quantity_remainder
                        FROM ' . _DB_PREFIX_ . 'product p
                        ' . Product::sqlStock('p', $id_product_attribute, true, $shop) . '
                        WHERE p.id_product = ' . (int) $id_product;

                $result2 = Db::getInstance()->getRow($sql);
                $product_qty = (int) $result2['quantity'] + (float) $result2['quantity_remainder'];
                // Quantity for product pack
                if (Pack::isPack($id_product)) {
                    $product_qty = Product::getQuantity($id_product, $id_product_attribute, null, $this);
                }
                if ($operator == 'up') {
                    if ($aggregate_in_cart) {
                        $q = 0;
                        $qf = $ppQty['quantity_fractional'];
                        $field = 'quantity_fractional';
                        $field_qty = '+ '.$qf;
                    } else {
                        $q = $ppQty['quantity'];
                        $qf = 0;
                        $field = 'quantity';
                        $field_qty = '+ '.$q;
                    }
                    $new_qty = \PP::resolveQty((int) $cartProductQuantity['cart_quantity'] + $q, (float) $cartProductQuantity['cart_quantity_fractional'] + $qf);
                    $new_min_max_qty = ($quantity_multidimensional ? (int) $cartProductQuantity['cart_quantity'] + $q : $new_qty);
                    $ppQty['set'] = '`'.$field.'` = `'.$field.'` '.$field_qty;
                } else {
                    if (empty($force_update_qty)) {
                        $new_qty = ($quantity_multidimensional && $properties['pp_ext'] == 1 ? \PP::resolveQty($quantity, $cartProductQuantity['cart_quantity_fractional']) : $quantity);
                        $new_min_max_qty = ($quantity_multidimensional ? $quantity : $new_qty);
                        $field = ($qty_policy_legacy || ($quantity_multidimensional && $properties['pp_ext'] == 1) || Pack::isPack($id_product) ? 'quantity' : 'quantity_fractional');
                        $ppQty['set'] = '`' . $field . '` = ' . $quantity;
                    } else {
                        $ppQty['set'] = '`quantity` = ' . $ppQty['quantity'] . ', `quantity_fractional` = ' . $ppQty['quantity_fractional'];
                    }
                }

                if (empty($force_update_qty) && !$skipAvailabilityCheckOutOfStock && !Product::isAvailableWhenOutOfStock((int) $result2['out_of_stock'])) {
                    if ($new_qty > $product_qty) {
                        return false;
                    }
                }
            } elseif ($operator == 'down') {
                if ($aggregate_in_cart) {
                    if ($qty_policy_legacy) {
                        $q = $quantity;
                        $qf = 0;
                        $field = 'quantity';
                        $field_qty = '- '.$q;
                    } else {
                        $q = 0;
                        $qf = $quantity;
                        $field = 'quantity_fractional';
                        $field_qty = '- '.$qf;
                    }
                } else {
                    $q = ($qty_policy_legacy ? (int) $quantity : 1);
                    $qf = 0;
                    $field = 'quantity';
                    $field_qty = '- '.$q;
                }
                $new_qty = \PP::resolveQty((int) $cartProductQuantity['cart_quantity'] - $q, (float) $cartProductQuantity['cart_quantity_fractional'] - $qf);
                $new_min_max_qty = ($quantity_multidimensional ? (int) $cartProductQuantity['cart_quantity'] - $q : $new_qty);
                if ($new_min_max_qty < $minimal_quantity && ($qty_policy_legacy || $quantity_multidimensional ? $minimal_quantity > 1 : $new_qty > 0)) {
                    return -1;
                }
                $ppQty['set'] = '`'.$field.'` = `'.$field.'` '.$field_qty;
            } else {
                return false;
            }
            if (empty($force_update_qty)) {
                if (($quantity_multidimensional ? $new_min_max_qty : $new_qty) <= 0) {
                    return $this->deleteProduct((int) $id_product, (int) $id_product_attribute, (int) $id_customization, (int) $id_address_delivery, $preserveGiftRemoval, $useOrderPrices, $id_cart_product);
                } elseif ($new_min_max_qty < $minimal_quantity) {
                    return -1;
                } elseif ($properties['pp_maximum_quantity'] > 0 && $new_min_max_qty > $properties['pp_maximum_quantity']) {
                    return -2;
                }
            }
            if ($operator == 'up' || $operator == 'down') {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'cart_product`
                    SET ' . pSql($ppQty['set']) . ', `date_add` = NOW()
                    WHERE `id_cart_product` = ' . (int) $id_cart_product .
                    (Configuration::get('PS_ALLOW_MULTISHIPPING') && $this->isMultiAddressDelivery() ? ' AND `id_address_delivery` = ' . (int) $id_address_delivery : '') . '
                    LIMIT 1'
                );
            } else {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'cart_product`
                    SET ' . pSql($ppQty['set']) . ', `date_add` = NOW()
                    WHERE `id_cart_product` = ' . (int) $id_cart_product .
                    (Configuration::get('PS_ALLOW_MULTISHIPPING') && $this->isMultiAddressDelivery() ? ' AND `id_address_delivery` = ' . (int) $id_address_delivery : '') . '
                    LIMIT 1'
                );
            }
        } elseif ($operator == 'up') {
            $sql = 'SELECT stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, IFNULL(stock.quantity_remainder, 0) as quantity_remainder
                    FROM ' . _DB_PREFIX_ . 'product p
                    ' . Product::sqlStock('p', $id_product_attribute, true, $shop) . '
                    WHERE p.id_product = ' . (int) $id_product;

            $result2 = Db::getInstance()->getRow($sql);

            // Quantity for product pack
            if (Pack::isPack($id_product)) {
                $result2['quantity'] = Product::getQuantity($id_product, $id_product_attribute);
            }
            $new_qty = \PP::resolveQty($ppQty['quantity'], $ppQty['quantity_fractional']);
            if (!$skipAvailabilityCheckOutOfStock  && !Product::isAvailableWhenOutOfStock((int) $result2['out_of_stock'])) {
                if ($new_qty > $result2['quantity'] + (PP::qtyPolicyFractional($properties['pp_qty_policy']) ? (float) $result2['quantity_remainder'] : 0)) {
                    return false;
                }
            }
            $qty_to_check = ($quantity_multidimensional ? $ppQty['quantity'] : $new_qty);
            if ($qty_to_check < $minimal_quantity) {
                return -1;
            } elseif ($properties['pp_maximum_quantity'] > 0 && $qty_to_check > $properties['pp_maximum_quantity']) {
                return -2;
            }
            $ppQty['set'] = '`quantity` = ' . $ppQty['quantity'] . ', `quantity_fractional` = ' . $ppQty['quantity_fractional'];
            $result_add = Db::getInstance()->insert(
                'cart_product',
                array(
                    'id_product'           => (int) $id_product,
                    'id_product_attribute' => (int) $id_product_attribute,
                    'id_cart'              => (int) $this->id,
                    'id_address_delivery'  => (int) $id_address_delivery,
                    'id_shop'              => (int) $shop->id,
                    'quantity'             => (int) $ppQty['quantity'],
                    'quantity_fractional'  => (float) $ppQty['quantity_fractional'],
                    'pp_data_type'         => ($is_bulk ? 'bulk' : null),
                    'pp_data'              => ($is_bulk ? pSQL($quantity_param['raw']) : null),
                    'date_add'             => date('Y-m-d H:i:s'),
                    'id_customization'     => (int) $id_customization,
                ),
                true
            );

            if (!$result_add) {
                return false;
            }
            $id_cart_product = Db::getInstance()->Insert_ID();
            if (is_array($ext_prop_quantities)) {
                $db = Db::getInstance();
                foreach ($ext_prop_quantities as $index => $value) {
                    $data = [];
                    if (is_array($value)) {
                        if (!empty($value['_type']) && Tools::strpos($value['_type'], '_') !== 0) {
                            foreach ($value as $k => $v) {
                                if (Tools::strpos($k, '_') !== 0) {
                                    if ($k == 'quantity') {
                                        $data['quantity'] = (float) (isset($value['_persist_quantity']) ? $value['_persist_quantity'] : $v);
                                    } elseif (!empty($v)) {
                                        $data[$k] = pSQL($v);
                                    }
                                }
                            }
                        }
                    } else {
                        $data['quantity'] = (float) $value;
                    }
                    if ($data) {
                        $data['id_cart_product'] = (int) $id_cart_product;
                        if (!isset($data['id_ext_prop'])) {
                            $data['id_ext_prop'] = (int) $index;
                        }
                        $db->insert('pp_product_ext', $data);
                    }
                }
            }
        }
        $this->last_icp = $id_cart_product;
        // refresh cache of self::_products
        $this->_products = $this->getProducts(true);
        $this->update();
        $context = Context::getContext()->cloneContext();
        $context->cart = $this;
        Cache::clean('getContextualValue_*');
        if (version_compare(_PS_VERSION_, '1.7.7.5', '<')) {
            CartRule::autoRemoveFromCart();
        } else {
            CartRule::autoRemoveFromCart(null, $useOrderPrices);
        }
        if ($auto_add_cart_rule) {
            if (version_compare(_PS_VERSION_, '1.7.7.5', '<')) {
                CartRule::autoAddToCart($context);
            } else {
                CartRule::autoAddToCart($context, $useOrderPrices);
            }
        }

        if ($product->customizable) {
            return $this->_updateCustomizationQuantity(
                $ppQty,
                (int) $id_customization,
                (int) $id_product,
                (int) $id_product_attribute,
                (int) $id_address_delivery,
                $operator,
                $id_cart_product
            );
        }

        return true;
    }

    protected function _updateCustomizationQuantity($quantity, $id_customization, $id_product, $id_product_attribute, $id_address_delivery, $operator = 'up', $id_cart_product = 0)
    {
        // Link customization to product combination when it is first added to cart
        if (is_array($quantity)) {
            $ppQty = $quantity;
            $quantity = (int) $quantity['quantity'];
        } else {
            $ppQty = array(
                'quantity' => (int) $quantity,
                'quantity_fractional' => 0
            );
        }
        if (empty($id_customization)) {
            $customization = $this->getProductCustomization($id_product, null, true);
            foreach ($customization as $field) {
                if ($field['quantity'] == 0) {
                    Db::getInstance()->execute('
                    UPDATE `' . _DB_PREFIX_ . 'customization`
                    SET `quantity` = ' . (int) $quantity . ',
                        `quantity_fractional` = ' . (float) $ppQty['quantity_fractional'].',
                        `id_product_attribute` = ' . (int) $id_product_attribute . ',
                        `id_address_delivery` = ' . (int) $id_address_delivery . ',
                        `id_cart_product` = ' . (int) $id_cart_product.',
                        `in_cart` = 1
                    WHERE `id_customization` = ' . (int) $field['id_customization']);
                }
            }
        }

        /* Deletion */
        if (!empty($id_customization) && (int) $quantity < 1) {
            return $this->_deleteCustomization((int) $id_customization, (int) $id_product, (int) $id_product_attribute);
        }

        /* Quantity update */
        if (!empty($id_customization)) {
            $result = Db::getInstance()->getRow('SELECT `quantity` FROM `' . _DB_PREFIX_ . 'customization` WHERE `id_customization` = ' . (int) $id_customization);
            if ($result && Db::getInstance()->numRows()) {
                if ($operator == 'down' && (int) $result['quantity'] - (int) $quantity < 1) {
                    return Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'customization` WHERE `id_customization` = ' . (int) $id_customization);
                }

                return Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'customization`
                    SET ' .
                    (isset($ppQty['set']) ? pSql($ppQty['set']) : '`quantity` = `quantity` '.($operator == 'up' ? '+ ' : '- ') . (int) $quantity).',
                        `id_product_attribute` = ' . (int) $id_product_attribute.',
                        `id_address_delivery` = ' . (int) $id_address_delivery.',
                        `id_cart_product` = ' . (int) $id_cart_product.',
                        `in_cart` = 1
                    WHERE `id_customization` = ' . (int) $id_customization
                );
            } else {
                Db::getInstance()->execute('
                    UPDATE `' . _DB_PREFIX_ . 'customization`
                    SET `id_address_delivery` = ' . (int) $id_address_delivery . ',
                    `id_product_attribute` = ' . (int) $id_product_attribute . ',
                    `in_cart` = 1
                    WHERE `id_customization` = ' . (int) $id_customization);
            }
        }
        // refresh cache of self::_products
        $this->_products = $this->getProducts(true);
        $this->update();

        return true;
    }

    public function deleteProduct(
        $id_product,
        $id_product_attribute = 0,
        $id_customization = 0,
        $id_address_delivery = 0,
        bool $preserveGiftsRemoval = true,
        bool $useOrderPrices = false,
        $id_cart_product = 0
    ) {
        if (($id_cart_product = \PP::resolveIcp($id_cart_product)) <= 0) {
            if (version_compare(_PS_VERSION_, '1.7.7', '<')) {
                return $this->deleteProductCore($id_product, $id_product_attribute, $id_customization, $id_address_delivery);
            } elseif (version_compare(_PS_VERSION_, '1.7.7.5', '<')) {
                return $this->deleteProductCore($id_product, $id_product_attribute, $id_customization, $id_address_delivery, $preserveGiftsRemoval);
            } else {
                return $this->deleteProductCore($id_product, $id_product_attribute, $id_customization, $id_address_delivery, $preserveGiftsRemoval, $useOrderPrices);
            }
        }

        if (isset(self::$_nbProducts[$this->id])) {
            unset(self::$_nbProducts[$this->id]);
        }

        if (isset(self::$_totalWeight[$this->id])) {
            unset(self::$_totalWeight[$this->id]);
        }

        $sql_icp = \PP::sqlIcp($id_cart_product);
        if ((int) $id_customization) {
            if (!$this->_deleteCustomization((int) $id_customization, (int) $id_product, (int) $id_product_attribute, (int) $id_address_delivery)) {
                return false;
            }
        }

        /* Get customization quantity */
        $customizations = Db::getInstance()->executeS(
            'SELECT `id_customization`
            FROM `'._DB_PREFIX_.'customization`
            WHERE `id_cart` = ' . (int) $this->id.'
            AND (`id_cart_product` = 0 or `id_cart_product` = ' . (int) $id_cart_product.')
            AND `id_product` = ' . (int) $id_product.
            ((int) $id_product_attribute ? ' AND `id_product_attribute` = ' . (int) $id_product_attribute : '') .
            ((int) $id_address_delivery ? ' AND `id_address_delivery` = ' . (int) $id_address_delivery : '')
        );
        if ($customizations) {
            foreach ($customizations as $customization) {
                if (!$this->_deleteCustomization((int) $customization['id_customization'], (int) $id_product, (int) $id_product_attribute, (int) $id_address_delivery)) {
                    return false;
                }
            }
        }

        $preservedGifts = [];
        $giftKey = (int) $id_product . '-' . (int) $id_product_attribute;
        if ($preserveGiftsRemoval) {
            $preservedGifts = $this->getProductsGifts($id_product, $id_product_attribute);
            if (isset($preservedGifts[$giftKey]) && $preservedGifts[$giftKey] > 0) {
                return Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'cart_product`
                    SET `quantity` = ' . (int) $preservedGifts[(int) $id_product . '-' . (int) $id_product_attribute] . '
                    WHERE `id_cart` = ' . (int) $this->id . '
                    AND `id_product` = ' . (int) $id_product .
                    ((int) $id_product_attribute ? ' AND `id_product_attribute` = ' . (int) $id_product_attribute : '')
                );
            }
        }

        /* Product deletion */
        $result = Db::getInstance()->execute('
        DELETE FROM `' . _DB_PREFIX_ . 'cart_product`
        WHERE `id_product` = ' . (int) $id_product . '
        AND `id_cart` = ' . (int) $this->id . $sql_icp);

        if ($result) {
            if ($multidimensional_plugin = \PP::getMultidimensionalPlugin()) {
                $multidimensional_plugin->cartDeleteProduct($id_product, $id_product_attribute, $id_customization, $id_address_delivery, $id_cart_product);
            }
            $return = $this->update();
            // refresh cache of self::_products
            $this->_products = $this->getProducts(true);
            if (!isset($preservedGifts[$giftKey]) || $preservedGifts[$giftKey] <= 0) {
                if (version_compare(_PS_VERSION_, '1.7.7.5', '<')) {
                    CartRule::autoRemoveFromCart();
                    CartRule::autoAddToCart();
                } else {
                    CartRule::autoAddToCart(null, $useOrderPrices);
                    CartRule::autoAddToCart(null, $useOrderPrices);
                }
            }

            return $return;
        }

        return false;
    }

    /**
     * Check if product quantities in Cart are available.
     *
     * @param bool $returnProductOnFailure Return the first found product with not enough quantity
     *
     * @return bool|array If all products are in stock: true; if not: either false or an array
     *                    containing the first found product which is not in stock in the
     *                    requested amount
     */
    public function checkQuantities($returnProductOnFailure = false)
    {
        if (Configuration::isCatalogMode() && !defined('_PS_ADMIN_DIR_')) {
            return false;
        }
        $products = $this->getProducts();
        $quantities = [];
        $bulks = [];
        $hasCustomization = false;
        $hasMultidimensional = false;
        foreach ($products as $product) {
            $id_product = (int) $product['id_product'];
            $id_product_attribute = (int) $product['id_product_attribute'];
            $bulk = PP::getProductBulk($product);
            $is_bulk = !empty($bulk['bulk']);
            if ($is_bulk) {
                $bulks[] = array('product' => $product, 'bulk' => $bulk);
            }
            if (!$this->allow_seperated_package &&
                !$product['allow_oosp'] &&
                StockAvailable::dependsOnStock($id_product) &&
                $product['advanced_stock_management'] &&
                (bool) Context::getContext()->customer->isLogged() &&
                ($delivery = $this->getDeliveryOption()) &&
                !empty($delivery)
            ) {
                $product['stock_quantity'] = StockManager::getStockByCarrier(
                    $id_product,
                    $id_product_attribute,
                    $delivery
                );
            }

            if (!$product['active'] || !$product['available_for_order']) {
                return $returnProductOnFailure ? $product : false;
            }

            if (!$product['allow_oosp'] && !$is_bulk) {
                $productQuantity = Product::getQuantity(
                    $id_product,
                    $id_product_attribute,
                    null,
                    $this,
                    $product['id_customization']
                );
                if ($productQuantity < 0) {
                    return $returnProductOnFailure ? $product : false;
                }
            }
            $quantity_multidimensional = \PP::isQuantityMultidimensional($product);
            $hasCustomization |= (bool)$product['pp_customization'];
            $hasMultidimensional |= ($quantity_multidimensional);
            $items = (int) $product['cart_quantity'];
            $quantity = \PP::resolveQty($items, $product['cart_quantity_fractional']);
            $qty = $quantity_multidimensional ? $items : $quantity;
            if ($product['pp_maximum_quantity'] > 0 && $qty > $product['pp_maximum_quantity']) {
                if ($returnProductOnFailure) {
                    $product['ppCheckQuantitiesMessage'] = \PSM::translate(
                        'cannot_order_more_than',
                        array(
                            '%quantity%' => \PP::formatQty($product['pp_maximum_quantity']),
                            '%text%' => \PSM::nvl($product['pp_qty_text'], \PSM::plural($product['pp_maximum_quantity'])),
                            '%name%' => $product['name']
                        )
                    );
                    return $product;
                }
                return false;
            }
            if (!isset($quantities[$id_product])) {
                $quantities[$id_product] = array('items' => 0, 'quantity' => 0);
            }
            $quantities[$id_product]['items'] += $items;
            $quantities[$id_product]['quantity'] += $quantity;
        }
        if (!empty($bulks)) {
            $bulk_quantities = [];
            foreach ($bulks as $entity) {
                $product = $entity['product'];
                if (!$product['allow_oosp']) {
                    $bulk = $entity['bulk'];
                    $id_product = $bulk['id_product'];
                    if (!isset($bulk_quantities[$id_product])) {
                        $bulk_quantities[$id_product] = [];
                    }
                    foreach ($bulk['bulk'] as $id_product_attribute => $data) {
                        if (!isset($bulk_quantities[$id_product][$id_product_attribute])) {
                            $bulk_quantities[$id_product][$id_product_attribute] = 0;
                        }
                        $bulk_quantities[$id_product][$id_product_attribute] += $data['quantity'];
                    }
                }
            }
            foreach ($bulk_quantities as $id_product => $attributes) {
                foreach ($attributes as $id_product_attribute => $quantity) {
                    $availableQuantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
                    $cartProduct = $this->getProductQuantity($id_product, $id_product_attribute, $product['id_customization']);
                    if (!empty($cartProduct['deep_quantity'])) {
                        $availableQuantity -= $cartProduct['deep_quantity'];
                    }
                    if ($availableQuantity < $quantity) {
                        return $returnProductOnFailure ? $product : false;
                    }
                }
            }
        }
        foreach ($products as $product) {
            if ($product['pp_total_maximum_quantity'] > 0) {
                $id_product = (int) $product['id_product'];
                $qty = ($quantity_multidimensional ? $quantities[$id_product]['items'] : $quantities[$id_product]['quantity']);
                if ($qty > $product['pp_total_maximum_quantity']) {
                    if ($returnProductOnFailure) {
                        $product['ppCheckQuantitiesMessage'] = \PSM::translate(
                            'cannot_order_more_than_in_total',
                            array(
                                '%quantity%' => \PP::formatQty($product['pp_total_maximum_quantity']),
                                '%text%' => \PSM::nvl($product['pp_qty_text'], \PSM::plural($product['pp_total_maximum_quantity'])),
                                '%name%' => $product['name']
                            )
                        );
                        return $product;
                    }
                    return false;
                }
            }
        }
        if ($hasMultidimensional && ($multidimensional_plugin = \PP::getMultidimensionalPlugin())) {
            $res = $multidimensional_plugin->cartCheckQuantities($products, $quantities);
            if (!empty($res)) {
                if ($returnProductOnFailure) {
                    $product['ppCheckQuantitiesMessage'] = $res;
                    return $product;
                }
                return false;
            }
        }
        if ($hasCustomization) {
            $hook_params = array(
                'hook' => 'cartCheckQuantities',
                'products' => $products,
                'quantities' => $quantities,
            );
            $res = Hook::exec('ppropertiesCustom', $hook_params, Module::getModuleIdByName('ppropertiescustom'), true);
            if (isset($res['ppropertiescustom']) && is_string($res['ppropertiescustom'])) {
                if ($returnProductOnFailure) {
                    $product['ppCheckQuantitiesMessage'] = $res['ppropertiescustom'];
                    return $product;
                }
                return false;
            }
        }
        return true;
    }

    public function duplicate()
    {
        if (!Validate::isLoadedObject($this)) {
            return false;
        }

        $cart = new Cart($this->id);
        $cart->id = null;
        $cart->id_shop = $this->id_shop;
        $cart->id_shop_group = $this->id_shop_group;

        if (!Customer::customerHasAddress((int) $cart->id_customer, (int) $cart->id_address_delivery)) {
            $cart->id_address_delivery = (int) Address::getFirstCustomerAddressId((int) $cart->id_customer);
        }

        if (!Customer::customerHasAddress((int) $cart->id_customer, (int) $cart->id_address_invoice)) {
            $cart->id_address_invoice = (int) Address::getFirstCustomerAddressId((int) $cart->id_customer);
        }

        if ($cart->id_customer) {
            $cart->secure_key = Cart::$_customer->secure_key;
        }

        $cart->add();

        if (!Validate::isLoadedObject($cart)) {
            return false;
        }

        $success = true;
        $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'cart_product` WHERE `id_cart` = ' . (int) $this->id);
        $smartprice_plugin = PP::getSmartpricePlugin();
        foreach ($products as $product) {
            if (PP::isMultidimensional(PP::getProductPropertiesByTemplateId(PP::getProductTemplateId($product))) || ($smartprice_plugin && $smartprice_plugin->productUsesPluginFeatures($product))) {
                return false;
            }
        }

        $orderId = Order::getIdByCartId((int) $this->id);
        $product_gift = [];
        if ($orderId) {
            $product_gift = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT cr.`gift_product`, cr.`gift_product_attribute` FROM `' . _DB_PREFIX_ . 'cart_rule` cr LEFT JOIN `' . _DB_PREFIX_ . 'order_cart_rule` ocr ON (ocr.`id_order` = ' . (int) $orderId . ') WHERE ocr.`deleted` = 0 AND ocr.`id_cart_rule` = cr.`id_cart_rule`');
        }

        $id_address_delivery = Configuration::get('PS_ALLOW_MULTISHIPPING') ? $cart->id_address_delivery : 0;

        // Customized products: duplicate customizations before products so that we get new id_customizations
        $customs = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT *
            FROM ' . _DB_PREFIX_ . 'customization c
            LEFT JOIN ' . _DB_PREFIX_ . 'customized_data cd ON cd.id_customization = c.id_customization
            WHERE c.id_cart = ' . (int) $this->id
        );

        // Get datas from customization table
        $customs_by_id = [];
        foreach ($customs as $custom) {
            if (!isset($customs_by_id[$custom['id_customization']])) {
                $customs_by_id[$custom['id_customization']] = [
                    'id_product_attribute' => $custom['id_product_attribute'],
                    'id_product' => $custom['id_product'],
                    'quantity' => $custom['quantity'],
                    'quantity_fractional' => $custom['quantity_fractional']
                ];
            }
        }

        // Backward compatibility: if true set customizations quantity to 0, they will be updated in Cart::_updateCustomizationQuantity
        $new_customization_method = (int) Db::getInstance()->getValue(
            'SELECT COUNT(`id_customization`) FROM `' . _DB_PREFIX_ . 'cart_product`
            WHERE `id_cart` = ' . (int) $this->id .
                ' AND `id_customization` != 0'
        ) > 0;

        // Insert new customizations
        $custom_ids = [];
        foreach ($customs_by_id as $customization_id => $val) {
            if ($new_customization_method) {
                $val['quantity'] = 0;
            }
            // TODO: update id_cart_product
            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'customization` (id_cart, id_product_attribute, id_product, `id_address_delivery`, quantity, quantity_fractional, `quantity_refunded`, `quantity_returned`, `in_cart`)
                VALUES(' . (int) $cart->id . ', ' . (int) $val['id_product_attribute'] . ', ' . (int) $val['id_product'] . ', ' . (int) $id_address_delivery . ', ' . (int) $val['quantity'] . ', ' . (float) $val['quantity_fractional'] . ', 0, 0, 1)'
            );
            $custom_ids[$customization_id] = Db::getInstance(_PS_USE_SQL_SLAVE_)->Insert_ID();
        }

        // Insert customized_data
        if (count($customs)) {
            $first = true;
            $sql_custom_data = 'INSERT INTO ' . _DB_PREFIX_ . 'customized_data (`id_customization`, `type`, `index`, `value`, `id_module`, `price`, `weight`) VALUES ';
            foreach ($customs as $custom) {
                if (!$first) {
                    $sql_custom_data .= ',';
                } else {
                    $first = false;
                }

                $customized_value = $custom['value'];

                if ((int) $custom['type'] == Product::CUSTOMIZE_FILE) {
                    $customized_value = md5(uniqid(mt_rand(0, mt_getrandmax()), true));
                    Tools::copy(_PS_UPLOAD_DIR_ . $custom['value'], _PS_UPLOAD_DIR_ . $customized_value);
                    Tools::copy(_PS_UPLOAD_DIR_ . $custom['value'] . '_small', _PS_UPLOAD_DIR_ . $customized_value . '_small');
                }

                $sql_custom_data .= '(' . (int) $custom_ids[$custom['id_customization']] . ', ' . (int) $custom['type'] . ', ' .
                    (int) $custom['index'] . ', \'' . pSQL($customized_value) . '\', ' .
                    (int) $custom['id_module'] . ', ' . (float) $custom['price'] . ', ' . (float) $custom['weight'] . ')';
            }
            Db::getInstance()->execute($sql_custom_data);
        }

        foreach ($products as $product) {
            if ($id_address_delivery) {
                if (Customer::customerHasAddress((int) $cart->id_customer, $product['id_address_delivery'])) {
                    $id_address_delivery = $product['id_address_delivery'];
                }
            }

            foreach ($product_gift as $gift) {
                if (isset($gift['gift_product'], $gift['gift_product_attribute']) && (int) $gift['gift_product'] == (int) $product['id_product'] && (int) $gift['gift_product_attribute'] == (int) $product['id_product_attribute']) {
                    $product['quantity'] = (int) $product['quantity'] - 1;
                }
            }

            $id_customization = (int) $product['id_customization'];

            $success &= $cart->updateQty(
                \PP::resolveQty($product['quantity'], $product['quantity_fractional']),
                (int) $product['id_product'],
                (int) $product['id_product_attribute'],
                isset($custom_ids[$id_customization]) ? (int) $custom_ids[$id_customization] : 0,
                'up',
                (int) $id_address_delivery,
                new Shop((int) $cart->id_shop),
                false,
                false
            );
        }

        return ['cart' => $cart, 'success' => $success];
    }

    public function getAssociatedLanguageId(): int
    {
        static $supported = null;
        if ($supported === null) {
            $supported = (version_compare(_PS_VERSION_, '1.7.7.3', '>='));
        }
        if ($supported) {
            return parent::getAssociatedLanguage()->getId();
        }

        $language = new Language($this->id_lang);
        if (null === $language->id) {
            $language = new Language(Configuration::get('PS_LANG_DEFAULT'));
        }
        return $language->id;
    }
}
