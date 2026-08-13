<?php
/**
 * ITOMIG Healthcheck - Reporter: DB-Spalten-Befüllung
 *
 * Bewertet Spalten-Befüllungsdaten in Kategorie 'columns'.
 * Liefert pro Tabelle EIN Detail-Finding (Tabelle als details.rows); zusätzlich
 * eine zusammenfassende Statistik und ein Finding für komplett leere Spalten.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportColumnFill(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['customer'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];
    $tabellen = $daten['tables'] ?? [];
    $leereTabellen = $daten['empty_tables'] ?? [];

    $spaltenGesamt = 0;
    $leereSpalten = 0;
    $volleSpalten = 0;
    $auffaelligeSpalten = []; // Spalten mit 0% Befüllung

    foreach ($tabellen as $t) {
        foreach ($t['columns'] as $s) {
            $spaltenGesamt++;
            if ($s['percent'] === 0.0 || $s['percent'] === 0) {
                $leereSpalten++;
                $auffaelligeSpalten[] = [
                    'Tabelle' => $t['table'],
                    'Spalte'  => $s['column'],
                    'Typ'     => $s['type'],
                    'Datensätze' => number_format($t['rows'], 0, ',', '.'),
                ];
            } elseif ((float) $s['percent'] >= 100.0) {
                $volleSpalten++;
            }
        }
    }

    $report->setCategorySummary(
        'columns',
        sprintf(
            '%d Tabellen analysiert, %d Spalten geprüft, %d komplett leer (0%%), %d komplett befüllt (100%%), %d leere Tabellen übersprungen.',
            count($tabellen),
            $spaltenGesamt,
            $leereSpalten,
            $volleSpalten,
            count($leereTabellen)
        ),
        [
            'tabellen_analysiert' => count($tabellen),
            'spalten_gesamt'      => $spaltenGesamt,
            'leere_spalten'       => $leereSpalten,
            'volle_spalten'       => $volleSpalten,
            'leere_tabellen'      => count($leereTabellen),
        ]
    );

    // Finding: komplett leere Spalten
    if (!empty($auffaelligeSpalten)) {
        $count = count($auffaelligeSpalten);
        $severity = $count > 100 ? 'warning' : 'info';
        $report->addFinding(
            'columns',
            sprintf('Komplett leere Spalten (%d)', $count),
            $severity,
            sprintf(
                '%d Spalten in befüllten Tabellen enthalten keinen einzigen Wert (0%% Befüllung). Diese Spalten sind Kandidaten für eine Entfernung oder ein Mapping-Problem im Datenmodell.',
                $count
            ),
            array_slice($auffaelligeSpalten, 0, 200)
        );
    }

    // Pro Tabelle ein Detail-Finding (kompakt, max. 50 Tabellen, um Report-Größe im Rahmen zu halten)
    foreach (array_slice($tabellen, 0, 50) as $t) {
        $rows = [];
        foreach ($t['columns'] as $s) {
            $rows[] = [
                'Spalte'         => $s['column'],
                'Typ'            => $s['type'],
                'Befüllt'        => number_format($s['filled'], 0, ',', '.') . ' / ' . number_format($s['total'], 0, ',', '.'),
                'Befüllungsgrad' => number_format((float) $s['percent'], 1, ',', '.') . '%',
            ];
        }
        $report->addFinding(
            'columns',
            sprintf('%s (%s Datensätze)', $t['table'], number_format($t['rows'], 0, ',', '.')),
            'info',
            sprintf('Spalten-Befüllung der Tabelle %s.', $t['table']),
            $rows
        );
    }

    if (count($tabellen) > 50) {
        $report->addFinding(
            'columns',
            'Weitere Tabellen',
            'info',
            sprintf(
                'Es wurden insgesamt %d Tabellen analysiert. Im Report werden die 50 größten dargestellt. Die vollständigen Daten finden Sie in der Roh-JSON.',
                count($tabellen)
            )
        );
    }

    if (!empty($leereTabellen)) {
        $report->addFinding(
            'columns',
            sprintf('Leere Tabellen (%d)', count($leereTabellen)),
            'info',
            'Folgende Tabellen enthalten keine Datensätze und wurden für die Spalten-Analyse übersprungen:',
            $leereTabellen
        );
    }

    return $report;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'column-fill');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "column-fill" gefunden.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportColumnFill($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'column-fill', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'column-fill', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
