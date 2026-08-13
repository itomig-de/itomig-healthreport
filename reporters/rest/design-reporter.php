<?php
/**
 * ITOMIG Healthcheck - Reporter: Design-Analyse
 *
 * Bewertet die vom design-collector erfassten Rohdaten und erzeugt einen
 * HealthcheckReport mit Findings + Ampel.
 *
 * Aufruf:
 *   php design-reporter.php [--config=/pfad/zur/config.php] [--raw=/pfad/zur/raw.json]
 *
 * Ohne --raw wird die jüngste Raw-JSON des aktuellen Kunden verwendet.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

/**
 * Bewertet die Design-Rohdaten und liefert einen HealthcheckReport zurück.
 *
 * @param array $raw    Rohdaten-Array (meta + data) wie vom Collector erzeugt
 * @param array $config Aktuelle Healthcheck-Config (für Schwellwerte)
 */
function reportDesign(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['customer'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];
    $inventar = $daten['class_inventory'] ?? [];
    $optionale = $daten['optional_classes'] ?? [];
    $sprachen = $daten['config']['languages'] ?? [];

    bewerteKlassenInventar($report, $inventar);
    bewerteEnumWerte($report);
    bewerteOptionaleKlassen($report, $optionale);
    bewerteUebersetzungen($report, $sprachen);

    foreach ($inventar['error'] ?? [] as $f) {
        $report->addFinding(
            'design',
            'Klasse nicht abrufbar: ' . $f['class'],
            'warning',
            'Beim Abruf der Klasse trat ein Fehler auf: ' . $f['message']
        );
    }

    return $report;
}

function bewerteKlassenInventar(HealthcheckReport $report, array $inventar): void
{
    $mit = $inventar['with_instances'] ?? [];
    $ohne = $inventar['without_instances'] ?? [];

    $totalMit = count($mit);
    $totalOhne = count($ohne);

    $report->setCategorySummary(
        'design',
        "Es wurden $totalMit Klassen mit Instanzen und $totalOhne Klassen ohne Instanzen identifiziert.",
        ['mit_instanzen' => $totalMit, 'ohne_instanzen' => $totalOhne]
    );

    if ($totalMit === 0) {
        $report->addFinding(
            'design',
            'Keine Klassen mit Instanzen gefunden',
            'warning',
            'Keine der geprüften CMDB-Basisklassen enthält Instanzen. Möglicherweise ist die iTop-Instanz leer oder die API liefert unerwartete Werte.'
        );
        return;
    }

    $top = array_slice($mit, 0, 10, true);
    $details = [];
    foreach ($top as $klasse => $count) {
        $details[] = ['Klasse' => $klasse, 'Anzahl Objekte' => (string) $count];
    }

    $report->addFinding(
        'design',
        'Klassen-Inventar (Top 10)',
        'info',
        'Die 10 meistgenutzten Klassen in dieser iTop-Instanz.',
        $details
    );
}

function bewerteEnumWerte(HealthcheckReport $report): void
{
    $report->addFinding(
        'design',
        'Enum-Werte Grundprüfung',
        'info',
        'Die Enum-Wert-Prüfung über die REST API ist eingeschränkt. '
        . 'Für eine tiefgehende Analyse der Enum-Codes empfehlen wir '
        . 'zusätzlich einen Blick in die iTop Datenmodell-XML-Dateien. '
        . 'Achten Sie insbesondere auf: Leerzeichen, Sonderzeichen und Umlaute in Enum-Codes.'
    );
}

function bewerteOptionaleKlassen(HealthcheckReport $report, array $optionale): void
{
    $ungenutzt = [];
    foreach ($optionale as $klasse => $info) {
        if ($info['error'] !== null) {
            continue;
        }
        if (($info['count'] ?? 0) === 0) {
            $ungenutzt[] = [
                'Klasse'       => $klasse,
                'Beschreibung' => $info['description'] ?? '',
            ];
        }
    }

    if (empty($ungenutzt)) {
        $report->addFinding(
            'design',
            'Alle optionalen Klassen werden genutzt',
            'ok',
            'Alle geprüften optionalen Klassen haben mindestens eine Instanz.'
        );
        return;
    }

    $count = count($ungenutzt);
    $severity = $count > 5 ? 'warning' : 'info';

    $report->addFinding(
        'design',
        "Potentiell ungenutzte Klassen ($count)",
        $severity,
        "$count optionale Klassen haben keine Instanzen. "
        . 'Prüfen Sie, ob diese Klassen im Datenmodell benötigt werden oder '
        . 'ob sie deaktiviert/ausgeblendet werden können.',
        $ungenutzt
    );
}

function bewerteUebersetzungen(HealthcheckReport $report, array $sprachen): void
{
    $list = empty($sprachen) ? 'keine konfiguriert' : implode(', ', $sprachen);

    $report->addFinding(
        'design',
        'Übersetzungs-Check',
        'info',
        'Konfigurierte Sprachen: ' . $list . '. '
        . 'Eine vollständige Übersetzungsprüfung erfordert Zugriff auf die '
        . 'Dictionary-Dateien des Datenmodells. Empfehlung: Prüfen Sie in der '
        . 'iTop-Konsole unter "Setup > Compilation" ob Warnungen zu fehlenden '
        . 'Übersetzungen angezeigt werden.'
    );
}

// --- CLI ---

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());

        $rawPath = HealthcheckUtils::parseRawOption()
            ?? HealthcheckUtils::latestRawJson($config, 'design');

        if ($rawPath === null) {
            throw new \RuntimeException(
                'Keine Rohdaten für Modul "design" gefunden. '
                . 'Bitte zuerst design-collector.php ausführen.'
            );
        }

        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);

        $report = reportDesign($raw, $config);

        $ts = HealthcheckUtils::timestamp();
        $htmlPath = HealthcheckUtils::reportPath($config, 'design', $ts, 'html');
        $jsonPath = HealthcheckUtils::reportPath($config, 'design', $ts, 'json');

        HealthcheckUtils::saveFile($htmlPath, $report->toHtml());
        HealthcheckUtils::saveJson($jsonPath, json_decode($report->toJson(), true));

        HealthcheckUtils::log("HTML-Report: $htmlPath", 'success');
        HealthcheckUtils::log("Findings-JSON: $jsonPath", 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
