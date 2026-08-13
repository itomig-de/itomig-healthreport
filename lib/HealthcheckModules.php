<?php
/**
 * ITOMIG Healthcheck - Modul-Registry
 *
 * Zentrale Auflistung aller 9 Module mit Tier, Config-Flag, Reporter-Datei
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
                'flag'          => 'design_analyse',
                'label'         => 'Design-Analyse',
                'category'      => 'design',
                'reporter_file' => $root . '/reporters/rest/design-reporter.php',
                'reporter_fn'   => 'reportDesign',
            ],
            'daten' => [
                'tier'          => 'rest',
                'flag'          => 'daten_analyse',
                'label'         => 'Daten-Analyse',
                'category'      => 'daten',
                'reporter_file' => $root . '/reporters/rest/daten-reporter.php',
                'reporter_fn'   => 'reportDaten',
            ],
            'synchro' => [
                'tier'          => 'rest',
                'flag'          => 'synchro_analyse',
                'label'         => 'Synchro-Analyse',
                'category'      => 'synchro',
                'reporter_file' => $root . '/reporters/rest/synchro-reporter.php',
                'reporter_fn'   => 'reportSynchro',
            ],
            'integration' => [
                'tier'          => 'rest',
                'flag'          => 'integration_analyse',
                'label'         => 'Integration & Benachrichtigung',
                'category'      => 'integration',
                'reporter_file' => $root . '/reporters/rest/integration-reporter.php',
                'reporter_fn'   => 'reportIntegration',
            ],
            'system' => [
                'tier'          => 'rest',
                'flag'          => 'system_analyse',
                'label'         => 'System-Analyse',
                'category'      => 'system',
                'reporter_file' => $root . '/reporters/rest/system-reporter.php',
                'reporter_fn'   => 'reportSystem',
            ],
            'datenschutz' => [
                'tier'          => 'rest',
                'flag'          => 'datenschutz_analyse',
                'label'         => 'Datenschutz-Analyse',
                'category'      => 'datenschutz',
                'reporter_file' => $root . '/reporters/rest/datenschutz-reporter.php',
                'reporter_fn'   => 'reportDatenschutz',
            ],
            'tabellen-uebersicht' => [
                'tier'          => 'db',
                'flag'          => 'tabellen_uebersicht',
                'label'         => 'DB: Tabellen-Übersicht',
                'category'      => 'tabellen',
                'reporter_file' => $root . '/reporters/db/tabellen-uebersicht-reporter.php',
                'reporter_fn'   => 'reportTabellenUebersicht',
            ],
            'spalten-befuellung' => [
                'tier'          => 'db',
                'flag'          => 'spalten_befuellung',
                'label'         => 'DB: Spalten-Befüllung',
                'category'      => 'befuellung',
                'reporter_file' => $root . '/reporters/db/spalten-befuellung-reporter.php',
                'reporter_fn'   => 'reportSpaltenBefuellung',
            ],
            'objekt-aktualitaet' => [
                'tier'          => 'db',
                'flag'          => 'objekt_aktualitaet',
                'label'         => 'DB: Objekt-Aktualität',
                'category'      => 'aktualitaet',
                'reporter_file' => $root . '/reporters/db/objekt-aktualitaet-reporter.php',
                'reporter_fn'   => 'reportObjektAktualitaet',
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
        if (!isset($data['ergebnis'][$category])) {
            return;
        }
        $catData = $data['ergebnis'][$category];

        if (!empty($catData['zusammenfassung'])) {
            $gesamt->setCategorySummary(
                $category,
                $catData['zusammenfassung']['text'] ?? '',
                $catData['zusammenfassung']['stats'] ?? []
            );
        }

        foreach ($catData['befunde'] ?? [] as $finding) {
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
