<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UserCountry\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\UserCountry\Columns\Region;

class GetRegion extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension      = new Region();
        $this->name           = Piwik::translate('UserCountry_Region');
        $this->documentation  = Piwik::translate('UserCountry_getRegionDocumentation') . '<br/>' . $this->getGeoIPReportDocSuffix();
        $this->order = 7;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->documentation = $this->documentation;

        $view->requestConfig->filter_limit = 5;

        $this->checkIfNoDataForGeoIpReport($view);
    }
}
