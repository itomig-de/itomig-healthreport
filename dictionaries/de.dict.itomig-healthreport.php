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
    'Menu:ItomigHealthreportUploads'  => 'Eingegangene Uploads',
    'Menu:ItomigHealthreportUploads+' => 'Über das Portal von Kunden hochgeladene Healthcheck-ZIPs, noch nicht ausgewertet',

    // Class: HealthcheckRun
    'Class:HealthcheckRun'                       => 'Healthcheck-Lauf',
    'Class:HealthcheckRun+'                      => 'Ein ausgewerteter Healthcheck-Collector-Lauf (ZIP-Upload + generierte Reports)',
    'Class:HealthcheckRun/Attribute:kunde_id'            => 'Kunde',
    'Class:HealthcheckRun/Attribute:zip_kunde_name'      => 'Kunde laut ZIP',
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
    'Class:HealthcheckRun:context'                       => 'Kontext',
    'Class:HealthcheckRun:versionen'                      => 'Versionen',
    'Class:HealthcheckRun:dateien'                        => 'Dateien',

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
    'Class:HealthcheckModuleReport:info'                         => 'Informationen',
    'Class:HealthcheckModuleReport:dateien'                      => 'Dateien',

    // Class: HealthcheckUpload
    'Class:HealthcheckUpload'                      => 'Healthcheck-Upload',
    'Class:HealthcheckUpload+'                     => 'Ein von einem Kunden über das Portal hochgeladenes Healthcheck-Collector-ZIP, noch nicht ausgewertet',
    'Class:HealthcheckUpload/Attribute:org_id'             => 'Kunde',
    'Class:HealthcheckUpload/Attribute:hochgeladen_von_id' => 'Hochgeladen von',
    'Class:HealthcheckUpload/Attribute:eingegangen_am'     => 'Eingegangen am',
    'Class:HealthcheckUpload/Attribute:dateiname'          => 'Dateiname',
    'Class:HealthcheckUpload/Attribute:zip_datei'          => 'ZIP-Datei',
    'Class:HealthcheckUpload/Attribute:status'             => 'Status',
    'Class:HealthcheckUpload/Attribute:status/Value:neu'         => 'Neu',
    'Class:HealthcheckUpload/Attribute:status/Value:ausgewertet' => 'Ausgewertet',
    'Class:HealthcheckUpload/Attribute:status/Value:fehler'      => 'Fehler',
    'Class:HealthcheckUpload/Attribute:run_id'             => 'Healthcheck-Lauf',
    'Class:HealthcheckUpload/Attribute:fehlermeldung'      => 'Fehlermeldung',
    'Class:HealthcheckUpload:context'                      => 'Kontext',
    'Class:HealthcheckUpload:auswertung'                   => 'Auswertung',

    // Portal - Upload-Brick
    'Brick:Portal:HealthReportUpload:Title'  => 'Healthcheck hochladen',
    'Brick:Portal:HealthReportUpload:Title+' => 'ZIP eines Healthcheck-Collector-Laufs hochladen',
    'Itomig:HealthReport:Portal:Intro'       => 'Hier das ZIP eines Healthcheck-Collector-Laufs hochladen. ITOMIG wertet es aus und meldet sich mit den Ergebnissen.',
    'Itomig:HealthReport:Portal:OwnUploads'  => 'Meine Uploads',
    'Itomig:HealthReport:Portal:UploadSuccess' => 'Das ZIP wurde erfolgreich hochgeladen und wird von ITOMIG ausgewertet.',

    // UI - Eingegangene Uploads (Konsole)
    'Itomig:HealthReport:UI:PendingUploadsTitle' => 'ITOMIG Healthcheck - Eingegangene Uploads',
    'Itomig:HealthReport:UI:PendingUploadsIntro' => 'Über das Portal von Kunden hochgeladene Healthcheck-ZIPs. Auf "Auswerten" klicken, um die Reporter-Pipeline zu starten und einen Healthcheck-Lauf zu erzeugen.',
    'Itomig:HealthReport:UI:PendingUploadsEmpty' => 'Keine Uploads vorhanden.',
    'Itomig:HealthReport:UI:ProcessUpload'       => 'Auswerten',

    // UI - Upload-Formular
    'Itomig:HealthReport:UI:PageTitle'   => 'ITOMIG Healthcheck - Auswertung',
    'Itomig:HealthReport:UI:Intro'       => 'ZIP eines Healthcheck-Collector-Laufs hochladen. Das ZIP wird importiert, alle enthaltenen Module werden ausgewertet und als Healthcheck-Lauf gespeichert.',
    'Itomig:HealthReport:UI:UploadLabel' => 'Healthcheck-ZIP',
    'Itomig:HealthReport:UI:OrganizationLabel' => 'Kunde',
    'Itomig:HealthReport:UI:OrganizationPlaceholder' => 'Bitte Organisation wählen…',
    'Itomig:HealthReport:UI:Submit'      => 'Hochladen und auswerten',
    'Itomig:HealthReport:UI:ShowReport'  => 'Report anzeigen',

    // Fehler
    'Itomig:HealthReport:Error:InvalidToken'  => 'Ungültiges oder abgelaufenes Transaction-Token. Bitte Seite neu laden.',
    'Itomig:HealthReport:Error:NoFile'        => 'Es wurde keine Datei hochgeladen.',
    'Itomig:HealthReport:Error:UploadFailed'  => 'Upload fehlgeschlagen: %1$s',
    'Itomig:HealthReport:Error:NotUploaded'   => 'Sicherheitsfehler: Datei stammt nicht aus einem HTTP-Upload.',
    'Itomig:HealthReport:Error:NotZip'        => 'Erwartet wird eine .zip-Datei.',
    'Itomig:HealthReport:Error:NoModule'      => 'Kein Modul konnte ausgewertet werden. Bitte ZIP-Inhalt prüfen.',
    'Itomig:HealthReport:Error:NoOrganization' => 'Bitte eine Organisation (Kunde) auswählen.',
    'Itomig:HealthReport:Error:ProcessingFailed' => 'Verarbeitung fehlgeschlagen: %1$s',
    'Itomig:HealthReport:Error:UploadNotFound' => 'Upload nicht gefunden.',
    'Itomig:HealthReport:Error:UploadAlreadyProcessed' => 'Dieser Upload wurde bereits ausgewertet.',
    'Itomig:HealthReport:Error:TempFileFailed' => 'Temporäre Datei zur Verarbeitung konnte nicht angelegt werden.',
));
