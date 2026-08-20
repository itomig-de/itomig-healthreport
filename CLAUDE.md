# CLAUDE.md

Guidance für Claude Code (claude.ai/code) bei Arbeit in diesem Repository.

## Project Overview

ITOMIG-interne Reporter-Pipeline für Healthcheck-ZIPs, verpackt als installierbare
**iTop-Extension** (`itomig-healthreport`). Nimmt das Collector-ZIP aus der iTop-Extension
`itomig-healthcheck` entgegen, wertet es aus und speichert das Ergebnis als iTop-Objekte
(`HealthcheckRun` + `HealthcheckModuleReport`). Kein externer Webserver, keine externe DB,
keine API-Calls — die Auswertung läuft direkt im iTop der ITOMIG. PHP 8.1+, nur Builtins
(`json`, `zip`).

**Eingang:** ZIP-Upload über das Menü „Healthcheck" in iTop (Route `itomig_healthreport.upload`)
oder wahlweise über eine Portal-Brick, mit der Kunden ihr ZIP selbst hochladen (nur Ablage als
`HealthcheckUpload`, Auswertung wird separat in der Konsole angestoßen — siehe „Portal-Upload
durch Kunden" weiter unten)
**Ausgang:** `HealthcheckRun`-Objekt (Management-Summary + Original-ZIP als Blob) mit
verknüpften `HealthcheckModuleReport`-Objekten (ein Detail-Report pro Modul, ebenfalls als Blob)

Die Kern-Auswertungslogik (`lib/`, `reporters/`, `tools/import-zip.php`) ist bewusst
iTop-unabhängig geblieben (globale Klassen/Funktionen, kein Namespace) und wird vom
iTop-Controller zur Laufzeit per `require_once` eingebunden — siehe „iTop-Extension" unten.

## Verzeichnislayout

```
itomig-healthreport-extension/            # = iTop-Modul-Wurzel (itop/extensions/itomig-healthreport/)
├── module.itomig-healthreport.php        # iTop-Modul-Definition (SetupWebPage::AddModule)
├── datamodel.itomig-healthreport.xml     # Klassen HealthcheckRun/HealthcheckModuleReport, Menüs
├── model.itomig-healthreport.php         # Auto-generiert bei Compile, nicht editieren
├── composer.json                         # PSR-4: Itomig\iTop\Extension\HealthReport\ -> src/
├── vendor/                                # Composer-Autoload, MUSS committed sein (kein composer install auf Ziel-iTop)
├── dictionaries/{de,en,fr}.dict.itomig-healthreport.php
├── templates/UploadForm.html.twig        # Upload-Formular (Twig, ibo-*/UI*-Makros)
├── src/
│   ├── Controller/HealthReportController.php  # Route itomig_healthreport.{show_form,upload,pending_uploads,process_upload}
│   ├── Portal/
│   │   ├── Brick/HealthReportUploadBrick.php       # Portal-Brick "Healthcheck hochladen"
│   │   ├── Router/HealthReportPortalRouter.php     # Registriert die Portal-Route (ItopExtensionsExtraRoutes)
│   │   └── Controller/HealthReportUploadBrickController.php  # Portal-Upload (nur Ablage, keine Auswertung)
│   └── Service/
│       ├── ReportPipeline.php            # Adapter: bindet lib/reporters/tools ein, liefert Ergebnis-Array
│       ├── RunPersister.php              # Persistiert das Ergebnis als HealthcheckRun/-ModuleReport
│       ├── UploadProcessor.php           # Wertet einen HealthcheckUpload nachtraeglich aus (Konsole)
│       └── ZipUploadValidator.php        # Gemeinsame Upload-Validierung (Konsole + Portal)
├── portal/templates/upload.html.twig     # Portal-Seite der Upload-Brick (Bootstrap 3, kein ibo-*)
├── portal-bootstrap.php                  # Bindet die Portal-Route ein, nur falls itop-portal-base installiert ist
├── lib/
│   ├── HealthcheckReport.php             # Findings, Ampel, HTML (toHtml + toHtmlManagementSummary)
│   ├── HealthcheckUtils.php              # Config-Loader, Pfad-Resolver, Logging
│   └── HealthcheckModules.php            # Modul-Registry + mergeReport
├── reporters/
│   ├── rest/<modul>-reporter.php         # 6 REST-Reporter
│   ├── db/<modul>-reporter.php           # 3 DB-Reporter
│   └── report-all.php                    # CLI-Orchestrator + runReporter()-Library-Funktion
├── tools/
│   ├── import-zip.php                    # importZip()-Library-Funktion + CLI
│   └── merge-report.php                  # Kundenversion-Merger (eigenständiges CLI-Tool)
├── config/
│   ├── reporter-defaults.php             # Git-tracked, Standard-Schwellwerte
│   └── healthcheck-config.php            # Gitignored, lokale Overrides (optional)
├── daten/<kunde>/raw/<modul>/<Y-m-d_His>.json    # Nebeneffekt der Engine, lokal/gitignored
└── auswertung/<kunde>/...                # Nebeneffekt der Engine, lokal/gitignored
```

**Wichtig:** Die Collectoren leben **nicht** in diesem Repo. Sie sind Teil der iTop-Extension
`itomig-healthcheck`. Dieses Repo ist reine Auswertung.

**Historie:** Bis Version 3.x lief dieses Repo als eigenständige Web-Anwendung (`web/`,
Docker-Setup, HTTP-Basic-Auth, Kunden-Share-Links). Diese Betriebsart wurde zugunsten der
iTop-Integration entfernt — Auswertung läuft ausschließlich noch innerhalb von iTop.

## iTop-Extension

### Deploy

```bash
# Entwicklung: Symlink ins lokale iTop
ln -s /pfad/zu/itomig-healthreport-extension /pfad/zu/itop/extensions/itomig-healthreport

# Danach: iTop-Setup/Toolkit ausführen, um Datamodell zu kompilieren und
# die Tabellen itomig_healthreport_run / itomig_healthreport_modul anzulegen.
```

Nach dem Deploy erscheint das Menü **„Healthcheck"** (nur für Admins sichtbar,
`enable_admin_only=1`) mit den Einträgen „Healthcheck hochladen" (Upload-Formular) und
„Healthcheck-Läufe" (OQL-Liste aller `HealthcheckRun`).

