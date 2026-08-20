<?php
/**
 * ITOMIG Healthcheck - Report-Generator
 *
 * Sammelt Befunde (Findings) und generiert JSON- und HTML-Reports
 * im ITOMIG Corporate Design mit Ampel-System.
 *
 * CATEGORY_LABELS umfasst alle 9 Module (6 REST + 3 DB) für den Gesamt-Report.
 */

declare(strict_types=1);

class HealthcheckReport
{
    private string $kundenname;
    private string $umgebung;
    private string $timestamp;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $findings = [];

    /** @var array<string, array{text: string, stats: array<string, mixed>}> */
    private array $summaries = [];

    private const SEVERITY_ORDER = ['critical', 'warning', 'info', 'ok'];

    private const SEVERITY_LABELS = [
        'critical' => 'Kritisch',
        'warning'  => 'Warnung',
        'info'     => 'Information',
        'ok'       => 'In Ordnung',
    ];

    private const SEVERITY_COLORS = [
        'critical' => '#dc3545',
        'warning'  => '#e3791d',
        'info'     => '#2794ab',
        'ok'       => '#28a745',
    ];

    public const CATEGORY_LABELS = [
        // REST API Tier
        'design'      => 'Qualität des Datenmodells',
        'data'        => 'Datenqualität',
        'synchro'     => 'Konsistenz der Datenquellen',
        'integration' => 'Integration & Benachrichtigung',
        'system'      => 'Server- & Systemzustand',
        'privacy'     => 'Datenschutz',
        // DB Tier
        'tables'      => 'DB: Tabellen-Übersicht',
        'columns'     => 'DB: Spalten-Befüllung',
        'freshness'   => 'DB: Objekt-Aktualität',
    ];

    public function __construct(string $kundenname, string $umgebung = '')
    {
        $this->kundenname = $kundenname;
        $this->umgebung = $umgebung;
        $this->timestamp = date('Y-m-d H:i:s');
    }

    /**
     * Fügt einen Befund hinzu.
     *
     * @param string $category    Kategorie-Schlüssel aus CATEGORY_LABELS
     * @param string $title       Kurztitel
     * @param string $severity    critical|warning|info|ok
     * @param string $description Beschreibung
     * @param array  $details     Optionale Detail-Daten (Liste, Tabelle, Key-Value)
     */
    public function addFinding(
        string $category,
        string $title,
        string $severity,
        string $description,
        array $details = []
    ): void {
        if (!isset($this->findings[$category])) {
            $this->findings[$category] = [];
        }

        $this->findings[$category][] = [
            'title'       => $title,
            'severity'    => $severity,
            'description' => $description,
            'details'     => $details,
        ];
    }

    /**
     * Setzt eine Zusammenfassung für eine Kategorie.
     */
    public function setCategorySummary(string $category, string $summary, array $stats = []): void
    {
        $this->summaries[$category] = [
            'text'  => $summary,
            'stats' => $stats,
        ];
    }

    /**
     * Ermittelt den Gesamt-Schweregrad einer Kategorie (höchster Befund).
     */
    public function getCategorySeverity(string $category): string
    {
        if (empty($this->findings[$category])) {
            return 'ok';
        }

        $highest = 'ok';
        foreach ($this->findings[$category] as $finding) {
            $currentIndex = array_search($finding['severity'], self::SEVERITY_ORDER, true);
            $highestIndex = array_search($highest, self::SEVERITY_ORDER, true);
            if ($currentIndex !== false && $highestIndex !== false && $currentIndex < $highestIndex) {
                $highest = $finding['severity'];
            }
        }

        return $highest;
    }

