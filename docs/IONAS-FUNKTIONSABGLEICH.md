# Funktionsabgleich WebCMS 2.0 und ionas

Dieser Abgleich basiert auf der öffentlich erreichbaren Produktseite der Chamaeleon GmbH, der öffentlich auffindbaren älteren ionas-Dokumentation und dem aktuellen WebCMS. Die verlinkte Wissensdatenbank unter `kb.ionas.de/ionas/de/` zeigt ohne Anmeldung nur eine Login-Seite. Kundenspezifische ionas-Module und Konfigurationen können deshalb nicht abschließend inventarisiert werden.

## Umgesetzt

| ionas-Funktionsbereich | WebCMS 2.0 |
| --- | --- |
| Strukturbaum und WYSIWYG/Drag-and-drop | Seitenbaum und visueller Blockeditor mit direkter Bearbeitung, Blocksuche, Duplizieren, Rückgängig/Wiederholen und Geräteansicht |
| Dokumenthistorie | Automatische Revision vor jeder Änderung, lesbarer Versionsstand und Wiederherstellung als Entwurf |
| Redaktioneller Workflow | Entwurf, Zur Freigabe, Veröffentlichung/Planung und Offline; Aufgaben und interne Notizen |
| Hierarchische Rechte | Fünf Rollen sowie optional auf Seiten und Unterseiten begrenzte Redaktionsbereiche |
| Zeitsteuerung | Veröffentlichung ab/bis unter Berücksichtigung der konfigurierten Zeitzone |
| Mehrsprachigkeit | Verknüpfte Sprachfassungen, `lang` und `hreflang` |
| Vorlagen und Syndikation | Seitenvorlagen sowie dynamisch geteilte, veröffentlichte Inhalte |
| Medien | Upload, Vorschau, Ordner, Alternativtext, Bildunterschrift, Urheberhinweis und Verwendungsnachweis |
| Formulargenerator | Zentrale Formulare, acht Feldtypen, Pflichtfelder, Auswahloptionen, serverseitige Validierung, Posteingang und CSV-Export |
| Nachrichten | Strukturierte Sammlung, Kategorien, Suche/Filter, Detailseiten, RSS und JSON |
| Kalender | Strukturierte Veranstaltungen, Detailseiten, iCalendar-Import/-Export und JSON |
| Verzeichnisse | Vereine/Firmen, Unterkünfte, Bürgerservice sowie Stellen/Ehrenamt als strukturierte Sammlungen |
| Umfragen | Antwortoptionen, eine Stimme pro Sitzung, Ergebnisanzeige |
| Forum/Kommentare | Beiträge und moderierte Kommentare |
| Newsletter | Double-opt-in-Anmeldung, Abmeldung und Export für externe Versanddienste |
| Kartendienste | Standortblock und Verzeichnis-Adressen mit OpenStreetMap-Link |
| Volltextsuche | Suche über veröffentlichte Seiten und Sammlungen |
| Statistik | Optionale datensparsame Seitenaufrufe pro Tag und Seite |
| SEO und Weiterleitungen | Meta-Beschreibung, SEO-Titel, Canonical, Social-Bild, `noindex`, Sitemap und 301/302-Regeln mit Schleifenprüfung |
| Schnittstellen | Öffentliche JSON-Endpunkte, RSS, iCalendar und Inhaltsimport/-export |

## Bewusst über externe Dienste gelöst

WebCMS exportiert Newsletter-Empfänger für einen Versanddienst, öffnet Karten in OpenStreetMap und stellt JSON-Schnittstellen für Drittsysteme bereit. E-Payment, S/MIME-Mailversand, eID-Authentifikation, Ratsinformationssysteme, landesspezifische Verwaltungsregister, Feratel/Deskline und Outdooractive benötigen jeweils Verträge, Zugangsdaten, genaue Datenmodelle oder externe Dienste. Dafür stellt WebCMS Erweiterungspunkte bereit, enthält aber ohne kundenspezifische Angaben keine vorgetäuschte Integration.

## Bekannte Grenzen

- Die eingebaute Statistik zählt Seitenaufrufe und führt keine Besucherprofile, Klickpfade oder Verweilzeitmessung.
- Der Kalenderimport unterstützt Einzeltermine; Serienregeln werden mit einer klaren Fehlermeldung abgelehnt.
- Formulare unterstützen derzeit keine Datei-Uploads oder Zahlungen.
- Geschützte Seiten erfordern ein CMS-Benutzerkonto. Hochgeladene Medien besitzen öffentliche URLs und eignen sich daher nicht für vertrauliche Dateien.
- Newsletter-E-Mails setzen einen funktionierenden PHP-Mailversand und eine konfigurierte Absenderadresse voraus. Der eigentliche Kampagnenversand erfolgt in einem spezialisierten Versanddienst.
