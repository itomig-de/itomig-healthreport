<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2026 ITOMIG GmbH
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('EN US', 'English', 'English', array(
    // Menu
    'Menu:ItomigHealthreportMenu'   => 'Healthcheck',
    'Menu:ItomigHealthreportMenu+'  => 'ITOMIG Healthcheck - evaluation of incoming collector ZIPs',
    'Menu:ItomigHealthreportUpload'  => 'Upload Healthcheck',
    'Menu:ItomigHealthreportUpload+' => 'Upload and evaluate a Healthcheck collector run ZIP',
    'Menu:ItomigHealthreportRuns'    => 'Healthcheck Runs',
    'Menu:ItomigHealthreportRuns+'   => 'History of all evaluated Healthcheck runs',
    'Menu:ItomigHealthreportUploads'  => 'Pending Uploads',
    'Menu:ItomigHealthreportUploads+' => 'Healthcheck ZIPs uploaded by customers via the portal, not yet evaluated',

    // Class: HealthcheckRun
    'Class:HealthcheckRun'                       => 'Healthcheck Run',
    'Class:HealthcheckRun+'                      => 'An evaluated Healthcheck collector run (ZIP upload + generated reports)',
    'Class:HealthcheckRun/Attribute:kunde_id'            => 'Customer',
    'Class:HealthcheckRun/Attribute:kunde_name'          => 'Customer (Name)',
    'Class:HealthcheckRun/Attribute:zip_kunde_name'      => 'Customer per ZIP',
    'Class:HealthcheckRun/Attribute:zip_customer_url'    => 'App Root URL per ZIP',
    'Class:HealthcheckRun/Attribute:zip_instance_id'     => 'Instance ID per ZIP',
    'Class:HealthcheckRun/Attribute:pruefsumme_status'   => 'Checksum',
    'Class:HealthcheckRun/Attribute:pruefsumme_status/Value:geprueft'       => 'Verified',
    'Class:HealthcheckRun/Attribute:pruefsumme_status/Value:nicht_geprueft' => 'Not verified (legacy collector ZIP)',
    'Class:HealthcheckRun/Attribute:umgebung'            => 'Environment',
    'Class:HealthcheckRun/Attribute:zeitpunkt'           => 'Timestamp',
    'Class:HealthcheckRun/Attribute:itop_version'        => 'iTop Version',
    'Class:HealthcheckRun/Attribute:db_server_version'   => 'DB Server Version',
    'Class:HealthcheckRun/Attribute:extension_version'   => 'Collector Extension Version',
    'Class:HealthcheckRun/Attribute:ampel_gesamt'        => 'Overall Status',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:rot'   => 'Red',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:gelb'  => 'Yellow',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:gruen' => 'Green',
    'Class:HealthcheckRun/Attribute:modul_anzahl'        => 'Number of Evaluated Modules',
    'Class:HealthcheckRun/Attribute:zip_datei'           => 'Original ZIP',
    'Class:HealthcheckRun/Attribute:summary_html'        => 'Management Summary (HTML)',
    'Class:HealthcheckRun/Attribute:summary_json'        => 'Management Summary (JSON)',
    'Class:HealthcheckRun/Attribute:modul_reports'       => 'Module Reports',
    'Class:HealthcheckRun:context'                       => 'Context',
    'Class:HealthcheckRun:versionen'                      => 'Versions',
    'Class:HealthcheckRun:dateien'                        => 'Files',

    // Class: HealthcheckModuleReport
    'Class:HealthcheckModuleReport'                      => 'Healthcheck Module Report',
    'Class:HealthcheckModuleReport+'                     => 'The evaluated report of a single Healthcheck module within a run',
    'Class:HealthcheckModuleReport/Attribute:run_id'             => 'Healthcheck Run',
    'Class:HealthcheckModuleReport/Attribute:kategorie'          => 'Category',
    'Class:HealthcheckModuleReport/Attribute:label'              => 'Label',
    'Class:HealthcheckModuleReport/Attribute:ampel'              => 'Status',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:rot'    => 'Red',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:gelb'   => 'Yellow',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:gruen'  => 'Green',
    'Class:HealthcheckModuleReport/Attribute:befund_anzahl'      => 'Number of Findings',
    'Class:HealthcheckModuleReport/Attribute:report_html'        => 'Detail Report (HTML)',
    'Class:HealthcheckModuleReport/Attribute:report_json'        => 'Detail Report (JSON)',
    'Class:HealthcheckModuleReport:info'                         => 'Information',
    'Class:HealthcheckModuleReport:dateien'                      => 'Files',

    // Class: HealthcheckUpload
    'Class:HealthcheckUpload'                      => 'Healthcheck Upload',
    'Class:HealthcheckUpload+'                     => 'A Healthcheck collector ZIP uploaded by a customer via the portal, awaiting evaluation',
    'Class:HealthcheckUpload/Attribute:org_id'             => 'Customer',
    'Class:HealthcheckUpload/Attribute:hochgeladen_von_id' => 'Uploaded By',
    'Class:HealthcheckUpload/Attribute:eingegangen_am'     => 'Received At',
    'Class:HealthcheckUpload/Attribute:dateiname'          => 'File Name',
    'Class:HealthcheckUpload/Attribute:zip_datei'          => 'ZIP File',
    'Class:HealthcheckUpload/Attribute:status'             => 'Status',
    'Class:HealthcheckUpload/Attribute:status/Value:neu'         => 'New',
    'Class:HealthcheckUpload/Attribute:status/Value:ausgewertet' => 'Evaluated',
    'Class:HealthcheckUpload/Attribute:status/Value:fehler'      => 'Error',
    'Class:HealthcheckUpload/Attribute:run_id'             => 'Healthcheck Run',
    'Class:HealthcheckUpload/Attribute:fehlermeldung'      => 'Error Message',
    'Class:HealthcheckUpload:context'                      => 'Context',
    'Class:HealthcheckUpload:auswertung'                   => 'Evaluation',

    // Portal - Upload brick
    'Brick:Portal:HealthReportUpload:Title'  => 'Upload Healthcheck',
    'Brick:Portal:HealthReportUpload:Title+' => 'Upload a Healthcheck collector run ZIP',
    'Itomig:HealthReport:Portal:Intro'       => 'Upload the ZIP of a Healthcheck collector run here. ITOMIG will evaluate it and get in touch with the results.',
    'Itomig:HealthReport:Portal:OwnUploads'  => 'My Uploads',
    'Itomig:HealthReport:Portal:UploadSuccess' => 'The ZIP was uploaded successfully and will be evaluated by ITOMIG.',

    // UI - Pending uploads (console)
    'Itomig:HealthReport:UI:PendingUploadsTitle' => 'ITOMIG Healthcheck - Pending Uploads',
    'Itomig:HealthReport:UI:PendingUploadsIntro' => 'Healthcheck ZIPs uploaded by customers via the portal. Click "Evaluate" to run the reporter pipeline and create a Healthcheck run.',
    'Itomig:HealthReport:UI:PendingUploadsEmpty' => 'No uploads available.',
    'Itomig:HealthReport:UI:ProcessUpload'       => 'Evaluate',

    // UI - Upload form
    'Itomig:HealthReport:UI:PageTitle'   => 'ITOMIG Healthcheck - Evaluation',
    'Itomig:HealthReport:UI:Intro'       => 'Upload a Healthcheck collector run ZIP. The ZIP is imported, all contained modules are evaluated and saved as a Healthcheck run.',
    'Itomig:HealthReport:UI:UploadLabel' => 'Healthcheck ZIP',
    'Itomig:HealthReport:UI:UploadFieldsetLegend' => 'Upload ZIP',
    'Itomig:HealthReport:UI:OrganizationLabel' => 'Customer',
    'Itomig:HealthReport:UI:OrganizationPlaceholder' => 'Please select an organization…',
    'Itomig:HealthReport:UI:Submit'      => 'Upload and Evaluate',
    'Itomig:HealthReport:UI:ShowReport'  => 'Show Report',

    // Errors
    'Itomig:HealthReport:Error:InvalidToken'  => 'Invalid or expired transaction token. Please reload the page.',
    'Itomig:HealthReport:Error:NoFile'        => 'No file was uploaded.',
    'Itomig:HealthReport:Error:UploadFailed'  => 'Upload failed: %1$s',
    'Itomig:HealthReport:Error:NotUploaded'   => 'Security error: file does not originate from an HTTP upload.',
    'Itomig:HealthReport:Error:NotZip'        => 'A .zip file is expected.',
    'Itomig:HealthReport:Error:NoModule'      => 'No module could be evaluated. Please check the ZIP content.',
    'Itomig:HealthReport:Error:NoOrganization' => 'Please select an organization (customer).',
    'Itomig:HealthReport:Error:ProcessingFailed' => 'Processing failed: %1$s',
    'Itomig:HealthReport:Error:UploadNotFound' => 'Upload not found.',
    'Itomig:HealthReport:Error:UploadAlreadyProcessed' => 'This upload has already been evaluated.',
    'Itomig:HealthReport:Error:TempFileFailed' => 'Could not create a temporary file for processing.',
));
