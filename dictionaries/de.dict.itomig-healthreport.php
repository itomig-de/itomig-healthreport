<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2026 ITOMIG GmbH
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('DE DE', 'German', 'Deutsch', array(
    // Menu
    'Menu:ItomigHealthreportMenu'   => 'Healthcheck',
    'Menu:ItomigHealthreportMenu+'  => 'ITOMIG Healthcheck - Auswertung eingehender Collector-ZIPs',
    'Menu:ItomigHealthreportUpload'  => 'Healthcheck hochladen',
    'Menu:ItomigHealthreportUpload+' => 'ZIP eines Healthcheck-Collector-Laufs hochladen und auswerten',
    'Menu:ItomigHealthreportRuns'    => 'Healthcheck-Läufe',
    'Menu:ItomigHealthreportRuns+'   => 'Historie aller ausgewerteten Healthcheck-Läufe',

    // Class: HealthcheckRun
    'Class:HealthcheckRun'                       => 'Healthcheck-Lauf',
    'Class:HealthcheckRun+'                      => 'Ein ausgewerteter Healthcheck-Collector-Lauf (ZIP-Upload + generierte Reports)',
    'Class:HealthcheckRun/Attribute:kunde'               => 'Kunde',
    'Class:HealthcheckRun/Attribute:umgebung'            => 'Umgebung',
    'Class:HealthcheckRun/Attribute:zeitpunkt'           => 'Zeitpunkt',
    'Class:HealthcheckRun/Attribute:itop_version'        => 'iTop-Version',
    'Class:HealthcheckRun/Attribute:db_server_version'   => 'DB-Server-Version',
    'Class:HealthcheckRun/Attribute:extension_version'   => 'Collector-Extension-Version',
    'Class:HealthcheckRun/Attribute:ampel_gesamt'        => 'Gesamt-Ampel',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:rot'   => 'Rot',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:gelb'  => 'Gelb',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:gruen' => 'Grün',
    'Class:HealthcheckRun/Attribute:modul_anzahl'        => 'Anzahl ausgewerteter Module',
    'Class:HealthcheckRun/Attribute:zip_datei'           => 'Original-ZIP',
    'Class:HealthcheckRun/Attribute:summary_html'        => 'Management-Summary (HTML)',
    'Class:HealthcheckRun/Attribute:summary_json'        => 'Management-Summary (JSON)',
    'Class:HealthcheckRun/Attribute:modul_reports'       => 'Modul-Reports',

    // Class: HealthcheckModuleReport
    'Class:HealthcheckModuleReport'                      => 'Healthcheck-Modul-Report',
    'Class:HealthcheckModuleReport+'                     => 'Der ausgewertete Report eines einzelnen Healthcheck-Moduls innerhalb eines Laufs',
    'Class:HealthcheckModuleReport/Attribute:run_id'             => 'Healthcheck-Lauf',
    'Class:HealthcheckModuleReport/Attribute:kategorie'          => 'Kategorie',
    'Class:HealthcheckModuleReport/Attribute:label'              => 'Bezeichnung',
    'Class:HealthcheckModuleReport/Attribute:ampel'              => 'Ampel',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:rot'    => 'Rot',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:gelb'   => 'Gelb',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:gruen'  => 'Grün',
    'Class:HealthcheckModuleReport/Attribute:befund_anzahl'      => 'Anzahl Befunde',
    'Class:HealthcheckModuleReport/Attribute:report_html'        => 'Detail-Report (HTML)',
    'Class:HealthcheckModuleReport/Attribute:report_json'        => 'Detail-Report (JSON)',

    // UI - Upload-Formular
    'Itomig:HealthReport:UI:PageTitle'   => 'ITOMIG Healthcheck - Auswertung',
    'Itomig:HealthReport:UI:Intro'       => 'ZIP eines Healthcheck-Collector-Laufs hochladen. Das ZIP wird importiert, alle enthaltenen Module werden ausgewertet und als Healthcheck-Lauf gespeichert.',
    'Itomig:HealthReport:UI:UploadLabel' => 'Healthcheck-ZIP',
    'Itomig:HealthReport:UI:Submit'      => 'Hochladen und auswerten',
    'Itomig:HealthReport:UI:ShowReport'  => 'Report anzeigen',

    // Fehler
    'Itomig:HealthReport:Error:InvalidToken'  => 'Ungültiges oder abgelaufenes Transaction-Token. Bitte Seite neu laden.',
    'Itomig:HealthReport:Error:NoFile'        => 'Es wurde keine Datei hochgeladen.',
    'Itomig:HealthReport:Error:UploadFailed'  => 'Upload fehlgeschlagen: %1$s',
    'Itomig:HealthReport:Error:NotUploaded'   => 'Sicherheitsfehler: Datei stammt nicht aus einem HTTP-Upload.',
    'Itomig:HealthReport:Error:NotZip'        => 'Erwartet wird eine .zip-Datei.',
    'Itomig:HealthReport:Error:NoModule'      => 'Kein Modul konnte ausgewertet werden. Bitte ZIP-Inhalt prüfen.',
    'Itomig:HealthReport:Error:ProcessingFailed' => 'Verarbeitung fehlgeschlagen: %1$s',
));
