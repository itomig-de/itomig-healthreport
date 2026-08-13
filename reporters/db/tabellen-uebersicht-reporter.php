<?php
/**
 * ITOMIG Healthcheck - Reporter: DB-Tabellen-Übersicht
 *
 * Erzeugt HealthcheckReport mit Findings in der Kategorie 'tabellen'.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportTabellenUebersicht(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['kunde'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['umgebung'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['daten'] ?? [];
    $tabellen = $daten['tabellen'] ?? [];
    $gesamt = (int) ($daten['gesamt_datensaetze'] ?? 0);
    $exclude = $daten['konfiguration']['exclude_prefixes'] ?? [];

    $mitDaten = array_values(array_filter($tabellen, fn(array $t) => $t['datensaetze'] > 0));
    $leer = array_values(array_filter($tabellen, fn(array $t) => $t['datensaetze'] === 0));

    $report->setCategorySummary(
        'tabellen',
        sprintf(
            '%d Tabellen, %s Datensätze gesamt, %d mit Daten, %d leer.',
            count($tabellen),
            number_format($gesamt, 0, ',', '.'),
            count($mitDaten),
            count($leer)
        ),
        [
            'tabellen_gesamt'    => count($tabellen),
            'gesamt_datensaetze' => $gesamt,
            'tabellen_mit_daten' => count($mitDaten),
            'leere_tabellen'     => count($leer),
        ]
    );

    // Top-30-Tabellen
    $top = array_slice($mitDaten, 0, 30);
    $details = [];
    foreach ($top as $i => $row) {
        $details[] = [
            '#'           => (string) ($i + 1),
            'Tabelle'     => $row['tabelle'],
            'Datensätze'  => number_format($row['datensaetze'], 0, ',', '.'),
        ];
    }
    $report->addFinding(
        'tabellen',
        'Top-30-Tabellen nach Datensätzen',
        'info',
        'Die 30 größten Tabellen der iTop-Datenbank.',
        $details
    );

    // Leere Tabellen
    if (!empty($leer)) {
        $severity = count($leer) > 50 ? 'warning' : 'info';
        $report->addFinding(
            'tabellen',
            sprintf('Leere Tabellen (%d)', count($leer)),
            $severity,
            sprintf(
                '%d Tabellen enthalten keine Datensätze. Das kann auf nicht genutzte Module oder Klassen hinweisen.',
                count($leer)
            ),
            array_map(fn(array $t) => $t['tabelle'], $leer)
        );
    } else {
        $report->addFinding(
            'tabellen',
            'Keine leeren Tabellen',
            'ok',
            'Alle ermittelten Tabellen enthalten Datensätze.'
        );
    }

    // Filter-Hinweis
    if (!empty($exclude)) {
        $report->addFinding(
            'tabellen',
            'Filter-Hinweis',
            'info',
            'Folgende Tabellen-Präfixe wurden ausgeschlossen: ' . implode(', ', array_map(fn(string $p) => $p . '*', $exclude))
        );
    }

    return $report;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'tabellen-uebersicht');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "tabellen-uebersicht" gefunden.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportTabellenUebersicht($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'tabellen-uebersicht', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'tabellen-uebersicht', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
