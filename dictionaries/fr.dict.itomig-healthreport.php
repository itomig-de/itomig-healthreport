<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2026 ITOMIG GmbH
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('FR FR', 'French', 'Français', array(
    // Menu
    'Menu:ItomigHealthreportMenu'   => 'Healthcheck',
    'Menu:ItomigHealthreportMenu+'  => 'ITOMIG Healthcheck - évaluation des ZIP de collecte entrants',
    'Menu:ItomigHealthreportUpload'  => 'Importer un Healthcheck',
    'Menu:ItomigHealthreportUpload+' => 'Importer et évaluer le ZIP d\'une exécution du collecteur Healthcheck',
    'Menu:ItomigHealthreportRuns'    => 'Exécutions Healthcheck',
    'Menu:ItomigHealthreportRuns+'   => 'Historique de toutes les exécutions Healthcheck évaluées',
    'Menu:ItomigHealthreportUploads'  => 'Imports en attente',
    'Menu:ItomigHealthreportUploads+' => 'ZIP Healthcheck importés par les clients via le portail, pas encore évalués',

    // Class: HealthcheckRun
    'Class:HealthcheckRun'                       => 'Exécution Healthcheck',
    'Class:HealthcheckRun+'                      => 'Une exécution du collecteur Healthcheck évaluée (import ZIP + rapports générés)',
    'Class:HealthcheckRun/Attribute:kunde_id'            => 'Client',
    'Class:HealthcheckRun/Attribute:kunde_name'          => 'Client (nom)',
    'Class:HealthcheckRun/Attribute:zip_kunde_name'      => 'Client selon le ZIP',
    'Class:HealthcheckRun/Attribute:zip_customer_url'    => 'URL racine (app_root_url) selon le ZIP',
    'Class:HealthcheckRun/Attribute:zip_instance_id'     => 'Identifiant d\'instance selon le ZIP',
    'Class:HealthcheckRun/Attribute:pruefsumme_status'   => 'Somme de contrôle',
    'Class:HealthcheckRun/Attribute:pruefsumme_status/Value:geprueft'       => 'Vérifiée',
    'Class:HealthcheckRun/Attribute:pruefsumme_status/Value:nicht_geprueft' => 'Non vérifiée (ZIP d\'un ancien collecteur)',
    'Class:HealthcheckRun/Attribute:umgebung'            => 'Environnement',
    'Class:HealthcheckRun/Attribute:zeitpunkt'           => 'Horodatage',
    'Class:HealthcheckRun/Attribute:itop_version'        => 'Version iTop',
    'Class:HealthcheckRun/Attribute:db_server_version'   => 'Version du serveur de base de données',
    'Class:HealthcheckRun/Attribute:extension_version'   => 'Version de l\'extension collecteur',
    'Class:HealthcheckRun/Attribute:ampel_gesamt'        => 'Statut global',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:rot'   => 'Rouge',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:gelb'  => 'Jaune',
    'Class:HealthcheckRun/Attribute:ampel_gesamt/Value:gruen' => 'Vert',
    'Class:HealthcheckRun/Attribute:modul_anzahl'        => 'Nombre de modules évalués',
    'Class:HealthcheckRun/Attribute:zip_datei'           => 'ZIP original',
    'Class:HealthcheckRun/Attribute:summary_html'        => 'Synthèse (HTML)',
    'Class:HealthcheckRun/Attribute:summary_json'        => 'Synthèse (JSON)',
    'Class:HealthcheckRun/Attribute:modul_reports'       => 'Rapports de module',
    'Class:HealthcheckRun:context'                       => 'Contexte',
    'Class:HealthcheckRun:versionen'                      => 'Versions',
    'Class:HealthcheckRun:dateien'                        => 'Fichiers',

    // Class: HealthcheckModuleReport
    'Class:HealthcheckModuleReport'                      => 'Rapport de module Healthcheck',
    'Class:HealthcheckModuleReport+'                     => 'Le rapport évalué d\'un module Healthcheck au sein d\'une exécution',
    'Class:HealthcheckModuleReport/Attribute:run_id'             => 'Exécution Healthcheck',
    'Class:HealthcheckModuleReport/Attribute:kategorie'          => 'Catégorie',
    'Class:HealthcheckModuleReport/Attribute:label'              => 'Libellé',
    'Class:HealthcheckModuleReport/Attribute:ampel'              => 'Statut',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:rot'    => 'Rouge',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:gelb'   => 'Jaune',
    'Class:HealthcheckModuleReport/Attribute:ampel/Value:gruen'  => 'Vert',
    'Class:HealthcheckModuleReport/Attribute:befund_anzahl'      => 'Nombre de constats',
    'Class:HealthcheckModuleReport/Attribute:report_html'        => 'Rapport détaillé (HTML)',
    'Class:HealthcheckModuleReport/Attribute:report_json'        => 'Rapport détaillé (JSON)',
    'Class:HealthcheckModuleReport:info'                         => 'Informations',
    'Class:HealthcheckModuleReport:dateien'                      => 'Fichiers',

    // Class: HealthcheckUpload
    'Class:HealthcheckUpload'                      => 'Import Healthcheck',
    'Class:HealthcheckUpload+'                     => 'Un ZIP du collecteur Healthcheck importé par un client via le portail, pas encore évalué',
    'Class:HealthcheckUpload/Attribute:org_id'             => 'Client',
    'Class:HealthcheckUpload/Attribute:hochgeladen_von_id' => 'Importé par',
    'Class:HealthcheckUpload/Attribute:eingegangen_am'     => 'Reçu le',
    'Class:HealthcheckUpload/Attribute:dateiname'          => 'Nom du fichier',
    'Class:HealthcheckUpload/Attribute:zip_datei'          => 'Fichier ZIP',
    'Class:HealthcheckUpload/Attribute:status'             => 'Statut',
    'Class:HealthcheckUpload/Attribute:status/Value:neu'         => 'Nouveau',
    'Class:HealthcheckUpload/Attribute:status/Value:ausgewertet' => 'Évalué',
    'Class:HealthcheckUpload/Attribute:status/Value:fehler'      => 'Erreur',
    'Class:HealthcheckUpload/Attribute:run_id'             => 'Exécution Healthcheck',
    'Class:HealthcheckUpload/Attribute:fehlermeldung'      => 'Message d\'erreur',
    'Class:HealthcheckUpload:context'                      => 'Contexte',
    'Class:HealthcheckUpload:auswertung'                   => 'Évaluation',

    // Portail - Brique d'import
    'Brick:Portal:HealthReportUpload:Title'  => 'Importer un Healthcheck',
    'Brick:Portal:HealthReportUpload:Title+' => 'Importer le ZIP d\'une exécution du collecteur Healthcheck',
    'Itomig:HealthReport:Portal:Intro'       => 'Importez ici le ZIP d\'une exécution du collecteur Healthcheck. ITOMIG l\'évaluera et reviendra vers vous avec les résultats.',
    'Itomig:HealthReport:Portal:OwnUploads'  => 'Mes imports',
    'Itomig:HealthReport:Portal:UploadSuccess' => 'Le ZIP a été importé avec succès et sera évalué par ITOMIG.',

    // UI - Imports en attente (console)
    'Itomig:HealthReport:UI:PendingUploadsTitle' => 'ITOMIG Healthcheck - Imports en attente',
    'Itomig:HealthReport:UI:PendingUploadsIntro' => 'ZIP Healthcheck importés par les clients via le portail. Cliquez sur "Évaluer" pour lancer la chaîne de reporting et créer une exécution Healthcheck.',
    'Itomig:HealthReport:UI:PendingUploadsEmpty' => 'Aucun import disponible.',
    'Itomig:HealthReport:UI:ProcessUpload'       => 'Évaluer',

    // UI - Formulaire d'import
    'Itomig:HealthReport:UI:PageTitle'   => 'ITOMIG Healthcheck - Évaluation',
    'Itomig:HealthReport:UI:Intro'       => 'Importer le ZIP d\'une exécution du collecteur Healthcheck. Le ZIP est importé, tous les modules qu\'il contient sont évalués et enregistrés comme exécution Healthcheck.',
    'Itomig:HealthReport:UI:UploadLabel' => 'ZIP Healthcheck',
    'Itomig:HealthReport:UI:UploadFieldsetLegend' => 'Importer le ZIP',
    'Itomig:HealthReport:UI:OrganizationLabel' => 'Client',
    'Itomig:HealthReport:UI:OrganizationPlaceholder' => 'Veuillez choisir une organisation…',
    'Itomig:HealthReport:UI:Submit'      => 'Importer et évaluer',
    'Itomig:HealthReport:UI:ShowReport'  => 'Afficher le rapport',

    // Erreurs
    'Itomig:HealthReport:Error:InvalidToken'  => 'Jeton de transaction invalide ou expiré. Veuillez recharger la page.',
    'Itomig:HealthReport:Error:NoFile'        => 'Aucun fichier n\'a été importé.',
    'Itomig:HealthReport:Error:UploadFailed'  => 'Échec de l\'import : %1$s',
    'Itomig:HealthReport:Error:NotUploaded'   => 'Erreur de sécurité : le fichier ne provient pas d\'un import HTTP.',
    'Itomig:HealthReport:Error:NotZip'        => 'Un fichier .zip est attendu.',
    'Itomig:HealthReport:Error:NoModule'      => 'Aucun module n\'a pu être évalué. Veuillez vérifier le contenu du ZIP.',
    'Itomig:HealthReport:Error:NoOrganization' => 'Veuillez choisir une organisation (client).',
    'Itomig:HealthReport:Error:ProcessingFailed' => 'Traitement échoué : %1$s',
    'Itomig:HealthReport:Error:UploadNotFound' => 'Import introuvable.',
    'Itomig:HealthReport:Error:UploadAlreadyProcessed' => 'Cet import a déjà été évalué.',
    'Itomig:HealthReport:Error:TempFileFailed' => 'Impossible de créer un fichier temporaire pour le traitement.',
));
