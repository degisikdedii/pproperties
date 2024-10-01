{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}
{* <span>pproperties: {$hook_name}[{$hook_type}][{$hook_origin}]</span> *}
{if $hook_name == "displayProductPriceBlock"}
  {if $hook_type == "weight"}
    {if isset($product.price_to_display)}
      <span style="display:none" data-price_to_display="{$product.price_to_display|pp_safeoutput:htmlspecialchars nofilter}"></span>{"pp.amendPriceToDisplay();"|pp_jscript_inline:true nofilter}
    {/if}
    {if $hook_origin == "product_sheet" && isset($product.pp_settings.total)}
      {* {dump($product.pp_settings)} *}
      {if $product.pp_price_display_mode == 0}
        <div class="pp_price pp_price_product_price product-price h5{if !$product.has_discount} has-discount{/if}">
          {if $product.pp_settings.total_amount_to_display}{$product.pp_settings.total_amount_to_display|pp_safeoutput nofilter}{else}&nbsp;{/if}
        </div>
        <div class="pp_price pp_price_product_price_tax_excl product-price h5 psm-hidden{if !$product.has_discount} has-discount{/if}">
          {if $product.pp_settings.total_amount_to_display_tax_excl}{$product.pp_settings.total_amount_to_display_tax_excl|pp_safeoutput nofilter}{else}&nbsp;{/if}
        </div>
        <div class="pp_price pp_price_product_price_tax_incl product-price h5 psm-hidden{if !$product.has_discount} has-discount{/if}">
          {if $product.pp_settings.total_amount_to_display_wt}{$product.pp_settings.total_amount_to_display_wt|pp_safeoutput nofilter}{else}&nbsp;{/if}
        </div>
      {/if}
      {hook h="displayProductPpropertiesSmartprice" product=$product type="add-to-cart"}
    {/if}
  {/if}
{/if}
{if $hook_name == "displayProductPproperties"}
  {if isset($product)}
    {if $hook_type == "add-to-cart"}
      <input type="hidden" name="pp_refresh_id" value="1">
      {if isset($product.pp_settings)}
        <div style="display:none;" data-pp-settings="{$product.pp_settings|json_encode|pp_safeoutput:htmlspecialchars nofilter}"></div>
        {"pp.amendProductDisplay();"|pp_jscript_inline:true nofilter}
      {/if}
    {/if}
    {if $product.show_price}
      {if $hook_type == "add-to-cart"}
        {hook h="displayProductPpropertiesPlugin" product=$product type=$hook_type}
        {hook h="displayProductPpropertiesMultidimensional" product=$product type=$hook_type}
      {elseif $hook_type == "explanation"}
        <div id="pp_explanation_wrapper">
          {if $product.pp_explanation}
            <div id="pp_explanation" class="pp_explanation clearfix">{$product.pp_explanation|pp_safeoutput nofilter}</div>
          {/if}
        </div>
        <div class="pp-add-to-cart-error clearfix"></div>
      {/if}
    {/if}
    {if in_array($hook_type, [
        "_partials/product",
        "modules/ps_shoppingcart/modal",
        "product-name-addional-info",
        "_partials/order-confirmation-table",
        "PaymentModule::validateOrder",
        "ps_emailalerts::hookActionValidateOrder"
      ])}
      {if $hook_type == "_partials/product" && !empty($product.pp_explanation)}
        <div id="pp_explanation_wrapper">
          {if $product.pp_explanation}
            <div id="pp_explanation" class="pp_explanation clearfix">{$product.pp_explanation|pp_safeoutput nofilter}</div>
          {/if}
        </div>
      {/if}
      {hook h="displayProductPpropertiesPlugin" product=$product type=$hook_type}
      {hook h="displayProductPpropertiesMultidimensional" product=$product type=$hook_type}
      {hook h="displayProductPpropertiesSmartprice" product=$product type=$hook_type}
    {/if}
    {if isset($product.pp_customization) and $product.pp_customization}{hook h="ppropertiesCustom" product=$product hook="displayProduct" type=$hook_type}{/if}
  {/if}
{/if}
