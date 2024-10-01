{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

<div class="panel integration">
    <div class="panel-heading"><i class="icon-AdminParentPreferences"></i> {l s='Integration' mod='pproperties'}</div>
    {if $version_mismatch_notes}
        {foreach from=$version_mismatch_notes key=title item=value}
            {if $value|is_array}
                <div class="alert alert-warning">
                <br/><div><b>{$title|escape:'htmlall':'UTF-8'}</b></div><br/>
                {foreach from=$value item=val}
                    <div>{$val|pp_safeoutput}</div>
                {/foreach}
                </div>
            {else}
                <div>{$value|pp_safeoutput}</div>
            {/if}
    {/foreach}
    {else}
        {if isset($integration.confirmation)}{$integration.confirmation|pp_safeoutput}{/if}
        {if isset($integration.display) && $integration.display}
            {foreach from=$integration.display key=title item=value}
                {if $value|is_array}
                    <br/><div><b>{$title|escape:'htmlall':'UTF-8'}</b></div>
                    {foreach from=$value item=val}
                        <div>{$val|pp_safeoutput}</div>
                    {/foreach}
                {else}
                    <div>{$value|pp_safeoutput}</div>
                {/if}
            {/foreach}
            {if isset($integration.hasDesc) && $integration.hasDesc}
                <hr/>
                <div class="alert alert-warning">
                    {$integration_instructions_link="<a href='{$integration._path|pp_safeoutput:href}integration_instructions.pdf?{$smarty.now|date_format:"U"}' target='_blank'>"}
                    <div><strong>{l s='If you see "%s" message, please, run setup by clicking on the "%s" button.' sprintf=[{'integration test failed'|psmtrans}, {'Run Setup'|psmtrans}] mod='pproperties'}</strong></div><br/>
                    <div>{l s='Read the "%s" document for more information or contact customer support to receive more assistance.' sprintf=$integration_instructions mod='pproperties'}</div>
                </div>
            {/if}
            <div class="tab-hr">&nbsp</div>
        {/if}
        <div class="tab-buttons">
            <a class="btn btn-default pp-action-btn runSetup{if not $access_edit} disabled{/if}"{if $access_edit} href="{$currenturl|pp_safeoutput:href}{$integration.btn_action|pp_safeoutput:href}"{/if}>{$integration.btn_title|escape:'htmlall':'UTF-8'}</a>
        </div>
    {/if}
</div>
