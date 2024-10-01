{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

<div id="pproperties-configure-context">
{hook h="adminPproperties" mode="displayPPropertiesHeader"}
<div class="pp-configure-head">
    <div class="version"><span>{'version'|psmtrans}: {$version|escape:'htmlall':'UTF-8'} [{$ppe_id|escape:'htmlall':'UTF-8'}]</span></div>
    <div id="pp_info_block" style="display: none;" class="ui-corner-all">
        <button class="close pp_info_close" type="button">×</button>
        <div class="clearfix"></div>
        <div class="pp_info_content"></div>
        <div class="clearfix"></div>
        <div class="pp_info_hide"><input id="pp_info_ignore" type="checkbox"/><label for="pp_info_ignore">{$s_pp_info_ignore|escape:'htmlall':'UTF-8'}</label></div>
    </div>
    <div class="clearfix"></div>
</div>
{$html|pp_safeoutput}
<div class="panel">
    <h3><a class="user-guide" href="{$_path|pp_safeoutput:href}readme_en.pdf" target="_blank"><i class="icon-book"></i>{'user guide'|psmtrans}</a></h3>
    <div id="tabs" style="visibility: hidden;">
        <ul>
        {foreach key=index item=tab from=$tabs}
            <li><a href="#pp-tabs-{$index|pp_safeoutput:href}">{if isset($tab.icon)}{$tab.icon}{/if}{$tab.name|escape:'htmlall':'UTF-8'}</a></li>
        {/foreach}
        </ul>
        {foreach key=index item=tab from=$tabs}
        <div id="pp-tabs-{$index|escape:'htmlall':'UTF-8'}" class="tab-{$tab.type|escape:'htmlall':'UTF-8'}">
            {$tab.html|pp_safeoutput}
        </div>
        {/foreach}
    </div>
    <div class="clearfix"></div>
    {hook h="adminPproperties" mode="displayPPropertiesFooter"}
    </div>
</div>
{literal}
<script type="text/javascript">
    pp.adminppropertiesurl = "{/literal}{$adminppropertiesurl|pp_safeoutput:href}{literal}";
    pp.adminpproperties_activetab = "{/literal}{$active|escape:'htmlall':'UTF-8'}{literal}";
    var ppFormParams = {};{/literal}
    {foreach key=key item=translation from=$jstranslations}
        ppFormParams.{$key|escape:'htmlall':'UTF-8'} = "{$translation|escape:'htmlall':'UTF-8'}";
    {/foreach}{literal}
</script>
{/literal}
