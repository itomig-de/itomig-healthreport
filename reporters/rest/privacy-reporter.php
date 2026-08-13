<?php
/**
 * ITOMIG Healthcheck - Reporter: Datenschutz-Analyse
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportPrivacy(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['customer'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];
    $cfg = $daten['config'] ?? [];

    if ($cfg['check_persons'] ?? true) {
        bewerteInaktivePersonen($report, $daten['persons'] ?? []);
        bewerteAelteresPersonendaten($report, $daten['inactive_sample'] ?? []);
    }
    bewerteDisabledUsers($report, $daten['disabled_users'] ?? []);
    bewerteDatenschutzRichtlinien($report, $daten['persons'] ?? [], $daten['audit_rules'] ?? []);

    return $report;
}

function bewerteInaktivePersonen(HealthcheckReport $report, array $personen): void
{
    if (($personen['error'] ?? null) !== null) {
        $report->addFinding(
            'privacy',
            'Personen-Analyse fehlgeschlagen',
            'warning',
            'Fehler bei der Analyse der Personen: ' . $personen['error']
        );
        return;
    }

    $total = (int) ($personen['total'] ?? 0);
    $aktiv = (int) ($personen['active'] ?? 0);
    $inaktiv = (int) ($personen['inactive'] ?? 0);
    $obsolet = $personen['obsolete'] !== null ? (int) $personen['obsolete'] : 0;

    $report->setCategorySummary(
        'privacy',
        "$total Personen gesamt: $aktiv aktiv, $inaktiv inaktiv, $obsolet obsolet."
    );

    $details = [
        ['Status' => 'Aktiv', 'Anzahl' => (string) $aktiv],
        ['Status' => 'Inaktiv', 'Anzahl' => (string) $inaktiv],
        ['Status' => 'Obsolet', 'Anzahl' => (string) $obsolet],
    ];

    $severity = 'ok';
    if ($inaktiv > 50) {
        $severity = 'info';
    }
    if ($inaktiv > 200) {
        $severity = 'warning';
    }

    $report->addFinding(
        'privacy',
        "Personen-Übersicht ($total)",
        $severity,
        $inaktiv > 0
            ? "$inaktiv inaktive Personen gefunden. Gemäß DSGVO sollten personenbezogene Daten "
              . "gelöscht oder anonymisiert werden, sobald der Zweck der Speicherung entfällt."
            : 'Keine inaktiven Personen gefunden.',
        $details
    );
}

function bewerteAelteresPersonendaten(HealthcheckReport $report, array $stichprobe): void
{
    if (($stichprobe['error'] ?? null) !== null) {
        $report->addFinding(
            'privacy',
            'Älteste Personendaten nicht ermittelbar',
            'info',
            'Die ältesten Personendaten konnten nicht ermittelt werden: ' . $stichprobe['error']
        );
        return;
    }

    $items = $stichprobe['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'privacy',
            'Keine inaktiven Personendaten',
            'ok',
            'Es gibt keine Personen mit Status "inaktiv" in der Datenbank.'
        );
        return;
    }

    $details = [];
    foreach ($items as $p) {
        $email = $p['email'] ?? '';
        $emailMasked = '';
        if ($email !== '') {
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $emailMasked = substr($parts[0], 0, 2) . '***@' . $parts[1];
            }
        }
        $details[] = [
            'Name'           => $p['friendlyname'] ?? 'Unbekannt',
            'E-Mail (mask.)' => $emailMasked ?: '-',
            'Organisation'   => $p['org_name'] ?? '-',
        ];
    }

    $count = count($items);
    $severity = $count > 10 ? 'warning' : 'info';

    $report->addFinding(
        'privacy',
        "Inaktive Personen (Stichprobe: $count)",
        $severity,
        "Stichprobe von $count inaktiven Personen. Prüfen Sie, ob für diese Datensätze noch "
        . 'ein Speicherzweck besteht. Gemäß Art. 17 DSGVO haben betroffene Personen ein Recht auf Löschung.',
        $details
    );
}

function bewerteDisabledUsers(HealthcheckReport $report, array $users): void
{
    if (($users['error'] ?? null) !== null) {
        $report->addFinding(
            'privacy',
            'User-Account-Analyse fehlgeschlagen',
            'info',
            'Fehler beim Abruf der User-Accounts: ' . $users['error']
        );
        return;
    }

    $count = (int) ($users['count'] ?? 0);
    if ($count === 0) {
        $report->addFinding(
            'privacy',
            'Keine deaktivierten User-Accounts',
            'ok',
            'Es gibt keine deaktivierten User-Accounts.'
        );
        return;
    }

    $items = array_slice($users['items'] ?? [], 0, 15);
    $details = [];
    foreach ($items as $u) {
        $details[] = [
            'Login'  => $u['login'] ?? 'Unbekannt',
            'Person' => $u['contactid_friendlyname'] ?? '-',
        ];
    }

    $severity = $count > 20 ? 'warning' : 'info';
    $report->addFinding(
        'privacy',
        "Deaktivierte User-Accounts ($count)",
        $severity,
        "$count deaktivierte User-Accounts vorhanden. "
        . 'Deaktivierte Accounts enthalten möglicherweise personenbezogene Daten (Login-Name, E-Mail). '
        . 'Prüfen Sie, ob diese bereinigt werden können.',
        $details
    );
}

function bewerteDatenschutzRichtlinien(HealthcheckReport $report, array $personen, array $audit): void
{
    $pruefungen = [];
    $fehlend = 0;

    if (($personen['obsolete_error'] ?? null) !== null) {
        $pruefungen[] = [
            'Richtlinie' => 'Obsolescence für Personen',
            'Status'     => 'Nicht prüfbar',
            'Empfehlung' => 'Manuell prüfen',
        ];
    } else {
        $obsolet = (int) ($personen['obsolete'] ?? 0);
        $pruefungen[] = [
            'Richtlinie' => 'Obsolescence für Personen',
            'Status'     => $obsolet > 0 ? 'Aktiv genutzt' : 'Nicht genutzt',
            'Empfehlung' => $obsolet > 0
                ? "$obsolet Personen als obsolet markiert"
                : 'Obsolescence-Regeln für Personen konfigurieren',
        ];
        if ($obsolet === 0) {
            $fehlend++;
        }
    }

    if (($audit['error'] ?? null) === null) {
        $datenschutzRegel = false;
        foreach ($audit['items'] ?? [] as $r) {
            $name = strtolower($r['name'] ?? '');
            $desc = strtolower($r['description'] ?? '');
            foreach (['datenschutz', 'dsgvo', 'gdpr', 'privacy'] as $kw) {
                if (strpos($name, $kw) !== false || strpos($desc, $kw) !== false) {
                    $datenschutzRegel = true;
                    break 2;
                }
            }
        }
        $pruefungen[] = [
            'Richtlinie' => 'Datenschutz-Audit-Regeln',
            'Status'     => $datenschutzRegel ? 'Vorhanden' : 'Nicht gefunden',
            'Empfehlung' => $datenschutzRegel
                ? 'Audit-Regeln für Datenschutz sind eingerichtet'
                : 'Erstellen Sie Audit-Regeln die z.B. inaktive Personen > X Tage melden',
        ];
        if (!$datenschutzRegel) {
            $fehlend++;
        }
    }

    $pruefungen[] = [
        'Richtlinie' => 'Löschkonzept',
        'Status'     => 'Manuell prüfen',
        'Empfehlung' => 'Dokumentiertes Löschkonzept für personenbezogene Daten in iTop',
    ];
    $pruefungen[] = [
        'Richtlinie' => 'Verarbeitungsverzeichnis',
        'Status'     => 'Manuell prüfen',
        'Empfehlung' => 'iTop im Verarbeitungsverzeichnis (Art. 30 DSGVO) aufgeführt?',
    ];

    $severity = $fehlend >= 2 ? 'warning' : 'info';

    $report->addFinding(
        'privacy',
        'Datenschutz-Richtlinien',
        $severity,
        $fehlend > 0
            ? "$fehlend automatisiert prüfbare Datenschutzmaßnahme(n) fehlen oder sind nicht aktiv. "
              . 'Weitere Aspekte erfordern eine manuelle Prüfung.'
            : 'Die automatisiert prüfbaren Datenschutzmaßnahmen sind vorhanden.',
        $pruefungen
    );
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'privacy');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "privacy" gefunden. Bitte zuerst privacy-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportPrivacy($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'privacy', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'privacy', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
