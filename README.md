# Local Consent

Ein eigenständiges WordPress-Plugin für lokale Einwilligungen. Kostenloser Quellcode unter GPL-2.0-or-later, ohne Account, CDN, Lizenzserver oder Zugriffslimit.

## Erste Version

- Google Maps, Google-Tags, YouTube und Vimeo vor der Einwilligung im HTML deaktivieren.
- Bekannte Consentmanager.net/.de-Loader beim Wechsel unterdrücken.
- Direkte externe Google-Fonts-Links und Inline-Style-Imports sperren; lokale Fonts weiterverwenden.
- Einzelne Dienste freigeben, alles ablehnen, alles erlauben und Auswahl ändern.
- Auswahl ausschließlich im Browser speichern, mit Ablaufdatum und Konfigurationsrevision.
- Beim Widerruf neu laden, damit bereits gestartete Skripte beendet werden.
- Deutsche und englische Oberfläche mit Polylang-Erkennung.

Die HTML-Sperre läuft vor der Auslieferung. Alle Besucher erhalten dieselbe gesperrte HTML-Version; erst der Browser aktiviert freigegebene Ressourcen. Ein Seiten-Cache muss nach dem Wechsel und nach Einstellungsänderungen geleert werden. Die tatsächlichen Netzwerkanfragen sind das Prüfkriterium.

## Installation

