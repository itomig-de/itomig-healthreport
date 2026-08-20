<?php
/**
 * Bindet die Portal-Route der Healthreport-Upload-Brick ein.
 *
 * Wird nur wirksam, wenn das iTop-Portal (itop-portal-base) installiert ist --
 * die Extension bleibt ohne Portal weiterhin voll funktionsfaehig (Konsole
 * unveraendert). Muster identisch zu
 * datamodels/2.x/approval-base/compatibilitybridge.php.
 */

if (
    is_dir(MODULESROOT . 'itop-portal-base')
    && file_exists(MODULESROOT . 'itop-portal-base/portal/vendor/autoload.php')
) {
    // Autoloader des Portal-Frameworks (Symfony, Combodo\iTop\Portal\*)
    require_once MODULESROOT . 'itop-portal-base/portal/vendor/autoload.php';
    // Eigener Composer-Autoloader (Itomig\iTop\Extension\HealthReport\*)
    require_once __DIR__ . '/vendor/autoload.php';
    // Muss explizit eingebunden werden, um die Portal-Route zu registrieren
    require_once __DIR__ . '/src/Portal/Router/HealthReportPortalRouter.php';
}
