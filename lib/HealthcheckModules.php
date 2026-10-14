<?php
/**
 * ITOMIG Healthcheck - Modul-Registry
 *
 * Zentrale Auflistung aller 11 Module mit Tier, Config-Flag, Reporter-Datei
 * und Kategorie-Key. Die Collectoren leben in der iTop-Extension
 * itomig-healthcheck — diese Registry wird nur noch von der Reporter-Pipeline
 * konsumiert (import-zip + report-from-zip + reporters/report-all).
 */

declare(strict_types=1);

class HealthcheckModules
{
    /**
     * @return array<string, array{
     *     tier: string,
     *     flag: string,
     *     label: string,
     *     category: string,
     *     reporter_file: string,
     *     reporter_fn: string
     * }>
     */
    public static function all(): array
    {
        $root = dirname(__DIR__);
        return [
            'design' => [
                'tier'          => 'rest',
                'flag'          => 'design_analysis',
                'label'         => 'Design-Analyse',
                'category'      => 'design',
                'reporter_file' => $root . '/reporters/rest/design-reporter.php',
                'reporter_fn'   => 'reportDesign',
            ],
            'data' => [
                'tier'          => 'rest',
                'flag'          => 'data_analysis',
                'label'         => 'Daten-Analyse',
                'category'      => 'data',
                'reporter_file' => $root . '/reporters/rest/data-reporter.php',
                'reporter_fn'   => 'reportData',
            ],
            'synchro' => [
                'tier'          => 'rest',
                'flag'          => 'synchro_analysis',
                'label'         => 'Synchro-Analyse',
                'category'      => 'synchro',
                'reporter_file' => $root . '/reporters/rest/synchro-reporter.php',
                'reporter_fn'   => 'reportSynchro',
            ],
            'integration' => [
                'tier'          => 'rest',
                'flag'          => 'integration_analysis',
                'label'         => 'Integration & Benachrichtigung',
                'category'      => 'integration',
                'reporter_file' => $root . '/reporters/rest/integration-reporter.php',
                'reporter_fn'   => 'reportIntegration',
            ],
            'system' => [
                'tier'          => 'rest',
                'flag'          => 'system_analysis',
                'label'         => 'System-Analyse',
                'category'      => 'system',
                'reporter_file' => $root . '/reporters/rest/system-reporter.php',
                'reporter_fn'   => 'reportSystem',
            ],
            'privacy' => [
                'tier'          => 'rest',
                'flag'          => 'privacy_analysis',
                'label'         => 'Datenschutz-Analyse',
                'category'      => 'privacy',
                'reporter_file' => $root . '/reporters/rest/privacy-reporter.php',
                'reporter_fn'   => 'reportPrivacy',
            ],
            'background-tasks' => [
                'tier'          => 'rest',
                'flag'          => 'background_tasks_analysis',
                'label'         => 'Hintergrund-Jobs',
                'category'      => 'background-tasks',
                'reporter_file' => $root . '/reporters/rest/background-tasks-reporter.php',
                'reporter_fn'   => 'reportBackgroundTasks',
            ],
            'error-log' => [
                'tier'          => 'rest',
                'flag'          => 'error_log_analysis',
                'label'         => 'Error-Log',
                'category'      => 'error-log',
                'reporter_file' => $root . '/reporters/rest/error-log-reporter.php',
                'reporter_fn'   => 'reportErrorLog',
            ],
            'table-overview' => [
                'tier'          => 'db',
                'flag'          => 'table_overview',
                'label'         => 'DB: Tabellen-Übersicht',
                'category'      => 'tables',
                'reporter_file' => $root . '/reporters/db/table-overview-reporter.php',
                'reporter_fn'   => 'reportTableOverview',
            ],
            'column-fill' => [
                'tier'          => 'db',
                'flag'          => 'column_fill',
                'label'         => 'DB: Spalten-Befüllung',
                'category'      => 'columns',
                'reporter_file' => $root . '/reporters/db/column-fill-reporter.php',
                'reporter_fn'   => 'reportColumnFill',
            ],
            'object-freshness' => [
                'tier'          => 'db',
                'flag'          => 'object_freshness',
                'label'         => 'DB: Objekt-Aktualität',
                'category'      => 'freshness',
                'reporter_file' => $root . '/reporters/db/object-freshness-reporter.php',
                'reporter_fn'   => 'reportObjectFreshness',
            ],
        ];
    }

    /**
     * Liefert die für einen Modul-Slug konfigurierte Definition oder null.
     */
    public static function get(string $modul): ?array
    {
        $all = self::all();
        return $all[$modul] ?? null;
    }

    /**
     * Module, die laut Config aktiv sind (Default: true, falls Flag fehlt).
     *
     * @return string[] Liste der Modul-Slugs in stabiler Reihenfolge
     */
    public static function active(array $config): array
    {
        $module = $config['module'] ?? [];
        $aktive = [];
        foreach (self::all() as $slug => $def) {
            if ($module[$def['flag']] ?? true) {
                $aktive[] = $slug;
            }
        }
        return $aktive;
    }

    /**
     * Findings + Summary eines Modul-Reports in den Gesamt-Report übertragen.
     */
    public static function mergeReport(HealthcheckReport $gesamt, HealthcheckReport $modul, string $category): void
    {
        $data = json_decode($modul->toJson(), true);
        if (!isset($data['results'][$category])) {
            return;
        }
        $catData = $data['results'][$category];

        if (!empty($catData['summary'])) {
            $gesamt->setCategorySummary(
                $category,
                $catData['summary']['text'] ?? '',
                $catData['summary']['stats'] ?? []
            );
        }

        foreach ($catData['findings'] ?? [] as $finding) {
            $gesamt->addFinding(
                $category,
                $finding['title'],
                $finding['severity'],
                $finding['description'],
                $finding['details'] ?? []
            );
        }
    }
}
