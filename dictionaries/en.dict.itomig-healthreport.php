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

    // Class: HealthcheckRun
    'Class:HealthcheckRun'                       => 'Healthcheck Run',
    'Class:HealthcheckRun+'                      => 'An evaluated Healthcheck collector run (ZIP upload + generated reports)',
    'Class:HealthcheckRun/Attribute:kunde'               => 'Customer',
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

    // UI - Upload form
    'Itomig:HealthReport:UI:PageTitle'   => 'ITOMIG Healthcheck - Evaluation',
    'Itomig:HealthReport:UI:Intro'       => 'Upload a Healthcheck collector run ZIP. The ZIP is imported, all contained modules are evaluated and saved as a Healthcheck run.',
    'Itomig:HealthReport:UI:UploadLabel' => 'Healthcheck ZIP',
    'Itomig:HealthReport:UI:Submit'      => 'Upload and Evaluate',
    'Itomig:HealthReport:UI:ShowReport'  => 'Show Report',

    // Errors
    'Itomig:HealthReport:Error:InvalidToken'  => 'Invalid or expired transaction token. Please reload the page.',
    'Itomig:HealthReport:Error:NoFile'        => 'No file was uploaded.',
    'Itomig:HealthReport:Error:UploadFailed'  => 'Upload failed: %1$s',
    'Itomig:HealthReport:Error:NotUploaded'   => 'Security error: file does not originate from an HTTP upload.',
    'Itomig:HealthReport:Error:NotZip'        => 'A .zip file is expected.',
    'Itomig:HealthReport:Error:NoModule'      => 'No module could be evaluated. Please check the ZIP content.',
    'Itomig:HealthReport:Error:ProcessingFailed' => 'Processing failed: %1$s',
));
