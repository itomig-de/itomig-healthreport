<?php
/**
 * ITOMIG Healthcheck - Reporter: Hintergrund-Jobs
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportBackgroundTasks(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['db_name'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];

    if (($daten['error'] ?? null) !== null) {
        $report->addFinding(
            'background-tasks',
            'Hintergrund-Jobs nicht abrufbar',
            'info',
            'Die Klasse BackgroundTask konnte nicht über die API abgefragt werden: '
            . $daten['error']
        );
        return $report;
    }

    bewerteUebersicht($report, $daten['summary'] ?? []);
    bewerteStuckJobs($report, $daten['stuck'] ?? []);
    bewerteOverdueJobs($report, $daten['overdue'] ?? [], (int) ($daten['config']['stuck_threshold_hours'] ?? 24));

    return $report;
}

function bewerteUebersicht(HealthcheckReport $report, array $summary): void
{
    if (empty($summary)) {
        $report->addFinding(
            'background-tasks',
            'Keine Hintergrund-Jobs gefunden',
            'info',
            'Es wurden keine BackgroundTask-Datensätze gefunden.'
        );
        return;
    }

    $total = (int) ($summary['total'] ?? 0);
    $running = (int) ($summary['running'] ?? 0);
    $byStatus = $summary['by_status'] ?? [];

    $report->setCategorySummary(
        'background-tasks',
        "$total Hintergrund-Job(s) konfiguriert, $running aktuell laufend.",
        $byStatus
    );

    $report->addFinding(
        'background-tasks',
        "Übersicht Hintergrund-Jobs ($total)",
        'info',
        "$total Hintergrund-Job(s) insgesamt, davon $running aktuell laufend.",
        [
            'Aktiv'     => $byStatus['active'] ?? 0,
            'Pausiert'  => $byStatus['paused'] ?? 0,
            'Entfernt'  => $byStatus['removed'] ?? 0,
        ]
    );
}

function bewerteStuckJobs(HealthcheckReport $report, array $stuck): void
{
    if (empty($stuck)) {
        $report->addFinding(
            'background-tasks',
            'Keine hängenden Jobs',
            'ok',
            'Kein laufender Hintergrund-Job läuft ungewöhnlich lange (kein Job als hängend erkannt).'
        );
        return;
    }

    $details = [];
    foreach ($stuck as $job) {
        $details[] = [
            'Klasse'          => $job['class_name'] ?? '',
            'Letzter Lauf'    => $job['latest_run_date'] ?? '',
            'Ausführendes Konto' => $job['system_user'] ?? 'unbekannt',
        ];
    }

    $report->addFinding(
        'background-tasks',
        count($stuck) . ' hängender Job(e) erkannt',
        'critical',
        'Diese Hintergrund-Jobs laufen aktuell, ihr letzter Lauf liegt aber ungewöhnlich lange zurück. '
        . 'Möglicherweise ist der Cron-Prozess blockiert oder abgestürzt. Prüfen Sie den zugehörigen Prozess auf dem Server.',
        $details
    );
}

function bewerteOverdueJobs(HealthcheckReport $report, array $overdue, int $stuckThresholdHours): void
{
    if (empty($overdue)) {
        $report->addFinding(
            'background-tasks',
            'Keine überfälligen Jobs',
            'ok',
            'Kein aktiver Hintergrund-Job ist überfällig (nächster geplanter Lauf liegt nicht in der Vergangenheit).'
        );
        return;
    }

    $details = [];
    foreach ($overdue as $job) {
        $details[] = [
            'Klasse'             => $job['class_name'] ?? '',
            'Geplanter Lauf'     => $job['next_run_date'] ?? '',
            'Letzter Lauf'       => $job['latest_run_date'] ?? '',
        ];
    }

    $report->addFinding(
        'background-tasks',
        count($overdue) . ' überfällige(r) Job(s)',
        'warning',
        "Diese aktiven Hintergrund-Jobs sind mehr als $stuckThresholdHours Stunden überfällig. "
        . 'Prüfen Sie, ob der iTop-Cron (itop-scheduler-service bzw. cron.php) regelmäßig läuft.',
        $details
    );
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'background-tasks');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "background-tasks" gefunden. Bitte zuerst importieren.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportBackgroundTasks($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'background-tasks', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'background-tasks', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
