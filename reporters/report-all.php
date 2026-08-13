<?php
/**
 * ITOMIG Healthcheck - Sammel-Reporter
 *
 * Liest pro aktivem Modul die jüngste Raw-JSON (oder eine via --timestamp= gewählte),
 * baut Findings + Ampel, schreibt pro Modul HTML+JSON in auswertung/{kunde}/ und
 * generiert einen Gesamt-Report.
 *
 * Aufruf: php report-all.php [--config=…] [--timestamp=Y-m-d_His]
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/HealthcheckReport.php';
require_once __DIR__ . '/../lib/HealthcheckUtils.php';
require_once __DIR__ . '/../lib/HealthcheckModules.php';

/**
 * Führt einen einzelnen Reporter aus.
 *
 * @param string      $modul           Modul-Slug aus der Registry
 * @param array       $config          Healthcheck-Config
 * @param string|null $rawTimestamp    Optional: Timestamp der zu lesenden Raw-JSON. Wenn null → jüngste.
 * @param string|null $reportTimestamp Optional: einheitlicher Timestamp für Output-Filenames in einem Lauf.
 * @param string|null $backToSummary   Optional: relativer Link zurück zum Management-Summary.
 *
 * @return array{html_path: string, json_path: string, report: HealthcheckReport, filename: string}
 */
function runReporter(
    string $modul,
    array $config,
    ?string $rawTimestamp = null,
    ?string $reportTimestamp = null,
    ?string $backToSummary = null
): array {
    $def = HealthcheckModules::get($modul);
    if ($def === null) {
        throw new \RuntimeException("Unbekanntes Modul: $modul");
    }

    require_once $def['reporter_file'];

    $fn = $def['reporter_fn'];
    if (!function_exists($fn)) {
        throw new \RuntimeException("Reporter-Funktion $fn() existiert nicht.");
    }

    $rawPath = $rawTimestamp !== null
        ? HealthcheckUtils::rawJsonPath($config, $modul, $rawTimestamp)
        : HealthcheckUtils::latestRawJson($config, $modul);

    if ($rawPath === null || !file_exists($rawPath)) {
        throw new \RuntimeException(
            "Keine Rohdaten für $modul" . ($rawTimestamp ? " (Timestamp $rawTimestamp)" : '') . ' gefunden.'
        );
    }

    $raw = HealthcheckUtils::loadRawJson($rawPath);
    /** @var HealthcheckReport $report */
    $report = $fn($raw, $config);

    $ts = $reportTimestamp ?? HealthcheckUtils::timestamp();
    $htmlPath = HealthcheckUtils::reportPath($config, $modul, $ts, 'html');
    $jsonPath = HealthcheckUtils::reportPath($config, $modul, $ts, 'json');
    HealthcheckUtils::saveFile($htmlPath, $report->toHtml($backToSummary));
    HealthcheckUtils::saveJson($jsonPath, json_decode($report->toJson(), true));

    return [
        'html_path' => $htmlPath,
        'json_path' => $jsonPath,
        'filename'  => basename($htmlPath),
        'report'    => $report,
    ];
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());

        $timestamp = null;
        foreach ($argv as $arg) {
            if (strpos($arg, '--timestamp=') === 0) {
                $timestamp = substr($arg, 12);
            }
        }

        echo "\n=========================================\n";
        echo "  ITOMIG Healthcheck - Report All\n";
        echo "  Kunde: " . ($config['kunde']['name'] ?? '-') . "\n";
        echo "  Datum: " . date('Y-m-d H:i:s') . "\n";
        echo "=========================================\n\n";

        $aktive = HealthcheckModules::active($config);
        if (empty($aktive)) {
            HealthcheckUtils::log('Keine Module aktiviert.', 'warning');
            exit(1);
        }

        $gesamt = new HealthcheckReport(
            $config['kunde']['name'] ?? '',
            $config['kunde']['umgebung'] ?? ''
        );

        // Einheitlicher Timestamp + Summary-Filename für den gesamten Lauf
        $reportTs = HealthcheckUtils::timestamp();
        $gesamtHtml = HealthcheckUtils::reportPath($config, 'healthcheck', $reportTs, 'html');
        $gesamtJson = HealthcheckUtils::reportPath($config, 'healthcheck', $reportTs, 'json');
        $summaryFilename = basename($gesamtHtml);

        $erfolg = 0;
        $detailLinks = [];
        foreach ($aktive as $modul) {
            HealthcheckUtils::log("--- Reporter: $modul ---", 'info');
            $def = HealthcheckModules::get($modul);
            try {
                $r = runReporter($modul, $config, $timestamp, $reportTs, $summaryFilename);
                HealthcheckUtils::log("HTML: {$r['html_path']}", 'success');
                HealthcheckModules::mergeReport($gesamt, $r['report'], $def['category']);
                $detailLinks[$def['category']] = $r['filename'];
                $erfolg++;
            } catch (\Throwable $e) {
                HealthcheckUtils::log("Reporter $modul fehlgeschlagen: " . $e->getMessage(), 'warning');
            }
            echo "\n";
        }

        if ($erfolg === 0) {
            HealthcheckUtils::log('Kein Modul konnte erfolgreich aufbereitet werden.', 'error');
            exit(1);
        }

        // Management-Summary (kompakt) statt vollem Sammel-Report
        HealthcheckUtils::saveFile($gesamtHtml, $gesamt->toHtmlManagementSummary($detailLinks));
        HealthcheckUtils::saveJson($gesamtJson, json_decode($gesamt->toJson(), true));

        echo "=========================================\n";
        echo "  Gesamt-Report: $gesamtHtml\n";
        echo "  $erfolg / " . count($aktive) . " Modul(e) ausgewertet\n";
        echo "=========================================\n";
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}

