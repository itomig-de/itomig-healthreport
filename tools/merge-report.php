<?php
/**
 * ITOMIG Healthcheck - Kundenversion-Merger
 *
 * Führt alle HTML-Reports eines Kunden-Healthcheck-Laufs zu einer einzigen,
 * in sich geschlossenen HTML-Datei zusammen. Das Ergebnis hat:
 *   - ein Inhaltsverzeichnis (TOC) am Anfang
 *   - das Management-Summary als erste Sektion (#uebersicht)
 *   - alle 9 Modul-Detail-Reports als Folge-Sektionen (#<modul-slug>)
 *
 * Aufruf:
 *   php tools/merge-report.php --kunde=itopprod                              # neuester Timestamp
 *   php tools/merge-report.php --kunde=itopprod --timestamp=2026-05-26_101350
 *   php tools/merge-report.php --kunde=itopprod --output=/eigener/pfad.html
 *
 * Default-Output: auswertung/<kunde>/healthcheck_<ts>_kundenversion.html
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/HealthcheckUtils.php';
require_once __DIR__ . '/../lib/HealthcheckModules.php';

/**
 * Erzeugt aus den HTML-Reports eines Kundenlaufs eine einzige Kundenversion-HTML.
 *
 * @return array{
 *     kunde: string,
 *     timestamp: string,
 *     output: string,
 *     module: array<string,string>,
 *     size_bytes: int
 * }
 */
function mergeReport(string $kunde, ?string $timestamp, ?string $outputPath, array $config): array
{
    $config['kunde'] = ['name' => $kunde, 'umgebung' => $config['kunde']['umgebung'] ?? ''];

    $kundeDir = dirname(HealthcheckUtils::reportPath($config, 'design', '0000-00-00_000000'));
    if (!is_dir($kundeDir)) {
        throw new \RuntimeException("Kundenverzeichnis nicht gefunden: $kundeDir");
    }

    if ($timestamp === null) {
        $timestamp = findLatestTimestamp($kundeDir);
        if ($timestamp === null) {
            throw new \RuntimeException("Kein Management-Summary (healthcheck_*.html) in $kundeDir gefunden.");
        }
        HealthcheckUtils::log("Verwende neuesten Timestamp: $timestamp", 'info');
    }

    $summaryPath = $kundeDir . '/healthcheck_' . $timestamp . '.html';
    if (!is_file($summaryPath)) {
        throw new \RuntimeException("Management-Summary fehlt: $summaryPath");
    }

    $summaryHtml = file_get_contents($summaryPath);
    if ($summaryHtml === false) {
        throw new \RuntimeException("Summary konnte nicht gelesen werden: $summaryPath");
    }

    $modulSlugs = array_keys(HealthcheckModules::all());

    $headHtml = extractHead($summaryHtml, $kunde, $timestamp);
    $bannerHtml = extractBanner($summaryHtml);

    $summaryContent = extractContainerContent($summaryHtml);
    $summaryContent = stripBackLink($summaryContent);
    $summaryContent = rewriteSummaryLinks($summaryContent, $timestamp, $modulSlugs);

    $tocItems = [['anchor' => 'uebersicht', 'label' => 'Übersicht']];
    $sections = [];

    foreach ($modulSlugs as $slug) {
        $modulPath = $kundeDir . '/' . $slug . '_' . $timestamp . '.html';
        if (!is_file($modulPath)) {
            HealthcheckUtils::log("Modul-Report fehlt, überspringe: $modulPath", 'warning');
            continue;
        }
        $modulHtml = file_get_contents($modulPath);
        if ($modulHtml === false) {
            HealthcheckUtils::log("Modul-Report nicht lesbar: $modulPath", 'warning');
            continue;
        }
        $modulContent = extractContainerContent($modulHtml);
        $modulContent = stripBackLink($modulContent);

        $modulDef = HealthcheckModules::get($slug);
        $label = $modulDef['label'] ?? $slug;

        $tocItems[] = ['anchor' => $slug, 'label' => $label];
        $sections[$slug] = ['label' => $label, 'content' => $modulContent];
    }

    if ($outputPath === null) {
        $outputPath = $kundeDir . '/healthcheck_' . $timestamp . '_kundenversion.html';
    }

    $finalHtml = composeMergedHtml($headHtml, $bannerHtml, $tocItems, $summaryContent, $sections);
    HealthcheckUtils::saveFile($outputPath, $finalHtml);

    return [
        'kunde'      => $kunde,
        'timestamp'  => $timestamp,
        'output'     => $outputPath,
        'module'     => array_combine(
            array_keys($sections),
            array_column($sections, 'label')
        ),
        'size_bytes' => filesize($outputPath) ?: 0,
    ];
}

