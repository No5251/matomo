<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\API\Renderer;

use Piwik\API\ApiRenderer;
use Piwik\Common;
use Piwik\ProxyHttp;

class Csv extends ApiRenderer
{
    public function renderSuccess($message)
    {
        Common::sendHeader("Content-Disposition: attachment; filename=piwik-report-export.csv");
        return "message\n" . $message;
    }

    /**
     * @param $message
     * @param \Exception|\Throwable $exception
     * @return string
     */
    public function renderException($message, $exception)
    {
        Common::sendHeader('Content-Type: text/html; charset=utf-8', true);
        return 'Error: ' . $message;
    }

    public function renderDataTable($dataTable)
    {
        $convertToUnicode = $this->requestObj->getBoolParameter('convertToUnicode', true);
        $idSite = $this->requestObj->getIntegerParameter('idSite', 0);

        if (empty($idSite)) {
            $idSite = 'all';
        }

        /** @var \Piwik\DataTable\Renderer\Csv $tableRenderer */
        $tableRenderer = $this->buildDataTableRenderer($dataTable);
        $tableRenderer->setConvertToUnicode($convertToUnicode);

        $method = Common::sanitizeInputValue($this->requestObj->getStringParameter('method', ''));

        $tableRenderer->setApiMethod($method);
        $tableRenderer->setIdSite($idSite);
        $tableRenderer->setTranslateColumnNames($this->requestObj->getBoolParameter('translateColumnNames', false));

        return $tableRenderer->render();
    }

    public function renderArray($array)
    {
        return $this->renderDataTable($array);
    }

    public function sendHeader()
    {
        Common::sendHeader("Content-Type: application/vnd.ms-excel", true);
        ProxyHttp::overrideCacheControlHeaders();
    }
}
