<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Service;

use HealthcheckModuleReport;
use HealthcheckRun;
use ormDocument;

/**
 * Persistiert das Ergebnis von ReportPipeline::run() als iTop-Objekte:
 * ein HealthcheckRun (Lauf) mit n HealthcheckModuleReport-Kindern (je Modul).
 */
class RunPersister
{
    /**
     * @param array $pipelineResult Rueckgabe von ReportPipeline::run()
     * @param int $iOrgId           ID der im Upload-Formular ausgewaehlten Organisation (Kunde)
     * @param string $zipBinary     Binaerinhalt des hochgeladenen ZIP (fuer das zip_datei-Blob)
     * @param string $zipFilename   Original-Dateiname des Uploads
     * @return int ID des angelegten HealthcheckRun
     */
    public function persist(array $pipelineResult, int $iOrgId, string $zipBinary, string $zipFilename): int
    {
        $oRun = new HealthcheckRun();
        $oRun->Set('kunde_id', $iOrgId);
        $oRun->Set('zip_kunde_name', $pipelineResult['kunde']);
        $oRun->Set('umgebung', $pipelineResult['umgebung']);
        $oRun->Set('zeitpunkt', $this->toItopDateTime($pipelineResult['zeitpunkt']));
        $oRun->Set('itop_version', (string) ($pipelineResult['itop_version'] ?? ''));
        $oRun->Set('db_server_version', (string) ($pipelineResult['db_server_version'] ?? ''));
        $oRun->Set('extension_version', (string) ($pipelineResult['extension_version'] ?? ''));
        $oRun->Set('zip_customer_url', (string) ($pipelineResult['customer_url'] ?? ''));
        $oRun->Set('zip_instance_id', (string) ($pipelineResult['instance_id'] ?? ''));
        $oRun->Set('ampel_gesamt', $pipelineResult['ampel_gesamt']);
        $oRun->Set('modul_anzahl', count($pipelineResult['module']));
        $oRun->Set('zip_datei', new ormDocument($zipBinary, 'application/zip', $zipFilename));
        $oRun->Set('summary_html', new ormDocument($pipelineResult['summary_html'], 'text/html', 'summary.html'));
        $oRun->Set('summary_json', new ormDocument($pipelineResult['summary_json'], 'application/json', 'summary.json'));
        $iRunId = (int) $oRun->DBInsert();

        foreach ($pipelineResult['module'] as $aModule) {
            $oModuleReport = new HealthcheckModuleReport();
            $oModuleReport->Set('run_id', $iRunId);
            $oModuleReport->Set('kategorie', $aModule['kategorie']);
            $oModuleReport->Set('label', $aModule['label']);
            $oModuleReport->Set('ampel', $aModule['ampel']);
            $oModuleReport->Set('befund_anzahl', $aModule['befund_anzahl']);
            $oModuleReport->Set('report_html', new ormDocument($aModule['html'], 'text/html', $aModule['kategorie'] . '.html'));
            $oModuleReport->Set('report_json', new ormDocument($aModule['json'], 'application/json', $aModule['kategorie'] . '.json'));
            $oModuleReport->DBInsert();
        }

        return $iRunId;
    }

    /**
     * Konvertiert den Y-m-d_His-Timestamp der Engine (Dateiname-Format) in das
     * von AttributeDateTime erwartete Format Y-m-d H:i:s.
     */
    private function toItopDateTime(string $engineTimestamp): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d_His', $engineTimestamp);
        if ($date === false) {
            return date('Y-m-d H:i:s');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