/**
 * Findet den neuesten Timestamp anhand der vorhandenen healthcheck_<ts>.html im Kundenordner.
 */
function findLatestTimestamp(string $kundeDir): ?string
{
    $files = glob($kundeDir . '/healthcheck_*.html');
    if (!$files) {
        return null;
    }
    $kundenversionSuffix = '_kundenversion.html';
    $timestamps = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (substr($name, -strlen($kundenversionSuffix)) === $kundenversionSuffix) {
            continue;
        }
        if (preg_match('/^healthcheck_(\d{4}-\d{2}-\d{2}_\d{6})\.html$/', $name, $m)) {
            $timestamps[] = $m[1];
        }
    }
    if (!$timestamps) {
        return null;
    }
    sort($timestamps);
    return end($timestamps);
}

/**
 * Extrahiert den <head>-Block aus einem Report, ersetzt den <title> durch den
 * Kundenversion-Titel und ergänzt das Zusatz-CSS für TOC + Section-Layout.
 */
function extractHead(string $html, string $kunde, string $timestamp): string
{
    if (!preg_match('/<head\b[^>]*>(.*?)<\/head>/s', $html, $m)) {
        throw new \RuntimeException('Konnte <head> im Summary nicht finden.');
    }
    $headInner = $m[1];

    $newTitle = 'ITOMIG Healthcheck — Kundenversion · ' . htmlspecialchars($kunde, ENT_QUOTES, 'UTF-8')
        . ' · ' . htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8');
    $headInner = preg_replace(
        '/<title>.*?<\/title>/s',
        '<title>' . $newTitle . '</title>',
        $headInner,
        1
    ) ?? $headInner;

    $extraCss = <<<CSS
    <style>
        /* Kundenversion-Layout: TOC + zusammengeführte Sektionen */
        .kundenversion-toc {
            max-width: 1000px;
            margin: 2rem auto;
            background: #fff;
            border-left: 5px solid var(--itomig-strong-cyan);
            border-radius: 10px;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 8px rgba(7,69,82,0.08);
        }
        .kundenversion-toc h2 {
            color: var(--itomig-deep-cyan);
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
            border-bottom: none;
        }
        .kundenversion-toc ol {
            list-style: decimal inside;
            padding-left: 0.25rem;
        }
        .kundenversion-toc li { margin: 0.35rem 0; }
        .kundenversion-toc a {
            color: var(--itomig-strong-cyan);
            text-decoration: none;
            border-bottom: 1px dashed transparent;
        }
        .kundenversion-toc a:hover {
            color: var(--itomig-deep-cyan);
            border-bottom-color: var(--itomig-strong-cyan);
        }
        section.kundenversion-section {
            scroll-margin-top: 1rem;
        }
        section.kundenversion-section + section.kundenversion-section {
            margin-top: 0;
            border-top: 3px solid var(--itomig-vivid-cyan);
        }
        .back-to-toc {
            max-width: 1000px;
            margin: 0 auto 1.5rem auto;
            padding: 0 2rem;
            text-align: right;
            font-size: 0.9rem;
        }
        .back-to-toc a {
            color: var(--itomig-strong-cyan);
            text-decoration: none;
            border-bottom: 1px dashed currentColor;
        }
        .back-to-toc a:hover { color: var(--itomig-deep-cyan); }
        @media print {
            .kundenversion-toc { page-break-after: always; }
            section.kundenversion-section { page-break-before: always; }
            .back-to-toc { display: none; }
        }
    </style>
CSS;

    return "<head>\n" . $headInner . "\n" . $extraCss . "\n</head>";
}