### Ablauf eines Uploads

1. `HealthReportController::OperationShowForm()` rendert `templates/UploadForm.html.twig`
   (Route `itomig_healthreport.show_form`).
2. `HealthReportController::OperationUpload()` (Route `itomig_healthreport.upload`) validiert
   Transaction-Token und Upload (Fehlercode, `is_uploaded_file()`, `.zip`-Endung — Muster der
   ehemaligen `web/process.php`), ruft dann `ReportPipeline::run($tmpPath)` auf.
3. `ReportPipeline` bindet `lib/HealthcheckReport.php`, `lib/HealthcheckUtils.php`,
   `lib/HealthcheckModules.php`, `tools/import-zip.php`, `reporters/report-all.php` per
   `require_once` ein und ruft die dort definierten globalen Funktionen `importZip()` und
   `runReporter()` auf (siehe „Modul-Schnittstellen" unten). Output-Pfade werden auf
   `APPROOT/data/itomig-healthreport/{daten,auswertung}` umgebogen; `HealthcheckUtils::log()`
   wird per Output-Buffering geschluckt (die Engine `echo`t unbedingt und hat keinen
   injizierbaren Logger).
4. `RunPersister::persist()` legt ein `HealthcheckRun`-Objekt an (Kunde/Umgebung/Ampel +
   Original-ZIP und Summary als `AttributeBlob`) und je Modul ein verknüpftes
   `HealthcheckModuleReport`-Objekt (Detail-HTML/JSON als Blob).
5. Redirect auf die native iTop-Detailseite des neuen `HealthcheckRun`
   (`pages/UI.php?operation=details&class=HealthcheckRun&id=...`). Report-HTMLs werden dort
   über iTops eingebauten Blob-Viewer angezeigt (`ormDocument::GetDisplayURL()`,
   `pages/ajax.render.php?operation=display_document`) — kein eigener Streaming-Code nötig.

### Portal-Upload durch Kunden

Zusaetzlich zum Konsolen-Upload gibt es eine Portal-Brick "Healthcheck hochladen"
(`src/Portal/Brick/HealthReportUploadBrick.php`, Route `p_healthreport_upload`), über die
Kunden ihr ZIP selbst hochladen koennen. Wichtig: **der Portal-Upload triggert die Auswertung
bewusst NICHT automatisch** — er legt nur ein `HealthcheckUpload`-Objekt (Status `neu`) mit
dem ZIP als Blob an (`HealthReportUploadBrickController::SubmitAction()`). ITOMIG sichtet die
eingegangenen Uploads in der Konsole unter Menü „Healthcheck → Eingegangene Uploads"
(Route `itomig_healthreport.pending_uploads`) und stoesst die Auswertung dort gezielt per
Button an (`OperationProcessUpload()` → `UploadProcessor::process()`). `UploadProcessor`
nutzt dabei unveraendert dieselbe `ReportPipeline`/`RunPersister`-Kette wie der Konsolen-Upload
und setzt danach `status=ausgewertet` + `run_id` auf dem Upload (oder `status=fehler` +
`fehlermeldung` bei einer fehlgeschlagenen Auswertung).

Die Portal-Integration folgt dem Standard-iTop-3.2-Muster für Extension-Bricks mit eigenem
Controller (Vorbild: `approval-base`): Brick-Klasse (`Combodo\iTop\Portal\Brick\PortalBrick`),
Router-Registrierung über `Combodo\iTop\Portal\Routing\ItopExtensionsExtraRoutes` und ein
eigener Controller (`Combodo\iTop\Portal\Controller\BrickController`). `portal-bootstrap.php`
bindet Autoloader + Router nur ein, wenn `itop-portal-base` tatsaechlich installiert ist —
ohne Portal bleibt die Extension unveraendert nutzbar. Rechte: `HealthcheckUpload` ist der
einzige Portal-sichtbare Teil der Extension (`user_rights` gewaehrt Profil `2`/"Portal user"
Lese-/Schreibrechte darauf); `HealthcheckRun`/`HealthcheckModuleReport` bleiben admin-only.

**Ausblick (noch nicht umgesetzt):** Anzeige der Auswertungsergebnisse (Management-Summary)
im Portal ist fuer eine zukuenftige Version vorgesehen — dafuer fehlt aktuell ein
Portal-Scope auf `HealthcheckRun` (das Feld heisst dort `kunde_id`, nicht `org_id`) sowie eine
Ausliefer-Route fuer die Blob-Felder.

### Wichtige Namens-Kollision

`lib/HealthcheckReport.php` definiert die **globale** Klasse `HealthcheckReport` (Findings +
HTML-Rendering). Die iTop-Persistenzklasse dafür heißt bewusst **`HealthcheckRun`**, nicht
`HealthcheckReport` — sonst fataler Klassenname-Konflikt beim Laden der Engine.

## Lokale CLI-Nutzung (Entwicklung/Debugging)

Die Engine bleibt per CLI direkt aufrufbar, unabhängig von iTop — nützlich zum Debuggen
einzelner Reporter ohne volle iTop-Installation:

```bash
# ZIP entpacken (nur Import, kein Reporting)
php tools/import-zip.php --zip=/pfad/zu/healthcheck.zip

# Alle aktiven Reporter gegen die zuletzt importierten Rohdaten laufen lassen
php reporters/report-all.php [--timestamp=…]

# Modul-spezifisch
php reporters/rest/design-reporter.php --raw=daten/<kunde>/raw/design/<ts>.json

# Alle HTMLs eines Laufs zu einer Kundenversion mergen
php tools/merge-report.php --kunde=<kunde> [--timestamp=<ts>]

# Syntax-Check
find . -name "*.php" -not -path "./.git/*" -not -path "./vendor/*" -exec php -l {} \;
```

Diese CLI-Tools schreiben nach `daten/`/`auswertung/` relativ zum Repo-Root (gitignored) —
unabhängig vom `APPROOT/data/itomig-healthreport/`-Pfad, den `ReportPipeline` beim
iTop-Betrieb verwendet.

## Modul-Schnittstellen (Reporter)

Jede Reporter-Datei exportiert eine Funktion `report<Name>(array $raw, array $config): HealthcheckReport`:

```php
function reportDesign(array $raw, array $config): HealthcheckReport
{
    // $raw['meta']  — kunde, umgebung, modul, tier, timestamp, itop_version|db_server_version
    // $raw['daten'] — modul-spezifische Rohdaten (genau wie vom Collector geschrieben)
    // $config       — Reporter-Schwellwerte (reporter-defaults.php oder Override)
    //
    // Reporter wendet Schwellwerte an, baut Findings, gibt HealthcheckReport zurück.
}
```

Der CLI-Guard jeder Datei akzeptiert `--raw=<pfad>` oder nimmt automatisch die jüngste Raw-JSON unter `daten/<kunde>/raw/<modul>/` (via `HealthcheckUtils::latestRawJson`).

## Orchestrator

`reporters/report-all.php::runReporter($modul, $config, $rawTimestamp, $reportTimestamp, $backToSummary)` ist der zentrale Entry, der alle Reporter-CLI-Files importieren. Signatur:
- `$rawTimestamp` — welche Raw-JSON gelesen wird (null = neueste)
- `$reportTimestamp` — gemeinsamer Output-Timestamp für alle Reports eines Laufs
- `$backToSummary` — relativer Link zum Management-Summary (für die Detail-Reports); von `ReportPipeline` bewusst auf `null` gesetzt, da die Navigation in iTop über `run_id`-Verknüpfung statt Datei-Querlinks läuft

`ReportPipeline::run()` (iTop-Betrieb) und `report-all.php`'s CLI-Block (lokale Nutzung) rufen beide dieselbe `runReporter()`-Funktion auf.

## Modul-Registry (`lib/HealthcheckModules.php`)

Zentrale Definition der 9 Module mit `tier`, `flag`, `label`, `category`, `reporter_file`, `reporter_fn`. Code geht über `HealthcheckModules::all()`, `::get($slug)`, `::active($config)`. `::mergeReport($gesamt, $modul, $category)` überträgt Findings + Summary eines Modul-Reports in den Gesamt-Report.

Die Kategorie-Keys (`design`, `data`, `synchro`, `integration`, `system`, `privacy`, `tables`, `columns`, `freshness`) müssen in `HealthcheckReport::CATEGORY_LABELS` registriert sein.

## HTML-Rendering (`lib/HealthcheckReport.php`)

- `toHtml(?string $backToSummary = null)` — voller Report aller Findings einer/mehrerer Kategorien. Optional mit Rück-Link zum Summary.
- `toHtmlManagementSummary(array $detailLinks)` — kompakter Summary:
  - Ampel-Grid (Karten klickbar wenn `$detailLinks[$category]` gesetzt)
  - Mini-Sektionen mit Counts + „Details →"-Button
  - „Wichtigste Befunde"-Sektion mit `critical` + `warning` aus allen Kategorien
- Bestehende `addFinding()`, `setCategorySummary()`, `toJson()` unverändert.

## Konfiguration

`HealthcheckUtils::loadConfig()` Fallback-Kette:
1. expliziter `$configPath`-Parameter
2. `config/healthcheck-config.php` (lokale Overrides, gitignored)
3. `config/reporter-defaults.php` (Standard, Git-tracked)

`ReportPipeline` ruft `loadConfig()` immer mit explizitem Pfad auf `config/reporter-defaults.php`
auf und biegt danach nur die `output.*_pfad`-Keys auf das iTop-Datenverzeichnis um — keine
Credentials erforderlich, da die Collectoren in der Extension `itomig-healthcheck` laufen.

## Konventionen

- PHP 8.1+, `declare(strict_types=1)`, PSR-12.
- Code-Kommentare, Variablen-Namen, Logs, Report-Texte auf Deutsch (Ausnahme: PHP-Klassen/Methoden-Namen in der Engine sind global und nicht namespaced, s.o.).
- ITOMIG-Farben: Deep Cyan `#074552`, Strong Cyan `#2794ab`, Vivid Cyan `#20d6fc`, Dark Gray `#454545`, Orange `#e3791d`.
- Dateinamen via `HealthcheckUtils::rawJsonPath()` / `reportPath()`.
- Connection/Filesystem-Fehler bleiben fatal; einzelne Modul-Reporter dürfen via try/catch tolerieren, dass Felder im Raw-JSON fehlen oder mit `fehler`-Kennzeichnung kommen.
- Dictionaries: de/en/fr sind Pflicht für neue Dict-Keys (ITOMIG-Standard, s. AGENTS.md).

## Schema der Eingangs-ZIPs

Erwartet wird, was die iTop-Extension `itomig-healthcheck` (Collector-Version ≥ 3.0.0) erzeugt.
**Breaking Change:** Seit Collector-Version 3.0.0 sind alle JSON-Keys englisch (siehe
`itomig-healthcheck/tools/key-mapping.php` für die vollständige DE→EN-Umstellung); Collector-Version
2.x lieferte noch deutsche Keys (`daten`/`kunde`/`umgebung`/...) und ist mit dieser Reporter-Version
nicht mehr kompatibel.

```
healthcheck_<kunde>_<ts>.zip
├── manifest.json      {customer, environment, itop_version, db_server_version, timestamp, extension_version, modules:[…]}
├── design.json        {meta:{…}, data:{…}}
├── data.json
├── synchro.json
├── integration.json
├── system.json
├── privacy.json
├── table-overview.json
├── column-fill.json
└── object-freshness.json
```

Jede Modul-JSON folgt `{meta: {customer, environment, module, tier, timestamp, collector_version, itop_version|db_server_version}, data: {<modul-spezifisch>}}`. Schema-Änderungen am Collector erfordern entsprechende Anpassungen an den Reportern hier — Compatibility-Test: das Test-ZIP unter `../test/` einmal durch `tools/import-zip.php` + `reporters/report-all.php` jagen.

**Datenschutz-Runde (2026-08):** Aus DSGVO-Gründen liefert der Collector keine
personenbezogenen Einzeldaten mehr, nur noch Aggregat-Zahlen — betroffen sind
`integration.user_accounts` (nur noch `active`/`disabled`/`with_admin`/`without_contact`/`error`,
kein `items[]` mit Login/Kontakt/Profil mehr), `privacy.inactive_sample` (nur noch
`{"error": null}`, Auswertung läuft stattdessen über `privacy.persons.inactive`) und
`privacy.disabled_users` (nur noch `count`/`error`, kein `items[]` mit Login/Person mehr).
`system.data.itop_version` ist komplett entfallen (war redundant zu `meta.itop_version`, das
unverändert vorhanden bleibt). Neu verfügbar: `system.data.php.{version,sapi,extensions}` mit
den echten PHP-Infos des Collector-Laufs. Optional, nicht auszuwerten: das ZIP kann zusätzlich
eine `system-information-<ts>.zip` (unveränderte Kopie des iTop-„System Information"-Reports)
enthalten; `manifest.json` bekommt dafür die Felder `system_report_included`,
`system_report_filename`, `system_report_error` — beim Parsen ignorieren.

## Verwandte Repos

| Repo | Verantwortung |
|------|---------------|
| **dieses Repo** | Reporter-Pipeline + iTop-Extension bei ITOMIG (Auswertung) |
| `itomig-healthcheck` (iTop-Extension) | Collector beim Kunden (Datensammlung) |

## Slash Commands (ITOMIG-Standards)

- `/itomig-itop-pr-review` · `/itomig-itop-security` · `/itomig-itop-qa-release` · `/itomig-itop-review`
- `/itomig-itop-analyze` · `/itomig-itop-extension-analyze` · `/itomig-extension-testcase` · `/itomig-extension-doku`

## Version

Version: 26.3.0
Last Updated: 2026-08-20
