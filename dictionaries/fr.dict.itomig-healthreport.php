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

    // Class: HealthcheckRun
    'Class:HealthcheckRun'                       => 'Exécution Healthcheck',
    'Class:HealthcheckRun+'                      => 'Une exécution du collecteur Healthcheck évaluée (import ZIP + rapports générés)',
    'Class:HealthcheckRun/Attribute:kunde'               => 'Client',
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

    // UI - Formulaire d'import
    'Itomig:HealthReport:UI:PageTitle'   => 'ITOMIG Healthcheck - Évaluation',
    'Itomig:HealthReport:UI:Intro'       => 'Importer le ZIP d\'une exécution du collecteur Healthcheck. Le ZIP est importé, tous les modules qu\'il contient sont évalués et enregistrés comme exécution Healthcheck.',
    'Itomig:HealthReport:UI:UploadLabel' => 'ZIP Healthcheck',
    'Itomig:HealthReport:UI:Submit'      => 'Importer et évaluer',
    'Itomig:HealthReport:UI:ShowReport'  => 'Afficher le rapport',

    // Erreurs
    'Itomig:HealthReport:Error:InvalidToken'  => 'Jeton de transaction invalide ou expiré. Veuillez recharger la page.',
    'Itomig:HealthReport:Error:NoFile'        => 'Aucun fichier n\'a été importé.',
    'Itomig:HealthReport:Error:UploadFailed'  => 'Échec de l\'import : %1$s',
    'Itomig:HealthReport:Error:NotUploaded'   => 'Erreur de sécurité : le fichier ne provient pas d\'un import HTTP.',
    'Itomig:HealthReport:Error:NotZip'        => 'Un fichier .zip est attendu.',
    'Itomig:HealthReport:Error:NoModule'      => 'Aucun module n\'a pu être évalué. Veuillez vérifier le contenu du ZIP.',
    'Itomig:HealthReport:Error:ProcessingFailed' => 'Traitement échoué : %1$s',
));
