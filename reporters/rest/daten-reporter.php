<?php
/**
 * ITOMIG Healthcheck - Reporter: Daten-Analyse
 *
 * Bewertet die vom daten-collector erfassten Rohdaten.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportDaten(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['kunde'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['umgebung'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['daten'] ?? [];

    bewerteObjektStatus($report, $daten['objekt_status'] ?? []);
    bewerteAuditRegeln($report, $daten['audit_regeln'] ?? []);
    bewerteBefuellung($report, $daten['befuellung'] ?? []);
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
        if ($info['aktiv'] !== null) {
            $rows[] = [
                'Klasse'                => $klasse,
                'Gesamt'                => (string) $info['total'],
                'Aktiv (nicht obsolet)' => (string) $info['aktiv'],
                'Obsolet'               => (string) $info['obsolet'],
            ];
            $totalObsolet += (int) $info['obsolet'];
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
        'daten',
        'Objekt-Status Übersicht',
        $severity,
        "Analyse des Aktiv/Obsolet-Verhältnisses der wichtigsten Klassen. $totalObsolet Objekte sind als obsolet markiert.",
        $rows
    );
}

function bewerteAuditRegeln(HealthcheckReport $report, array $audit): void
{
    if (($audit['fehler'] ?? null) !== null) {
        $report->addFinding(
            'daten',
            'Audit-Regeln konnten nicht geprüft werden',
            'warning',
            'Fehler beim Abruf der Audit-Regeln: ' . $audit['fehler']
        );
        return;
    }

    $regeln = $audit['regeln'] ?? [];
    $ruleCount = count($regeln);

    if ($ruleCount === 0) {
        $report->addFinding(
            'daten',
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

    $categoryCount = (int) ($audit['kategorien_anzahl'] ?? 0);
    $report->setCategorySummary(
        'daten',
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
        'daten',
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
        if (($b['fehler'] ?? null) !== null) {
            continue;
        }
        $total = (int) ($b['total'] ?? 0);
        $betroffen = $b['betroffen'];
        if ($betroffen === null || $betroffen === 0) {
            continue;
        }
        $prozent = $total > 0 ? round(($betroffen / $total) * 100, 1) : 0;
        $auffaellig[] = [
            'Prüfung'   => $b['beschreibung'] ?? '',
            'Betroffen' => "$betroffen von $total ({$prozent}%)",
        ];
    }

    if (empty($auffaellig)) {
        $report->addFinding(
            'daten',
            'Befüllungsgrad in Ordnung',
            'ok',
            'Keine Auffälligkeiten beim Befüllungsgrad der geprüften Felder gefunden.'
        );
        return;
    }

    $severity = count($auffaellig) > 2 ? 'warning' : 'info';
    $report->addFinding(
        'daten',
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
        if (($info['obsolet'] ?? null) === null) {
            continue;
        }
        $prozent = $info['total'] > 0 ? round(($info['obsolet'] / $info['total']) * 100, 1) : 0;
        if ($info['obsolet'] > 0) {
            $genutzt = true;
        }
        $rows[] = [
            'Klasse'  => $klasse,
            'Gesamt'  => (string) $info['total'],
            'Obsolet' => "{$info['obsolet']} ({$prozent}%)",
        ];
    }

    if (empty($rows)) {
        return;
    }

    if (!$genutzt) {
        $report->addFinding(
            'daten',
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
            'daten',
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
        'daten',
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
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'daten');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "daten" gefunden. Bitte zuerst daten-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportDaten($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        $htmlPath = HealthcheckUtils::reportPath($config, 'daten', $ts, 'html');
        $jsonPath = HealthcheckUtils::reportPath($config, 'daten', $ts, 'json');
        HealthcheckUtils::saveFile($htmlPath, $report->toHtml());
        HealthcheckUtils::saveJson($jsonPath, json_decode($report->toJson(), true));
        HealthcheckUtils::log("HTML-Report: $htmlPath", 'success');
        HealthcheckUtils::log("Findings-JSON: $jsonPath", 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