/**
 * Extrahiert den oberen Banner (<div class="header">…</div>) aus dem Summary.
 * Dieser kommt im Kundenversion-Layout genau einmal vor.
 */
function extractBanner(string $html): string
{
    $doc = loadHtmlDocument($html);
    $xpath = new \DOMXPath($doc);
    $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " header ")]');
    if ($nodes && $nodes->length > 0) {
        return $doc->saveHTML($nodes->item(0)) ?: '';
    }
    return '';
}

/**
 * Extrahiert das Inner-HTML des <div class="container"> aus einem Report.
 * Das enthält den eigentlichen Inhalt (ohne globalen Header-Banner und Footer).
 */
function extractContainerContent(string $html): string
{
    $doc = loadHtmlDocument($html);
    $xpath = new \DOMXPath($doc);
    $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " container ")]');
    if (!$nodes || $nodes->length === 0) {
        return '';
    }
    $container = $nodes->item(0);
    $inner = '';
    foreach ($container->childNodes as $child) {
        $inner .= $doc->saveHTML($child);
    }
    return $inner;
}

/**
 * Entfernt den <div class="back-link">…</div>-Block (Detail-Reports verlinken
 * standardmäßig zurück auf die Summary-Datei — in der Kundenversion sinnlos).
 */
function stripBackLink(string $html): string
{
    return preg_replace('/<div class="back-link">.*?<\/div>/s', '', $html) ?? $html;
}

/**
 * Schreibt relative Dateilinks der Summary auf Anker-Links um.
 *   href="design_<ts>.html"  →  href="#design"
 */
function rewriteSummaryLinks(string $html, string $timestamp, array $modulSlugs): string
{
    $search = [];
    $replace = [];
    foreach ($modulSlugs as $slug) {
        $search[] = 'href="' . $slug . '_' . $timestamp . '.html"';
        $replace[] = 'href="#' . $slug . '"';
    }
    return str_replace($search, $replace, $html);
}

/**
 * Lädt HTML robust in einen DOMDocument (UTF-8-sicher, ohne Warnings).
 */
