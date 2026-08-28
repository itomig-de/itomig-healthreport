<?php
/**
 * ITOMIG Healthcheck - Reporter: DB-Objekt-Aktualität
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportObjectFreshness(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['db_name'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $klassen = $raw['data']['classes'] ?? [];

    $aktiv7 = 0;
    $aktiv30 = 0;
    $aktiv90 = 0;
    $aktiv365 = 0;
    $inaktivUeber1Jahr = 0;
    $inaktivKlassen = [];

    foreach ($klassen as $k) {
        $tage = (int) ($k['last_change_age_days'] ?? 0);
        if ($tage <= 7) {
            $aktiv7++;
        }
        if ($tage <= 30) {
            $aktiv30++;
        }
        if ($tage <= 90) {
            $aktiv90++;
        }
        if ($tage <= 365) {
            $aktiv365++;
        } else {
            $inaktivUeber1Jahr++;
            $inaktivKlassen[] = [
                'Klasse'         => $k['class'],
                'Letzte Änderung' => substr((string) $k['last_change'], 0, 10),
                'Alter (Tage)'   => (string) $tage,
                'Objekte'        => number_format($k['objects_changed'], 0, ',', '.'),
            ];
        }
    }

    $report->setCategorySummary(
        'freshness',
        sprintf(
            '%d Klassen analysiert: %d aktiv (≤7 Tage), %d (≤30 Tage), %d (≤90 Tage), %d inaktiv (>1 Jahr).',
            count($klassen),
            $aktiv7,
            $aktiv30,
            $aktiv90,
            $inaktivUeber1Jahr
        ),
        [
            'klassen_gesamt'       => count($klassen),
            'aktiv_7_tage'         => $aktiv7,
            'aktiv_30_tage'        => $aktiv30,
            'aktiv_90_tage'        => $aktiv90,
            'aktiv_365_tage'       => $aktiv365,
            'inaktiv_ueber_1_jahr' => $inaktivUeber1Jahr,
        ]
    );

    // Aktive Klassen (Top 20 nach Aktualität)
    $top = array_slice($klassen, 0, 20);
    $topRows = [];
    foreach ($top as $i => $k) {
        $topRows[] = [
            '#'                  => (string) ($i + 1),
            'Klasse'             => $k['class'],
            'Letzte Änderung'    => substr((string) $k['last_change'], 0, 10),
            'Alter (Tage)'       => (string) $k['last_change_age_days'],
            'Neueste Erstellung' => $k['latest_creation'] !== null ? substr((string) $k['latest_creation'], 0, 10) : '-',
            'Objekte geändert'   => number_format($k['objects_changed'], 0, ',', '.'),
            'Objekte erstellt'   => number_format($k['objects_created'], 0, ',', '.'),
        ];
    }
    $report->addFinding(
        'freshness',
        'Aktuellste Klassen (Top 20)',
        'info',
        'Klassen mit der jüngsten Änderung — sortiert nach Alter aufsteigend.',
        $topRows
    );

    if (!empty($inaktivKlassen)) {
        $severity = count($inaktivKlassen) > 30 ? 'warning' : 'info';
        $report->addFinding(
            'freshness',
            sprintf('Inaktive Klassen (%d, >1 Jahr ohne Änderung)', count($inaktivKlassen)),
            $severity,
            'Klassen, deren letzte Änderung länger als ein Jahr zurückliegt. '
            . 'Prüfen Sie, ob diese Klassen tatsächlich noch in Nutzung sind oder ob sie aus dem Datenmodell entfernt werden können.',
            $inaktivKlassen
        );
    } else {
        $report->addFinding(
            'freshness',
            'Keine inaktiven Klassen',
            'ok',
            'Alle analysierten Klassen wurden in den letzten 12 Monaten geändert.'
        );
    }

    return $report;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'object-freshness');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "object-freshness" gefunden.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportObjectFreshness($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'object-freshness', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'object-freshness', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
