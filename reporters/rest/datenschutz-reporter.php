<?php
/**
 * ITOMIG Healthcheck - Reporter: Datenschutz-Analyse
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportDatenschutz(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['kunde'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['umgebung'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['daten'] ?? [];
    $cfg = $daten['konfiguration'] ?? [];

    if ($cfg['pruefe_personen'] ?? true) {
        bewerteInaktivePersonen($report, $daten['personen'] ?? []);
        bewerteAelteresPersonendaten($report, $daten['inaktive_stichprobe'] ?? []);
    }
    bewerteDisabledUsers($report, $daten['disabled_users'] ?? []);
    bewerteDatenschutzRichtlinien($report, $daten['personen'] ?? [], $daten['audit_regeln'] ?? []);

    return $report;
}

function bewerteInaktivePersonen(HealthcheckReport $report, array $personen): void
{
    if (($personen['fehler'] ?? null) !== null) {
        $report->addFinding(
            'datenschutz',
            'Personen-Analyse fehlgeschlagen',
            'warning',
            'Fehler bei der Analyse der Personen: ' . $personen['fehler']
        );
        return;
    }

    $total = (int) ($personen['total'] ?? 0);
    $aktiv = (int) ($personen['aktiv'] ?? 0);
    $inaktiv = (int) ($personen['inaktiv'] ?? 0);
    $obsolet = $personen['obsolet'] !== null ? (int) $personen['obsolet'] : 0;

    $report->setCategorySummary(
        'datenschutz',
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
        'datenschutz',
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
    if (($stichprobe['fehler'] ?? null) !== null) {
        $report->addFinding(
            'datenschutz',
            'Älteste Personendaten nicht ermittelbar',
            'info',
            'Die ältesten Personendaten konnten nicht ermittelt werden: ' . $stichprobe['fehler']
        );
        return;
    }

    $items = $stichprobe['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'datenschutz',
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
        'datenschutz',
        "Inaktive Personen (Stichprobe: $count)",
        $severity,
        "Stichprobe von $count inaktiven Personen. Prüfen Sie, ob für diese Datensätze noch "
        . 'ein Speicherzweck besteht. Gemäß Art. 17 DSGVO haben betroffene Personen ein Recht auf Löschung.',
        $details
    );
}

function bewerteDisabledUsers(HealthcheckReport $report, array $users): void
{
    if (($users['fehler'] ?? null) !== null) {
        $report->addFinding(
            'datenschutz',
            'User-Account-Analyse fehlgeschlagen',
            'info',
            'Fehler beim Abruf der User-Accounts: ' . $users['fehler']
        );
        return;
    }

    $count = (int) ($users['count'] ?? 0);
    if ($count === 0) {
        $report->addFinding(
            'datenschutz',
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
        'datenschutz',
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

    if (($personen['obsolet_fehler'] ?? null) !== null) {
        $pruefungen[] = [
            'Richtlinie' => 'Obsolescence für Personen',
            'Status'     => 'Nicht prüfbar',
            'Empfehlung' => 'Manuell prüfen',
        ];
    } else {
        $obsolet = (int) ($personen['obsolet'] ?? 0);
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

    if (($audit['fehler'] ?? null) === null) {
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
        'datenschutz',
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
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'datenschutz');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "datenschutz" gefunden. Bitte zuerst datenschutz-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportDatenschutz($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'datenschutz', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'datenschutz', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
