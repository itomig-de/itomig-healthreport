<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Portal\Controller;

use Combodo\iTop\Portal\Brick\BrickCollection;
use Combodo\iTop\Portal\Brick\BrickNotFoundException;
use Combodo\iTop\Portal\Controller\BrickController;
use DBObjectSearch;
use DBObjectSet;
use Dict;
use HealthcheckRun;
use HealthcheckUpload;
use Itomig\iTop\Extension\HealthReport\Portal\Brick\HealthReportUploadBrick;
use Itomig\iTop\Extension\HealthReport\Service\ReportRenderer;
use Itomig\iTop\Extension\HealthReport\Service\ZipUploadValidator;
use ormDocument;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use UserRights;
use utils;

/**
 * Portal-Controller der Healthreport-Upload-Brick.
 *
 * Nimmt das Healthcheck-Collector-ZIP eines Kunden entgegen und legt es als
 * HealthcheckUpload (Status "neu") ab. Stoesst bewusst KEINE Auswertung an --
 * das geschieht separat in der Konsole ueber
 * HealthReportController::OperationProcessUpload() (siehe UploadProcessor).
 */
class HealthReportUploadBrickController extends BrickController
{
    /** @var BrickCollection|null */
    private $oBrickCollection;

    #[Required]
    public function SetBrickCollection(BrickCollection $oBrickCollection): void
    {
        $this->oBrickCollection = $oBrickCollection;
    }

    /**
     * Rendert das Upload-Formular + den letzten eigenen Upload (GET).
     */
    public function DisplayAction(Request $oRequest, $sBrickId)
    {
        $oBrick = $this->getBrick($sBrickId);

        $iOrgId = $this->getCurrentUserOrgId();
        $oLastRun = $iOrgId > 0 ? $this->getLastRun($iOrgId) : null;

        $aData = [
            'oBrick'         => $oBrick,
            'sBrickId'       => $sBrickId,
            'sTransactionId' => utils::GetNewTransactionId(),
            'sSubmitUrl'     => $this->generateUrl('p_healthreport_upload_submit', ['sBrickId' => $sBrickId]),
            'bHasOrg'        => $iOrgId > 0,
            'sMessage'       => (string) $oRequest->get('msg', ''),
            'sErrorMessage'  => (string) $oRequest->get('err', ''),
            'aLastUpload'    => $iOrgId > 0 ? $this->getLastUpload($iOrgId) : null,
            'aLastRun'       => $oLastRun !== null ? [
                'zeitpunkt'  => (string) $oLastRun->Get('zeitpunkt'),
                'report_url' => $this->generateUrl('p_healthreport_upload_report', ['sBrickId' => $sBrickId]),
            ] : null,
        ];

        return $this->render($oBrick->GetPageTemplatePath(), $aData);
    }

    /**
     * Liefert den aktuellsten Bericht der eigenen Organisation als
     * eigenstaendiges HTML (GET) - wird per iframe in die Brick-Seite
     * eingebettet. Ohne Query-Parameter "category" die Management-Summary,
     * sonst die Detailansicht der Kategorie.
     *
     * Zugriff: nur ueber eine fuer das Profil freigegebene Brick (getBrick())
     * und nur auf Laeufe der eigenen Organisation (getLastRun()).
     */
    public function ReportAction(Request $oRequest, $sBrickId)
    {
        $this->getBrick($sBrickId);
        $oRenderer = new ReportRenderer();

        $iOrgId = $this->getCurrentUserOrgId();
        if ($iOrgId <= 0) {
            return $this->reportResponse(403, $oRenderer->renderError(Dict::S('Itomig:HealthReport:Error:NoOrganization')));
        }

        $oRun = $this->getLastRun($iOrgId);
        if ($oRun === null) {
            return $this->reportResponse(404, $oRenderer->renderError(Dict::S('Itomig:HealthReport:Error:NoReport')));
        }

        $sCategory = (string) $oRequest->query->get('category', '');

        try {
            $sHtml = $oRenderer->render(
                (string) $oRun->Get('bericht_json'),
                $sCategory !== '' ? $sCategory : null,
                fn(?string $sLinkCategory): string => $this->generateUrl(
                    'p_healthreport_upload_report',
                    $sLinkCategory !== null
                        ? ['sBrickId' => $sBrickId, 'category' => $sLinkCategory]
                        : ['sBrickId' => $sBrickId]
                )
            );

            return $this->reportResponse(200, $sHtml);
        } catch (RuntimeException $e) {
            return $this->reportResponse(400, $oRenderer->renderError($e->getMessage()));
        }
    }