function loadHtmlDocument(string $html): \DOMDocument
{
    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    // Meta-Charset-Hint erzwingt UTF-8-Parsing in libxml.
    $prefixed = '<?xml encoding="UTF-8">' . $html;
    $doc->loadHTML($prefixed, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $doc;
}

/**
 * Setzt das endgültige Kundenversion-HTML zusammen.
 *
 * @param array<int, array{anchor:string, label:string}> $tocItems
 * @param array<string, array{label:string, content:string}> $sections
 */
function composeMergedHtml(
    string $headHtml,
    string $bannerHtml,
    array $tocItems,
    string $uebersichtContent,
    array $sections
): string {
    $tocLis = '';
    foreach ($tocItems as $item) {
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        $tocLis .= '            <li><a href="#' . $item['anchor'] . '">' . $label . '</a></li>' . "\n";
    }

    $tocHtml = <<<HTML
    <nav class="kundenversion-toc" id="inhaltsverzeichnis" aria-label="Inhaltsverzeichnis">
        <h2>Inhaltsverzeichnis</h2>
        <ol>
$tocLis        </ol>
    </nav>
HTML;

    $backToToc = '<div class="back-to-toc"><a href="#inhaltsverzeichnis">&uarr; Zum Inhaltsverzeichnis</a></div>';

    $sectionsHtml = '';

    // Übersicht / Management-Summary als erste Sektion
    $sectionsHtml .= '<section id="uebersicht" class="kundenversion-section">' . "\n";
    $sectionsHtml .= '<div class="container">' . "\n";
    $sectionsHtml .= $uebersichtContent;
    $sectionsHtml .= '</div>' . "\n";
    $sectionsHtml .= $backToToc . "\n";
    $sectionsHtml .= '</section>' . "\n";

    foreach ($sections as $slug => $sec) {
        $sectionsHtml .= '<section id="' . $slug . '" class="kundenversion-section">' . "\n";
        $sectionsHtml .= '<div class="container">' . "\n";
        $sectionsHtml .= $sec['content'];
        $sectionsHtml .= '</div>' . "\n";
        $sectionsHtml .= $backToToc . "\n";
        $sectionsHtml .= '</section>' . "\n";
    }

    $year = date('Y');
    $footerHtml = <<<HTML
    <div class="footer">
        &copy; $year ITOMIG GmbH &middot; <a href="https://www.itomig.de">www.itomig.de</a><br>
        Dieser Report wurde automatisch generiert durch den ITOMIG Healthcheck (Kundenversion).
    </div>
HTML;

    // Anker-Scroll per JS: hilft, wenn die Datei in einem SPA-Viewer/iframe
    // gehostet wird, der Hash-Navigation abfängt und sonst eine "leere Seite"
    // zeigt. Wir verhindern die Default-Navigation und scrollen programmatisch.
    $anchorJs = <<<'JS'
    <script>
    (function () {
        function scrollToId(id) {
            if (!id) return false;
            var target = document.getElementById(id);
            if (!target) return false;
            target.scrollIntoView({ behavior: 'auto', block: 'start' });
            return true;
        }
        document.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (href.length <= 1) return;
            if (scrollToId(href.slice(1))) {
                e.preventDefault();
            }
        });
        function initialJump() {
            if (window.location.hash) {
                scrollToId(window.location.hash.slice(1));
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initialJump);
        } else {
            initialJump();
        }
    })();
    </script>
JS;

    return "<!DOCTYPE html>\n<html lang=\"de\">\n"
        . $headHtml . "\n"
        . "<body>\n"
        . $bannerHtml . "\n"
        . $tocHtml . "\n"
        . $sectionsHtml
        . $footerHtml . "\n"
        . $anchorJs . "\n"
        . "</body>\n</html>\n";
}

// --- CLI ---

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $config = HealthcheckUtils::loadConfig(HealthcheckUtils::parseConfigOption());

        $kunde = null;
        $timestamp = null;
        $outputPath = null;
        foreach ($argv as $arg) {
            if (strpos($arg, '--kunde=') === 0) {
                $kunde = substr($arg, 8);
            } elseif (strpos($arg, '--timestamp=') === 0) {
                $timestamp = substr($arg, 12);
            } elseif (strpos($arg, '--output=') === 0) {
                $outputPath = substr($arg, 9);
            }
        }

        if ($kunde === null || $kunde === '') {
            throw new \RuntimeException('Bitte --kunde=<slug> angeben (z.B. --kunde=itopprod).');
        }

        echo "\n=========================================\n";
        echo "  ITOMIG Healthcheck - Kundenversion\n";
        echo "=========================================\n\n";

        $result = mergeReport($kunde, $timestamp, $outputPath, $config);

        echo "\n=========================================\n";
        echo "  Merge abgeschlossen\n";
        echo "  Kunde:     {$result['kunde']}\n";
        echo "  Timestamp: {$result['timestamp']}\n";
        echo "  Module:    " . count($result['module']) . " Sektion(en)\n";
        echo "  Output:    {$result['output']}\n";
        echo "  Größe:     " . number_format($result['size_bytes'] / 1024, 1, ',', '.') . " KB\n";
        echo "=========================================\n";
    } catch (\Exception $e) {
        HealthcheckUtils::log($e->getMessage(), 'error');
        exit(1);
    }
}
