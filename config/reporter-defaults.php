<?php
/**
 * ITOMIG Healthcheck - Reporter-Defaults
 *
 * Diese Config enthält NUR Reporter-relevante Einstellungen (Schwellwerte,
 * Modul-Aktivierung, Output-Pfade). Keine API-/DB-Credentials, weil die
 * Datensammlung in der iTop-Extension läuft. Wird als Fallback geladen, wenn
 * keine vollständige config/healthcheck-config.php vorhanden ist (typischer
 * Fall: ITOMIG-Infrastruktur wertet eingehende ZIPs aus).
 *
 * Diese Datei ist Git-committed und enthält keine Geheimnisse.
 */

declare(strict_types=1);

return [
    // Wird beim Import aus manifest.json überschrieben
    'kunde' => [
        'name'     => '',
        'umgebung' => '',
    ],

    // Welche Module beim report-all ausgewertet werden
    'module' => [
        'design_analysis'      => true,
        'data_analysis'        => true,
        'synchro_analysis'     => true,
        'integration_analysis' => true,
        'system_analysis'      => true,
        'privacy_analysis'     => true,
        'table_overview'       => true,
        'column_fill'          => true,
        'object_freshness'     => true,
    ],

    // Reporter-Verhalten pro Modul (für Standard-ITOMIG-Reports)
    'integration' => [
        'pruefe_mail'          => true,
        'pruefe_webhooks'      => true,
        'pruefe_incoming_mail' => true,
    ],
    'system' => [
        'pruefe_php_config' => true,
        'pruefe_db'         => true,
        'pruefe_versionen'  => true,
    ],
    'datenschutz' => [
        'inaktiv_tage'    => 365,
        'pruefe_personen' => true,
        'pruefe_logs'     => true,
    ],

    // Schwellwerte/Default-Parameter (sofern der Reporter sie braucht;
    // Collector-Parameter wie 'synchro.fehler_tage' werden bevorzugt aus der
    // Raw-JSON-meta gelesen, damit der Reporter die exakte Sammel-Periode trifft)
    'daten' => [
        'veraltet_tage' => 365,
    ],
    'synchro' => [
        'fehler_tage'         => 30,
        'max_full_load_hours' => 24,
    ],

    'output' => [
        'daten_pfad'      => __DIR__ . '/../daten',
        'auswertung_pfad' => __DIR__ . '/../auswertung',
    ],
];
