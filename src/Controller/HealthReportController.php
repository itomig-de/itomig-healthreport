<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Itomig\iTop\Extension\HealthReport\Service\ReportPipeline;
use Itomig\iTop\Extension\HealthReport\Service\ReportRenderer;
use Itomig\iTop\Extension\HealthReport\Service\RunPersister;
use Itomig\iTop\Extension\HealthReport\Service\UploadProcessor;
use Itomig\iTop\Extension\HealthReport\Service\ZipUploadValidator;
use utils;

/**
 * Twig-Controller fuer die Healthreport-Upload-Seite.
 *
 * Stellt u. a. folgende Operationen bereit:
 *  - show_form:   rendert das Upload-Formular
 *  - upload:      importiert das hochgeladene ZIP, wertet alle Module aus und
 *                 speichert das Ergebnis als HealthcheckRun (ein Gesamt-JSON,
 *                 siehe RunPersister)
 *  - show_report: rendert bericht_json eines HealthcheckRun als HTML
 *                 (siehe ReportRenderer)
 *
 * Die Route folgt dem iTop-3.2-Konventionsmuster (analog itomig-healthcheck):
 *   /pages/UI.php?route=itomig_healthreport.<operation>
 */
class HealthReportController extends Controller
{
    public const ROUTE_NAMESPACE = 'itomig_healthreport';

    public function __construct($sViewPath = '', $sModuleName = 'core', $aAdditionalPaths = [])
    {
        $sModuleName = 'itomig-healthreport';
        // Ueber __DIR__ statt MODULESROOT.'itomig-healthreport/...' aufloesen: der
        // physische Ordnername unter extensions/ muss so nicht mit dem Modul-Code
        // uebereinstimmen (z.B. beim Deploy als itomig-healthreport-extension).
        $sViewPath = dirname(__DIR__, 2) . '/templates';
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
            'sTransactionId'   => utils::GetNewTransactionId(),
            'sFormAction'      => utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?route=itomig_healthreport.upload',
            'sErrorMessage'    => (string) utils::ReadParam('err', '', false, 'raw_data'),
            'aOrganizations'   => $this->getOrganizations(),
            'sSelectedOrgId'   => (string) utils::ReadParam('org_id', '', false, 'raw_data'),
        ];

        $this->m_sOperation = 'UploadForm';
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

        $iOrgId = (int) utils::ReadPostedParam('org_id', '0', 'raw_data');

