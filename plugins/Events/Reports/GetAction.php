<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Events\Reports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\Events\Columns\EventAction;

class GetAction extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension     = new EventAction();
        $this->name          = Piwik::translate('Events_EventActions');
        $this->documentation = Piwik::translate('Events_EventActionsReportDocumentation');
        $this->metrics       = ['nb_events', 'nb_visits', 'nb_uniq_visitors', 'sum_event_value', 'min_event_value', 'max_event_value', 'nb_events_with_value'];
        if (Common::getRequestVar('secondaryDimension', false) == 'eventCategory') {
            $this->actionToLoadSubTables = 'getCategoryFromNameId';
        } else {
            $this->actionToLoadSubTables = 'getNameFromActionId';
        }
        $this->order = 1;
    }
}