Das Plugin-ZIP gibt es unter [GitHub Releases](https://github.com/tbuck-software/wp-local-consent/releases). In WordPress hochladen und aktivieren, dann unter **Plugins → Local Consent** konfigurieren. Das Paket enthält nur Plugin-Code, Assets, Lizenz und WordPress-Readme. Der WordPress-Verzeichnisname bleibt `local-consent`.

Auf Desktop öffnet das Banner über dem Fingerabdruck-Button und schrumpft beim Schließen zu ihm. Der FAB bleibt dabei sichtbar und klickbar. Auf Mobile fährt das Bottom Sheet von unten ein und hinaus; der FAB ist währenddessen verborgen. Die Aktionen bleiben beim Scrollen sichtbar. Herunterziehen lehnt alle Dienste ab. Reduzierte Bewegung deaktiviert die Animationen.

## Entwicklung und Tests

Benötigt Make, Python 3, Node.js ab 18, PHP ab 7.4 mit SQLite und WP-CLI. WP-CLI kann über `WP_CLI_PHAR=/pfad/wp-cli.phar` angegeben werden.

```sh
git clone https://github.com/tbuck-software/wp-local-consent.git
cd wp-local-consent
make dev
```

`make dev` richtet beim ersten Start ein eigenes WordPress mit SQLite und einer Testseite unter `.local/wordpress` ein. Danach startet die Seite auf http://127.0.0.1:8091. Login: `local-admin`, Passwort in `.local/admin-password.txt`. Das ignorierte Verzeichnis enthält keine Produktionsdaten. Eine vorhandene Installation ohne lokalen Marker wird nicht überschrieben. `make dev PORT=8092` ändert den Port; bei bereits eingerichtetem WordPress dessen `home` und `siteurl` entsprechend anpassen.

| Befehl | Wirkung |
| --- | --- |
| `make setup` | Lokale Umgebung ohne Server einrichten |
| `make dev` | Umgebung einrichten und Server starten |
| `make seed` | Lokale Testseite wiederherstellen |
| `make test` | Syntax, JavaScript, PHP-Integration und Paket prüfen |
| `make test-js` | JavaScript-Tests ohne WordPress |
| `make package` | `dist/local-consent-0.1.4.zip` bauen |
| `make help` | Befehle und Variablen anzeigen |

Die Testseite verwendet lokale Platzhalter und ein Testskript. Sie prüft Freigabe und Lade-Reihenfolge ohne echte Tracking-Aufrufe. Die PHP-Tests benötigen ein eingerichtetes WordPress. Eine andere isolierte Testinstallation kann mit `make test LOCAL_CONSENT_WP=/pfad/wordpress` verwendet werden. Der Setup-Befehl arbeitet immer nur in der eigenen `.local/wordpress`-Installation.

Das bestehende Website-Projekt kann weiterhin `../local-consent` verlinken. Für einen Clone unter anderem Namen dort `python3 scripts/site.py link-plugin --plugin-path ../wp-local-consent` verwenden.

## Releases

GitHub Actions prüft Änderungen auf `main` und Pull Requests. Ein Versions-Tag startet dieselben Tests auf WordPress 6.7 und 6.8.1, baut das ZIP und veröffentlicht es zusammen mit einer SHA-256-Prüfsumme als GitHub Release.

Vor einem neuen Release die Version im Plugin-Header, `LocalConsent\VERSION` und `readme.txt` gemeinsam ändern, Changelog und `RELEASE.md` aktualisieren, dann testen und committen:

```sh
make test
make package
git push origin main
git tag v0.1.5
git push origin v0.1.5
```

`v0.1.5` ist das Beispiel für den nächsten Release. Stimmen Tag und Paketversion nicht überein, bricht der Paketbau ab. Releases aktualisieren keine WordPress-Installation automatisch; Updates erfolgen über das ZIP. `Update URI: false` verhindert Updates durch ein fremdes Plugin mit demselben Verzeichnisnamen.

## Design

Unter **Plugins → Local Consent → Design** übernimmt der Modus „Automatisch“ die vorhandene Fließtext- und Überschriftenschrift, Hintergrund- und Textfarben sowie geeignete Button- oder Linkfarben. Rundungen stammen von vorhandenen Buttons. Fehlen passende Elemente, gelten neutrale Standardwerte. Automatisch erkannte Textfarben werden auf ausreichenden Kontrast geprüft. Die Erkennung ist eine Annäherung, keine vollständige Auswertung aller Theme-Regeln.

„Eigenes Design“ öffnet die Felder für Schrift, Farben und Rundungen. Jede Farbe hat einen Farbwähler und ein synchronisiertes Hexfeld. Rechts erscheint das echte Banner mit Fingerabdruck-Icon; Änderungen und Vorlagen werden dort sofort dargestellt, ohne Einstellungen zu speichern. Auf kleinen Bildschirmen steht die Vorschau unter den Feldern. Noch leere Felder übernehmen die aktuell wirksamen automatischen Werte. Die Vorlage „Automatisches Design“ übernimmt alle Werte erneut; „Hell“, „Weich“ und „Dunkel“ bieten fertige Farben und Rundungen. Vorlagen ändern die Schrift nicht. Die Übernahme verwendet eine geschützte Vorschau derselben Website. Sie aktiviert keine vom Plugin verwalteten Dienste und verändert keine gespeicherten Besuchereinwilligungen. Ist die Vorschau nicht erreichbar oder nicht auf demselben Origin, bleiben manuelle Felder verfügbar. Für leere Felder gelten im eigenen Design die Standardwerte. Es werden keine Schriftdateien installiert oder externe Schriftanbieter eingebunden. Eine konfigurierte Schrift muss auf der Website vorhanden sein; sonst nutzt der Browser die angegebenen Ersatzschriften.

Im Reiter „Import / Export“ eine Datei wählen oder JSON einfügen und **Importieren** wählen. Ein Import ersetzt ausschließlich das Design. Dienstfreigaben und Sperren bleiben bestehen. Ungültige Dateien ändern keine Einstellungen. Der Export enthält nur das gespeicherte Design, keine dynamisch erkannten Werte. Designänderungen setzen bestehende Besuchereinwilligungen nicht zurück. Seiten-Caches nach Änderungen leeren.

```json
{
  "version": 1,
  "mode": "auto",
  "tokens": {
    "accent": "#003154",
    "accentText": "#ffffff",
    "radius": 3,
    "buttonRadius": 3,
    "launcherRadius": 24
  }
}
```

Alle Tokens sind optional. Farben verwenden sechsstellige Hexwerte. `fontFamily` und `headingFontFamily` enthalten CSS-Schriftfamilien ohne Funktionen oder URLs. `fontSize` liegt zwischen 14 und 22, `radius` und `buttonRadius` zwischen 0 und 32, `launcherRadius` zwischen 0 und 24. Zahlen sind Pixelwerte. Ohne `launcherRadius` ist das Icon rund. Weitere Farben sind `background`, `text`, `muted`, `border` und `focus`. Eigene Farbkombinationen auf Kontrast prüfen. Dateien sind auf 16 KB begrenzt.

## Weitere Dienste integrieren

```php
add_filter('local_consent_services', function ($services) {
    $services['example_video'] = array(
        'label' => 'Example Video',
        'category' => 'external',
        'description' => 'Lädt Videos vom angegebenen Anbieter und überträgt Verbindungsdaten.',
        'rules' => array(array('host' => 'video.example.org', 'path' => '/embed/')),
    );
    return $services;
});
```

Den Dienst anschließend im Admin aktivieren. Die Host-Regel gilt für den Host und seine Subdomains; `path` ist ein wörtliches Präfix. IDs dürfen nur Buchstaben, Zahlen und Unterstriche enthalten. Keine Zugangsdaten in Beschreibungen oder URLs hinterlegen.

Für Skripte oder iframes mit einer eigenen URL, die ebenfalls Einwilligung brauchen:

```html
<script data-local-consent="example_video" src="/meine-integration.js"></script>
```

Eigene dynamische Integrationen prüfen `window.LocalConsent?.allows('example_video')` vor jedem Laden. `local-consent:ready` und `local-consent:change` werden auf `document` ausgelöst. `window.LocalConsent.open()` öffnet die Auswahl. Dienstkonfiguration und Speicherung werden zentral verwaltet; eine neue Integration braucht keine eigene Consent-Datenbank.

## Grenzen dieser Version

Die automatischen Regeln gelten für unterstützte URLs und übliche Google-Tag-Inline-Loader im von WordPress ausgelieferten HTML. Unbekannte Plugins, dynamische JavaScript-Loader, externe CSS-Dateien, HTTP-Link-Header, serverseitiges Tracking und PHP-Cookies brauchen eine eigene Prüfung oder Integration. Ein MutationObserver findet zusätzlich eingefügte, bereits gesperrte Elemente; er verhindert keine Anfragen ungesperrter Elemente.

Die Sperre setzt vollständige, nicht vorzeitig geflushte HTML-Ausgaben voraus. HTML-Seiten, die vor WordPress aus einem Cache ausgeliefert werden, müssen bereits die gesperrte Version enthalten. Andere Consent-Plugins beim Wechsel deaktivieren. Technische Tests ersetzen keine Prüfung der konkreten Dienstbeschreibungen und Zwecke. Google Consent Mode und IAB TCF sind nicht enthalten.

Beim Widerruf werden bekannte lesbare Google-Analytics-Cookies auf erreichbaren Domains und Pfaden abgelöscht. Fremde Domains, HttpOnly-Cookies und unbekannte Cookie-Namen lassen sich damit nicht löschen.

## Verifikation der ersten Version

Die [lokalen Testergebnisse](tests/verification.json) halten die geprüften WordPress-Versionen, Browser-Fälle und Grenzen fest. Das installierbare ZIP wurde in einer isolierten WordPress-Installation erfolgreich installiert. Die GitHub-Workflows führen die JavaScript-Tests, PHP-Integrationstests auf WordPress 6.7 und 6.8.1 sowie den Paketbau aus.
