# ITOMIG Healthreport — iTop-Extension zur Report-Auswertung

ITOMIG-interne iTop-Extension (`itomig-healthreport`) zur Auswertung von Healthcheck-Rohdaten
aus Kunden-iTop-Instanzen. Nimmt das ZIP, das die iTop-Extension
[`itomig-healthcheck`](https://github.com/itomig-de/itomig-healthcheck) auf Kundenseite
erzeugt, wertet es aus und speichert Report + Rohdaten als iTop-Objekte im iTop der ITOMIG.

## Architektur (zweiteilig)

```
Kunde (iTop)                   ITOMIG (iTop mit dieser Extension)
─────────────                  ───────────────────────────────────
itomig-healthcheck              itomig-healthreport
└─ Collector                    ├─ Menü "Healthcheck" → Upload-Formular
   (PHP-Extension im iTop)      ├─ HealthReportController → ReportPipeline
   schreibt JSON-Rohdaten       │  (bindet lib/reporters/tools ein)
   und packt sie ins ZIP        └─ RunPersister → HealthcheckRun + HealthcheckModuleReport
        │
        ▼
   healthcheck_*.zip ───────► Upload im iTop-Menü "Healthcheck"
```

Die Trennung erlaubt:
- Datensammlung beim Kunden (im iTop-Kontext, ohne API-Credentials, internes API)
- Reproduzierbare Reports aus persistierten Rohdaten (als iTop-Objekt mit Historie/ACL)
- Re-Reporting mit angepassten Schwellwerten, ohne neuen Kundenzugriff

## Module (9 insgesamt)

| Tier | Slug | Inhalt |
|------|------|--------|
| REST | `design` | Datenmodell (Klassen, Enums, Übersetzungen) |
| REST | `data` | Datenqualität (Audit-Regeln, Befüllung, Obsolescence) |
| REST | `synchro` | Synchronisations-Konsistenz |
| REST | `integration` | Benachrichtigungen, Webhooks, Mail, AI |
| REST | `system` | iTop-Version, Module, PHP-Empfehlungen |
| REST | `privacy` | DSGVO, inaktive Personen, Löschkonzepte |
| DB | `table-overview` | Tabellen-Inventar |
| DB | `column-fill` | Spalten-Befüllungsgrad |
| DB | `object-freshness` | Letzte Änderung pro iTop-Klasse |

> **Breaking Change (JSON-Keys):** Seit Collector-Version 3.0.0 der Extension `itomig-healthcheck`
> sind alle JSON-Keys der Modul-Dateien, des Manifests und der Findings-JSON (`toJson()`) englisch
> (`data` statt `daten`, `customer` statt `kunde`, `results`/`findings`/`status` statt
> `ergebnis`/`befunde`/`ampel`, u.v.m.). Diese Extension muss im Lock-Step mit der passenden
> `itomig-healthcheck`-Version betrieben werden - es gibt keine Rückwärtskompatibilität zu
> deutschen Keys. Vollständige Liste: `itomig-healthcheck/tools/dump-keys.php` bzw.
> `itomig-healthcheck/docs/healthcheck-keys.csv`.

## Installation

```bash
# In itop/extensions/ verlinken oder kopieren
ln -s /pfad/zu/itomig-healthreport-extension /pfad/zu/itop/extensions/itomig-healthreport

# iTop-Setup/Toolkit ausführen, um das Datamodell zu kompilieren
# (legt die Tabellen itomig_healthreport_run / itomig_healthreport_modul an)
```

Danach erscheint im iTop-Backend das Menü **„Healthcheck"** (nur für Admins sichtbar):
- **„Healthcheck hochladen"** — Upload-Formular für das Collector-ZIP
- **„Healthcheck-Läufe"** — Liste aller ausgewerteten `HealthcheckRun`-Objekte

Beim Upload wird das ZIP importiert, alle enthaltenen Module werden ausgewertet und als
`HealthcheckRun` (inkl. verknüpfter `HealthcheckModuleReport`-Objekte je Modul) gespeichert.
Anschließend wird auf die Detailseite des neuen Laufs weitergeleitet — Original-ZIP,
Management-Summary und jeder Modul-Report liegen dort als Datei-Anhang (Blob) vor und werden
über den nativen iTop-Dokumenten-Viewer angezeigt.

## Lokale CLI-Nutzung (Entwicklung/Debugging)

Die Auswertungslogik (`lib/`, `reporters/`, `tools/import-zip.php`) ist unabhängig von iTop
und bleibt per CLI direkt nutzbar — praktisch zum Debuggen einzelner Reporter:

```bash
# Voraussetzung: ein vom Kunden geliefertes ZIP der iTop-Extension itomig-healthcheck
php tools/import-zip.php --zip=/pfad/zur/healthcheck_kunde-x_2026-05-14_102524.zip
php reporters/report-all.php                                # alle aktiven Reporter, neueste Rohdaten
php reporters/rest/design-reporter.php --raw=…               # einzelnen Reporter laufen lassen
php tools/merge-report.php --kunde=kunde-x                   # HTMLs zu einer Kundenversion mergen

# Syntax-Check aller PHP-Files
find . -name "*.php" -not -path "./.git/*" -not -path "./vendor/*" -exec php -l {} \;
```

Details zum internen Aufbau (Reporter-Schnittstelle, Modul-Registry, HTML-Rendering,
`ReportPipeline`-Adapter) stehen in `CLAUDE.md`.

## Konfiguration

`config/reporter-defaults.php` (Git-tracked, keine Geheimnisse) enthält die Reporter-Schwellwerte und Modul-Aktivierung für den ITOMIG-Standardfall. Lokale Overrides können in `config/healthcheck-config.php` (gitignored) abgelegt werden.

## Voraussetzungen

- iTop 3.2, PHP 8.1 – 8.3 mit `zip`- und `json`-Extension
- Keine externe DB, kein API-Zugang — die Auswertung arbeitet rein auf den eingehenden ZIPs

## Repos im Healthcheck-Universum

| Repo | Zweck |
|------|-------|
| **dieses Repo** (`itomig-healthreport`) | iTop-Extension zur Report-Auswertung bei ITOMIG |
| `itomig-healthcheck` (iTop-Extension) | Collector beim Kunden |

## Dokumentation

- **Architektur-Hinweise für Code-Bearbeiter**: `CLAUDE.md`.
- Eine Bedienungs-Anleitung/DokuWiki-Seite für die iTop-Integration ist noch zu erstellen
  (z. B. via `/itomig-extension-doku`).
