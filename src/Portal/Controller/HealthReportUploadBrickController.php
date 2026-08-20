<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Portal\Controller;

use Combodo\iTop\Portal\Brick\BrickCollection;
use Combodo\iTop\Portal\Controller\BrickController;
use DBObjectSearch;
use DBObjectSet;
use Dict;
use HealthcheckUpload;
use Itomig\iTop\Extension\HealthReport\Service\ZipUploadValidator;
use ormDocument;
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
     * Rendert das Upload-Formular + die Liste der eigenen Uploads (GET).
     */
    public function DisplayAction(Request $oRequest, $sBrickId)
    {
        $oBrickCollection = $this->oBrickCollection ?? $this->get('brick_collection');
        $oBrick = $oBrickCollection->GetBrickById($sBrickId);

        $iOrgId = $this->getCurrentUserOrgId();

        $aData = [
            'oBrick'         => $oBrick,
            'sBrickId'       => $sBrickId,
            'sTransactionId' => utils::GetNewTransactionId(),
            'sSubmitUrl'     => $this->generateUrl('p_healthreport_upload_submit', ['sBrickId' => $sBrickId]),
            'bHasOrg'        => $iOrgId > 0,
            'sMessage'       => (string) $oRequest->get('msg', ''),
            'sErrorMessage'  => (string) $oRequest->get('err', ''),
            'aUploads'       => $iOrgId > 0 ? $this->getOwnUploads($iOrgId) : [],
        ];

        return $this->render($oBrick->GetPageTemplatePath(), $aData);
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
     * @return array<int, array{eingegangen_am: string, dateiname: string, status: string}>
     */
    private function getOwnUploads(int $iOrgId): array
    {
        $oSearch = DBObjectSearch::FromOQL('SELECT HealthcheckUpload WHERE org_id = :org_id');
        $oSet = new DBObjectSet($oSearch, ['eingegangen_am' => false], ['org_id' => $iOrgId]);

        $aUploads = [];
        while ($oUpload = $oSet->Fetch()) {
            $aUploads[] = [
                'eingegangen_am' => (string) $oUpload->Get('eingegangen_am'),
                'dateiname'      => (string) $oUpload->Get('dateiname'),
                'status'         => (string) $oUpload->Get('status'),
            ];
        }

        return $aUploads;
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
