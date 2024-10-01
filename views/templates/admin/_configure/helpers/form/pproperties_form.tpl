{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

{extends file="helpers/form/form.tpl"}
{block name="input" append}
    {if $input.type == 'div'}
        <div{if isset($input.class)} class="{$input.class|escape:'htmlall':'UTF-8'}"{/if}>{$input.name|escape:'htmlall':'UTF-8'}</div>
    {elseif $input.type == 'switch_with_label'}
        <span class="switch prestashop-switch fixed-width-xl">
            {foreach $input.values as $value}
            <input type="radio" name="{$input.name|escape:'htmlall':'UTF-8'}"{if $value.value == 1} id="{$input.name|escape:'htmlall':'UTF-8'}_on"{else} id="{$input.name|escape:'htmlall':'UTF-8'}_off"{/if} value="{$value.value|escape:'htmlall':'UTF-8'}"{if $fields_value[$input.name] == $value.value} checked="checked"{/if}{if isset($input.disabled) && $input.disabled} disabled="disabled"{/if}{if isset($value.class)} class="{$value.class|escape:'htmlall':'UTF-8'}"{/if}/>
            {strip}
            <label {if $value.value == 1} for="{$input.name|escape:'htmlall':'UTF-8'}_on"{else} for="{$input.name|escape:'htmlall':'UTF-8'}_off"{/if}>
                {$value.label|escape:'htmlall':'UTF-8'}
            </label>
            {/strip}
            {/foreach}
            <a class="slide-button btn"></a>
        </span>
    {elseif $input.type == 'clearcache'}
        <div class="clearfix row-padding-top">
            <a class="btn btn-default pp-action-btn{if not $access_edit} disabled{/if}" href="{$current|pp_safeoutput:href}&amp;token={$token|escape:'htmlall':'UTF-8'}&amp;clickClearCache=1">
                <i class="icon-eraser"></i>
                {$input.name|escape:'htmlall':'UTF-8'}
            </a>
        </div>
    {/if}
{/block}
{block name="label" append}
    {if isset($input.advice) && is_array($input.advice) && (strpos($input.advice.type, 'label') !== false)}
        <div style="display: none;" class="col-lg-3 advice-label pp-advice{if isset($input.advice.class)} {$input.advice.class|escape:'htmlall':'UTF-8'}{/if}"{if isset($input.advice.bind)} data-bind="{$input.advice.bind}"{/if}><span data-ref="{$input.advice.key|pp_safeoutput:ref}"><i class="icon-graduation-cap"></i><span class="readmore">{$s_read_explanation|escape:'htmlall':'UTF-8'}</span></span></div>
    {/if}
{/block}
{block name="description" append}
    {if isset($input.checkboxes)}
        {foreach $input.checkboxes as $checkbox}
            {foreach $checkbox.values.query as $value}
                {assign var=id_checkbox value=$input.checkbox_name|cat:'_'|cat:$value[$checkbox.values.id]}
                {if isset($checkbox.separate) && $checkbox.separate}<div class="checkbox-separator"></div>{/if}
                <div class="checkbox{if isset($checkbox.class)} {$checkbox.class|escape:'htmlall':'UTF-8'}{/if}">
                    <label for="{$id_checkbox|escape:'htmlall':'UTF-8'}">
                        <input type="checkbox"
                            name="{$id_checkbox|escape:'htmlall':'UTF-8'}"
                            id="{$id_checkbox|escape:'htmlall':'UTF-8'}"
                            {if isset($value.val)}value="{$value.val|pp_safeoutput:value}"{/if}
                            {if isset($fields_value[$id_checkbox]) && $fields_value[$id_checkbox]}checked="checked"{/if} />
                        {$value[$checkbox.values.name]|escape:'htmlall':'UTF-8'}
                    </label>
                </div>
            {/foreach}
        {/foreach}
    {/if}
    {if isset($input.advice) && is_array($input.advice)}
        {if strpos($input.advice.type, 'description') !== false}
            <div style="display:none;" class="advice-description pp-advice{if isset($input.advice.class)} {$input.advice.class|escape:'htmlall':'UTF-8'}{/if}"{if isset($input.advice.bind)} data-bind="{$input.advice.bind}"{/if}><i class="icon-graduation-cap"></i>{$input.advice.html} <span data-ref="{$input.advice.key|pp_safeoutput:ref}" class="readmore">{$s_read_more|escape:'htmlall':'UTF-8'}</span></div>
        {/if}
        <div data-advice-content="{$input.advice.key|pp_safeoutput:ref}" style="display: none;"></div>
    {/if}
{/block}
{block name="other_input" append}
    {if $key == 'warning'}
        <div class="alert alert-warning{if isset($field.class)} {$field.class|escape:'htmlall':'UTF-8'}{/if}">{$field.text|escape:'htmlall':'UTF-8'}</div>
    {elseif $key == 'multidimensional-feature'}
        {hook h="adminMultidimensional" mode="multidimensional-feature" id_pp_template=$fields.form.id_pp_template}
        <div class="multidimensional-feature">
            {if $fields.form.multidimensional}
                <a class="readme_url" target="_blank" href="{$field.readme_url|pp_safeoutput:href}"><i class="icon-book"></i>{$field.readme_pdf|escape:'htmlall':'UTF-8'}</a>
            {/if}
            <span class="feature">{$field.text|escape:'htmlall':'UTF-8'}</span>
            <span class="clear"></span>
            {if !$fields.form.multidimensional}
                <div class="alert alert-warning dimensions-toggle">{$field.disabled|pp_safeoutput}</div>
            {/if}
        </div>
    {elseif $key == 'dimensions-table'}
        {if $fields.form.multidimensional}
            {hook h="adminMultidimensional" mode="beforeTable" id_pp_template=$fields.form.id_pp_template}
        {/if}
        <table id="multidimensional-table" class="table dimensions-toggle">
            <caption>
                <div style="display: none;" class="advice-label pp-advice"><span data-ref="pp_ext_dimensions"><i class="icon-graduation-cap"></i><span class="readmore">{$s_read_explanation|escape:'htmlall':'UTF-8'}</span></span></div>
                <div data-advice-content="pp_ext_dimensions" style="display: none;"></div>
            </caption>
            <thead>
                {foreach from=$field.th item=th}
                <th>{$th|escape:'htmlall':'UTF-8'}</th>
                {/foreach}
                {if $fields.form.multidimensional}
                    {hook h="adminMultidimensional" mode="th-after" id_pp_template=$fields.form.id_pp_template}
                {/if}
            </thead>
            {foreach from=$field.tbody item=tbody name=tbody_loop}
                <tbody>
                {foreach from=$tbody.tr item=tr name=tr_loop}
                    <tr>
                    {foreach from=$tr item=td}
                        {foreach from=$td item=input name=td_loop}
                        <td class="td-{$input.type|escape:'htmlall':'UTF-8'}{if isset($input.td_class)} {$input.td_class|escape:'html':'UTF-8'}{/if}">
                            {if $input.type == 'text'}
                                {assign var='value_text' value=$fields_value[$input.name]}
                                <input type="text"
                                    name="{$input.name|escape:'htmlall':'UTF-8'}"
                                    {if isset($input.class)}class="{$input.class|escape:'html':'UTF-8'}"{/if}
                                    id="{if isset($input.id)}{$input.id|escape:'htmlall':'UTF-8'}{else}{$input.name|escape:'htmlall':'UTF-8'}{/if}"
                                    {if isset($input.data_type)} data-type="{$input.data_type|escape:'htmlall':'UTF-8'}"{/if}
                                    value="{if isset($input.string_format) && $input.string_format}{$value_text|string_format:$input.string_format|pp_safeoutput:value}{else}{$value_text|pp_safeoutput:value}{/if}"
                                    {if isset($input.size)} size="{$input.size|escape:'htmlall':'UTF-8'}"{/if}
                                    {if isset($input.class)} class="{$input.class|escape:'htmlall':'UTF-8'}"{/if}
                                    {if isset($input.readonly) && $input.readonly} readonly="readonly"{/if}
                                    {if isset($input.disabled) && $input.disabled} disabled="disabled"{/if}
                                    />
                            {elseif $input.type == 'select'}
                                <select name="{$input.name|escape:'html':'UTF-8'}"
                                        class="{if isset($input.class)}{$input.class|escape:'html':'UTF-8'}{/if}"
                                        id="{if isset($input.id)}{$input.id|escape:'html':'UTF-8'}{else}{$input.name|escape:'html':'UTF-8'}{/if}"
                                        {if isset($input.data_type)} data-type="{$input.data_type|escape:'htmlall':'UTF-8'}"{/if}>
                                        {foreach $input.options.query as $option}
                                            <option value="{$option[$input.options.id]|escape:'htmlall':'UTF-8'}"
                                                    {if $fields_value[$input.name] == $option[$input.options.id]|escape:'htmlall':'UTF-8'}
                                                        selected="selected"
                                                    {/if}
                                            >{$option[$input.options.name]|pp_safeoutput:value}</option>
                                        {/foreach}
                                </select>
                            {/if}
                            {if $smarty.foreach.td_loop.iteration == 1}
                                <input type="hidden" name="dimension_index[{$tbody.dimension_index|pp_safeoutput}]" value="{$tbody.dimension_index|pp_safeoutput}">
                                <input type="hidden" name="dimension_id_ext_prop_{$tbody.dimension_index|pp_safeoutput}" data-type="dimension_id_ext_prop_" value="{$tbody.id_ext_prop|pp_safeoutput}">
                            {/if}
                        </td>
                        {if $fields.form.multidimensional}
                            {hook h="adminMultidimensional" mode="td-after" id_pp_template=$fields.form.id_pp_template td_index=$smarty.foreach.td_loop.iteration dimension_index=$smarty.foreach.tbody_loop.iteration}
                        {/if}
                        {/foreach}
                    {/foreach}
                    {if $fields.form.multidimensional}
                        {hook h="adminMultidimensional" mode="tr" id_pp_template=$fields.form.id_pp_template dimension_index=$smarty.foreach.tbody_loop.iteration}
                    {/if}
                    </tr>
                    {if $fields.form.multidimensional}
                        {hook h="adminMultidimensional" mode="tr-after" id_pp_template=$fields.form.id_pp_template dimension_index=$smarty.foreach.tbody_loop.iteration}
                    {/if}
                {/foreach}
                </tbody>
            {/foreach}
        </table>
        {if isset($field.help)}
            <p class="help-block{if isset($field.help.class)} {$field.help.class|escape:'html':'UTF-8'}{/if}">
            {foreach $field.help.text as $v}
                {$v|pp_safeoutput}<br/>
            {/foreach}
            </p>
        {/if}
        {if $fields.form.multidimensional}
            {hook h="adminMultidimensional" mode="afterTable" id_pp_template=$fields.form.id_pp_template}
        {/if}
    {/if}
{/block}
{block name="footer" append}
    {if !$integrated}
        <div class="alert alert-warning">{$integration_message|pp_safeoutput_lenient}</div>
    {/if}
{/block}
{block name="after" append}
    {if $integrated}
        {if isset($fields.form.script)}
            {foreach $fields.form.script as $script}
                {if $script == 'multidimensional'}
                    <script type="text/javascript">
                        $("select#pp_ext_method").on("change keyup", function () {
                            if ($(this).get(0).selectedIndex > 0)
                                $(".dimensions-toggle, #fieldset_dimensions_form_2 .panel-footer").fadeIn("slow");
                            else
                                $(".dimensions-toggle, #fieldset_dimensions_form_2 .panel-footer").fadeOut("slow");
                        });
                        {if !$fields.form.multidimensional}
                            $(".dimensions-toggle input, .dimensions-toggle select").attr("disabled", "disabled");
                            $("#fieldset_dimensions_form_2 .panel-footer").remove();
                        {/if}
                        jQuery(function() {
                            $("select#pp_ext_method").trigger('change');
                        });
                    </script>
                {/if}
            {/foreach}
        {/if}
    {/if}
{/block}