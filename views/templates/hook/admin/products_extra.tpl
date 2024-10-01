{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

<div id="product-modulepproperties">
    <h2>{$s_header|escape:'htmlall':'UTF-8'}</h2>
    <div class="pp-tip pp-tip-container hint" style="display:none;">
        <div class="pp-tip-title"></div>
        <div class="pp-tip-description-container">
            <span class="pp-tip-description">{$s_advice|escape:'htmlall':'UTF-8'}</span>
        </div>
    </div>
    <div class="psm-alert psm-alert-warning hint" role="alert" style="display:none;"><p>{$s_hint|escape:'htmlall':'UTF-8'}</p></div>
    {if !$integrated}<div class="psm-alert psm-alert-warning" role="alert"><p>{$integration_warning|escape:'htmlall':'UTF-8'}</p></div>{/if}
    {if !$multidimensional}<div class="psm-alert psm-alert-warning pp_multidimensional" role="alert" style="display:none;"><p>{$multidimensional_warning|escape:'htmlall':'UTF-8'}</p></div>{/if}
{if $integrated}
    <div class="row">
        <fieldset class="form-group col-lg-9">
            <label class="form-control-label pp_template_select_label" for="id_pp_template">{$s_product_template|escape:'htmlall':'UTF-8'}</label>
            <select name="id_pp_template" id="id_pp_template" class="form-control">
                {assign var=boTemplates value=PP::getAdminProductsTemplates($id_pp_template)}
                {foreach from=$boTemplates item=template name=bo}
                    <option value="{$template['id_pp_template']}"{if $id_pp_template == $template['id_pp_template']} selected{/if}>{$template['name']|pp_safeoutput:value}</option>
                {/foreach}
            </select>
            <p class="pp_template_desc"></p>
        </fieldset>
        <fieldset class="form-group col-lg-3 pp_template_toggle">
            <label class="form-control-label">&nbsp;</label>
            <div class="form-control btn-group no-border">
                <a class="btn edit-pp-template-link" href="index.php?controller=adminmodules&amp;configure=pproperties&amp;token={Tools::getAdminTokenLite('AdminModules')|pp_safeoutput}&amp;tab_module=administration&amp;module_name=pproperties&amp;clickEditTemplate&amp;mode=edit&amp;pp=1&amp;id=" target="_blank"><i class="material-icons">mode_edit</i> {$s_edit_template|escape:'htmlall':'UTF-8'}</a>
                <a class="btn btn-link dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></a>
                <div class="dropdown-menu dropdown-menu-right" x-placement="bottom-start">
                    <a class="dropdown-item configure-pp-templates-link" href="index.php?controller=adminmodules&amp;configure=pproperties&amp;token={Tools::getAdminTokenLite('AdminModules')|escape:'htmlall':'UTF-8'}&amp;tab_module=administration&amp;module_name=pproperties" target="_blank">
                        <i class="material-icons icon-template">reorder</i> {$s_configure_templates|escape:'htmlall':'UTF-8'}
                    </a>
                </div>
            </div>
        </fieldset>
    </div>
    <div class="pp_template_values pp_template_toggle">
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_policy|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_qty_policy_expl pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_mode|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_qty_mode_expl pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_display_mode|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_display_mode_expl pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_price_display_mode|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_price_display_mode_expl pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_price_text|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_price_text pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_text|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_qty_text pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_unity_text|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_unity_text pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_unit_price_ratio|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_unit_price_ratio pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_minimum_price_ratio|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9 pp_minimum_price_ratio pp_template_value"></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_minimum_quantity|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_minimum_quantity pp_template_value"></span> <span class="pp_bo_minimum_quantity_text pp_template_value"></span></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_maximum_quantity|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_maximum_quantity pp_template_value"></span> <span class="pp_bo_maximum_quantity_text pp_template_value"></span></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_total_maximum_quantity|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_total_maximum_quantity pp_template_value"></span> <span class="pp_bo_total_maximum_quantity_text pp_template_value"></span></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_default_quantity|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_default_quantity pp_template_value"></span> <span class="pp_bo_default_quantity_text pp_template_value"></span></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_step|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_qty_step pp_template_value"></span> <span class="pp_bo_qty_step_text pp_template_value"></span></label></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_shift|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_qty_shift pp_template_value"></span> <span class="pp_bo_qty_shift_text pp_template_value"></span></label></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_decimals|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_qty_decimals pp_template_value"></span></label></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_qty_values|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_qty_values pp_template_value"></span></label>
        </div>
        <div class="row">
            <label class="col-lg-3">{$s_pp_explanation|escape:'htmlall':'UTF-8'}</label>
            <label class="col-lg-9"><span class="pp_explanation pp_template_value"></span></label>
        </div>
    </div>
{/if}
</div>
{if $integrated}
    <script>
        window.pproperties_hook_admin_product = function() {
            ppAdminTemplates.minQtyExpl = {
                "-1" : "{$s_ppMinQtyExpl_disable|pp_safeoutput}",
                "0" : "{$s_ppMinQtyExpl_0|pp_safeoutput}",
                "1" : "{$s_ppMinQtyExpl_1|pp_safeoutput}",
                "2" : "{$s_ppMinQtyExpl_2|pp_safeoutput}",
                "ext" : "{$s_ppMinQtyExpl_ext|pp_safeoutput}"
            };
            ppAdminTemplates.minQtyExplShort = {
                "0" : "{$s_ppMinQtyExplShort_0|pp_safeoutput}",
                "1" : "{$s_ppMinQtyExplShort_1|pp_safeoutput}",
                "2" : "{$s_ppMinQtyExplShort_2|pp_safeoutput}",
                "ext" : "{$s_ppMinQtyExplShort_ext|pp_safeoutput}"
            };
            ppAdminTemplates.qtyPolicyExpl = {
                "0" : "{$s_pp_qty_policy_0|pp_safeoutput}",
                "1" : "{$s_pp_qty_policy_1|pp_safeoutput}",
                "2" : "{$s_pp_qty_policy_2|pp_safeoutput}",
                "ext" : "{$s_pp_qty_policy_ext|pp_safeoutput}"
            };
            ppAdminTemplates.qtyModeExpl = {
                "0" : "{$s_pp_qty_mode_0|pp_safeoutput}",
                "1" : "{$s_pp_qty_mode_1|pp_safeoutput}"
            };
            {foreach from=$s_pp_qty_mode_options item=v}
                ppAdminTemplates.qtyModeExpl[{$v}] = "{${'s_pp_qty_mode_'|cat:$v}|pp_safeoutput}";
            {/foreach}
            ppAdminTemplates.displayModeExpl = {
                "0" : "{$s_pp_display_mode_0|pp_safeoutput}",
                "1" : "{$s_pp_display_mode_1|pp_safeoutput}"
            };
            {foreach from=$s_pp_display_mode_options item=v}
                ppAdminTemplates.displayModeExpl[{$v}] = "{${'s_pp_display_mode_'|cat:$v}|pp_safeoutput}";
            {/foreach}
            ppAdminTemplates.priceDisplayModeExpl = {
                "0" : "{$s_pp_price_display_mode_0|pp_safeoutput}",
                "1" : "{$s_pp_price_display_mode_1|pp_safeoutput}"
            };
            ppAdminTemplates.s_minimum_quantity = "{$s_minimum_quantity|pp_safeoutput}";
            ppProduct.id = {$product->id|pp_safeoutput};
            ppProduct.s_pp_unity_text_expl = "{$s_pp_unity_text_expl|escape:'html':'UTF-8'}";
            {assign var=hasAttributes value=$product->hasAttributes()}
            ppProduct.hasAttributes = {$hasAttributes|pp_safeoutput};
            ppProduct.fallback_ext_quantity = 1;
            {assign var=boTemplates value=PP::getAdminProductsTemplates($id_pp_template)}
            ppAdminTemplates.templates = {json_encode($boTemplates)|pp_safeoutput};
            {foreach from=$boTemplates item=template name=bo}
                {if $id_pp_template == $template['id_pp_template']}
                    ppAdminTemplates.currentTemplate = {$smarty.foreach.bo.index|pp_safeoutput};
                {/if}
            {/foreach}
            {* $('#form_step1_inputPackItems').closest('.row').before('<div class="small pp_desc">' + "{$s_pack_hint|pp_safeoutput}" + '</div>'); *}
            $('#form_step1_inputPackItems').closest('.row').prev('h2').append(' <span class="help-box" data-toggle="popover" data-content="' + "{$s_pack_hint|pp_safeoutput}" + '"></span>');
            $('[data-toggle="popover"]').popover();
        };
    </script>
    {if isset($hook_html) && is_array($hook_html)}
        {foreach from=$hook_html item=html key=module}
            <div class="hook-{$module|escape:'htmlall':'UTF-8'}">{$html|pp_safeoutput}</div>
        {/foreach}
    {/if}
{/if}