<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Service;

use Dict;
use RuntimeException;

/**
 * Rendert HealthcheckRun.bericht_json zur Anzeigezeit als HTML (Issue #2).
 *
 * Nutzt unveraendert die HTML-Ausgabe der Engine (HealthcheckReport::
 * toHtmlManagementSummary() / toHtml()), nachdem der Report per
 * HealthcheckReport::fromJson() aus dem gespeicherten JSON rekonstruiert wurde.
 *
 * Bewusst unabhaengig vom Aufrufkontext: Die Zugriffspruefung und das
 * URL-Schema (Konsolen-Route vs. kuenftige Portal-Route) liefert der Aufrufer,
 * die Links werden ueber einen Link-Builder erzeugt.
 */
class ReportRenderer
{
    /**
     * HTTP-Header fuer die Auslieferung eines gerenderten Berichts. Der Bericht
     * enthaelt kein JavaScript und darf nur auf demselben Host eingebettet werden
     * (iframe im Reiter "Bericht" bzw. kuenftig im Portal).
     */
    public const SECURITY_HEADERS = [
        'Content-Type'            => 'text/html; charset=utf-8',
        'X-Frame-Options'         => 'SAMEORIGIN',
        'X-Content-Type-Options'  => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'",
    ];

    public function __construct()
    {
        require_once dirname(__DIR__, 2) . '/lib/HealthcheckReport.php';
    }

    /**
     * @param string                        $berichtJson Inhalt von HealthcheckRun.bericht_json
     * @param string|null                   $category    Kategorie-Key fuer die Detailansicht, null = Management-Summary
     * @param callable(?string): string     $linkBuilder Liefert die URL zur Summary (null) bzw. zu einer Kategorie
     * @throws RuntimeException Wenn kein Bericht vorliegt, das JSON ungueltig oder die Kategorie unbekannt ist.
     */
    public function render(string $berichtJson, ?string $category, callable $linkBuilder): string
    {
        if (trim($berichtJson) === '') {
            throw new RuntimeException(Dict::S('Itomig:HealthReport:Error:NoReport'));
        }

        $report = \HealthcheckReport::fromJson($berichtJson);

        if ($category === null || $category === '') {
            $detailLinks = [];
            foreach (array_keys(\HealthcheckReport::CATEGORY_LABELS) as $key) {
                $detailLinks[$key] = $linkBuilder($key);
            }

            return $report->toHtmlManagementSummary($detailLinks);
        }

        if (!isset(\HealthcheckReport::CATEGORY_LABELS[$category])) {
            throw new RuntimeException(Dict::S('Itomig:HealthReport:Error:UnknownCategory'));
        }

        return $report->onlyCategory($category)->toHtml($linkBuilder(null));
    }
}
