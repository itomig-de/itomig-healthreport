<?php
/**
 * ITOMIG Healthcheck - Hilfsfunktionen
 *
 * Utility-Klasse für die ITOMIG-interne Auswertungs-Pipeline:
 * eingehende Collector-ZIPs (von der iTop-Extension itomig-healthcheck) werden
 * importiert und durch die Reporter in HTML-Reports übersetzt.
 *
 * Pfad-Resolver für das Pro-Kunde-Layout:
 *   daten/{kunde}/raw/{modul}/{Y-m-d_His}.json
 *   auswertung/{kunde}/{modul}_{Y-m-d_His}.{html,json}
 */

declare(strict_types=1);

class HealthcheckUtils
{
    /**
     * Lädt die Reporter-Konfiguration.
     *
     * Fallback-Kette:
     *   1. expliziter $configPath
     *   2. config/healthcheck-config.php (lokale Overrides, gitignored)
     *   3. config/reporter-defaults.php (im Repo, Standard für ITOMIG)
     */
    public static function loadConfig(?string $configPath = null): array
    {
        if ($configPath === null) {
            $localOverride = __DIR__ . '/../config/healthcheck-config.php';
            $reporterDefaults = __DIR__ . '/../config/reporter-defaults.php';

            if (file_exists($localOverride)) {
                $configPath = $localOverride;
            } elseif (file_exists($reporterDefaults)) {
                $configPath = $reporterDefaults;
            }
        }

        if ($configPath === null || !file_exists($configPath)) {
            throw new \RuntimeException(
                "Konfigurationsdatei nicht gefunden.\n"
                . "Erwartet wird config/reporter-defaults.php oder ein eigenes config/healthcheck-config.php."
            );
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException('Konfigurationsdatei muss ein Array zurückgeben.');
        }

        return $config;
    }

    /**
     * Bereinigt einen String für die Verwendung als Datei-/Verzeichnisname.
     */
    public static function sanitizeFilename(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/[^a-z0-9_\-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);

        return trim($name, '_');
    }

    /**
     * Ermittelt den Kunden-Slug (sanitized Kundenname) aus der Config.
     * Wird als Top-Level-Ordner unter daten/ und auswertung/ verwendet.
     */
    public static function customerSlug(array $config): string
    {
        return self::sanitizeFilename($config['kunde']['name'] ?? 'unbekannt');
    }

    /**
     * Erzeugt einen Timestamp im Format Y-m-d_His (für Dateinamen).
     */
    public static function timestamp(): string
    {
        return date('Y-m-d_His');
    }

    /**
     * Pfad zur Raw-JSON-Datei eines Collectors.
     * Format: {daten_pfad}/{kunde}/raw/{modul}/{ts}.json
     */
    public static function rawJsonPath(array $config, string $modul, ?string $ts = null): string
    {
        $base = self::dataBasePath($config);
        $slug = self::customerSlug($config);
        $ts = $ts ?? self::timestamp();

        return $base . '/' . $slug . '/raw/' . $modul . '/' . $ts . '.json';
    }

    /**
     * Pfad zu einer Report-Datei (HTML oder Findings-JSON).
     * Format: {auswertung_pfad}/{kunde}/{modul}_{ts}.{ext}
     */
    public static function reportPath(array $config, string $modul, ?string $ts = null, string $ext = 'html'): string
    {
        $base = self::reportBasePath($config);
        $slug = self::customerSlug($config);
        $ts = $ts ?? self::timestamp();

        return $base . '/' . $slug . '/' . $modul . '_' . $ts . '.' . $ext;
    }

    /**
     * Ermittelt die jüngste Raw-JSON-Datei für ein Modul des aktuellen Kunden.
     * Liefert null, wenn keine Daten vorhanden.
     */
    public static function latestRawJson(array $config, string $modul): ?string
    {
        $base = self::dataBasePath($config);
        $slug = self::customerSlug($config);
        $dir = $base . '/' . $slug . '/raw/' . $modul;

        if (!is_dir($dir)) {
            return null;
        }

        $files = glob($dir . '/*.json') ?: [];
        if (empty($files)) {
            return null;
        }

        sort($files);

        return end($files);
    }

