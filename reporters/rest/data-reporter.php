<?php
/**
 * ITOMIG Healthcheck - Reporter: Daten-Analyse
 *
 * Bewertet die vom data-collector erfassten Rohdaten.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportData(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['db_name'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];

    bewerteObjektStatus($report, $daten['object_status'] ?? []);
    bewerteAuditRegeln($report, $daten['audit_rules'] ?? []);
    bewerteBefuellung($report, $daten['data_completeness'] ?? []);
    bewerteObsolescence($report, $daten['obsolescence'] ?? []);
    bewerteArchiv($report);

    return $report;
}

function bewerteObjektStatus(HealthcheckReport $report, array $status): void
{
    $rows = [];
    $totalObsolet = 0;

    foreach ($status as $klasse => $info) {
        if (($info['total'] ?? 0) === 0) {
            continue;
        }
        if ($info['active'] !== null) {
            $rows[] = [
                'Klasse'                => $klasse,
                'Gesamt'                => (string) $info['total'],
                'Aktiv (nicht obsolet)' => (string) $info['active'],
                'Obsolet'               => (string) $info['obsolete'],
            ];
            $totalObsolet += (int) $info['obsolete'];
        } else {
            $rows[] = [
                'Klasse'  => $klasse,
                'Gesamt'  => (string) $info['total'],
                'Hinweis' => 'Obsolescence-Status nicht prüfbar',
            ];
        }
    }

    if (empty($rows)) {
        return;
    }

    $severity = 'info';
    if ($totalObsolet > 100) {
        $severity = 'warning';
    }
    if ($totalObsolet > 500) {
        $severity = 'critical';
    }

    $report->addFinding(
        'data',
        'Objekt-Status Übersicht',
        $severity,
        "Analyse des Aktiv/Obsolet-Verhältnisses der wichtigsten Klassen. $totalObsolet Objekte sind als obsolet markiert.",
        $rows
    );
}

function bewerteAuditRegeln(HealthcheckReport $report, array $audit): void
{
    if (($audit['error'] ?? null) !== null) {
        $report->addFinding(
            'data',
            'Audit-Regeln konnten nicht geprüft werden',
            'warning',
            'Fehler beim Abruf der Audit-Regeln: ' . $audit['error']
        );
        return;
    }

    $regeln = $audit['rules'] ?? [];
    $ruleCount = count($regeln);

    if ($ruleCount === 0) {
        $report->addFinding(
            'data',
            'Keine Audit-Regeln definiert',
            'warning',
            'Es sind keine Audit-Regeln in der iTop-Instanz definiert. '
            . 'Audit-Regeln sind ein wichtiges Werkzeug zur fortlaufenden '
            . 'Qualitätssicherung der Daten. Empfehlung: Definieren Sie Audit-Regeln '
            . 'für geschäftskritische Datenfelder und -beziehungen.'
        );
        return;
    }

    $aktiv = 0;
    $inaktiv = 0;
    $details = [];
    foreach ($regeln as $r) {
        $isAktiv = in_array((string) ($r['valid_flag'] ?? ''), ['true', '1', 'yes'], true);
        $isAktiv ? $aktiv++ : $inaktiv++;
        $details[] = [
            'Regel'  => $r['name'] ?? 'Unbekannt',
            'Status' => $isAktiv ? 'Aktiv' : 'Inaktiv',
        ];
    }

    $categoryCount = (int) ($audit['category_count'] ?? 0);
    $report->setCategorySummary(
        'data',
        "$categoryCount Audit-Kategorien und $ruleCount Audit-Regeln gefunden ($aktiv aktiv, $inaktiv inaktiv)."
    );

    $severity = 'ok';
    if ($ruleCount < 5) {
        $severity = 'info';
    }
    if ($inaktiv > $aktiv) {
        $severity = 'warning';
    }

    $report->addFinding(
        'data',
        "Audit-Regeln ($ruleCount definiert)",
        $severity,
        "$aktiv von $ruleCount Audit-Regeln sind aktiv. "
        . ($inaktiv > 0
            ? "Empfehlung: Prüfen Sie die $inaktiv inaktiven Regeln — sind sie bewusst deaktiviert?"
            : 'Alle Regeln sind aktiv.'),
        $details
    );
}

function bewerteBefuellung(HealthcheckReport $report, array $befuellung): void
{
    $auffaellig = [];
    foreach ($befuellung as $b) {
        if (($b['error'] ?? null) !== null) {
            continue;
        }
        $total = (int) ($b['total'] ?? 0);
        $betroffen = $b['affected'];
        if ($betroffen === null || $betroffen === 0) {
            continue;
        }
        $prozent = $total > 0 ? round(($betroffen / $total) * 100, 1) : 0;
        $auffaellig[] = [
            'Prüfung'   => $b['description'] ?? '',
            'Betroffen' => "$betroffen von $total ({$prozent}%)",
        ];
    }

    if (empty($auffaellig)) {
        $report->addFinding(
            'data',
            'Befüllungsgrad in Ordnung',
            'ok',
            'Keine Auffälligkeiten beim Befüllungsgrad der geprüften Felder gefunden.'
        );
        return;
    }

    $severity = count($auffaellig) > 2 ? 'warning' : 'info';
    $report->addFinding(
        'data',
        'Befüllungsgrad-Analyse',
        $severity,
        count($auffaellig) . ' Auffälligkeiten beim Befüllungsgrad gefunden.',
        $auffaellig
    );
}

function bewerteObsolescence(HealthcheckReport $report, array $obs): void
{
    $rows = [];
    $genutzt = false;
    foreach ($obs as $klasse => $info) {
        if (($info['total'] ?? 0) === 0) {
            continue;
        }
        if (($info['obsolete'] ?? null) === null) {
            continue;
        }
        $prozent = $info['total'] > 0 ? round(($info['obsolete'] / $info['total']) * 100, 1) : 0;
        if ($info['obsolete'] > 0) {
            $genutzt = true;
        }
        $rows[] = [
            'Klasse'  => $klasse,
            'Gesamt'  => (string) $info['total'],
            'Obsolet' => "{$info['obsolete']} ({$prozent}%)",
        ];
    }

    if (empty($rows)) {
        return;
    }

    if (!$genutzt) {
        $report->addFinding(
            'data',
            'Obsolescence wird nicht genutzt',
            'warning',
            'In keiner der geprüften Klassen sind Objekte als obsolet markiert. '
            . 'Empfehlung: Konfigurieren Sie Obsolescence-Regeln, damit veraltete '
            . 'Objekte automatisch als obsolet markiert werden (z.B. basierend auf '
            . 'dem letzten Synchronisationszeitpunkt).',
            $rows
        );
    } else {
        $report->addFinding(
            'data',
            'Obsolescence-Nutzung',
            'ok',
            'Obsolescence-Flags werden aktiv genutzt.',
            $rows
        );
    }
}

function bewerteArchiv(HealthcheckReport $report): void
{
    $report->addFinding(
        'data',
        'Archiv-Modus Prüfung',
        'info',
        'Der Archiv-Modus kann über die REST API nur eingeschränkt geprüft werden. '
        . 'Empfehlung: Prüfen Sie in der iTop-Konfiguration (config-itop.php) ob '
        . 'der Parameter "archive.enable" auf "true" gesetzt ist. '
        . 'Der Archiv-Modus ermöglicht es, alte Tickets und CIs zu archivieren, '
        . 'wodurch sie aus den normalen Ansichten verschwinden aber erhalten bleiben.'
    );
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'data');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "data" gefunden. Bitte zuerst data-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportData($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        $htmlPath = HealthcheckUtils::reportPath($config, 'data', $ts, 'html');
        $jsonPath = HealthcheckUtils::reportPath($config, 'data', $ts, 'json');
        HealthcheckUtils::saveFile($htmlPath, $report->toHtml());
        HealthcheckUtils::saveJson($jsonPath, json_decode($report->toJson(), true));
        HealthcheckUtils::log("HTML-Report: $htmlPath", 'success');
        HealthcheckUtils::log("Findings-JSON: $jsonPath", 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