        try {
            $this->validateOrganization($iOrgId);
            $sTmpPath = $this->validateUpload();

            $oPipeline = new ReportPipeline();
            $aResult = $oPipeline->run($sTmpPath);

            $sZipBinary = (string) file_get_contents($sTmpPath);
            $sZipFilename = (string) ($_FILES['zip']['name'] ?? 'healthcheck.zip');

            $oPersister = new RunPersister();
            $iRunId = $oPersister->persist($aResult, $iOrgId, $sZipBinary, $sZipFilename);

            $this->redirectToRunDetails($iRunId);
        } catch (\Throwable $e) {
            $this->redirectToFormWithError(
                \Dict::Format('Itomig:HealthReport:Error:ProcessingFailed', $e->getMessage()),
                $iOrgId
            );
        }
    }

    /**
     * Liefert alle Organisationen (id, name) alphabetisch sortiert fuer das
     * Auswahl-Dropdown im Upload-Formular.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function getOrganizations(): array
    {
        $oSearch = \DBObjectSearch::FromOQL('SELECT Organization');
        $oSet = new \DBObjectSet($oSearch, ['name' => true]);

        $aOrganizations = [];
        while ($oOrg = $oSet->Fetch()) {
            $aOrganizations[] = [
                'id'   => (int) $oOrg->GetKey(),
                'name' => (string) $oOrg->Get('name'),
            ];
        }

        return $aOrganizations;
    }

    /**
     * Prueft, dass eine gueltige, existierende Organisation ausgewaehlt wurde.
     */
    private function validateOrganization(int $iOrgId): void
    {
        if ($iOrgId <= 0 || \MetaModel::GetObject('Organization', $iOrgId, false) === null) {
            throw new \RuntimeException(\Dict::S('Itomig:HealthReport:Error:NoOrganization'));
        }
    }

    /**
     * Prueft den Upload (Fehlercode, Herkunft, Dateiendung) und liefert den
     * temporaeren Pfad der hochgeladenen Datei.
     *
     * Muster identisch zur ehemaligen web/process.php; die eigentliche
     * Pruefung liegt in ZipUploadValidator, damit sie auch vom
     * Portal-Upload-Controller genutzt werden kann.
     */
    private function validateUpload(): string
    {
        return (new ZipUploadValidator())->validate();
    }

    /**
     * Rendert die Liste der ueber das Portal eingegangenen, noch nicht
     * ausgewerteten Uploads (GET).
     */
    public function OperationPendingUploads(): void
    {
        $oSearch = \DBObjectSearch::FromOQL('SELECT HealthcheckUpload');
        $oSet = new \DBObjectSet($oSearch, ['eingegangen_am' => false]);

        $aUploads = [];
        while ($oUpload = $oSet->Fetch()) {
            $oOrg = \MetaModel::GetObject('Organization', (int) $oUpload->Get('org_id'), false);
            $iRunId = (int) $oUpload->Get('run_id');
            $aUploads[] = [
                'id'             => (int) $oUpload->GetKey(),
                'org_name'       => $oOrg !== null ? (string) $oOrg->Get('name') : '',
                'eingegangen_am' => (string) $oUpload->Get('eingegangen_am'),
                'dateiname'      => (string) $oUpload->Get('dateiname'),
                'status'         => (string) $oUpload->Get('status'),
                'run_id'         => $iRunId,
                'run_url'        => $iRunId > 0
                    ? utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?operation=details&class=HealthcheckRun&id=' . $iRunId
                    : '',
            ];
        }

        $aParams = [
            'sTransactionId' => utils::GetNewTransactionId(),
            'sFormAction'    => utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?route=itomig_healthreport.process_upload',
            'sErrorMessage'  => (string) utils::ReadParam('err', '', false, 'raw_data'),
            'aUploads'       => $aUploads,
        ];

        $this->m_sOperation = 'PendingUploads';
        $this->DisplayPage($aParams);
    }

    /**
     * Stoesst die Auswertung eines im Portal eingegangenen Uploads an (POST).
     */
    public function OperationProcessUpload(): void
    {
        $sTransactionId = utils::ReadPostedParam('transaction_id', '', 'transaction_id');
        if (!utils::IsTransactionValid($sTransactionId)) {
            $this->redirectToPendingUploadsWithError(\Dict::S('Itomig:HealthReport:Error:InvalidToken'));
            return;
        }

        $iUploadId = (int) utils::ReadPostedParam('upload_id', '0', 'raw_data');

        try {
            $oProcessor = new UploadProcessor();
            $iRunId = $oProcessor->process($iUploadId);

            $this->redirectToRunDetails($iRunId);
        } catch (\Throwable $e) {
            $this->redirectToPendingUploadsWithError(
                \Dict::Format('Itomig:HealthReport:Error:ProcessingFailed', $e->getMessage())
            );
        }
    }

    /**
     * Liefert den Bericht eines HealthcheckRun als eigenstaendiges HTML aus
     * (GET) - ohne iTop-Seitenrahmen, da er im iframe des Reiters "Bericht"
     * bzw. in einem eigenen Fenster angezeigt wird.
     *
     * Ohne Parameter "category" die Management-Summary, sonst die
     * Detailansicht der Kategorie.
     */
    public function OperationShowReport(): void
    {
        $iRunId = (int) utils::ReadParam('id', 0, false, 'integer');
        $sCategory = (string) utils::ReadParam('category', '', false, 'raw_data');

        $oRenderer = new ReportRenderer();
        $oRun = $iRunId > 0 ? \MetaModel::GetObject('HealthcheckRun', $iRunId, false) : null;
        if ($oRun === null) {
            $this->sendReportResponse(404, $oRenderer->renderError(\Dict::S('Itomig:HealthReport:Error:RunNotFound')));
        }

        try {
            $sHtml = $oRenderer->render(
                (string) $oRun->Get('bericht_json'),
                $sCategory !== '' ? $sCategory : null,
                fn(?string $sLinkCategory): string => $this->getReportUrl($iRunId, $sLinkCategory)
            );
            $this->sendReportResponse(200, $sHtml);
        } catch (\RuntimeException $e) {
            $this->sendReportResponse(400, $oRenderer->renderError($e->getMessage()));
        }
    }

    private function getReportUrl(int $iRunId, ?string $sCategory = null): string
    {
        $sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?route=itomig_healthreport.show_report&id=' . $iRunId;
        if ($sCategory !== null) {
            $sUrl .= '&category=' . rawurlencode($sCategory);
        }

        return $sUrl;
    }

    private function sendReportResponse(int $iStatus, string $sHtml): never
    {
        http_response_code($iStatus);
        foreach (ReportRenderer::SECURITY_HEADERS as $sName => $sValue) {
            header($sName . ': ' . $sValue);
        }
        echo $sHtml;
        exit;
    }

    private function redirectToPendingUploadsWithError(string $sMessage): void
    {
        $sUrl = utils::GetAbsoluteUrlAppRoot()
            . 'pages/UI.php?route=itomig_healthreport.pending_uploads&err=' . rawurlencode($sMessage);
        header('Location: ' . $sUrl);
        exit;
    }

    private function redirectToRunDetails(int $iRunId): void
    {
        $sUrl = utils::GetAbsoluteUrlAppRoot() . 'pages/UI.php?operation=details&class=HealthcheckRun&id=' . $iRunId;
        header('Location: ' . $sUrl);
        exit;
    }

    private function redirectToFormWithError(string $sMessage, int $iOrgId = 0): void
    {
        $sUrl = utils::GetAbsoluteUrlAppRoot()
            . 'pages/UI.php?route=itomig_healthreport.show_form&err=' . rawurlencode($sMessage);
        if ($iOrgId > 0) {
            $sUrl .= '&org_id=' . $iOrgId;
        }
        header('Location: ' . $sUrl);
        exit;
    }
}
