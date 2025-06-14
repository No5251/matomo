<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Framework;

use Piwik\Common;
use Piwik\Option;

/**
 * TODO: doc
 *
 * TODO: describe need to keep strings as small as possible
 */
class XssTesting
{
    public const OPTION_NAME = 'Tests.xssEntries';

    public function forTwig($type, $sanitize = false)
    {
        $n = $this->addXssEntry($type, 'twig');

        $result = "<script>_x($n)</script>";
        if ($sanitize) {
            // NOTE: since API\Request does sanitization, API methods do not. when calling them, we must
            // sometimes do sanitization ourselves.
            $result = Common::sanitizeInputValue($result);
        }
        return $result;
    }

    /**
     * @deprecated
     */
    public function forAngular($type, $sanitize = false)
    {
        return $this->forVueJs($type, $sanitize);
    }

    public function forVueJs($type, $sanitize = false)
    {
        $n = $this->addXssEntry($type, 'vuejs');

        $result = "{{_Vue.h.constructor`_x($n)`()}}";
        if ($sanitize) {
            $result = Common::sanitizeInputValue($result);
        }
        return $result;
    }

    private function addXssEntry($attackVectorType, $injectionType)
    {
        $entries = $this->getXssEntries();
        $xssName = $injectionType . '-(' . $attackVectorType . ')';

        $index = array_search($xssName, $entries);
        if ($index !== false) {
            return $index;
        }

        $key = count($entries);
        $entries[$key] = $xssName;
        $this->setXssEntries($entries);
        return $key;
    }

    public function getXssEntries()
    {
        $value = Option::get(self::OPTION_NAME);
        return json_decode($value, $isAssoc = true) ?: [];
    }

    private function setXssEntries($entries)
    {
        $value = json_encode($entries);
        Option::set(self::OPTION_NAME, $value);
    }

    private function getJavaScriptCode()
    {
        $entries = json_encode($this->getXssEntries());

        $js = <<<JS
window._xssEntryTypes = $entries;
window._x = function triggerXss(id) {
    var message = "XSS triggered (id = " + id + "): " + window._xssEntryTypes[id];
    $(document.body).append(message);
    var e = (new Error(message));
    console.log(e.stack || e.message);
};
JS;
        return $js;
    }

    public static function getJavaScriptAddEvent()
    {
        $xssTesting = new XssTesting();
        return ['Template.jsGlobalVariables', \Piwik\DI::value(function (&$out) use ($xssTesting) {
            $out .= $xssTesting->getJavaScriptCode();
        })];
    }

    /**
     * Since the XSS entries option is stored in the OmniFixture dump AND is modified when setting up
     * UITestFixture, we want to make sure it has all the right entries. Otherwise some failures will be
     * harder to debug.
     */
    public function sanityCheck()
    {
        $expectedEntries = [
            'twig-(site name)',
            'twig-(goal name)',
            'twig-(goal description)',
            'vuejs-(Piwik test two)',
            'vuejs-(second goal)',
            'vuejs-(goal description)',
            'twig-(pageurl)',
            'twig-(page title)',
            'twig-(referrerUrl)',
            'twig-(keyword)',
            'twig-(customdimension)',
            'twig-(customvarname)',
            'twig-(customvarval)',
            'twig-(userid)',
            'twig-(lang)',
            'twig-(city)',
            'twig-(region)',
            'twig-(country)',
            'vuejs-(pageurl)',
            'vuejs-(page title)',
            'vuejs-(referrerUrl)',
            'vuejs-(keyword)',
            'vuejs-(customdimension)',
            'vuejs-(customvarname)',
            'vuejs-(customvarval)',
            'vuejs-(userid)',
            'vuejs-(lang)',
            'vuejs-(city)',
            'vuejs-(region)',
            'vuejs-(country)',
            'twig-(annotation)',
            'vuejs-(Annotation note 3)',
            'twig-(excludedparameter)',
            'vuejs-(excludedparameter)',
            'twig-(scheduledreport)',
            'twig-(dimensionname)',
            'twig-(category)',
            'twig-(reportname)',
            'twig-(reportdoc)',
            'twig-(subcategory)',
            'twig-(processedmetricname)',
            'twig-(processedmetricdocs)',
            'vuejs-(dimensionname)',
            'vuejs-(category)',
            'vuejs-(reportname)',
            'vuejs-(reportdoc)',
            'vuejs-(subcategory)',
            'vuejs-(processedmetricname)',
            'vuejs-(processedmetricdocs)',
            'vuejs-(scheduledreport)',
            'twig-(segment)',
            'vuejs-(From Europe segment)',
            'twig-(dashboard name0)',
            'vuejs-(dashboard name1)',
            'vuejs-(datatablerow)',
            'twig-(datatablerow)',
        ];

        $actualEntries = $this->getXssEntries();
        $actualEntries = array_unique($actualEntries);
        $actualEntries = array_filter($actualEntries);
        $actualEntries = array_values($actualEntries);

        try {
            \PHPUnit\Framework\Assert::assertEquals($expectedEntries, $actualEntries);
        } catch (\Exception $ex) {
            print "XssTesting::sanityCheck() failed, got: " . var_export($actualEntries, true)
                . "\nexpected: " . var_export($expectedEntries, true);
        }
    }

    public function dangerousLink($desc)
    {
        return 'javascript:alert("' . $desc . '")';
    }
}