    /**
     * Exportiert den Report als JSON.
     */
    public function toJson(): string
    {
        $data = [
            'meta' => [
                'customer'    => $this->kundenname,
                'environment' => $this->umgebung,
                'timestamp'   => $this->timestamp,
                'version'     => '1.0.0',
            ],
            'results' => [],
        ];

        foreach (self::CATEGORY_LABELS as $key => $label) {
            if (!isset($this->findings[$key]) && !isset($this->summaries[$key])) {
                continue;
            }

            $data['results'][$key] = [
                'label'      => $label,
                'status'     => $this->getCategorySeverity($key),
                'summary'    => $this->summaries[$key] ?? null,
                'findings'   => $this->findings[$key] ?? [],
                'statistics' => [
                    'critical' => $this->countBySeverity($key, 'critical'),
                    'warning'  => $this->countBySeverity($key, 'warning'),
                    'info'     => $this->countBySeverity($key, 'info'),
                    'ok'       => $this->countBySeverity($key, 'ok'),
                ],
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Generiert den vollständigen Detail-Report (alle Findings einer/mehrerer Kategorien).
     *
     * @param string|null $backToSummary Optionaler relativer Link zurück zum Management-Summary.
     */
    public function toHtml(?string $backToSummary = null): string
    {
        $html = $this->getHtmlHeader($backToSummary);
        $html .= $this->getHtmlSummarySection();

        foreach (self::CATEGORY_LABELS as $key => $label) {
            if (!isset($this->findings[$key]) && !isset($this->summaries[$key])) {
                continue;
            }
            $html .= $this->getHtmlCategorySection($key, $label);
        }

        $html .= $this->getHtmlFooter();

        return $html;
    }

    /**
     * Generiert ein kompaktes Management-Summary mit Ampel-Übersicht, Mini-Sektionen pro
     * Kategorie und einer "Wichtigste Befunde"-Sektion (critical + warning). Detail-Links
     * machen die Ampel-Karten klickbar und ergänzen jeden Mini-Block um einen "Details"-Button.
     *
     * @param array<string, string> $detailLinks Mapping Kategorie-Key → relativer Pfad zum Detail-Report
     */
    public function toHtmlManagementSummary(array $detailLinks = []): string
    {
        $html = $this->getHtmlHeader(null, true);
        $html .= $this->getHtmlSummarySection($detailLinks);
        $html .= $this->getHtmlMiniSections($detailLinks);
        $html .= $this->getHtmlTopFindings($detailLinks);
        $html .= $this->getHtmlFooter();

        return $html;
    }

    private function countBySeverity(string $category, string $severity): int
    {
        if (empty($this->findings[$category])) {
            return 0;
        }

        return count(array_filter(
            $this->findings[$category],
            fn(array $f) => $f['severity'] === $severity
        ));
    }

    private function getHtmlHeader(?string $backToSummary = null, bool $isSummaryPage = false): string
    {
        $kunde = htmlspecialchars($this->kundenname);
        $umgebung = htmlspecialchars($this->umgebung);
        $datum = htmlspecialchars($this->timestamp);
        $titleSuffix = $isSummaryPage ? ' (Übersicht)' : '';
        $backLinkHtml = '';
        if ($backToSummary !== null && $backToSummary !== '') {
            $href = htmlspecialchars($backToSummary);
            $backLinkHtml = '<div class="back-link"><a href="' . $href . '">&larr; Zurück zum Gesamtüberblick</a></div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITOMIG Healthcheck$titleSuffix – $kunde</title>
    <style>
        :root {
            --itomig-deep-cyan: #074552;
            --itomig-strong-cyan: #2794ab;
            --itomig-vivid-cyan: #20d6fc;
            --itomig-dark-gray: #454545;
            --itomig-orange: #e3791d;
            --color-critical: #dc3545;
            --color-warning: #e3791d;
            --color-info: #2794ab;
            --color-ok: #28a745;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--itomig-dark-gray);
            line-height: 1.6;
            background: #f8fafb;
        }
        .header {
            background: linear-gradient(135deg, var(--itomig-deep-cyan), var(--itomig-strong-cyan));
            color: #fff;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .header h1 { font-size: 2rem; margin-bottom: 0.3rem; }
        .header .meta { font-size: 0.95rem; opacity: 0.85; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .ampel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .ampel-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(7,69,82,0.08);
            border-left: 5px solid #ccc;
            text-align: center;
        }
        .ampel-card.critical { border-left-color: var(--color-critical); }
        .ampel-card.warning { border-left-color: var(--color-warning); }
        .ampel-card.info { border-left-color: var(--color-info); }
        .ampel-card.ok { border-left-color: var(--color-ok); }
        .ampel-dot {
            display: inline-block;
            width: 24px; height: 24px;
            border-radius: 50%;
            margin-bottom: 0.5rem;
        }
        .ampel-dot.critical { background: var(--color-critical); }
        .ampel-dot.warning { background: var(--color-warning); }
        .ampel-dot.info { background: var(--color-info); }
        .ampel-dot.ok { background: var(--color-ok); }
        .ampel-card h3 { color: var(--itomig-deep-cyan); font-size: 1rem; margin: 0.5rem 0; }
        .ampel-stats { font-size: 0.85rem; color: #777; }
        .category { margin: 2.5rem 0; }
        .category h2 {
            color: var(--itomig-deep-cyan);
            font-size: 1.4rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--itomig-strong-cyan);
        }
        .category-summary {
            background: #fff;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .finding {
            background: #fff;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #ccc;
        }
        .finding.critical { border-left-color: var(--color-critical); }
        .finding.warning { border-left-color: var(--color-warning); }
        .finding.info { border-left-color: var(--color-info); }
        .finding.ok { border-left-color: var(--color-ok); }
        .finding-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.4rem;
        }
        .severity-badge {
            font-size: 0.75rem;
            padding: 0.15rem 0.6rem;
            border-radius: 4px;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
        }
        .severity-badge.critical { background: var(--color-critical); }
        .severity-badge.warning { background: var(--color-warning); }
        .severity-badge.info { background: var(--color-info); }
        .severity-badge.ok { background: var(--color-ok); }
        .finding h4 { color: var(--itomig-deep-cyan); font-size: 1rem; }
        .finding p { font-size: 0.9rem; color: #555; margin-top: 0.3rem; }
        .finding-details {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: #f5f7f8;
            border-radius: 6px;
            font-size: 0.85rem;
            overflow-x: auto;
        }
        .finding-details table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .finding-details th, .finding-details td {
            padding: 0.4rem 0.6rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .finding-details th {
            background: var(--itomig-deep-cyan);
            color: #fff;
            font-weight: 600;
        }
        .footer {
            text-align: center;
            padding: 2rem;
            font-size: 0.8rem;
            color: #999;
            border-top: 1px solid #e0e0e0;
            margin-top: 3rem;
        }
        .footer a { color: var(--itomig-strong-cyan); text-decoration: none; }

        /* Rück-Link in Detail-Reports */
        .back-link {
            margin: 0 0 1.5rem 0;
            font-size: 0.9rem;
        }
        .back-link a {
            color: var(--itomig-strong-cyan);
            text-decoration: none;
            border-bottom: 1px dotted var(--itomig-strong-cyan);
        }
        .back-link a:hover { color: var(--itomig-deep-cyan); border-bottom-style: solid; }

        /* Klickbare Ampel-Karten im Summary */
        a.ampel-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        a.ampel-card-link:hover .ampel-card {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(7,69,82,0.16);
        }

        /* Mini-Sektionen im Management-Summary */
        .mini-sections { margin: 2.5rem 0 1rem 0; }
        .mini-sections > h2 {
            color: var(--itomig-deep-cyan);
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--itomig-strong-cyan);
        }
        .mini-section {
            background: #fff;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #ccc;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1rem;
            align-items: center;
        }
        .mini-section.critical { border-left-color: var(--color-critical); }
        .mini-section.warning { border-left-color: var(--color-warning); }
        .mini-section.info { border-left-color: var(--color-info); }
        .mini-section.ok { border-left-color: var(--color-ok); }
        .mini-section h3 {
            color: var(--itomig-deep-cyan);
            font-size: 1rem;
            margin-bottom: 0.2rem;
        }
        .mini-section .mini-summary {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 0.3rem;
        }
        .mini-section .mini-counts {
            font-size: 0.8rem;
            color: #777;
        }
        .mini-section .mini-counts strong.critical { color: var(--color-critical); }
        .mini-section .mini-counts strong.warning { color: var(--color-warning); }
        .detail-button {
            background: var(--itomig-strong-cyan);
            color: #fff !important;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .detail-button:hover { background: var(--itomig-deep-cyan); }

        /* Wichtigste-Befunde-Sektion */
        .top-findings-section { margin: 2.5rem 0; }
        .top-findings-section > h2 {
            color: var(--itomig-deep-cyan);
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--itomig-strong-cyan);
        }
        .category-group-label {
            font-size: 0.85rem;
            color: var(--itomig-deep-cyan);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 1rem 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .category-group-label a {
            font-weight: 400;
            font-size: 0.75rem;
            color: var(--itomig-strong-cyan);
            text-decoration: none;
            margin-left: auto;
        }
        .category-group-label a:hover { text-decoration: underline; }
        .top-findings-empty {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            color: #555;
            border-left: 4px solid var(--color-ok);
        }

        @media print {
            body { background: #fff; }
            .container { max-width: 100%; }
            .finding, .ampel-card, .category-summary, .mini-section { break-inside: avoid; }
            .back-link, .detail-button { display: none; }
            a.ampel-card-link:hover .ampel-card { transform: none; box-shadow: 0 2px 8px rgba(7,69,82,0.08); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ITOMIG Healthcheck$titleSuffix</h1>
        <p class="meta">$kunde – $umgebung | Analyse vom $datum</p>
    </div>
    <div class="container">
        $backLinkHtml
HTML;
    }

    /**
     * @param array<string, string> $detailLinks Mapping Kategorie → relativer Detail-Report-Pfad.
     *                                            Wenn gesetzt, werden die Ampel-Karten klickbar.
     */
    private function getHtmlSummarySection(array $detailLinks = []): string
    {
        $html = '<h2 style="color: var(--itomig-deep-cyan); margin-bottom: 1rem;">Gesamtübersicht</h2>';
        $html .= '<div class="ampel-grid">';

        foreach (self::CATEGORY_LABELS as $key => $label) {
            if (!isset($this->findings[$key]) && !isset($this->summaries[$key])) {
                continue;
            }

            $severity = $this->getCategorySeverity($key);
            $severityLabel = self::SEVERITY_LABELS[$severity];
            $critical = $this->countBySeverity($key, 'critical');
            $warning = $this->countBySeverity($key, 'warning');
            $total = count($this->findings[$key] ?? []);
            $labelEsc = htmlspecialchars($label);

            $cardHtml = <<<HTML
            <div class="ampel-card $severity">
                <div class="ampel-dot $severity"></div>
                <h3>$labelEsc</h3>
                <p><strong>$severityLabel</strong></p>
                <p class="ampel-stats">$total Befunde ($critical kritisch, $warning Warnungen)</p>
            </div>
HTML;

            if (isset($detailLinks[$key])) {
                $href = htmlspecialchars($detailLinks[$key]);
                $html .= '<a class="ampel-card-link" href="' . $href . '">' . $cardHtml . '</a>';
            } else {
                $html .= $cardHtml;
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Mini-Sektion pro Kategorie: kompakte Beschreibung + Counts + Detail-Button.
     *
     * @param array<string, string> $detailLinks
     */
    private function getHtmlMiniSections(array $detailLinks = []): string
    {
        $hasContent = false;
        $inner = '';

        foreach (self::CATEGORY_LABELS as $key => $label) {
            if (!isset($this->findings[$key]) && !isset($this->summaries[$key])) {
                continue;
            }
            $hasContent = true;

            $severity = $this->getCategorySeverity($key);
            $severityLabel = self::SEVERITY_LABELS[$severity];
            $critical = $this->countBySeverity($key, 'critical');
            $warning = $this->countBySeverity($key, 'warning');
            $info = $this->countBySeverity($key, 'info');
            $ok = $this->countBySeverity($key, 'ok');

            $summaryText = $this->summaries[$key]['text'] ?? '';
            $summaryEsc = htmlspecialchars($summaryText);
            $labelEsc = htmlspecialchars($label);

            $detailButton = '';
            if (isset($detailLinks[$key])) {
                $href = htmlspecialchars($detailLinks[$key]);
                $detailButton = '<a class="detail-button" href="' . $href . '">Details &rarr;</a>';
            }

            $countsHtml = sprintf(
                '<strong class="critical">%d kritisch</strong> &middot; <strong class="warning">%d Warnungen</strong> &middot; %d Hinweise &middot; %d OK',
                $critical,
                $warning,
                $info,
                $ok
            );

            $inner .= <<<HTML
        <div class="mini-section $severity">
            <span class="severity-badge $severity">$severityLabel</span>
            <div>
                <h3>$labelEsc</h3>
                <p class="mini-summary">$summaryEsc</p>
                <p class="mini-counts">$countsHtml</p>
            </div>
            $detailButton
        </div>
HTML;
        }

        if (!$hasContent) {
            return '';
        }

        return '<div class="mini-sections"><h2>Bereiche im Überblick</h2>' . $inner . '</div>';
    }

    /**
     * "Wichtigste Befunde": alle critical + warning Findings, gruppiert nach Kategorie
     * (Reihenfolge: zuerst Kategorien mit critical, dann mit warning).
     *
     * @param array<string, string> $detailLinks
     */
    private function getHtmlTopFindings(array $detailLinks = []): string
    {
        $top = []; // [category => ['critical' => [], 'warning' => []]]

        foreach ($this->findings as $cat => $list) {
            foreach ($list as $f) {
                $sev = $f['severity'] ?? 'info';
                if ($sev === 'critical' || $sev === 'warning') {
                    $top[$cat][$sev][] = $f;
                }
            }
        }

        $html = '<div class="top-findings-section"><h2>Wichtigste Befunde</h2>';

        if (empty($top)) {
            $html .= '<div class="top-findings-empty">Keine kritischen oder warnenden Befunde &mdash; alle Bereiche unauffällig.</div>';
            $html .= '</div>';
            return $html;
        }

        // Kategorien sortieren: erst die mit critical, dann nur warning, in CATEGORY_LABELS-Reihenfolge
        $sortedCats = [];
        foreach (self::CATEGORY_LABELS as $key => $label) {
            if (isset($top[$key]['critical'])) {
                $sortedCats[$key] = $label;
            }
        }
        foreach (self::CATEGORY_LABELS as $key => $label) {
            if (isset($top[$key]) && !isset($sortedCats[$key])) {
                $sortedCats[$key] = $label;
            }
        }

        foreach ($sortedCats as $cat => $label) {
            $labelEsc = htmlspecialchars($label);
            $detailLink = '';
            if (isset($detailLinks[$cat])) {
                $href = htmlspecialchars($detailLinks[$cat]);
                $detailLink = '<a href="' . $href . '">Detail-Report &rarr;</a>';
            }
            $html .= '<div class="category-group-label">' . $labelEsc . $detailLink . '</div>';

            // critical zuerst, dann warning
            foreach (['critical', 'warning'] as $sev) {
                foreach ($top[$cat][$sev] ?? [] as $finding) {
                    $html .= $this->getHtmlFinding($finding);
                }
            }
        }

        $html .= '</div>';

        return $html;
    }

    private function getHtmlCategorySection(string $key, string $label): string
    {
        $label = htmlspecialchars($label);
        $html = "<div class=\"category\"><h2>$label</h2>";

        if (isset($this->summaries[$key])) {
            $text = htmlspecialchars($this->summaries[$key]['text']);
            $html .= "<div class=\"category-summary\">$text</div>";
        }

        $findings = $this->findings[$key] ?? [];

        usort($findings, function (array $a, array $b): int {
            $ai = array_search($a['severity'], self::SEVERITY_ORDER, true);
            $bi = array_search($b['severity'], self::SEVERITY_ORDER, true);
            return $ai - $bi;
        });

        foreach ($findings as $finding) {
            $html .= $this->getHtmlFinding($finding);
        }

        $html .= '</div>';

        return $html;
    }

    private function getHtmlFinding(array $finding): string
    {
        $severity = $finding['severity'];
        $severityLabel = htmlspecialchars(self::SEVERITY_LABELS[$severity] ?? $severity);
        $title = htmlspecialchars($finding['title']);
        $description = htmlspecialchars($finding['description']);

        $html = <<<HTML
        <div class="finding $severity">
            <div class="finding-header">
                <span class="severity-badge $severity">$severityLabel</span>
                <h4>$title</h4>
            </div>
            <p>$description</p>
HTML;

        if (!empty($finding['details'])) {
            $html .= $this->renderDetails($finding['details']);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderDetails(array $details): string
    {
        $html = '<div class="finding-details">';

        if (isset($details[0]) && is_string($details[0])) {
            $html .= '<ul>';
            foreach ($details as $item) {
                $html .= '<li>' . htmlspecialchars((string) $item) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
            return $html;
        }

        if (isset($details[0]) && is_array($details[0])) {
            $headers = array_keys($details[0]);
            $html .= '<table><thead><tr>';
            foreach ($headers as $h) {
                $html .= '<th>' . htmlspecialchars((string) $h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($details as $row) {
                $html .= '<tr>';
                foreach ($headers as $h) {
                    $html .= '<td>' . htmlspecialchars((string) ($row[$h] ?? '')) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<table>';
            foreach ($details as $k => $v) {
                $k = htmlspecialchars((string) $k);
                $v = htmlspecialchars((string) (is_array($v) ? json_encode($v) : $v));
                $html .= "<tr><td><strong>$k</strong></td><td>$v</td></tr>";
            }
            $html .= '</table>';
        }

        $html .= '</div>';

        return $html;
    }

    private function getHtmlFooter(): string
    {
        $year = date('Y');

        return <<<HTML
    </div>
    <div class="footer">
        &copy; $year ITOMIG GmbH &middot; <a href="https://www.itomig.de">www.itomig.de</a><br>
        Dieser Report wurde automatisch generiert durch den ITOMIG Healthcheck.
    </div>
</body>
</html>
HTML;
    }
}
