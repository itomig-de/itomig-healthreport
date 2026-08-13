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
| REST | `daten` | Datenqualität (Audit-Regeln, Befüllung, Obsolescence) |
| REST | `synchro` | Synchronisations-Konsistenz |
| REST | `integration` | Benachrichtigungen, Webhooks, Mail, AI |
| REST | `system` | iTop-Version, Module, PHP-Empfehlungen |
| REST | `datenschutz` | DSGVO, inaktive Personen, Löschkonzepte |
| DB | `tabellen-uebersicht` | Tabellen-Inventar |
| DB | `spalten-befuellung` | Spalten-Befüllungsgrad |
| DB | `objekt-aktualitaet` | Letzte Änderung pro iTop-Klasse |

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
