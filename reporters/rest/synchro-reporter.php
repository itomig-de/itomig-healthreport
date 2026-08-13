<?php
/**
 * ITOMIG Healthcheck - Reporter: Synchro-Analyse
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportSynchro(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['kunde'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['umgebung'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['daten'] ?? [];
    $maxFullLoadHours = (int) ($daten['konfiguration']['max_full_load_hours']
        ?? $config['synchro']['max_full_load_hours'] ?? 24);
    $fehlerTage = (int) ($daten['konfiguration']['fehler_tage']
        ?? $config['synchro']['fehler_tage'] ?? 30);

    $datenquellenItems = bewerteDatenquellen($report, $daten['datenquellen'] ?? []);
    bewerteGesperrteAttribute($report, $daten['sync_attribute'] ?? []);
    bewerteReconciliationKeys($report, $datenquellenItems);
    bewerteSyncFehler($report, $daten['sync_logs'] ?? [], $fehlerTage);
    bewerteFullLoadIntervall($report, $datenquellenItems, $maxFullLoadHours);
    bewerteLoeschregeln($report, $datenquellenItems);

    return $report;
}

function bewerteDatenquellen(HealthcheckReport $report, array $datenquellen): array
{
    if (($datenquellen['fehler'] ?? null) !== null) {
        $report->addFinding(
            'synchro',
            'Datenquellen konnten nicht abgerufen werden',
            'critical',
            'Fehler beim Abruf der Datenquellen: ' . $datenquellen['fehler']
        );
        return [];
    }

    $items = $datenquellen['items'] ?? [];
    $count = count($items);

    if ($count === 0) {
        $report->addFinding(
            'synchro',
            'Keine Datenquellen konfiguriert',
            'info',
            'In dieser iTop-Instanz sind keine Synchronisations-Datenquellen eingerichtet. '
            . 'Wenn Daten aus externen Systemen importiert werden sollen, empfehlen wir '
            . 'die Einrichtung von Sync-Datenquellen statt manuellem CSV-Import.'
        );
        return [];
    }

    $inventar = [];
    $klassenMap = [];
    foreach ($items as $ds) {
        $name = $ds['name'] ?? 'Unbekannt';
        $scope = $ds['scope_class'] ?? 'Unbekannt';
        $inventar[] = [
            'Datenquelle' => $name,
            'Zielklasse'  => $scope,
            'Status'      => $ds['status'] ?? '',
        ];
        $klassenMap[$scope][] = $name;
    }

    $report->setCategorySummary(
        'synchro',
        "$count Synchronisations-Datenquellen für " . count($klassenMap) . ' verschiedene Klassen konfiguriert.'
    );

    $report->addFinding(
        'synchro',
        "Datenquellen-Inventar ($count Quellen)",
        'info',
        'Übersicht aller konfigurierten Synchronisations-Datenquellen.',
        $inventar
    );

    $mehrfach = array_filter($klassenMap, fn(array $q) => count($q) > 1);
    if (!empty($mehrfach)) {
        $details = [];
        foreach ($mehrfach as $klasse => $quellen) {
            $details[] = [
                'Klasse'         => $klasse,
                'Anzahl Quellen' => (string) count($quellen),
                'Datenquellen'   => implode(', ', $quellen),
            ];
        }
        $report->addFinding(
            'synchro',
            'Klassen mit mehreren Datenquellen',
            'warning',
            count($mehrfach) . ' Klasse(n) haben mehrere Datenquellen. '
            . 'Stellen Sie sicher, dass die Abgrenzung klar definiert ist und '
            . 'keine Konflikte bei der Synchronisation entstehen.',
            $details
        );
    }

    return $items;
}

function bewerteGesperrteAttribute(HealthcheckReport $report, array $syncAttribute): void
{
    if (($syncAttribute['fehler'] ?? null) !== null) {
        $report->addFinding(
            'synchro',
            'Gesperrte Attribute konnten nicht geprüft werden',
            'info',
            'Die Klasse SynchroAttribute ist möglicherweise nicht über die API verfügbar: '
            . $syncAttribute['fehler']
        );
        return;
    }

    $items = $syncAttribute['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'synchro',
            'Synchronisations-Attribute',
            'info',
            'Keine Detail-Informationen zu Sync-Attributen verfügbar.'
        );
        return;
    }

    $proQuelle = [];
    foreach ($items as $a) {
        $src = $a['sync_source_name'] ?? 'Unbekannt';
        if (!isset($proQuelle[$src])) {
            $proQuelle[$src] = ['gesperrt' => 0, 'total' => 0];
        }
        $proQuelle[$src]['total']++;
        if (in_array((string) ($a['update'] ?? ''), ['1', 'yes', 'true'], true)) {
            $proQuelle[$src]['gesperrt']++;
        }
    }

    $details = [];
    $vielGesperrt = false;
    foreach ($proQuelle as $quelle => $info) {
        $prozent = $info['total'] > 0 ? round(($info['gesperrt'] / $info['total']) * 100, 1) : 0;
        if ($prozent > 80) {
            $vielGesperrt = true;
        }
        $details[] = [
            'Datenquelle'      => $quelle,
            'Gesperrte Felder' => "{$info['gesperrt']} von {$info['total']} ({$prozent}%)",
        ];
    }

    $report->addFinding(
        'synchro',
        'Gesperrte Attribute pro Datenquelle',
        $vielGesperrt ? 'warning' : 'ok',
        $vielGesperrt
            ? 'Einige Datenquellen sperren einen sehr hohen Anteil der Attribute. '
              . 'Prüfen Sie, ob alle gesperrten Felder tatsächlich durch die Quelle aktualisiert werden müssen.'
            : 'Die Verteilung der gesperrten Attribute erscheint angemessen.',
        $details
    );
}

function bewerteReconciliationKeys(HealthcheckReport $report, array $datenquellen): void
{
    if (empty($datenquellen)) {
        return;
    }
    $details = [];
    $ohneCustom = 0;
    foreach ($datenquellen as $ds) {
        $policy = $ds['reconciliation_policy'] ?: 'use_attributes';
        $details[] = [
            'Datenquelle'           => $ds['name'] ?? 'Unbekannt',
            'Reconciliation-Policy' => $policy,
        ];
        if ($policy === 'use_primary_key') {
            $ohneCustom++;
        }
    }
    $report->addFinding(
        'synchro',
        'Reconciliation Keys',
        $ohneCustom > 0 ? 'info' : 'ok',
        $ohneCustom > 0
            ? "$ohneCustom Datenquelle(n) verwenden nur den Primary Key zur Zuordnung. "
              . 'Empfehlung: Prüfen Sie, ob zusätzliche Abgleichsattribute sinnvoll wären, '
              . 'um Zuordnungsfehler bei Re-Importen zu vermeiden.'
            : 'Alle Datenquellen verwenden Attribut-basierte Reconciliation.',
        $details
    );
}

function bewerteSyncFehler(HealthcheckReport $report, array $syncLogs, int $fehlerTage): void
{
    if (($syncLogs['fehler'] ?? null) !== null) {
        $report->addFinding(
            'synchro',
            'Sync-Fehler konnten nicht analysiert werden',
            'warning',
            'Fehler beim Abruf der Sync-Logs: ' . $syncLogs['fehler']
        );
        return;
    }

    $items = $syncLogs['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'synchro',
            'Keine Sync-Logs gefunden',
            'info',
            "In den letzten $fehlerTage Tagen wurden keine Synchronisations-Logs gefunden. "
            . 'Entweder werden keine Synchronisationen ausgeführt oder die Logs wurden bereinigt.'
        );
        return;
    }

    $proQuelle = [];
    $totalFehler = 0;
    foreach ($items as $log) {
        $src = $log['sync_source_name'] ?? 'Unbekannt';
        $errors = (int) ($log['stats_nb_obj_errors'] ?? 0);
        if (!isset($proQuelle[$src])) {
            $proQuelle[$src] = ['laeufe' => 0, 'fehler' => 0, 'fehler_laeufe' => 0];
        }
        $proQuelle[$src]['laeufe']++;
        if ($errors > 0) {
            $proQuelle[$src]['fehler'] += $errors;
            $proQuelle[$src]['fehler_laeufe']++;
            $totalFehler += $errors;
        }
    }

    if ($totalFehler === 0) {
        $report->addFinding(
            'synchro',
            'Keine Sync-Fehler',
            'ok',
            "In den letzten $fehlerTage Tagen sind keine Synchronisationsfehler aufgetreten. "
            . count($items) . ' Sync-Läufe wurden analysiert.'
        );
        return;
    }

    $details = [];
    foreach ($proQuelle as $src => $info) {
        if ($info['fehler'] > 0) {
            $details[] = [
                'Datenquelle'       => $src,
                'Sync-Läufe'        => (string) $info['laeufe'],
                'Fehler gesamt'     => (string) $info['fehler'],
                'Läufe mit Fehlern' => (string) $info['fehler_laeufe'],
            ];
        }
    }

    $severity = $totalFehler > 100 ? 'critical' : 'warning';
    $report->addFinding(
        'synchro',
        "Sync-Fehler ($totalFehler in $fehlerTage Tagen)",
        $severity,
        "$totalFehler Fehler in " . count($details) . " Datenquelle(n) in den letzten $fehlerTage Tagen. "
        . 'Wiederkehrende Fehler sollten untersucht und behoben werden.',
        $details
    );
}

function bewerteFullLoadIntervall(HealthcheckReport $report, array $datenquellen, int $maxStunden): void
{
    if (empty($datenquellen)) {
        return;
    }
    $details = [];
    $zuLang = 0;
    foreach ($datenquellen as $ds) {
        $periodicity = $ds['full_load_periodicity'] ?: 'Nicht konfiguriert';
        $details[] = [
            'Datenquelle'         => $ds['name'] ?? 'Unbekannt',
            'Full-Load-Intervall' => (string) $periodicity,
        ];
        if (is_numeric($periodicity) && (int) $periodicity > ($maxStunden * 3600)) {
            $zuLang++;
        }
    }
    $report->addFinding(
        'synchro',
        'Full-Load-Intervalle',
        $zuLang > 0 ? 'warning' : 'ok',
        $zuLang > 0
            ? "$zuLang Datenquelle(n) haben ein Full-Load-Intervall von mehr als {$maxStunden}h. "
              . 'Ein zu langes Intervall kann dazu führen, dass gelöschte Objekte in der Quelle nicht rechtzeitig erkannt werden.'
            : 'Alle Full-Load-Intervalle liegen im empfohlenen Bereich.',
        $details
    );
}

function bewerteLoeschregeln(HealthcheckReport $report, array $datenquellen): void
{
    if (empty($datenquellen)) {
        return;
    }
    $details = [];
    $keineLoesch = 0;
    $direktLoeschen = 0;
    foreach ($datenquellen as $ds) {
        $policy = $ds['delete_policy'] ?: 'Nicht konfiguriert';
        $retention = $ds['delete_policy_retention'] ?? '';
        $details[] = [
            'Datenquelle'  => $ds['name'] ?? 'Unbekannt',
            'Lösch-Policy' => $policy,
            'Aufbewahrung' => $retention !== '' ? "$retention Tage" : '-',
        ];
        if (in_array($policy, ['ignore', 'Nicht konfiguriert'], true)) {
            $keineLoesch++;
        }
        if ($policy === 'delete') {
            $direktLoeschen++;
        }
    }

    $severity = 'ok';
    $hinweise = [];
    if ($keineLoesch > 0) {
        $severity = 'warning';
        $hinweise[] = "$keineLoesch Quelle(n) ignorieren gelöschte Objekte — "
            . 'diese Objekte bleiben in iTop bestehen auch wenn sie in der Quelle nicht mehr vorhanden sind.';
    }
    if ($direktLoeschen > 0) {
        $severity = 'warning';
        $hinweise[] = "$direktLoeschen Quelle(n) löschen Objekte direkt — "
            . 'Empfehlung: Nutzen Sie stattdessen "update_then_delete" oder "update" mit Obsolescence, um Datenverlust zu vermeiden.';
    }

    $report->addFinding(
        'synchro',
        'Löschregeln der Datenquellen',
        $severity,
        !empty($hinweise) ? implode(' ', $hinweise) : 'Alle Löschregeln sind angemessen konfiguriert.',
        $details
    );
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'synchro');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "synchro" gefunden. Bitte zuerst synchro-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportSynchro($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'synchro', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'synchro', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
