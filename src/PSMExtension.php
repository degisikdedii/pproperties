<?php
/**
 * This source file is subject to the commercial software
 * license agreement available through the world-wide-web at this URL:
 * https://psandmore.com/licenses/sla
 * If you are unable to obtain the license, please send an email to
 * support@psandmore.com and we will send you a copy immediately.
 *
 * @author    PS&More www.psandmore.com <support@psandmore.com>
 * @copyright Since 2011 PS&More
 * @license   https://psandmore.com/licenses/sla Software License Agreement
 */

// phpcs:disable Generic.Files.LineLength
namespace PrestaShop\Module\Pproperties;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

/**
 * This class is used by Twig_Environment and provide some methods callable from a twig template
 */
class PSMExtension extends Twig_Extension
{
    public function getFilters()
    {
        return array(
            new Twig_SimpleFilter('pp', array($this, 'ppFilter'), array('is_safe' => array('all'))),
            new Twig_SimpleFilter('pp_safeoutput', array($this, 'safeoutput'), array('is_safe' => array('all'))),
            new Twig_SimpleFilter('psm', array($this, 'psm'), array('is_safe' => array('all'))),
            new Twig_SimpleFilter('psmtrans', array($this, 'psmtrans'), array('is_safe' => array('all'))),
            new Twig_SimpleFilter('ucfirst', array($this, 'ucfirst'), array('is_safe' => array('all'))),
            new Twig_SimpleFilter('lcfirst', array($this, 'lcfirst'), array('is_safe' => array('all'))),
            new Twig_SimpleFilter('displayQty', array('PP', 'twigDisplayQty'), array('is_safe' => array('all'))),
        );
    }

    public function getFunctions()
    {
        return array(
            new Twig_SimpleFunction('displayQty', array('PP', 'twigDisplayQty'), array('is_safe' => array('all'))),
            new Twig_SimpleFunction('pp', array($this, 'ppFunction')),
        );
    }

    public function ppFilter($source, $product, $mode, $wrap = true)
    {
        $key = 'pp_' . $mode;
        if (\PP::is_array($product)) {
            if (isset($product[$key])) {
                $text = $product[$key];
            }
        }
        if (!isset($text)) {
            $properties = \PP::getProductProperties($product);
            if (isset($properties[$key])) {
                $text = $properties[$key];
            }
        }
        if (isset($text) && $text != '') {
            if ($wrap === true) {
                return $source . \PP::wrap($text, $key, 'span', null, ['safeotput' => true]);
            }
            if (is_array($wrap)) {
                if (array_key_exists('left', $wrap)) {
                    $source .= $wrap['left'];
                }
                $source .= $text;
                if (array_key_exists('right', $wrap)) {
                    $source .= $wrap['right'];
                }
                return $source;
            }
            if ($wrap == 'left') {
                return $source . ' ' . $text;
            }
            if ($wrap == 'right') {
                return $source . $text . ' ';
            }
        }
        return $source;
    }

    public function safeoutput($string, $type = null)
    {
        switch ($type) {
            case 'html':
                return \PP::safeOutput($string);
            case 'js':
            case 'javascript':
                return \PP::safeOutputJS($string);
            case 'value':
                return \PP::safeOutputValue($string);
            case 'htmlspecialchars':
                return htmlspecialchars(htmlspecialchars($string, ENT_QUOTES | ENT_HTML401, 'UTF-8'), ENT_COMPAT, 'UTF-8');
            default:
                return $string;
        }
    }

    public function psm($value, $type, $param = null)
    {
        return \PP::smartyModifierPSM($value, $type, $param);
    }

    public function psmtrans($string, $parameters = null, $module = null)
    {
        return \PSM::translate($string, $parameters, $module);
    }

    public function ucfirst($string)
    {
        return \Tools::ucfirst($string);
    }

    public function lcfirst($string)
    {
        return lcfirst($string);
    }

    public function ppFunction()
    {
        $args = func_get_args();
        $name = array_shift($args);
        return call_user_func_array(['PP', $name], $args);
    }

    public function getName()
    {
        return 'twig_psm_extension';
    }
}
