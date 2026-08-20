<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Service;

use Dict;
use HealthcheckUpload;
use MetaModel;
use RuntimeException;
use Throwable;

/**
 * Wertet einen bereits gespeicherten HealthcheckUpload (Portal-Upload) aus
 * und persistiert das Ergebnis wie beim Konsolen-Upload als HealthcheckRun.
 *
 * Bindeglied zwischen dem Portal-Eingang (HealthcheckUpload, siehe
 * HealthReportUploadBrickController) und der bestehenden, unveraenderten
 * ReportPipeline/RunPersister-Kette (siehe HealthReportController::OperationUpload()).
 *
 * Die Auswertung wird bewusst NICHT automatisch beim Portal-Upload
 * angestossen, sondern erst hier, wenn ITOMIG dies in der Konsole ("Eingegangene
 * Uploads" -> "Auswerten") explizit ausloest.
 */
class UploadProcessor
{
    /**
     * @throws RuntimeException Wenn der Upload nicht existiert oder bereits ausgewertet ist.
     */
    public function process(int $iUploadId): int
    {
        $oUpload = MetaModel::GetObject('HealthcheckUpload', $iUploadId, false);
        if (!$oUpload instanceof HealthcheckUpload) {
            throw new RuntimeException(Dict::S('Itomig:HealthReport:Error:UploadNotFound'));
        }

        if ((string) $oUpload->Get('status') === 'ausgewertet') {
            throw new RuntimeException(Dict::S('Itomig:HealthReport:Error:UploadAlreadyProcessed'));
        }

        $sTmpPath = $this->writeBlobToTempFile($oUpload);

        try {
            $oPipeline = new ReportPipeline();
            $aResult = $oPipeline->run($sTmpPath);

            $oBlob = $oUpload->Get('zip_datei');
            $sZipBinary = $oBlob->GetData();
            $sZipFilename = (string) $oUpload->Get('dateiname');
            $iOrgId = (int) $oUpload->Get('org_id');

            $oPersister = new RunPersister();
            $iRunId = $oPersister->persist($aResult, $iOrgId, $sZipBinary, $sZipFilename);

            $oUpload->Set('status', 'ausgewertet');
            $oUpload->Set('run_id', $iRunId);
            $oUpload->Set('fehlermeldung', '');
            $oUpload->DBUpdate();

            return $iRunId;
        } catch (Throwable $e) {
            $oUpload->Set('status', 'fehler');
            $oUpload->Set('fehlermeldung', $e->getMessage());
            $oUpload->DBUpdate();

            throw $e;
        } finally {
            if (is_file($sTmpPath)) {
                unlink($sTmpPath);
            }
        }
    }

    /**
     * Schreibt das zip_datei-Blob des Uploads in eine temporaere Datei, da
     * ReportPipeline::run() einen Dateipfad erwartet.
     */
    private function writeBlobToTempFile(HealthcheckUpload $oUpload): string
    {
        $oBlob = $oUpload->Get('zip_datei');

        $sTmpPath = (string) tempnam(sys_get_temp_dir(), 'itomig-healthreport-upload-');
        if ($sTmpPath === '') {
            throw new RuntimeException(Dict::S('Itomig:HealthReport:Error:TempFileFailed'));
        }

        file_put_contents($sTmpPath, $oBlob->GetData());

        return $sTmpPath;
    }
}
