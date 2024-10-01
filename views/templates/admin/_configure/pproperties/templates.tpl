{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

<div class="panel templates">
    {if $integrated}
        <div class="panel-heading">{'templates'|psmtrans|ucfirst}</div>
        <a class="btn btn-link add-new" href="{$currenturl|pp_safeoutput:href}clickEditTemplate&amp;mode=add"><i class="icon-plus-sign"></i>{'Add new'|psmtrans:'Admin.Actions'}</a>
        <div style="height: 10px;">&nbsp;</div>
        <table class="table pp-admin-table templates">
            <thead>
                <th class="center">{'ID'|psmtrans:'Admin.Global'}</th>
                <th>{'name'|psmtrans}</th>
                <th>{'s_pp_qty_policy'|psmtrans}</th>
                <th>{'s_pp_qty_mode'|psmtrans} *</th>
                <th>{'s_pp_display_mode'|psmtrans} **</th>
                <th>{'explanation'|psmtrans} ***</th>
                <th>{'s_pp_qty_text'|psmtrans}</th>
                <th>{'s_pp_price_text'|psmtrans}</th>
                <th>{'s_pp_unity_text'|psmtrans}</th>
                <th>{'s_pp_unit_price_ratio'|psmtrans}</th>
                <th>{'s_pp_minimum_quantity'|psmtrans}</th>
                <th>{'s_pp_default_quantity'|psmtrans}</th>
                <th class="center" style="width:80px;" colspan="2">{'Actions'|psmtrans:'Admin.Global'}</th>
            </thead>
            {foreach from=$templates item=template}
                {assign var=action value="{$currenturl}id={$template.id_pp_template}&amp;"}
                {assign var=editTemplate value="{$action}clickEditTemplate&amp;mode="}
                {assign var=onclick value="onclick=\"document.location='{$editTemplate}edit'\""}
                <tbody>
                    <tr>
                        <td class="pointer center" rowspan="2" {$onclick|pp_safeoutput}>{$template.id_pp_template}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{$template.name|pp_safeoutput_lenient}</td>
                        <td class="p nowrap center" {$onclick|pp_safeoutput}>{if $template.pp_qty_policy == 1}{l s='whole' mod='pproperties'}{elseif $template.pp_qty_policy == 2}{l s='fract' mod='pproperties'}{else}{'items'|psmtrans}{/if}</td>
                        <td class="p nowrap center" {$onclick|pp_safeoutput}>{if $template.qty_mode}{$template.qty_mode|pp_safeoutput_lenient}{else}&nbsp;{/if}</td>
                        <td class="p nowrap center" {$onclick|pp_safeoutput}>{if $template.display_mode}{$template.display_mode|pp_safeoutput_lenient}{else}&nbsp;{/if}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{if $template.pp_explanation|pp_safeoutput_lenient}{{'Yes'|psmtrans:'Admin.Global'}|lcfirst} ({$template.pp_bo_buy_block_index}){else}&nbsp;{/if}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{$template.pp_qty_text|pp_safeoutput_lenient}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{$template.pp_price_text|pp_safeoutput_lenient}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{$template.pp_unity_text|pp_safeoutput_lenient}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{if (float)$template.pp_unit_price_ratio == 0}&nbsp;{else}{(float)$template.pp_unit_price_ratio|pp_safeoutput_lenient}{/if}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{$template.pp_minimum_quantity|formatQty}{if $template.pp_ext != 1} {$template.pp_bo_qty_text|pp_safeoutput_lenient}{/if}</td>
                        <td class="p nowrap" {$onclick|pp_safeoutput}>{$template.pp_default_quantity|formatQty}{if $template.pp_ext != 1} {$template.pp_bo_qty_text|pp_safeoutput_lenient}{/if}</td>
                        <td class="text-right" rowspan="2">
                            <a href="{$currenturl|pp_safeoutput:href}clickHiddenStatusTemplate&amp;show={(int)$template.pp_bo_hidden}&amp;id={$template.id_pp_template|pp_safeoutput_lenient}" title="{if (int)$template.pp_bo_hidden}{'hidden'|psmtrans}{else}{'visible'|psmtrans}{/if}" class="list-action-enable action-{if (int)$template.pp_bo_hidden}disabled{else}enabled{/if}"><i class="icon-check{if (int)$template.pp_bo_hidden} hidden{/if}"></i><i class="icon-remove{if !(int)$template.pp_bo_hidden} hidden{/if}"></i></a>
                        </td>
                        <td class="text-right" rowspan="2">
                            <div class="btn-group-action">
                                <div class="btn-group pull-right">
                                    <a href="{$editTemplate|pp_safeoutput:href}edit" class="btn btn-default"><i class="icon-pencil"></i> {'Edit'|psmtrans:'Admin.Actions'}</a>
                                    <button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                        <span class="caret"></span>&nbsp;
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a {if $access_edit}href="{$editTemplate|pp_safeoutput:href}copy"{/if}>
                                                <i class="icon-copy"></i> {'Copy'|psmtrans:'Admin.Actions'}
                                            </a>
                                        </li>
                                        <li>
                                            <a {if $access_edit}href="{$action|pp_safeoutput:href}clickDeleteTemplate"{/if}
                                                onclick="return confirm('{l s='Do you really want to delete template #%s: %s?' sprintf=[$template.id_pp_template, $template.name] mod='pproperties'}');">
                                                <i class="icon-trash"></i> {'Delete'|psmtrans:'Admin.Actions'}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="pointer nowrap description" colspan="11" {$onclick|pp_safeoutput}>{$template.description}</td>
                    </tr>
                </tbody>
            {/foreach}
        </table>
        <table class="table pp-admin-table with-asterisk qty_mode_table">
            <caption>* {'s_pp_qty_mode'|psmtrans}</caption>
            <thead><th>#</th><th>{'description'|psmtrans|ucfirst}</th></thead>
            {foreach from=$qty_mode_text key=key item=text}
                <tr><td>{$key|pp_safeoutput_lenient + 1}</td><td>{$text|pp_safeoutput_lenient}</td></tr>
            {/foreach}
        </table>
        <table class="table pp-admin-table with-asterisk display_mode_table">
            <caption>** {'s_pp_display_mode'|psmtrans}</caption>
            <thead><th>#</th><th>{'description'|psmtrans|ucfirst}</th></thead>
            {foreach from=$display_mode_text key=key item=text}
                <tr><td>{$key|pp_safeoutput_lenient + 1}</td><td>{$text|pp_safeoutput_lenient}</td></tr>
            {/foreach}
        </table>
        <table class="table pp-admin-table with-asterisk buy_block_table">
            <caption>*** {l s='explanation as appears in shop (if set to yes)' mod='pproperties'}</caption>
            <thead><th>{'ID'|psmtrans:'Admin.Global'}</th><th>{'text'|psmtrans|ucfirst}</th></thead>
            <tbody>
                {foreach from=$buy_block_text key=key item=text}
                    <tr><td>{$key|pp_safeoutput_lenient}</td><td>{$text|pp_safeoutput_lenient}</td></tr>
                {/foreach}
            </tbody>
        </table>
        <div class="tab-hr">&nbsp</div>
        <p style="margin-top:10px;">{l s='"Restore Defaults" button restores templates to the factory settings known at the installation time. User created templates are not affected.' mod='pproperties'}</p>
        <div class="tab-buttons">
            <a class="btn btn-default{if not $access_edit} disabled{/if}" href="{$currenturl|pp_safeoutput:href}submitRestoreDefaults" onclick="return confirm('{l s='Restore Defaults' mod='pproperties'}');"><i class="icon-star"></i> {l s='Restore Defaults' mod='pproperties'}</a>
        </div>
    {else}
        <div class="alert alert-warning">{$integration_message|pp_safeoutput_lenient}</div>
    {/if}
</div>
