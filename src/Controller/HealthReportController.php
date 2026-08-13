<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Itomig\iTop\Extension\HealthReport\Service\ReportPipeline;
use Itomig\iTop\Extension\HealthReport\Service\RunPersister;
use utils;

/**
 * Twig-Controller fuer die Healthreport-Upload-Seite.
 *
 * Stellt zwei Operationen bereit:
 *  - show_form: rendert das Upload-Formular
 *  - upload:    importiert das hochgeladene ZIP, wertet alle Module aus und
 *               speichert das Ergebnis als HealthcheckRun + HealthcheckModuleReport
 *
 * Die Route folgt dem iTop-3.2-Konventionsmuster (analog itomig-healthcheck):
 *   /pages/UI.php?route=itomig_healthreport.<operation>
 */
class HealthReportController extends Controller
{
    public const ROUTE_NAMESPACE = 'itomig_healthreport';

    private const UPLOAD_ERROR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE   => 'Datei überschreitet upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'Datei überschreitet MAX_FILE_SIZE im Formular.',
        UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Keine Datei hochgeladen.',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein tmp-Verzeichnis verfügbar.',
        UPLOAD_ERR_CANT_WRITE => 'Schreibfehler beim Upload.',
        UPLOAD_ERR_EXTENSION  => 'Upload durch PHP-Extension blockiert.',
    ];

    public function __construct($sViewPath = '', $sModuleName = 'core', $aAdditionalPaths = [])
    {
        $sModuleName = 'itomig-healthreport';
        $sViewPath = MODULESROOT . 'itomig-healthreport/templates';
        parent::__construct($sViewPath, $sModuleName, $aAdditionalPaths);

        // Admin-only - identisches Muster zu itomig-healthcheck.
        $this->AllowOnlyAdmin();
        $this->CheckAccess();
    }

    /**
     * Rendert das Upload-Formular (GET).
     */
    public function OperationShowForm(): void
    {
        $aParams = [
            'sTransactionId' => utils::GetNewTransactionId(),
            'sFormAction'    => utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?route=itomig_healthreport.upload',
            'sErrorMessage'  => (string) utils::ReadParam('err', '', false, 'raw_data'),
        ];

        $this->m_sOperation = 'ShowForm';
        $this->DisplayPage($aParams);
    }

    /**
     * Importiert das hochgeladene ZIP, wertet alle Module aus und
     * persistiert das Ergebnis (POST).
     */
    public function OperationUpload(): void
    {
        $sTransactionId = utils::ReadPostedParam('transaction_id', '', 'transaction_id');
        if (!utils::IsTransactionValid($sTransactionId)) {
            $this->redirectToFormWithError(\Dict::S('Itomig:HealthReport:Error:InvalidToken'));
            return;
        }

        try {
            $sTmpPath = $this->validateUpload();

            $oPipeline = new ReportPipeline();
            $aResult = $oPipeline->run($sTmpPath);

            $sZipBinary = (string) file_get_contents($sTmpPath);
            $sZipFilename = (string) ($_FILES['zip']['name'] ?? 'healthcheck.zip');

            $oPersister = new RunPersister();
            $iRunId = $oPersister->persist($aResult, $sZipBinary, $sZipFilename);

            $this->redirectToRunDetails($iRunId);
        } catch (\Throwable $e) {
            $this->redirectToFormWithError(
                \Dict::Format('Itomig:HealthReport:Error:ProcessingFailed', $e->getMessage())
            );
        }
    }

    /**
     * Prueft den Upload (Fehlercode, Herkunft, Dateiendung) und liefert den
     * temporaeren Pfad der hochgeladenen Datei.
     *
     * Muster identisch zur ehemaligen web/process.php.
     */
    private function validateUpload(): string
    {
        $iErrorCode = $_FILES['zip']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (empty($_FILES['zip']['tmp_name']) || $iErrorCode !== UPLOAD_ERR_OK) {
            $sReason = self::UPLOAD_ERROR_MESSAGES[$iErrorCode] ?? 'unbekannter Fehler';
            throw new \RuntimeException(\Dict::Format('Itomig:HealthReport:Error:UploadFailed', $sReason));
        }

        $sTmpPath = $_FILES['zip']['tmp_name'];
        if (!is_uploaded_file($sTmpPath)) {
            throw new \RuntimeException(\Dict::S('Itomig:HealthReport:Error:NotUploaded'));
        }

        $sOrigName = (string) $_FILES['zip']['name'];
        $sExt = strtolower(pathinfo($sOrigName, PATHINFO_EXTENSION));
        if ($sExt !== 'zip') {
            throw new \RuntimeException(\Dict::S('Itomig:HealthReport:Error:NotZip'));
        }

        return $sTmpPath;
    }

    private function redirectToRunDetails(int $iRunId): void
    {
        $sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?operation=details&class=HealthcheckRun&id=' . $iRunId;
        header('Location: ' . $sUrl);
        exit;
    }

    private function redirectToFormWithError(string $sMessage): void
    {
        $sUrl = utils::GetAbsoluteUrlAppRoot()
            . 'pages/UI.php?route=itomig_healthreport.show_form&err=' . rawurlencode($sMessage);
        header('Location: ' . $sUrl);
        exit;
    }
}
