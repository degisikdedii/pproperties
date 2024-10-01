{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

{* <span>pproperties: {$hook_name}[{$hook_type}][{$hook_origin}] *}
{strip}
{if $hook_name == "displayAdminProductPproperties"}
  {if isset($product)}
    {if in_array($hook_type, ["orders/_product_line", "pdf", "orderCreateProductFoundProduct"])}
      {hook h="displayAdminProductPpropertiesMultidimensional" product=$product type=$hook_type}
      {hook h="displayAdminProductPpropertiesSmartprice" product=$product type=$hook_type}
    {/if}
  {/if}
{/if}
{/strip}
