<?php
/**
 * ITOMIG Healthcheck - Reporter: Error-Log
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportErrorLog(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['db_name'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];

    if (($daten['error'] ?? null) !== null) {
        $report->addFinding(
            'error-log',
            'Error-Log nicht lesbar',
            'info',
            'Die Datei log/error.log konnte nicht gelesen werden: ' . $daten['error']
        );
        return $report;
    }

    if (!($daten['file']['exists'] ?? false)) {
        $report->addFinding(
            'error-log',
            'Kein Error-Log vorhanden',
            'ok',
            'Die Datei log/error.log existiert nicht. Das kann auf einen sehr sauberen Betrieb '
            . 'oder auf eine externe Log-Rotation hinweisen.'
        );
        return $report;
    }

    bewerteLevelUebersicht($report, $daten['summary']['by_level'] ?? [], $daten['file'] ?? []);
    bewerteWiederkehrendeMuster($report, $daten['patterns'] ?? []);

    return $report;
}

function bewerteLevelUebersicht(HealthcheckReport $report, array $byLevel, array $file): void
{
    $errorCount = (int) ($byLevel['Error'] ?? 0);
    $warningCount = (int) ($byLevel['Warning'] ?? 0);
    $total = array_sum($byLevel);

    $report->setCategorySummary(
        'error-log',
        "$total Log-Zeile(n) im gelesenen Fenster ($errorCount Error, $warningCount Warning).",
        $byLevel
    );

    $severity = 'ok';
    if ($errorCount > 0) {
        $severity = $errorCount > 50 ? 'critical' : 'warning';
    }

    $zeitraum = trim(($file['first_entry'] ?? '') . ' – ' . ($file['last_entry'] ?? ''), ' –');

    $report->addFinding(
        'error-log',
        "Log-Level-Übersicht ($total Zeilen)",
        $severity,
        "Im ausgewerteten Fenster ($zeitraum) wurden $errorCount Error- und $warningCount Warning-Meldungen "
        . 'protokolliert (jeweils die letzten Bytes von log/error.log, keine rotierten Dateien).',
        $byLevel
    );
}

function bewerteWiederkehrendeMuster(HealthcheckReport $report, array $patterns): void
{
    if (empty($patterns)) {
        $report->addFinding(
            'error-log',
            'Keine wiederkehrenden Muster erkannt',
            'ok',
            'Im ausgewerteten Fenster wurden keine Fehlermuster mit mehreren Vorkommen gefunden.'
        );
        return;
    }

    $wiederkehrend = array_filter($patterns, fn($p) => ($p['distinct_days'] ?? 0) >= 3);

    $details = [];
    foreach ($patterns as $p) {
        $details[] = [
            'Level'      => $p['level'] ?? '',
            'Meldung'    => $p['message'] ?? '',
            'Anzahl'     => $p['count'] ?? 0,
            'Tage'       => $p['distinct_days'] ?? 0,
            'Zuletzt'    => $p['last_seen'] ?? '',
        ];
    }

    if (!empty($wiederkehrend)) {
        $report->addFinding(
            'error-log',
            count($wiederkehrend) . ' periodisch wiederkehrende(s) Muster',
            'warning',
            'Diese Fehlermeldungen treten an mindestens 3 unterschiedlichen Tagen auf und deuten auf ein '
            . 'strukturelles, nicht nur einmaliges Problem hin. Eine Untersuchung der Ursache wird empfohlen.',
            array_values(array_map(fn($p) => [
                'Level'   => $p['level'] ?? '',
                'Meldung' => $p['message'] ?? '',
                'Anzahl'  => $p['count'] ?? 0,
                'Tage'    => $p['distinct_days'] ?? 0,
            ], $wiederkehrend))
        );
    }

    $report->addFinding(
        'error-log',
        'Häufigste Fehlermuster (Top ' . count($patterns) . ')',
        'info',
        'Übersicht der häufigsten normalisierten Fehlermeldungen im gelesenen Fenster.',
        $details
    );
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'error-log');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "error-log" gefunden. Bitte zuerst importieren.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportErrorLog($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'error-log', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'error-log', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
