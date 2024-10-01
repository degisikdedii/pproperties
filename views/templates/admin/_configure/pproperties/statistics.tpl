{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

<div class="panel statistics">
    {if $integrated}
        <div class="panel-heading">{'statistics'|psmtrans|ucfirst}</div>
        {if isset($existing) || isset($missing)}
            {if isset($existing)}
                <table class="table pp-admin-table statistics" style="margin-bottom:15px;" cellspacing="0" cellpadding="0">
                    <thead>
                        <th class="center" style="width:35px;">{'ID'|psmtrans:'Admin.Global'}</th>
                        <th class="nowrap" style="width:140px;">{'template'|psmtrans}</th>
                        <th class="nowrap center" style="width:70px;">{l s='# of products' mod='pproperties'}</th>
                        <th style="width:60%;">{l s='product IDs' mod='pproperties'}</th>
                    </thead>
                    <tbody>
                        {foreach from=$existing item=row}
                            <tr>
                                <td class="center"><a href="{$currenturl|pp_safeoutput:href}clickEditTemplate&amp;mode=edit&amp;id={$row.id|escape:'htmlall':'UTF-8'}" title='{"Edit template"|psmtrans} "{$row.name|pp_safeoutput:title}"'>{$row.id|escape:'htmlall':'UTF-8'}</a></td>
                                <td class="nowrap"><a href="{$currenturl|pp_safeoutput:href}clickEditTemplate&amp;mode=edit&amp;id={$row.id|escape:'htmlall':'UTF-8'}" title='{"Edit template"|psmtrans} "{$row.name|pp_safeoutput:title}"'} mod='pproperties'}">{$row.name|escape:'htmlall':'UTF-8'}</a></td>
                                {if $row.count > 0}
                                    <td class="center">{$row.count|escape:'htmlall':'UTF-8'}</td>
                                    <td>
                                    {foreach from=$row.products item=product name=products}
                                    <a href="{$product.href|pp_safeoutput:href}" target="_blank" title='{"Edit"|psmtrans:"Admin.Global"} "{$product.name|pp_safeoutput:title}"'>{$product.id_product|escape:'htmlall':'UTF-8'}</a>{if !$smarty.foreach.products.last},{/if}
                                    {/foreach}
                                    </td>
                                {else}
                                    <td> </td>
                                    <td> </td>
                                {/if}
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            {/if}
            {if isset($missing)}
                <table class="table pp-admin-table statistics" style="margin-bottom:15px;" cellspacing="0" cellpadding="0">
                    <caption>{l s='Products using non-existing templates' mod='pproperties'}</caption>
                        <thead>
                            <th>{l s='product ID (template ID)' mod='pproperties'}</th>
                        </thead>
                        <tbody>
                            <tr><td>
                            {foreach from=$missing item=product name=products}
                                <a href="{$product.href|pp_safeoutput:href} target="_blank" title="{$product.name|escape:'htmlall':'UTF-8'}">{$product.id_product|escape:'htmlall':'UTF-8'}</a>{if !$smarty.foreach.products.last},{/if}
                            {/foreach}
                            </td></tr>
                        </tbody>
                </table>
            {/if}
            <div class="tab-hr">&nbsp</div>
        {/if}
        <div class="tab-buttons">
            <a class="btn btn-default pp-action-btn" href="{$currenturl|pp_safeoutput:href}submitStatistics">{'Run analysis'|psmtrans}</a>
        </div>
    {else}
        <div class="alert alert-warning">{$integration_message|pp_safeoutput_lenient}</div>
    {/if}
</div>
