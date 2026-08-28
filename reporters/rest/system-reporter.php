<?php
/**
 * ITOMIG Healthcheck - Reporter: System-Analyse
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/HealthcheckReport.php';
require_once __DIR__ . '/../../lib/HealthcheckUtils.php';

function reportSystem(array $raw, array $config): HealthcheckReport
{
    $kunde = $raw['meta']['db_name'] ?? ($config['kunde']['name'] ?? '');
    $umgebung = $raw['meta']['environment'] ?? ($config['kunde']['umgebung'] ?? '');
    $report = new HealthcheckReport($kunde, $umgebung);

    $daten = $raw['data'] ?? [];
    $cfg = $daten['config'] ?? [];

    $itopVersion = $raw['meta']['itop_version'] ?? null;
    bewerteItopVersion($report, $itopVersion !== null ? (string) $itopVersion : null);
    bewerteModulInstallationen($report, $daten['module_installations'] ?? []);
    if ($cfg['check_php_config'] ?? true) {
        bewertePhpKonfiguration($report, $daten['php'] ?? []);
    }
    if ($cfg['check_db'] ?? true) {
        bewerteDatenbankUebersicht($report, $daten['main_classes'] ?? []);
    }

    return $report;
}

function bewerteItopVersion(HealthcheckReport $report, ?string $version): void
{
    if ($version === null || $version === '') {
        $report->addFinding(
            'system',
            'iTop-Version nicht ermittelbar',
            'info',
            'Die iTop-Version konnte nicht über die API ermittelt werden. '
            . 'Prüfen Sie die Version manuell unter: Administration > iTop Hub / About.'
        );
        return;
    }

    $matrix = [
        ['3.2', '9.9', 'ok', 'Aktuelle Version', 'Diese Version wird aktiv unterstützt und erhält Sicherheitsupdates.'],
        ['3.1', '3.1.99', 'info', 'Unterstützte Version', 'iTop 3.1 wird noch unterstützt. Ein Upgrade auf 3.2 ist empfehlenswert.'],
        ['3.0', '3.0.99', 'warning', 'Ältere Version', 'iTop 3.0 nähert sich dem End-of-Life. Planen Sie ein Upgrade.'],
        ['2.7', '2.7.99', 'warning', 'Veraltete Version', 'iTop 2.7 ist veraltet. Ein Upgrade ist dringend empfohlen. Bekannte Sicherheitslücken können bestehen.'],
        ['0.0', '2.6.99', 'critical', 'End-of-Life', 'Diese Version wird nicht mehr unterstützt und erhält keine Sicherheitsupdates. Ein sofortiges Upgrade ist erforderlich.'],
    ];

    $severity = 'info';
    $statusText = 'Unbekannte Version';
    $beschreibung = "Die Version $version konnte nicht eingeordnet werden.";

    foreach ($matrix as [$min, $max, $sev, $st, $hint]) {
        if (version_compare($version, $min, '>=') && version_compare($version, $max, '<=')) {
            $severity = $sev;
            $statusText = $st;
            $beschreibung = $hint;
            break;
        }
    }

    $report->setCategorySummary('system', "iTop-Version: $version — $statusText");
    $report->addFinding(
        'system',
        "iTop Version $version",
        $severity,
        $beschreibung,
        [
            'Version'       => $version,
            'Dokumentation' => 'https://www.itophub.io/wiki/page?id=latest:release:start',
        ]
    );
}

function bewerteModulInstallationen(HealthcheckReport $report, array $modules): void
{
    if (($modules['error'] ?? null) !== null) {
        $report->addFinding(
            'system',
            'Modul-Versionen nicht prüfbar',
            'info',
            'Die Klasse ModuleInstallation ist möglicherweise nicht über die API zugänglich: '
            . $modules['error']
        );
        return;
    }

    $items = $modules['items'] ?? [];
    if (empty($items)) {
        $report->addFinding(
            'system',
            'Modul-Informationen nicht verfügbar',
            'info',
            'Die Modul-Installationshistorie konnte nicht abgerufen werden.'
        );
        return;
    }

    $latest = [];
    foreach ($items as $m) {
        $name = $m['name'] ?? '';
        if ($name === '') {
            continue;
        }
        if (!isset($latest[$name]) || ($m['installed'] ?? '') > $latest[$name]['installed']) {
            $latest[$name] = $m;
        }
    }

    $itomig = [];
    $combodo = 0;
    $sonstige = 0;
    foreach ($latest as $m) {
        $name = $m['name'];
        if (stripos($name, 'itomig') !== false) {
            $itomig[] = [
                'Modul'       => $name,
                'Version'     => $m['version'] ?? '',
                'Installiert' => $m['installed'] ?? '',
            ];
        } elseif (stripos($name, 'combodo') !== false || stripos($name, 'itop-') !== false) {
            $combodo++;
        } else {
            $sonstige++;
        }
    }

    $moduleCount = count($latest);
    $report->addFinding(
        'system',
        "Installierte Module ($moduleCount)",
        'info',
        "$moduleCount Module installiert: $combodo Combodo/Standard, " . count($itomig) . " ITOMIG, $sonstige Sonstige.",
        !empty($itomig) ? $itomig : []
    );
}

function bewertePhpKonfiguration(HealthcheckReport $report, array $php): void
{
    $version = $php['version'] ?? null;
    if ($version !== null && $version !== '') {
        bewertePhpVersion($report, (string) $version, (string) ($php['sapi'] ?? ''));
        bewertePhpExtensions($report, $php['extensions'] ?? []);
    }

    $empfehlungen = [
        ['Parameter' => 'PHP Version', 'Empfehlung' => '>= 8.1 (für iTop 3.x)'],
        ['Parameter' => 'memory_limit', 'Empfehlung' => '>= 256M'],
        ['Parameter' => 'max_execution_time', 'Empfehlung' => '>= 300'],
        ['Parameter' => 'upload_max_filesize', 'Empfehlung' => '>= 20M'],
        ['Parameter' => 'post_max_size', 'Empfehlung' => '>= 32M'],
        ['Parameter' => 'max_input_vars', 'Empfehlung' => '>= 5000'],
        ['Parameter' => 'date.timezone', 'Empfehlung' => 'Explizit gesetzt (z.B. Europe/Berlin)'],
        ['Parameter' => 'opcache.enable', 'Empfehlung' => '1 (für Performance)'],
    ];

    $report->addFinding(
        'system',
        'PHP-Konfiguration (manuelle Prüfung)',
        'info',
        'Die zentralen php.ini-Werte (memory_limit, max_execution_time, …) werden vom Collector '
        . 'nicht mitgeliefert. Empfehlung: Prüfen Sie die folgenden Parameter in der php.ini '
        . 'bzw. über phpinfo() auf dem Server.',
        $empfehlungen
    );
}

function bewertePhpVersion(HealthcheckReport $report, string $version, string $sapi): void
{
    $severity = 'ok';
    $hinweis = "PHP $version wird für iTop 3.2 unterstützt (empfohlen: 8.1 - 8.3).";
    if (version_compare($version, '8.1', '<')) {
        $severity = 'critical';
        $hinweis = "PHP $version wird von iTop 3.2 nicht unterstützt (mindestens 8.1 erforderlich). "
            . 'Ein Upgrade der PHP-Version ist dringend erforderlich.';
    } elseif (version_compare($version, '8.4', '>=')) {
        $severity = 'warning';
        $hinweis = "PHP $version ist neuer als von iTop 3.2 unterstützt (8.1 - 8.3). "
            . 'Prüfen Sie, ob eine ältere PHP-Version verwendet werden sollte.';
    }

    $report->addFinding(
        'system',
        "PHP-Version $version",
        $severity,
        $hinweis,
        [
            'Version' => $version,
            'SAPI'    => $sapi !== '' ? $sapi : 'unbekannt',
        ]
    );
}

function bewertePhpExtensions(HealthcheckReport $report, array $extensions): void
{
    $vorhanden = array_map('strtolower', $extensions);
    $benoetigt = ['json', 'zip', 'mysqli', 'mbstring', 'curl', 'gd', 'openssl', 'iconv', 'xml', 'soap'];

    $details = [];
    $fehlend = [];
    foreach ($benoetigt as $ext) {
        $ok = in_array($ext, $vorhanden, true);
        $details[] = ['Extension' => $ext, 'Status' => $ok ? 'Vorhanden' : 'Fehlt'];
        if (!$ok) {
            $fehlend[] = $ext;
        }
    }

    $severity = empty($fehlend) ? 'ok' : 'warning';
    $text = empty($fehlend)
        ? 'Alle für iTop relevanten PHP-Extensions sind geladen (' . count($extensions) . ' Extensions insgesamt).'
        : 'Folgende für iTop relevante PHP-Extensions fehlen: ' . implode(', ', $fehlend) . '.';

    $report->addFinding(
        'system',
        'PHP-Extensions',
        $severity,
        $text,
        $details
    );
}

function bewerteDatenbankUebersicht(HealthcheckReport $report, array $hauptklassen): void
{
    $details = [];
    $total = 0;
    foreach ($hauptklassen as $info) {
        if (($info['error'] ?? null) !== null || ($info['count'] ?? null) === null) {
            continue;
        }
        $details[] = [
            'Klasse' => $info['label'] ?? '',
            'Anzahl' => number_format((int) $info['count'], 0, ',', '.'),
        ];
        $total += (int) $info['count'];
    }

    $severity = 'info';
    $hinweis = 'Insgesamt ca. ' . number_format($total, 0, ',', '.') . ' Objekte in den Hauptklassen.';
    if ($total > 1000000) {
        $severity = 'warning';
        $hinweis .= ' Bei dieser Datenmenge ist auf ausreichende DB-Performance und Indexierung zu achten.';
    }

    $report->addFinding(
        'system',
        'Datenbank-Übersicht',
        $severity,
        $hinweis . ' Eine detaillierte DB-Analyse (Tabellengrößen, Index-Nutzung, Wachstum) erfordert direkten Datenbankzugriff.',
        $details
    );

    $report->addFinding(
        'system',
        'Datenbank-Detailanalyse (manuell)',
        'info',
        'Für eine tiefgehende DB-Analyse empfehlen wir folgende Prüfungen direkt auf dem Datenbankserver:',
        [
            ['Prüfung' => 'Tabellengröße', 'Befehl' => "SELECT table_name, ROUND(data_length/1024/1024,2) AS size_mb FROM information_schema.tables WHERE table_schema = 'itop' ORDER BY data_length DESC LIMIT 20"],
            ['Prüfung' => 'Index-Nutzung', 'Befehl' => 'SHOW INDEX FROM <tabelle>'],
            ['Prüfung' => 'Langsame Queries', 'Befehl' => 'slow_query_log aktivieren und analysieren'],
        ]
    );
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());
        $rawPath = HealthcheckUtils::parseRawOption() ?? HealthcheckUtils::latestRawJson($config, 'system');
        if ($rawPath === null) {
            throw new \RuntimeException('Keine Rohdaten für Modul "system" gefunden. Bitte zuerst system-collector.php ausführen.');
        }
        HealthcheckUtils::log("Lese Rohdaten: $rawPath", 'info');
        $raw = HealthcheckUtils::loadRawJson($rawPath);
        $report = reportSystem($raw, $config);
        $ts = HealthcheckUtils::timestamp();
        HealthcheckUtils::saveFile(HealthcheckUtils::reportPath($config, 'system', $ts, 'html'), $report->toHtml());
        HealthcheckUtils::saveJson(HealthcheckUtils::reportPath($config, 'system', $ts, 'json'), json_decode($report->toJson(), true));
        HealthcheckUtils::log('Report geschrieben.', 'success');
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
