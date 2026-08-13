<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Service;

use RuntimeException;
use Throwable;

/**
 * Adapter zwischen der iTop-Extension und der bestehenden, iTop-unabhaengigen
 * Reporter-Pipeline dieses Repos (lib/, reporters/, tools/import-zip.php).
 *
 * Die Engine besteht aus globalen Klassen/Funktionen (HealthcheckReport,
 * HealthcheckUtils, HealthcheckModules, importZip(), runReporter()) und wird
 * hier unveraendert per require_once eingebunden. Sie ist bewusst nicht
 * namespaced, daher kapselt dieser Adapter die Aufrufe und schluckt das
 * unbedingte HealthcheckUtils::log()-Echo per Output-Buffering.
 *
 * Ablauf entspricht 1:1 dem frueheren web/process.php (siehe Zeilen 60-101
 * der alten Web-UI, vor deren Entfernung im Zuge der iTop-Integration).
 */
class ReportPipeline
{
    /** @var string Absoluter Pfad zum Repo-Root (Extension-Modul-Wurzel). */
    private string $engineRoot;

    public function __construct()
    {
        $this->engineRoot = dirname(__DIR__, 2);
        $this->requireEngine();
    }

    /**
     * Importiert ein Healthcheck-Collector-ZIP und wertet alle enthaltenen
     * Module aus.
     *
     * @return array{
     *     kunde: string,
     *     umgebung: string,
     *     zeitpunkt: string,
     *     itop_version: string|null,
     *     db_server_version: string|null,
     *     extension_version: string|null,
     *     ampel_gesamt: string,
     *     summary_html: string,
     *     summary_json: string,
     *     module: array<int, array{
     *         kategorie: string,
     *         label: string,
     *         ampel: string,
     *         befund_anzahl: int,
     *         html: string,
     *         json: string
     *     }>
     * }
     * @throws RuntimeException Wenn das ZIP ungueltig ist oder kein Modul ausgewertet werden konnte.
     */
    public function run(string $zipPath): array
    {
        $config = $this->loadConfig();

        $imported = $this->withSuppressedLog(
            fn() => \importZip($zipPath, $config)
        );

        $config['kunde'] = [
            'name'     => $imported['kunde'],
            'umgebung' => $imported['umgebung'],
        ];

        $gesamt = new \HealthcheckReport($imported['kunde'], $imported['umgebung']);
        $reportTs = \HealthcheckUtils::timestamp();

        $module = [];
        $erfolg = 0;
        foreach (array_keys($imported['imported']) as $modul) {
            $def = \HealthcheckModules::get($modul);
            if ($def === null) {
                continue;
            }
            try {
                // runReporter() schreibt als Nebeneffekt HTML+JSON unter
                // APPROOT/data/itomig-healthreport/auswertung/ (Engine-Verhalten,
                // siehe reporters/report-all.php). Die eigentliche Persistenz
                // erfolgt ueber RunPersister als iTop-Blob; die Dateien dienen
                // nur als Zwischenablage der Engine und werden hier ignoriert.
                $r = $this->withSuppressedLog(
                    fn() => \runReporter($modul, $config, $imported['timestamp'], $reportTs, null)
                );
                \HealthcheckModules::mergeReport($gesamt, $r['report'], $def['category']);

                $ampel = $r['report']->getCategorySeverity($def['category']);
                $module[] = [
                    'kategorie'     => $def['category'],
                    'label'         => \HealthcheckReport::CATEGORY_LABELS[$def['category']] ?? $def['label'],
                    'ampel'         => $this->toAmpel($ampel),
                    'befund_anzahl' => $this->countFindings($r['report'], $def['category']),
                    'html'          => $r['report']->toHtml(null),
                    'json'          => $r['report']->toJson(),
                ];
                $erfolg++;
            } catch (Throwable $e) {
                // Einzelner Reporter darf scheitern, der Rest laeuft trotzdem
                continue;
            }
        }

        if ($erfolg === 0) {
            throw new RuntimeException(\Dict::S('Itomig:HealthReport:Error:NoModule'));
        }

        $ampelGesamt = 'gruen';
        foreach ($module as $m) {
            $ampelGesamt = $this->highestAmpel($ampelGesamt, $m['ampel']);
        }

        return [
            'kunde'              => $imported['kunde'],
            'umgebung'           => $imported['umgebung'],
            'zeitpunkt'          => $imported['timestamp'],
            'itop_version'       => $imported['itop_version'],
            'db_server_version'  => $imported['db_server_version'],
            'extension_version'  => $imported['extension_version'],
            'ampel_gesamt'       => $ampelGesamt,
            'summary_html'       => $gesamt->toHtmlManagementSummary([]),
            'summary_json'       => $gesamt->toJson(),
            'module'             => $module,
        ];
    }

    /**
     * Laedt die Reporter-Config und biegt die Output-Pfade auf ein
     * iTop-schreibbares Arbeitsverzeichnis um (APPROOT/data/itomig-healthreport).
     */
    private function loadConfig(): array
    {
        $config = \HealthcheckUtils::loadConfig($this->engineRoot . '/config/reporter-defaults.php');

        $dataDir = APPROOT . 'data/itomig-healthreport';
        $config['output']['daten_pfad'] = $dataDir . '/daten';
        $config['output']['auswertung_pfad'] = $dataDir . '/auswertung';

        return $config;
    }

    private function requireEngine(): void
    {
        require_once $this->engineRoot . '/lib/HealthcheckReport.php';
        require_once $this->engineRoot . '/lib/HealthcheckUtils.php';
        require_once $this->engineRoot . '/lib/HealthcheckModules.php';
        require_once $this->engineRoot . '/tools/import-zip.php';
        require_once $this->engineRoot . '/reporters/report-all.php';
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withSuppressedLog(callable $callback)
    {
        ob_start();
        try {
            return $callback();
        } finally {
            ob_get_clean();
        }
    }

    private function countFindings(\HealthcheckReport $report, string $category): int
    {
        $data = json_decode($report->toJson(), true);
        $findings = $data['results'][$category]['findings'] ?? [];

        return count($findings);
    }

    private function toAmpel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'rot',
            'warning'  => 'gelb',
            default    => 'gruen',
        };
    }

    private function highestAmpel(string $a, string $b): string
    {
        $order = ['gruen' => 0, 'gelb' => 1, 'rot' => 2];

        return ($order[$b] ?? 0) > ($order[$a] ?? 0) ? $b : $a;
    }
}