    /**
     * Verarbeitet den Upload (POST) und legt einen HealthcheckUpload an.
     */
    public function SubmitAction(Request $oRequest, $sBrickId)
    {
        $sTransactionId = (string) $oRequest->request->get('transaction_id', '');
        if (!utils::IsTransactionValid($sTransactionId)) {
            return $this->redirectWithMessage($sBrickId, null, Dict::S('Itomig:HealthReport:Error:InvalidToken'));
        }

        $iOrgId = $this->getCurrentUserOrgId();
        if ($iOrgId <= 0) {
            return $this->redirectWithMessage($sBrickId, null, Dict::S('Itomig:HealthReport:Error:NoOrganization'));
        }

        try {
            $sTmpPath = (new ZipUploadValidator())->validate();

            $oUpload = new HealthcheckUpload();
            $oUpload->Set('org_id', $iOrgId);
            $oUpload->Set('hochgeladen_von_id', UserRights::GetContactObject()->GetKey());
            $oUpload->Set('eingegangen_am', date('Y-m-d H:i:s'));
            $oUpload->Set('dateiname', (string) ($_FILES['zip']['name'] ?? 'healthcheck.zip'));
            $oUpload->Set('zip_datei', new ormDocument(
                (string) file_get_contents($sTmpPath),
                'application/zip',
                (string) ($_FILES['zip']['name'] ?? 'healthcheck.zip')
            ));
            $oUpload->Set('status', 'neu');
            $oUpload->DBInsert();

            return $this->redirectWithMessage($sBrickId, Dict::S('Itomig:HealthReport:Portal:UploadSuccess'), null);
        } catch (Throwable $e) {
            return $this->redirectWithMessage($sBrickId, null, $e->getMessage());
        }
    }

    /**
     * Liefert nur den zuletzt eingegangenen Upload der Organisation (Issue #6) --
     * kein voller Verlauf.
     *
     * @return array{eingegangen_am: string, dateiname: string, status: string}|null
     */
    private function getLastUpload(int $iOrgId): ?array
    {
        $oSearch = DBObjectSearch::FromOQL('SELECT HealthcheckUpload WHERE org_id = :org_id');
        $oSet = new DBObjectSet($oSearch, ['eingegangen_am' => false], ['org_id' => $iOrgId]);
        $oSet->SetLimit(1);

        $oUpload = $oSet->Fetch();
        if ($oUpload === null) {
            return null;
        }

        return [
            'eingegangen_am' => (string) $oUpload->Get('eingegangen_am'),
            'dateiname'      => (string) $oUpload->Get('dateiname'),
            'status'         => (string) $oUpload->Get('status'),
        ];
    }

    /**
     * Liefert den aktuellsten HealthcheckRun der Organisation oder null.
     *
     * Portal-Profile haben bewusst keine Leserechte auf HealthcheckRun (die
     * Klasse bleibt admin-only). Deshalb wird hier nach der Pruefung der
     * eigenen Organisation gezielt mit AllowAllData() gelesen - die
     * Einschraenkung auf kunde_id = Organisation des Kontakts ersetzt den
     * fehlenden Portal-Scope.
     */
    private function getLastRun(int $iOrgId): ?HealthcheckRun
    {
        $oSearch = DBObjectSearch::FromOQL('SELECT HealthcheckRun WHERE kunde_id = :org_id');
        $oSearch->AllowAllData();
        $oSet = new DBObjectSet($oSearch, ['zeitpunkt' => false], ['org_id' => $iOrgId]);
        $oSet->SetLimit(1);

        $oRun = $oSet->Fetch();

        return $oRun instanceof HealthcheckRun ? $oRun : null;
    }

    /**
     * Loest die Brick ueber die BrickCollection auf. Diese enthaelt nur Bricks,
     * die fuer die Profile des angemeldeten Nutzers freigegeben sind - sonst
     * BrickNotFoundException.
     */
    private function getBrick(string $sBrickId): HealthReportUploadBrick
    {
        $oBrickCollection = $this->oBrickCollection ?? $this->get('brick_collection');
        $oBrick = $oBrickCollection->GetBrickById($sBrickId);
        if (!$oBrick instanceof HealthReportUploadBrick) {
            throw new BrickNotFoundException('Brick with id = "' . $sBrickId . '" is not a Healthcheck upload brick.');
        }

        return $oBrick;
    }

    private function reportResponse(int $iStatus, string $sHtml): Response
    {
        return new Response($sHtml, $iStatus, ReportRenderer::SECURITY_HEADERS);
    }

    /**
     * Liefert die Organisation des angemeldeten Portal-Kontakts, 0 falls keine
     * Organisation zugeordnet ist.
     */
    private function getCurrentUserOrgId(): int
    {
        $oContact = UserRights::GetContactObject();
        if ($oContact === null) {
            return 0;
        }

        return (int) $oContact->Get('org_id');
    }

    private function redirectWithMessage(string $sBrickId, ?string $sMessage, ?string $sError): RedirectResponse
    {
        $aParams = ['sBrickId' => $sBrickId];
        if ($sMessage !== null) {
            $aParams['msg'] = $sMessage;
        }
        if ($sError !== null) {
            $aParams['err'] = $sError;
        }

        return $this->redirect($this->generateUrl('p_healthreport_upload', $aParams));
    }
}
