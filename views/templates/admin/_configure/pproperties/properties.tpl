{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

<div class="panel properties">
    {if $integrated}
        {foreach from=$types key=key item=type name=properties}
            {if $type|is_array}
                {if $key == 'attributes'}
                    {assign var=title value={'attributes'|psmtrans}}
                    {assign var=name value={'attribute'|psmtrans}}
                {elseif $key == 'texts'}
                    {assign var=title value={'texts'|psmtrans}}
                    {assign var=name value={'text'|psmtrans}}
                {elseif $key == 'dimensions'}
                    {assign var=title value={'dimensions'|psmtrans}}
                    {assign var=name value={'dimension'|psmtrans}}
                {/if}
                {if !$smarty.foreach.properties.first}
                    <div style="margin-top: 15px;">&nbsp;</div>
                {/if}
                <div class="panel-heading">{$title|pp_safeoutput|ucfirst}</div>
                <a class="btn btn-link add-new" href="{$currenturl|pp_safeoutput:href}clickEditProperty&amp;mode=add&amp;type={$type.id|pp_safeoutput}"><i class="icon-plus-sign"></i>{'Add new'|psmtrans:'Admin.Actions'}</a>
                <div style="height: 10px;">&nbsp;</div>
                <table class="table pp-admin-table">
                    <thead>
                        <th class="center" style="width:50px;">{'ID'|psmtrans:'Admin.Global'}</th>
                        {if $type.metric}
                        <th><b>{$name|pp_safeoutput|ucfirst}</b> <span style="font-weight:normal;font-style:italic">{'metric'|psmtrans}</span></th>
                        {/if}
                        {if $type.nonmetric}
                        <th><b>{$name|pp_safeoutput|ucfirst}</b> <span style="font-weight:normal;font-style:italic">{'non metric (imperial/US)'|psmtrans}</span></th>
                        {/if}
                        <th class="center" style="width:80px;">{'Actions'|psmtrans:'Admin.Global'}</th>
                    </thead>
                    <tbody>
                        {foreach from=$properties key=id item=prop}
                            {if $property_types.$id == $type.id}
                                <tr>
                                    <td class="center">{$id|pp_safeoutput}</td>
                                    {if $type.metric}
                                    <td>{$prop.text_1|pp_safeoutput_lenient}</td>
                                    {/if}
                                    {if $type.nonmetric}
                                    <td>{$prop.text_2|pp_safeoutput_lenient}</td>
                                    {/if}
                                    <td>
                                        <div class="btn-group-action">
                                            <div class="btn-group pull-right">
                                                <a href="{$currenturl|pp_safeoutput:href}clickEditProperty&amp;mode=edit&amp;type={$type.id|pp_safeoutput}&amp;id={$id|pp_safeoutput}" class="btn btn-default"><i class="icon-pencil"></i> {'Edit'|psmtrans:'Admin.Actions'}</a>
                                                <button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                                    <span class="caret"></span>&nbsp;
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a {if $access_edit}href="{$currenturl|pp_safeoutput:href}clickDeleteProperty&amp;mode=edit&amp;type={$type.id|pp_safeoutput}&amp;id={$id|pp_safeoutput}"{/if} onclick="return confirm('{l s='Do you really want to delete property #%s?' sprintf=$id mod='pproperties'}');">
                                                            <i class="icon-trash"></i> {'Delete'|psmtrans:'Admin.Actions'}</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            {/if}
                        {/foreach}
                    </tbody>
                </table>
            {/if}
        {/foreach}
    {else}
        <div class="alert alert-warning">{$integration_message|pp_safeoutput_lenient}</div>
    {/if}
</div>
