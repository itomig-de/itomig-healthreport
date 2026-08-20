<?php
/**
 * ITOMIG Healthcheck - ZIP-Import
 *
 * Entpackt ein vom Collector (iTop-Extension itomig-healthcheck) erzeugtes
 * Healthcheck-ZIP ins Pro-Kunde-Layout:
 *   daten/{kunde-slug}/raw/{modul}/{Y-m-d_His}.json
 *
 * Aufruf:
 *   php tools/import-zip.php --zip=/pfad/zur/healthcheck_xxx.zip [--config=…]
 *
 * Wird auch als Library-Funktion `importZip($zipPath, $config)` von
 * report-from-zip.php genutzt.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/HealthcheckUtils.php';
require_once __DIR__ . '/../lib/HealthcheckModules.php';

/**
 * Entpackt das ZIP und schreibt die Modul-JSONs ins Pro-Kunde-Layout.
 *
 * Hinweis (Collector-Version >= 26.3.0 von itomig-healthcheck): `manifest.customer`
 * ist seither die volle app_root_url der Kundeninstanz (Fallback db_name, falls
 * app_root_url leer ist) und daher nicht mehr als Kurz-/Anzeigename geeignet.
 * Der bisherige Zweck von `customer` (Slug fuer daten/auswertung, Anzeigename)
 * wird deshalb aus dem neuen Feld `db_name` gespeist. `customer` (roh, als URL)
 * und `instance_id` (stabile UUID der Kundeninstanz) werden zusaetzlich
 * durchgereicht, um kuenftig Mismatches zwischen Name und Instanz erkennen zu
 * koennen - siehe itomig-healthcheck/docs/key-changes-for-report-creator.md
 * Abschnitte 6 und 8.
 *
 * @return array{
 *     kunde: string,
 *     umgebung: string,
 *     timestamp: string,
 *     itop_version: string|null,
 *     db_server_version: string|null,
 *     extension_version: string|null,
 *     customer_url: string|null,
 *     instance_id: string|null,
 *     imported: array<string, string>
 * }
 */
function importZip(string $zipPath, array $config): array
{
    if (!file_exists($zipPath)) {
        throw new \RuntimeException("ZIP-Datei nicht gefunden: $zipPath");
    }

    $zip = new \ZipArchive();
    $opened = $zip->open($zipPath);
    if ($opened !== true) {
        throw new \RuntimeException("ZIP konnte nicht geöffnet werden (Fehlercode $opened): $zipPath");
    }

    $manifestRaw = $zip->getFromName('manifest.json');
    if ($manifestRaw === false) {
        $zip->close();
        throw new \RuntimeException('manifest.json fehlt im ZIP — kein gültiges Healthcheck-Collector-Paket.');
    }

    try {
        $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        $zip->close();
        throw new \RuntimeException('manifest.json ist kein gültiges JSON: ' . $e->getMessage());
    }

    // Name/Slug bewusst aus db_name statt customer: customer ist seit
    // Collector-Version 26.3.0 die app_root_url (URL), db_name entspricht dem
    // fruehen Verhalten (Datenbankname der Kundeninstanz).
    $kundeRaw = (string) ($manifest['db_name'] ?? 'unbekannt');
    $kundeSlug = HealthcheckUtils::sanitizeFilename($kundeRaw);
    if ($kundeSlug === '') {
        $kundeSlug = 'unbekannt';
    }
    $umgebung = (string) ($manifest['environment'] ?? '');
    $appRootUrl = (string) ($manifest['app_root_url'] ?? '');
    $customerUrl = isset($manifest['customer']) ? (string) $manifest['customer'] : null;
    $instanceId = isset($manifest['instance_id']) ? (string) $manifest['instance_id'] : null;

    // Kunde in Config-Kopie überschreiben für die Pfad-Resolver; app_root_url
    // nur für Anzeige/Debugging mitgegeben, nicht für die Pfadbildung genutzt.
    $config['kunde'] = ['name' => $kundeSlug, 'umgebung' => $umgebung, 'app_root_url' => $appRootUrl];

    $ts = parseManifestTimestamp((string) ($manifest['timestamp'] ?? ''));
    $module = $manifest['modules'] ?? array_keys(HealthcheckModules::all());

    HealthcheckUtils::log("Import-Quelle: $zipPath", 'info');
    HealthcheckUtils::log("Kunde: $kundeSlug · Umgebung: $umgebung · Timestamp: $ts", 'info');
    HealthcheckUtils::log(count($module) . ' Modul(e) im Manifest', 'info');

    $imported = [];
    foreach ($module as $modul) {
        $jsonName = $modul . '.json';
        $raw = $zip->getFromName($jsonName);
        if ($raw === false) {
            HealthcheckUtils::log("Modul-JSON fehlt im ZIP: $jsonName", 'warning');
            continue;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            HealthcheckUtils::log("Modul-JSON $jsonName ist ungültig: " . $e->getMessage(), 'warning');
            continue;
        }

        if (!is_array($data) || !isset($data['meta'], $data['data'])) {
            HealthcheckUtils::log("Modul-JSON $jsonName hat unerwartetes Schema (meta/data fehlt)", 'warning');
            continue;
        }

        $targetPath = HealthcheckUtils::rawJsonPath($config, $modul, $ts);
        HealthcheckUtils::saveJson($targetPath, $data);
        HealthcheckUtils::log("Importiert: $targetPath", 'success');
        $imported[$modul] = $targetPath;
    }

    $zip->close();

    return [
        'kunde'             => $kundeSlug,
        'umgebung'          => $umgebung,
        'timestamp'         => $ts,
        'itop_version'      => $manifest['itop_version'] ?? null,
        'db_server_version' => $manifest['db_server_version'] ?? null,
        'extension_version' => $manifest['extension_version'] ?? null,
        'customer_url'      => $customerUrl,
        'instance_id'       => $instanceId,
        'imported'          => $imported,
    ];
}

/**
 * Konvertiert ISO-8601-Timestamp aus dem Manifest in das Y-m-d_His-Filename-Format.
 */
function parseManifestTimestamp(string $iso): string
{
    if ($iso === '') {
        return HealthcheckUtils::timestamp();
    }
    try {
        return (new \DateTimeImmutable($iso))->format('Y-m-d_His');
    } catch (\Exception $e) {
        return HealthcheckUtils::timestamp();
    }
}

// --- CLI ---

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());

        $zipPath = null;
        foreach ($argv as $arg) {
            if (strpos($arg, '--zip=') === 0) {
                $zipPath = substr($arg, 6);
            }
        }
        if ($zipPath === null && isset($argv[1]) && strpos($argv[1], '--') !== 0) {
            $zipPath = $argv[1];
        }
        if ($zipPath === null) {
            throw new \RuntimeException('Bitte ZIP-Pfad angeben: --zip=/pfad/zur/healthcheck.zip');
        }

        echo "\n=========================================\n";
        echo "  ITOMIG Healthcheck - ZIP-Import\n";
        echo "=========================================\n\n";

        $result = importZip($zipPath, $config);

        echo "\n=========================================\n";
        echo "  Import abgeschlossen\n";
        echo "  Kunde:     {$result['kunde']}\n";
        echo "  Umgebung:  {$result['umgebung']}\n";
        echo "  Timestamp: {$result['timestamp']}\n";
        echo "  Module:    " . count($result['imported']) . " importiert\n";
        echo "=========================================\n";
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
