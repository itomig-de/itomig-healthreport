<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Service;

use HealthcheckRun;
use ormDocument;

/**
 * Persistiert das Ergebnis von ReportPipeline::run() als HealthcheckRun.
 *
 * Die gesamte Auswertung (Management-Summary + alle Modul-Ergebnisse) wird
 * als ein JSON am Run gespeichert (bericht_json) - keine HTML-Blobs und keine
 * separaten Modul-Report-Objekte mehr (siehe Issue #8). Die HTML-Darstellung
 * wird spaeter separat zur Anzeigezeit aus bericht_json gerendert.
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
        $oRun->Set('pruefsumme_status', (string) ($pipelineResult['pruefsumme_status'] ?? 'nicht_geprueft'));
        $oRun->Set('ampel_gesamt', $pipelineResult['ampel_gesamt']);
        $oRun->Set('modul_anzahl', count($pipelineResult['module']));
        $oRun->Set('zip_datei', new ormDocument($zipBinary, 'application/zip', $zipFilename));
        $oRun->Set('bericht_json', $pipelineResult['bericht_json']);

        return (int) $oRun->DBInsert();
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
