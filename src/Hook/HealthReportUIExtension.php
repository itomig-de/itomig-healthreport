<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Hook;

use AbstractApplicationUIExtension;
use Dict;
use HealthcheckRun;
use utils;
use WebPage;

/**
 * Ergaenzt die Detailseite eines HealthcheckRun um den Reiter "Bericht",
 * der den aus bericht_json gerenderten HTML-Bericht (Route
 * itomig_healthreport.show_report) per iframe einbettet.
 */
class HealthReportUIExtension extends AbstractApplicationUIExtension
{
    private const TAB_CODE = 'Class:HealthcheckRun:Tab:Report';

    /**
     * @inheritDoc
     */
    public function OnDisplayRelations($oObject, WebPage $oPage, $bEditMode = false)
    {
        if ($bEditMode || !$oObject instanceof HealthcheckRun) {
            return;
        }
        if (trim((string) $oObject->Get('bericht_json')) === '') {
            return;
        }

        $sReportUrl = utils::EscapeHtml(
            utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?route=itomig_healthreport.show_report&id=' . (int) $oObject->GetKey()
        );
        $sTabTitle = Dict::S(self::TAB_CODE);

        $sPreviousTab = $oPage->GetCurrentTab();
        $oPage->SetCurrentTab(self::TAB_CODE, $sTabTitle);
        $oPage->add(
            '<p><a href="' . $sReportUrl . '" target="_blank" rel="noopener">'
            . utils::EscapeHtml(Dict::S('Class:HealthcheckRun:Tab:Report:OpenInNewWindow'))
            . '</a></p>'
            . '<iframe src="' . $sReportUrl . '" title="' . utils::EscapeHtml($sTabTitle) . '"'
            . ' style="width: 100%; height: 80vh; border: 0;"></iframe>'
        );
        $oPage->SetCurrentTab($sPreviousTab);
    }
}
