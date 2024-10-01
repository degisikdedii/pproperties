{*
* @author    PS&More www.psandmore.com <support@psandmore.com>
* @copyright Since 2011 PS&More
* @license   https://psandmore.com/licenses/sla Software License Agreement
*}

{if $helper_key == "createHeaderJsScript"}
<script type="text/javascript" data-keepinline="true">
    if (typeof window.pp !== "object") {
        window.pp = {};
    }
    window.pp.psversion = "{$psversion}";
    window.pp.version = "{$version}";
    window.pp.theme = "{$theme}";
    window.pp.controller = "{$controller}";
    window.pp.module = "{$module}";
    window.pp.debug = {$debug};
    window.pp.cfg = {$cfg|pp_safeoutput nofilter};
    {if isset($decimalSign)}
    window.pp.decimalSign = "{$decimalSign}";
    {/if}
    {if isset($psandmore_url)}
    window.pp.psandmore_url = "{$psandmore_url|pp_safeoutput nofilter}";
    window.pp.powered_by_psandmore_text = "{$powered_by_psandmore_text|pp_safeoutput nofilter}";
    window.pp.powered_by_psandmore_title = "{$powered_by_psandmore_title|pp_safeoutput nofilter}";
    {/if}
    {if isset($demo)}
    window.pp.demo = true;
    {/if}
</script>
{elseif $helper_key == "displayBackOfficeHeader_integration_warning"}
<script>
    jQuery(function() {
        $("body").prepend('<div class="alert psm-alert psm-alert-danger psm-alert-danger-highlight" style="clear:both;z-index:10000;position:fixed;width:100%;"><button data-dismiss="alert" class="close" type="button">×</button><p>{$message|pp_safeoutput nofilter}</p></div>');
    });
</script>
{elseif $helper_key == "displayBackOfficeHeader_AdminAttributeGenerator"}
<script>
    $(function() {
        $('#generator input[name="quantity"]').wrap('<span class="input-group"></span>').after('<span class="input-group-addon pp_bo_qty_text">'{$pp_bo_qty_text|pp_safeoutput nofilter}'</span>');
    });
</script>
{elseif $helper_key == "displayBackOfficeHeader_upgrade"}
<script>
    jQuery(function() {
        $("#upgradeNow").remove();
        $("#currentConfiguration table tbody").append('<tr><td>{$message|pp_safeoutput nofilter}<br>{$compatibilityText|pp_safeoutput nofilter}</td><td><img alt="ok" src="../img/admin/disabled.gif"></td></tr>');
    });
</script>
{/if}
