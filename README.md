# Nachschreibe-Manager 📝

Ein webbasiertes Tool zur effizienten Verwaltung von Nachschreibeterminen für Lehrkräfte. Die Anwendung ermöglicht das Erfassen von Schülern, das Verwalten von Attesten (AU) und das automatisierte Erstellen von E-Mail-Einladungen sowie PDF-Listen.

## ✨ Features

* **Schüler-Verwaltung:** Erfassen von Name, Klasse, Lehrerkürzel, Fach/Leistung und Dauer.
* **Moodle-Anbindung:** Automatischer Import von Schülerdaten (Name, Mail, Klasse) aus JSON-Exporten im Ordner `/klassen`.
* **Autocomplete:** Intelligente Vorschlagsliste beim Tippen des Namens für schnelles Ausfüllen.
* **Status-Tracking:** Verfolgung von Einladungsstatus, Attest-Eingang (AU) und Meldung auf der zentralen Liste.
* **Automatisierung:** Ein-Klick-E-Mail-Einladung mit vorformuliertem Text.
* **Export:** Generierung einer druckfertigen PDF-Übersicht (sortiert nach Datum und Name).
* **Sicherheit:** Zugriffsschutz durch PIN-Abfrage und Backup-Funktion der Datenbank.

## 🚀 Installation & Setup

1.  **Dateien kopieren:** Lade die `index.php` auf deinen Webserver (mit PHP-Unterstützung).
2.  **Ordner erstellen:** * Erstelle einen Ordner namens `klassen/` für deine Moodle-Exporte.
    * Stelle sicher, dass der Webserver Schreibrechte im Hauptverzeichnis hat (für die `data.json`).
3.  **PIN konfigurieren:** Setze die Umgebungsvariable `APP_PIN` in deiner Serverkonfiguration (z. B. Docker).
4.  **Daten importieren:** Kopiere deine Teilnehmerlisten aus Moodle als `.json` in den `/klassen` Ordner.

## 📊 Datenstruktur

Die Anwendung nutzt zwei Arten von Datenstrukturen:

### 1. Persistente Speicherung (`data.json`)
Die eingetragenen Termine werden in einer flachen JSON-Datei gespeichert. Jeder Datensatz folgt diesem Schema:

```json
{
  "id": "uuid-v4-string",
  "name": "Max Mustermann",
  "teacher": "MUST",
  "schoolClass": "KSM25Z",
  "duration": "90",
  "email": "mustermann.max@schule.de",
  "examName": "LF3 Klassenarbeit",
  "makeUpDate": "2026-05-15T08:00",
  "hasAU": true,
  "status": "pending",
  "invited": false,
  "registered": false
}
```

### 2. Import-Struktur (Moodle-JSON)
Das Tool erwartet im Ordner `klassen/` Dateien mit der typischen Moodle-Teilnehmerstruktur:

```json
[
  [
    {
      "vorname": "Max",
      "nachname": "Mustermann",
      "e-mail-adresse": "mustermann.max@schule.de",
      "gruppen": "KSM25Z Globale Gruppe"
    }
  ]
]
```
*Hinweis: Der Anhang "Globale Gruppe" wird beim Import automatisch entfernt.*

## ⚖️ Lizenz & Urheberrecht

Der **Nachschreibe-Manager** ist unter der Lizenz **CC BY-NC-SA 4.0** (Creative Commons Namensnennung - Nicht kommerziell - Weitergabe unter gleichen Bedingungen) lizenziert.

* **Urheber des Originals:** Herr-FR (Lizenz: CC BY-NC 4.0)
* **Änderungen & Erweiterungen:** Herr-NM (Lizenz: CC BY-NC-SA 4.0)

Der vollständige Lizenztext findet sich in der Datei `LICENSE` oder unter [Creative Commons](https://creativecommons.org/licenses/by-nc-sa/4.0/deed.de).