<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Portal\Brick;

use Combodo\iTop\Portal\Brick\PortalBrick;

/**
 * Portal-Brick "Healthcheck hochladen": zeigt Kunden im Portal ein
 * Upload-Formular fuer ihr Healthcheck-Collector-ZIP.
 *
 * Nimmt das ZIP nur entgegen (HealthcheckUpload, Status "neu") -- die
 * eigentliche Auswertung (ReportPipeline/RunPersister) wird bewusst NICHT
 * hier angestossen, sondern erst manuell von ITOMIG in der Konsole
 * (siehe HealthReportController::OperationProcessUpload()).
 */
class HealthReportUploadBrick extends PortalBrick
{
    public const DEFAULT_DECORATION_CLASS_HOME = 'fas fa-file-upload';
    public const DEFAULT_DECORATION_CLASS_NAVIGATION_MENU = 'fas fa-file-upload fa-2x';
    public const DEFAULT_PAGE_TEMPLATE_PATH = 'itomig-healthreport/portal/templates/upload.html.twig';

    /** @var string|null Name der Symfony-Route, ueber die die Brick-Seite erreichbar ist. */
    public static $sRouteName = 'p_healthreport_upload';
}
