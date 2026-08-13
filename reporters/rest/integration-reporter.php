<?php
/**
 * ITOMIG Healthcheck - Reporter: Integration & Benachrichtigung
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportIntegration(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['kunde'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['umgebung'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['daten'] ?? [];
    $cfg = $daten['konfiguration'] ?? [];

    bewerteUserAccounts($report, $daten['user_accounts'] ?? []);
    if ($cfg['pruefe_mail'] ?? true) {
        bewerteMailAktionen($report, $daten['mail_aktionen'] ?? []);
    }
    if ($cfg['pruefe_webhooks'] ?? true) {
        bewerteWebhooks($report, $daten['webhooks'] ?? []);
    }
    if ($cfg['pruefe_incoming_mail'] ?? true) {
        bewerteIncomingMail($report, $daten['incoming_mail'] ?? []);
    }
    bewerteAiIntegration($report, $daten['ai_integration'] ?? []);

    return $report;
}

function bewerteUserAccounts(HealthcheckReport $report, array $users): void
{
    if (($users['fehler'] ?? null) !== null) {
        $report->addFinding(
            'integration',
            'User-Accounts konnten nicht geprüft werden',
            'warning',
            'Fehler beim Abruf der User-Accounts: ' . $users['fehler']
        );
        return;
    }

    $aktiv = (int) ($users['aktiv'] ?? 0);
    $deaktiviert = (int) ($users['deaktiviert'] ?? 0);
    $admins = (int) ($users['mit_admin'] ?? 0);
    $ohneKontakt = (int) ($users['ohne_kontakt'] ?? 0);

    $report->setCategorySummary(
        'integration',
        "$aktiv aktive und $deaktiviert deaktivierte User-Accounts. $admins User mit Administrator-Profil."
    );

    if ($ohneKontakt > 0) {
        $report->addFinding(
            'integration',
            "User ohne Kontakt-Zuordnung ($ohneKontakt)",
            'warning',
            "$ohneKontakt aktive User-Accounts sind keiner Person zugeordnet. "
            . 'Empfehlung: Weisen Sie jedem User-Account eine Person zu, damit Aktionen nachvollziehbar sind.'
        );
    }

    if ($admins > 3) {
        $report->addFinding(
            'integration',
            "Viele Administrator-Accounts ($admins)",
            'warning',
            "$admins User haben ein Administrator-Profil. "
            . 'Empfehlung: Beschränken Sie Administrator-Rechte auf das Minimum und nutzen Sie spezifische Profile für alltägliche Aufgaben.'
        );
    } else {
        $report->addFinding(
            'integration',
            'Administrator-Accounts',
            'ok',
            "$admins User mit Administrator-Profil — im empfohlenen Bereich."
        );
    }

    if ($deaktiviert > $aktiv && $aktiv >= 0) {
        $report->addFinding(
            'integration',
            "Viele deaktivierte Accounts ($deaktiviert)",
            'info',
            "Es gibt mehr deaktivierte ($deaktiviert) als aktive ($aktiv) User-Accounts. "
            . 'Prüfen Sie, ob alte Accounts bereinigt werden können.'
        );
    }
}

function bewerteMailAktionen(HealthcheckReport $report, array $mail): void
{
    if (($mail['fehler'] ?? null) !== null) {
        $report->addFinding(
            'integration',
            'Benachrichtigungen konnten nicht geprüft werden',
            'info',
            'Fehler beim Abruf der Benachrichtigungskonfiguration: ' . $mail['fehler']
        );
        return;
    }

    $triggerCount = (int) ($mail['trigger_count'] ?? 0);
    $aktionen = $mail['aktionen'] ?? [];
    $actionCount = count($aktionen);

    if ($triggerCount === 0 && $actionCount === 0) {
        $report->addFinding(
            'integration',
            'Keine Benachrichtigungen konfiguriert',
            'warning',
            'Es sind weder Trigger noch Benachrichtigungsaktionen eingerichtet. '
            . 'Ohne Benachrichtigungen werden Anwender nicht über Ticket-Änderungen informiert.'
        );
        return;
    }

    $aktiv = 0;
    $inaktiv = 0;
    foreach ($aktionen as $a) {
        if (in_array($a['status'] ?? '', ['enabled', 'test'], true)) {
            $aktiv++;
        } else {
            $inaktiv++;
        }
    }

    $details = [
        'Trigger'          => (string) $triggerCount,
        'Aktionen gesamt'  => (string) $actionCount,
        'Aktionen aktiv'   => (string) $aktiv,
        'Aktionen inaktiv' => (string) $inaktiv,
    ];

    $report->addFinding(
        'integration',
        'Mail-Benachrichtigungen',
        ($inaktiv > $aktiv && $actionCount > 0) ? 'warning' : 'ok',
        "$triggerCount Trigger und $actionCount Benachrichtigungsaktionen konfiguriert ($aktiv aktiv, $inaktiv inaktiv).",
        $details
    );
}

function bewerteWebhooks(HealthcheckReport $report, array $webhooks): void
{
    $items = $webhooks['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'integration',
            'Keine Webhooks konfiguriert',
            'info',
            'Es sind keine Webhook- oder Event-Notification-Konfigurationen vorhanden. '
            . 'Webhooks ermöglichen die Integration mit externen Systemen (z.B. Teams, Slack, Monitoring-Tools).'
        );
        return;
    }
    $proKlasse = [];
    foreach ($items as $w) {
        $proKlasse[$w['klasse']][] = $w;
    }
    foreach ($proKlasse as $klasse => $list) {
        $details = [];
        foreach ($list as $w) {
            $details[] = [
                'Name'   => $w['name'] ?? 'Unbekannt',
                'Status' => $w['status'] ?? '-',
            ];
        }
        $report->addFinding(
            'integration',
            "$klasse (" . count($list) . ' konfiguriert)',
            'info',
            count($list) . " $klasse-Objekte gefunden.",
            $details
        );
    }
}

function bewerteIncomingMail(HealthcheckReport $report, array $incoming): void
{
    if (($incoming['fehler'] ?? null) !== null) {
        $report->addFinding(
            'integration',
            'Incoming Mail nicht prüfbar',
            'info',
            'Die Klasse MailInboxStandard ist nicht verfügbar. Das Mail-Modul ist möglicherweise nicht installiert.'
        );
        return;
    }

    $items = $incoming['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'integration',
            'Kein E-Mail-Eingang konfiguriert',
            'info',
            'Es ist keine MailInboxStandard konfiguriert. '
            . 'Wenn Tickets per E-Mail erstellt werden sollen, sollte mindestens eine Mailbox eingerichtet werden.'
        );
        return;
    }

    $details = [];
    $inaktiv = 0;
    foreach ($items as $mb) {
        if (($mb['status'] ?? 'inactive') !== 'active') {
            $inaktiv++;
        }
        $details[] = [
            'Mailbox'    => $mb['name'] ?? 'Unbekannt',
            'Server'     => $mb['server'] ?? '-',
            'Protokoll'  => $mb['protocol'] ?? '-',
            'Zielklasse' => $mb['target_class'] ?? '-',
            'Status'     => $mb['status'] ?? 'inactive',
        ];
    }

    $count = count($items);
    $report->addFinding(
        'integration',
        "Eingehende Mailboxen ($count)",
        $inaktiv > 0 ? 'warning' : 'ok',
        "$count Mailbox(en) konfiguriert" . ($inaktiv > 0 ? ", davon $inaktiv inaktiv." : '.'),
        $details
    );
}

function bewerteAiIntegration(HealthcheckReport $report, array $ai): void
{
    $gefunden = false;
    foreach ($ai as $klasse => $info) {
        if (($info['count'] ?? 0) > 0) {
            $gefunden = true;
            $report->addFinding(
                'integration',
                "AI-Konfiguration: $klasse ({$info['count']})",
                'info',
                "{$info['count']} $klasse-Objekte gefunden. Eine AI-Integration ist konfiguriert."
            );
        }
    }
    if (!$gefunden) {
        $report->addFinding(
            'integration',
            'Keine AI-Integration erkannt',
            'info',
            'Es wurden keine bekannten AI-Integrations-Klassen gefunden. '
            . 'iTop bietet ab Version 3.2 Möglichkeiten zur AI-Integration (z.B. Ticket-Zusammenfassung, Klassifizierungs-Vorschläge).'
        );
    }
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'integration');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "integration" gefunden. Bitte zuerst integration-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportIntegration($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'integration', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'integration', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
