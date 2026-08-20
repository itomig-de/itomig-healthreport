<?php

/**
 * Registriert die Portal-Route der Healthreport-Upload-Brick.
 *
 * Wird NICHT autogeladen (anderer Namespace-Kontext als das Standard-PSR-4
 * Mapping fuer Bricks), sondern explizit ueber portal-bootstrap.php
 * eingebunden -- Muster identisch zu approval-base/src/Portal/Router/ApprovalBrickRouter.php.
 */

use Combodo\iTop\Portal\Routing\ItopExtensionsExtraRoutes;

/** @noinspection PhpUnhandledExceptionInspection */
ItopExtensionsExtraRoutes::AddRoutes(
    array(
        array(
            'pattern'  => '/healthreport/upload/{sBrickId}',
            'callback' => 'Itomig\\iTop\\Extension\\HealthReport\\Portal\\Controller\\HealthReportUploadBrickController::DisplayAction',
            'bind'     => 'p_healthreport_upload',
        ),
        array(
            'pattern'  => '/healthreport/upload/{sBrickId}/submit',
            'callback' => 'Itomig\\iTop\\Extension\\HealthReport\\Portal\\Controller\\HealthReportUploadBrickController::SubmitAction',
            'bind'     => 'p_healthreport_upload_submit',
        ),
    )
);

if (!defined('ITOP_DESIGN_LATEST_VERSION')) {
    require_once APPROOT . 'setup/itopdesignformat.class.inc.php';
}
if (version_compare(ITOP_DESIGN_LATEST_VERSION, 3.1, '>=')) {
    /** @noinspection PhpUnhandledExceptionInspection */
    ItopExtensionsExtraRoutes::AddControllersClasses(
        array(
            'Itomig\iTop\Extension\HealthReport\Portal\Controller\HealthReportUploadBrickController',
        )
    );
}