    /**
     * Listet alle Raw-JSON-Dateien für ein Modul (sortiert, neueste zuerst).
     *
     * @return array<int, array{path: string, timestamp: string}>
     */
    public static function listRawJson(array $config, string $modul): array
    {
        $base = self::dataBasePath($config);
        $slug = self::customerSlug($config);
        $dir = $base . '/' . $slug . '/raw/' . $modul;

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.json') ?: [];
        rsort($files);

        $result = [];
        foreach ($files as $path) {
            $result[] = [
                'path'      => $path,
                'timestamp' => pathinfo($path, PATHINFO_FILENAME),
            ];
        }

        return $result;
    }

    /**
     * Lädt eine Raw-JSON-Datei und gibt das dekodierte Array zurück.
     */
    public static function loadRawJson(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Raw-JSON-Datei nicht gefunden: $path");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Raw-JSON-Datei konnte nicht gelesen werden: $path");
        }

        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException("Raw-JSON enthält kein Array: $path");
        }

        return $data;
    }

    /**
     * Speichert JSON-Daten in eine Datei (Verzeichnis wird angelegt).
     */
    public static function saveJson(string $filepath, array $data): void
    {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($filepath, $json);
    }

    /**
     * Speichert eine Text-Datei (HTML, CSV, ...).
     */
    public static function saveFile(string $filepath, string $contents): void
    {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filepath, $contents);
    }

    /**
     * Konsolen-Logger mit Level-Prefix.
     */
    public static function log(string $message, string $level = 'info'): void
    {
        $prefixes = [
            'info'    => '[INFO]',
            'success' => '[OK]',
            'warning' => '[WARN]',
            'error'   => '[FEHLER]',
        ];

        $prefix = $prefixes[$level] ?? '[INFO]';
        $timestamp = date('H:i:s');

        echo "$timestamp $prefix $message\n";
    }

    /**
     * Parst die --config CLI-Option aus $argv.
     */
    public static function parseConfigOption(?array $argv = null): ?string
    {
        $argv = $argv ?? ($GLOBALS['argv'] ?? []);
        foreach ($argv as $arg) {
            if (strpos($arg, '--config=') === 0) {
                return substr($arg, 9);
            }
        }

        return null;
    }

    /**
     * Parst die --raw CLI-Option (Pfad zu einer Raw-JSON-Datei für Reporter).
     */
    public static function parseRawOption(?array $argv = null): ?string
    {
        $argv = $argv ?? ($GLOBALS['argv'] ?? []);
        foreach ($argv as $arg) {
            if (strpos($arg, '--raw=') === 0) {
                return substr($arg, 6);
            }
        }

        return null;
    }

    /**
     * Liefert das Basis-Datenverzeichnis aus der Config (kanonisiert).
     */
    private static function dataBasePath(array $config): string
    {
        return self::canonicalize($config['output']['daten_pfad'] ?? __DIR__ . '/../daten');
    }

    /**
     * Liefert das Basis-Auswertungsverzeichnis aus der Config (kanonisiert).
     */
    private static function reportBasePath(array $config): string
    {
        return self::canonicalize($config['output']['auswertung_pfad'] ?? __DIR__ . '/../auswertung');
    }

    /**
     * Kanonisiert einen Pfad: legt das Verzeichnis ggf. an und gibt den
     * realpath zurück (auflösen von ../). Macht Pfade in Logs lesbar, ohne
     * dass die Datei selbst existieren muss.
     */
    private static function canonicalize(string $path): string
    {
        $path = rtrim($path, '/');
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $real = realpath($path);
        return $real !== false ? $real : $path;
    }
}
