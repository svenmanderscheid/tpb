# The Printing Brothers

## Gesamt-Systemkonzept für Website, Backend, Bestellungen, Produktion, Finanzen und Etikettendruck

**Stand:** 13. August 2026  
**Status:** Version 1.5 – Kapitel 20 erweitert um 20.10 (Direktkauf ab Go-live, v1.4) und 20.11 (zwei Produkttypen: Standardprodukte mit Hausdesign und Konfigurator-Produkte, v1.5); noch keine Produktionsfreigabe  
**Geltungsbereich:** geplanter Cricut-/Textildruckbetrieb mit Sitz in Luxemburg  
**Markenname:** The Printing Brothers  
**Rechtliche Firmenbezeichnung:** noch festzulegen und nach Eintragung zu ergänzen  
**Bestehender Prototyp:** bisherige MOMNT-Studio-Website auf Next.js/React, Cloudflare Workers und D1  
**Beschlossene Rahmenbedingung:** Hosting soll bei Hostinger bleiben  
**Aktuell empfohlene Zielplattform:** WordPress, WooCommerce, PHP und MySQL auf Hostinger; eigener TPB-Konfigurator und eigenes Betriebs-Plugin; Tarif Business und Plattformwahl vor dem Code-Umbau noch ausdrücklich bestätigen

> Dieses Dokument ist eine technische und organisatorische Planungsgrundlage. Die rechtlichen Kapitel beruhen auf offiziellen luxemburgischen und europäischen Quellen, ersetzen aber keine Prüfung durch einen luxemburgischen Rechtsanwalt, Steuerberater oder Buchhalter. Rechtstexte, Steuerstatus und Aufbewahrungsregeln müssen vor dem öffentlichen Start nochmals mit den dann gültigen Unternehmensdaten geprüft werden.

---

## Inhaltsverzeichnis

1. [Ergebnis und wichtigste Entscheidungen](#1-ergebnis-und-wichtigste-entscheidungen)
2. [Aktueller Stand der Website](#2-aktueller-stand-der-website)
3. [Zielbild und Systemarchitektur](#3-zielbild-und-systemarchitektur)
4. [Öffentliche Website und Kundenportal](#4-öffentliche-website-und-kundenportal)
5. [Konfigurator und Preisberechnung](#5-konfigurator-und-preisberechnung)
6. [Admin-Backend](#6-admin-backend)
7. [Bestell- und Produktionsprozess](#7-bestell--und-produktionsprozess)
8. [Lager, Einkauf und Kundenware](#8-lager-einkauf-und-kundenware)
9. [Etiketten, QR-Codes und Drucker](#9-etiketten-qr-codes-und-drucker)
10. [Globale Datenbank und Dateispeicher](#10-globale-datenbank-und-dateispeicher)
11. [Rechnungen, Zahlungen, Ausgaben und Gewinn](#11-rechnungen-zahlungen-ausgaben-und-gewinn)
12. [Rechtliche Anforderungen in Luxemburg](#12-rechtliche-anforderungen-in-luxemburg)
13. [Datenschutz, Sicherheit und Aufbewahrung](#13-datenschutz-sicherheit-und-aufbewahrung)
14. [Lokale Entwicklung, Staging und Veröffentlichung](#14-lokale-entwicklung-staging-und-veröffentlichung)
15. [Umsetzungsphasen und Prioritäten](#15-umsetzungsphasen-und-prioritäten)
16. [Tests und Freigabekriterien](#16-tests-und-freigabekriterien)
17. [Offene Geschäftsentscheidungen](#17-offene-geschäftsentscheidungen)
18. [Offizielle Quellen](#18-offizielle-quellen)
19. [Aufwand, Zeitplan und Kosten](#19-aufwand-zeitplan-und-kosten)
20. [Betriebsvollständigkeit – das System als Firma](#20-betriebsvollständigkeit--das-system-als-firma)

---

# 1. Ergebnis und wichtigste Entscheidungen

The Printing Brothers sollte nicht aus mehreren unverbundenen Tabellen, E-Mails und Dateien aufgebaut werden. Empfehlenswert ist ein einziges Betriebssystem für das Unternehmen: Der öffentliche Konfigurator, das Kundenportal, das Admin-Backend, die Produktion, das Lager, die Rechnungen und die Auswertungen greifen auf dieselbe strukturierte Datenbasis zu.

Die wichtigsten Arbeitsentscheidungen und Empfehlungen sind:

1. **WordPress und WooCommerce sollen die Zielplattform werden.**  
   WooCommerce liefert Produktkatalog, Varianten, Warenkorb, Checkout, Kunden, Bestellungen, Gutscheine, Zahlungsverknüpfungen und grundlegende Lager-/Shopfunktionen. Dadurch muss nicht der komplette Handelsteil neu entwickelt werden.

2. **Der Hostinger Website Builder wird nicht verwendet.**  
   Er ist für einen einfachen Standardshop leicht zu bedienen, aber für den eigenen visuellen Konfigurator, die Produktionssteuerung, die Kostenrechnung und einen späteren Plattformwechsel zu eingeschränkt. Gemeint ist ausdrücklich eine selbst gehostete WordPress-Installation mit WooCommerce auf Hostinger.

3. **Spezialfunktionen kommen in ein eigenes PHP-Plugin.**  
   Das Plugin **TPB Production Suite** ergänzt Konfigurator, Preis-Engine, Proof-Freigaben, Produktionsjobs, Material-/Zeiterfassung, Gewinn, Rechnungsarchiv, Etiketten und Auditlog. Gestaltung und Seitentemplates liegen getrennt im **TPB Theme**. Geschäftslogik darf nicht in das Theme oder in lose PHP-Dateien verteilt werden.

4. **MySQL wird die zentrale Geschäftsdatenbank.**  
   WordPress und WooCommerce verwalten ihre Kerndaten; zusätzliche strukturierte Printshop-Daten liegen in sauber versionierten TPB-Tabellen derselben Datenbank. „Globale Datenbank“ bedeutet eine zentrale Quelle der Wahrheit, nicht eine einzige riesige Tabelle und keine parallelen Excel-Schattenbestände.

5. **Sensible Dateien werden privat gespeichert.**  
   Öffentliche Produktbilder dürfen in die WordPress-Mediathek. Logos, Druckdateien, Korrekturabzüge, Rechnungs-PDFs und Belege benötigen dagegen einen geschützten Speicher außerhalb des öffentlich abrufbaren Webverzeichnisses beziehungsweise einen autorisierten Download-Endpunkt. Ein späterer Wechsel zu Objektspeicher bleibt möglich.

6. **Jeder Preis wird auf dem Server nachgerechnet.**  
   Der Browser zeigt eine Vorschau, ist aber niemals die verbindliche Quelle für einen Rechnungsbetrag.

7. **Angebote und Aufträge erhalten unveränderliche Snapshots.**  
   Ändert ihr morgen Rohlingkosten oder Preise, bleiben gestern freigegebene Angebote und Rechnungen unverändert nachvollziehbar.

8. **Rechnungen werden in drei Formen archiviert.**  
   Strukturierte Daten in MySQL, ein kanonischer JSON-Snapshot und ein finalisiertes, hashgesichertes PDF im privaten Archiv. UBL/Peppol kann später für öffentliche Auftraggeber ergänzt werden.

9. **Ein Auftrag hat mehrere getrennte Status.**  
   Angebot, Grafikfreigabe, Zahlung, Beschaffung, Produktion, Versand und Rechnung dürfen nicht in einem einzigen Feld „in Bearbeitung“ vermischt werden.

10. **Etikettendruck läuft über eine Druckwarteschlange.**  
   Im MVP werden Etiketten als PDF erzeugt. Für automatischen Druck wird später ein lokaler Print-Agent oder QZ Tray auf dem Produktions-PC eingesetzt.

11. **Alles wird zuerst lokal entwickelt und getestet.**  
    Danach folgt eine getrennte WordPress-Staging-Seite auf Hostinger. Erst nach Abnahme wird ausschließlich der geprüfte Code veröffentlicht. Live-Bestellungen, Live-Datenbank und Uploads werden bei einem Code-Update niemals durch Staging-Daten überschrieben.

12. **Die bisherige Next.js-Seite bleibt bis zur Abnahme als Prototyp erhalten.**  
    Farben, Inhalte, Nutzerführung, Produktbilder und Konfiguratorverhalten dienen als Vorlage. Die neue WordPress-Version wird parallel lokal aufgebaut; die bestehende Website wird erst nach erfolgreichem Test und kontrollierter Domain-Umschaltung abgelöst.

13. **Das Backend trackt das Geschäft, ersetzt aber nicht automatisch die offizielle Buchhaltung.**  
    Es erzeugt saubere Exporte und Belege für Buchhalter oder Steuerberater. Ob zusätzliche zertifizierte Buchhaltungssoftware nötig ist, wird mit der luxemburgischen Buchhaltung abgestimmt.

## 1.1 Technologieempfehlung vom 13. August 2026

Die Entscheidung berücksichtigt drei Punkte: Das Hosting soll bei Hostinger bleiben, der Betreiber kennt bisher vor allem reines PHP, und der Shop braucht neben Standardhandel einen ungewöhnlich tiefen Produkt- und Produktionskonfigurator.

| Variante | Vorteil | Nachteil | Entscheidung |
|---|---|---|---|
| Hostinger Website Builder | schnellster einfacher Standardshop | eingeschränkte Erweiterbarkeit und unvollständiger Export von Shop, Layout, Integrationen, Formularen und SEO-Einstellungen | nicht verwenden |
| bestehendes Next.js/Cloudflare-System | vorhandener visueller Prototyp | anderer Stack, eigenes Shop-/Backend müsste fast vollständig gebaut werden | nur als Referenz bis zur Migration behalten |
| vollständig eigenes Laravel-System | saubere PHP-Architektur und maximale Kontrolle | Katalog, Checkout, Kunden- und Bestellfunktionen müssten selbst entwickelt und dauerhaft gewartet werden | belastbare Ausweichoption, nicht Primärweg |
| WordPress + WooCommerce + eigenes Plugin | fertiger Shopkern, PHP-nah und stark erweiterbar | Pluginpflege, Updateprüfung und spezielle TPB-Funktionen bleiben eigene Verantwortung | **aktuelle Hauptempfehlung** |

Hostinger Business könnte alternativ auch eine verwaltete Node.js-/Next.js-Anwendung betreiben. Der bestehende Prototyp müsste dafür dennoch von Vinext/Cloudflare Workers, D1 und der bisherigen Anmeldung auf eine Hostinger-taugliche Next.js-Ausgabe, MySQL, eigene Authentifizierung und dauerhaften Dateispeicher umgestellt werden. Diese Route ist technisch möglich, spart für einen Shop aber weniger Arbeit als WooCommerce und passt schlechter zu den vorhandenen PHP-Kenntnissen.

Laravel 12 mit PHP 8.3/8.4, MySQL, Blade und wenig JavaScript bleibt eine sinnvolle Alternative, falls WooCommerce später nachweislich eine fachliche Grenze erreicht. Laravel wird nicht parallel als zweites Backend aufgebaut. Eine Neubewertung erfolgt nur anhand dokumentierter Probleme, etwa dauerhaft konfliktträchtiger Erweiterungen, unzureichender Queue-Möglichkeiten, mehrerer Produktionsstandorte oder einer primär API-getriebenen Plattform. Unstrukturiertes „Full PHP“ ohne Framework oder Plugin-Architektur wird wegen Authentifizierung, Rollen, Datenmigrationen, Dateischutz und langfristiger Wartung nicht empfohlen.

Für den Konfigurator bleibt JavaScript im Browser notwendig: Logo hochladen, verschieben, skalieren sowie Vorder-/Rückseite umschalten kann kein serverseitiges PHP allein flüssig darstellen. Der Server bleibt trotzdem PHP-basiert und prüft jede Konfiguration und jeden Preis erneut.

WooCommerce kann grundsätzlich auch auf einem kleineren WordPress-fähigen Hostinger-Paket laufen. Für den produktiven TPB-Shop wird Business empfohlen – nicht wegen Next.js oder Node.js, sondern wegen WordPress-Staging, täglichen Backups und mehr Reserven für WooCommerce, Bild-/PDF-Verarbeitung und Adminaufgaben. Die lokal entwickelte Seite kann bis zur Kaufentscheidung ohne Hostinger-Upgrade aufgebaut werden.

## 1.2 Was das fertige System beantworten muss

Das Backend muss jederzeit verlässlich beantworten können:

- Was hat ein Kunde konfiguriert, freigegeben, bestellt und bezahlt?
- Welche Grafikversion und welche Position wurden tatsächlich produziert?
- Welche Rohlinge und Materialien sind vorhanden, reserviert, bestellt oder verbraucht?
- Welche Aufträge sind blockiert und warum?
- Wie viel Arbeitszeit und Ausschuss sind je Auftrag und Position entstanden?
- Welche Rechnungen sind offen, teilweise bezahlt, überfällig oder gutgeschrieben?
- Wie hoch waren geplanter und tatsächlicher Deckungsbeitrag pro Artikel, Auftrag, Kunde und Monat?
- Welche Dateien und Rechtstextversionen galten bei einer Bestellung?
- Wer hat welchen Status, Preis, Nachlass oder Beleg wann geändert?
- Welches Etikett wurde mit welcher Vorlage auf welchem Drucker gedruckt?

## 1.3 Was nicht im ersten MVP gebaut werden sollte

- keine fotorealistische 3D-Simulation mit Garantie auf exakte Druckwirkung;
- keine vollwertige Finanzbuchhaltung mit Jahresabschluss;
- keine eigene Kreditkartenverarbeitung;
- keine industrielle automatische Etiketten-Applikationsmaschine;
- keine komplette Peppol-Sendeplattform;
- kein kompliziertes Microservice-System;
- keine künstliche Intelligenz, bevor Bestellungen und Daten verlässlich funktionieren.


## 1.4 Gegenprüfung der Plattformwahl: strukturierter Eigenbau im vorhandenen Stack („TPB Pure“)

*Ergänzt in Version 1.2. Dieser Abschnitt ist bewusst ein Gegengewicht zur Hauptempfehlung aus 1.1 und liefert die Grundlage für die in Kapitel 17 geforderte ausdrückliche Bestätigung der Plattformwahl vor dem Code-Umbau.*

Die Vergleichstabelle in 1.1 stellt WooCommerce nur dem Hostinger Builder, dem bestehenden Next.js-Prototyp und Laravel gegenüber. Eine vierte realistische Variante fehlt: ein strukturierter, framework-freier PHP-/MySQL-Eigenbau nach dem Muster der bereits erfolgreich betriebenen internen Projekte – klare Verzeichnis- und Modulstruktur, zentraler Router, eigenes Auth-/Rollenmodul, ausschließlich Prepared Statements, versionierte SQL-Migrationen, `.env`-Konfiguration, kein Build-Step. Die Ablehnung von „unstrukturiertem Full PHP“ in 1.1 trifft diese Variante nicht: Strukturiert bedeutet hier, dieselben Architekturprinzipien aus 3.2 umzusetzen, nur ohne WordPress darunter.

Drei Beobachtungen sprechen dafür, diese Variante ernsthaft zu prüfen:

1. **Der empfohlene MVP (Pfad A) nutzt WooCommerce kaum.** Der risikoärmste Start ist laut 7.2 und 17.1 der Angebotsweg: Konfiguration → Richtpreis → Angebotsanfrage → Angebot → Annahme → Anzahlung → Proof → Produktion → Rechnung. In genau diesem Ablauf kommen Warenkorb, Checkout, Gutscheine und Zahlungs-Plugins – also die eigentlichen Stärken von WooCommerce – gar nicht oder nur am Rand vor. Der gesamte fachliche Kern (Konfigurator, Preis-Engine, Proofs, Produktion, Lager, Rechnungen, Etiketten, Audit) ist ohnehin in beiden Varianten Eigenentwicklung.

2. **Unter Art. 57bis entfällt die Steuerkomplexität.** Solange die Kleinunternehmerregelung gilt, gibt es keine Steuersätze, keine Steuerberechnung im Checkout und keine grenzüberschreitende Steuerlogik. Ein wesentlicher Grund für einen fertigen Shopkern greift damit im MVP-Zeitraum nicht.

3. **WooCommerce erzeugt eigene Dauerkomplexität.** Ein erheblicher Teil dieses Dokuments – HPOS-/CRUD-Regeln, Checkout Blocks, Plugin-Freigabeblätter, Update-Governance, Cookie-Inventar nach jedem Plugin-Update – existiert nur, weil WordPress darunter liegt. Diese Arbeit fällt nicht einmalig an, sondern dauerhaft bei jedem Core-, Theme- und Plugin-Update, unabhängig vom Geschäftsvolumen.

| Kriterium | WooCommerce + TPB-Plugin | TPB Pure (strukturierter Eigenbau) |
|---|---|---|
| Shopkern: Warenkorb, Checkout, Gutscheine | fertig vorhanden | Eigenbau – im Pfad-A-MVP aber kaum benötigt |
| Konfigurator, Preis-Engine, Proof, Produktion, Rechnung, Etiketten, Audit | Eigenbau als Plugin | Eigenbau – Umfang praktisch identisch |
| Lernkurve | WordPress-Plugin-API, Hooks, HPOS, Nonces, Capabilities müssen neu gelernt werden | vorhandene PHP-/MySQL-Kenntnisse sofort produktiv |
| Laufende Wartung | Core-, Theme- und Fremdplugin-Updates dauerhaft auf Staging testen | nur eigener Code und PHP-Version |
| Angriffsfläche | WordPress ist Standardziel automatisierter Angriffe; Härtung nach 13.1 zwingend | kleine, unbekannte Oberfläche; Sorgfalt nach 13.1 trotzdem zwingend |
| Zahlungen (Pfad B) | fertige Gateway-Plugins | gehostete Zahlungsseite über offizielles PHP-SDK; überschaubar, aber eigene Verantwortung inklusive Webhooks |
| Kundenkonto | von WordPress geliefert | Statuslink genügt im MVP (4.3); Konto später Eigenbau |
| Zeit bis Pfad B (Direktkauf) | kürzer | länger, da Warenkorb/Checkout dann nachgebaut werden |
| Abhängigkeiten und Datenhoheit | Fremdplugin- und Update-Abhängigkeiten | volle Datenhoheit; Datenmodell aus Kapitel 10 bleibt plattformneutral migrierbar |

**Entscheidungsregel:**

- Ist der direkte Onlinekauf (Pfad B) innerhalb der ersten 6–12 Monate geschäftlich zwingend **und** sind die Gründer bereit, WordPress-/WooCommerce-Plugin-Entwicklung als dauerhafte Kompetenz zu pflegen, bleibt es bei der Hauptempfehlung aus 1.1.
- Genügt Pfad A absehbar – das realistische Anfangsgeschäft mit Vereinen, Firmen und Sammelbestellungen läuft ohnehin über Angebot und Anzahlung – und zählt Entwicklungsgeschwindigkeit im vertrauten Stack, wird TPB Pure bevorzugt. WooCommerce bleibt dann eine später bewertbare Option, weil Snapshots, IDs, Statusachsen und Dateistruktur (Kapitel 10) plattformneutral entworfen sind.
- Bei Unsicherheit entscheidet ein zeitlich begrenzter Vergleichs-Spike: derselbe Miniaturpfad (Produkt → serverseitiger Preis → gespeicherte Konfiguration → Angebot → Rechnungs-Snapshot) wird einmal als WooCommerce-Plugin und einmal pure umgesetzt, je maximal eine Woche Abendarbeit. Bewertet werden Geschwindigkeit, Verständlichkeit und Debugbarkeit. Das Ergebnis wird in 17.2 dokumentiert.

Unabhängig vom Ausgang gelten in beiden Varianten unverändert: serverseitige Preise, Snapshots, Minor Units, getrennte Statusachsen, privater Dateispeicher, Auditlog, Idempotenz, versionierte Migrationen, Staging-Trennung und Backups. Dieses Dokument bleibt zu weit über 80 % plattformunabhängig gültig; bei einer Entscheidung für TPB Pure sind vor allem 5.6, 6 (linke Tabellenspalte), 10.1/10.2 (Woo-Zuordnungen), 12.8.1 (WordPress-Zeilen) und 14 sinngemäß auf den Eigenbau zu übertragen.

---

# 2. Aktueller Stand der Website

Die bestehende Website wurde technisch analysiert. Es wurde dabei nichts geändert und nichts veröffentlicht.

## 2.1 Bereits vorhanden

| Bereich | Aktueller Stand |
|---|---|
| Technik | Next.js 16, React 19, TypeScript, Vinext/Vite, Cloudflare Workers |
| Datenbank | Cloudflare D1 mit Drizzle-Migrationen |
| Startseite | Landingpage mit Ideen, Ablauf und Anfrage |
| Konfigurator | 6 Produkte, Farben, Menge, bis zu 5 Motive, Vorder-/Rückseite, Positions-Presets, Drag-and-drop und Richtpreis |
| Upload | Logo wird nur lokal im Browser geladen |
| Admin | Preisparameter, Staffelpreise, Techniken, Positionen, Extras und Konditionen |
| API | Öffentlicher Lesezugriff und geschützter Schreibzugriff für Preiskonfiguration |
| Hosting | Sites-/Cloudflare-Konfiguration vorhanden |

## 2.2 Kritische Lücken

| Lücke | Auswirkung |
|---|---|
| D1 speichert nur eine JSON-Preiskonfiguration | Keine Auswertung nach Kunde, Produkt, Auftrag oder Monat |
| Keine Kunden, Angebote oder Aufträge | Der Button „Konfiguration anfragen“ erzeugt keinen Geschäftsvorgang |
| Upload nur im Browser-Arbeitsspeicher | Datei und Gestaltung gehen bei Reload verloren |
| Keine serverseitige Preisfreigabe | Ein Browserwert wäre manipulierbar |
| Keine R2-Dateiablage | Logos, Proofs, PDFs und Belege können nicht sicher archiviert werden |
| Keine Rechnungen, Zahlungen oder Ausgaben | Gewinn und offene Beträge sind nicht nachvollziehbar |
| Keine Varianten/SKUs und Lagerbewegungen | Bestand, Größen, Farben und Einkauf fehlen |
| Keine Grafikversionen und Freigaben | Unklar, welche Datei der Kunde freigegeben hat |
| Keine Produktions- und Zeitdaten | Nur geschätzte, keine tatsächlichen Kosten |
| Keine Rollen und kein belastbares Auditlog | Änderungen sind nicht ausreichend abgesichert |
| Erster schreibender Nutzer kann Admin werden | Das aktuelle Bootstrap-Verhalten muss vor echten Daten entfernt werden |
| Keine Laufzeitvalidierung für Preis-JSON | Fehlerhafte Daten könnten übernommen werden |
| Veraltete Startertests | Die Tests prüfen nicht das aktuelle Produkt |
| Zwei Lockfiles | Ein Package Manager muss verbindlich festgelegt werden |
| Git noch ohne Basis-Commit | Vor Umbauten sollte lokal ein überprüfter Ausgangsstand gesichert werden |

## 2.3 Konsequenz

Der sichtbare Prototyp bleibt eine wertvolle fachliche und gestalterische Vorlage, wird aber nicht direkt zur Produktionsplattform erweitert. Farben, Texte, Mockups, Bedienlogik und Preisregeln werden in das TPB WordPress-Theme und die TPB Production Suite portiert. React-/Next.js-Komponenten können dabei nicht unverändert in WordPress kopiert werden; die relevanten Interaktionen werden als schlankes JavaScript-Modul neu eingebunden.

Die bestehende Seite bleibt unverändert erreichbar, bis die neue WordPress-/WooCommerce-Version lokal und auf Hostinger-Staging den vollständigen Referenzauftrag bestanden hat. Es erfolgt weder ein voreiliges Überschreiben noch ein automatisches Übernehmen der alten D1-Testdaten in die neue MySQL-Produktionsdatenbank.

---

# 3. Zielbild und Systemarchitektur

~~~mermaid
flowchart LR
    K["Kunde: Website, Konfigurator, Konto"] --> WP["WordPress + WooCommerce + TPB Theme"]
    A["Mitarbeiter: WooCommerce-Admin + TPB Backend"] --> PL["TPB Production Suite"]
    WP --> PL
    PL --> DB["MySQL: WooCommerce- und TPB-Fachdaten"]
    PL --> FS["Privater Datei- und Rechnungsbereich"]
    PL --> JOB["Scheduler / Action Queue / Outbox"]
    JOB --> MAIL["E-Mail"]
    JOB --> PAY["Zahlungsanbieter"]
    JOB --> SHIP["Versanddienst"]
    PA["Lokaler Print-Agent"] --> PL
    PA --> PR["Etikettendrucker"]
    SC["USB-QR-/Barcodescanner"] --> A
~~~

## 3.1 Komponenten

### Öffentliche Website

- Produktkatalog und Produktdetails;
- Konfigurator mit echter Speicherung;
- Warenkorb oder Angebotsentwurf;
- Checkout;
- rechtliche Seiten;
- Bestellbestätigung;
- Kundenportal beziehungsweise sicherer Statuslink.

### WordPress und TPB Theme

- Seiten, Navigation, Inhalte und rechtliche Seiten;
- Wiederverwendung der bestehenden Farben und visuellen Richtung;
- schnelle, responsive und barrierearme Templates;
- öffentliche Produkt- und Informationsseiten;
- möglichst wenig Theme-spezifische Geschäftslogik.

### WooCommerce-Kern

- Produkte, Varianten, Farben, Größen und Grundbestand;
- Kundenkonten, Warenkorb und Checkout;
- Bestellungen, Gutscheine, Versand- und Zahlungsanbindung;
- Shop-E-Mails und grundlegende Auswertungen;
- High-Performance Order Storage (HPOS) für neue Bestellungen;
- Zugriff auf Bestellungen ausschließlich über die WooCommerce-CRUD-APIs, nicht durch direkte Schreibzugriffe auf interne Tabellen.

### TPB Production Suite

- validiert alle Eingaben;
- berechnet verbindliche Preise;
- speichert Konfigurationen, Motive und Proof-Versionen;
- prüft Rollen und erlaubte Statusübergänge;
- erzeugt zusätzliche Produktions-, Lager-, Rechnungs- und Auditdaten und erstellt oder ändert WooCommerce-Bestellungen ausschließlich über dessen CRUD-APIs;
- stellt nur kurzlebige, autorisierte Dateilinks bereit;
- verarbeitet Zahlungs- und Versand-Webhooks idempotent;
- erweitert WooCommerce, ohne Kern- oder Fremdplugin-Dateien zu verändern.

### MySQL

- WordPress-/WooCommerce-Kerndaten und relationale TPB-Geschäftsdaten;
- WooCommerce bleibt Eigentümer von Produkt-, Kunden- und Bestelldaten;
- TPB-Tabellen speichern nur zusätzliche Fachinformationen und referenzieren WooCommerce-IDs;
- TPB-Kalkulationswerte intern deterministisch in Minor Units; Übergabe an WooCommerce über dessen Währungs-/Decimal-APIs;
- Zeitpunkte in UTC, Anzeige in Europe/Luxembourg;
- unveränderliche Ereignisse für Audit und Statushistorie;
- keine großen Dateien.

### Privater Dateispeicher

- Original-Logos und bereinigte Produktionsdateien;
- Korrekturabzüge;
- Rechnungen, Gutschriften und JSON-Snapshots;
- Ausgabenbelege und Lieferantenrechnungen;
- gerenderte Etiketten;
- Exporte und Backups;
- keine sensiblen Dateien als frei erratbare WordPress-Media-URL.

### Hintergrundverarbeitung

Ein Vorgang wie „Rechnung ausstellen“ soll nicht direkt fünf externe Aktionen unkontrolliert ausführen. Zuerst wird der Geschäftsvorgang atomar in MySQL gespeichert. Eine Outbox beziehungsweise WooCommerce Action Scheduler verarbeitet danach E-Mail, PDF, Webhook, Export oder Druckjob erneut versuchbar und nachvollziehbar. Auf Hostinger wird ein echter Cronjob verwendet, damit zeitkritische Aufgaben nicht allein von Seitenaufrufen abhängen. Dauerhaft laufende Hintergrundprozesse werden auf Shared Hosting nicht vorausgesetzt.

### Lokaler Print-Agent

Der Print-Agent läuft auf dem Produktions-PC und fragt ausgehend nach freigegebenen Druckjobs. Dadurch muss der Cloudserver nicht auf einen Drucker im privaten Netzwerk zugreifen.

## 3.2 Architekturprinzipien

1. **Eine Quelle der Wahrheit:** keine parallelen Excel-Listen als zweite Bestands- oder Rechnungsquelle.
2. **Snapshots statt rückwirkender Veränderung:** Produktname, Preis, Kostenannahmen, Steuerstatus und Rechtstexte werden bei Annahme eingefroren.
3. **Ereignisse statt stiller Überschreibung:** Status, Lager und Druck werden durch nachvollziehbare Ereignisse geändert.
4. **Entwurf und Finalisierung trennen:** Entwürfe sind editierbar, finalisierte Rechnungen und Freigaben nicht.
5. **Berechtigung auf dem Server:** eine versteckte Schaltfläche ist keine Zugriffskontrolle.
6. **Datenschutz durch Minimierung:** QR-Codes, Logs und Etiketten enthalten nur notwendige Daten.
7. **Lokale Reproduzierbarkeit:** Entwickler können den kompletten Beispielprozess ohne Produktionsdaten ausführen.
8. **Austauschbare Integrationen:** Zahlung, Versand und Drucker werden über Adapter angebunden.
9. **WooCommerce respektieren:** keine Änderungen an WordPress-/WooCommerce-Kerndateien, keine direkten Schreibzugriffe auf HPOS-Tabellen und keine doppelte Bestellwahrheit.
10. **Plugin-Minimalismus:** nur notwendige, gepflegte und kompatible Plugins installieren; jede Erweiterung wird auf Staging auf Sicherheit, HPOS, Checkout und Konflikte geprüft.

---

# 4. Öffentliche Website und Kundenportal

## 4.1 Empfohlene Seitenstruktur

| Seite | Hauptfunktion | Was gespeichert wird |
|---|---|---|
| Startseite | Nutzen, Zielgruppen, Beispiele, Vertrauen | nur consent-konforme anonyme Messwerte |
| Produkte | visuelle Produktkarten, Filter, „ab“-Preis | Produkt-/Variantenaufrufe |
| Produktdetail | Farben, Größen, Material, Pflege, Lieferzeit, Sicherheit | ausgewählte Variante |
| Konfigurator | Produkt, Farbe, Größenmatrix, Logos, Positionen, Technik | Konfigurationsentwurf und Preisversion |
| Warenkorb/Anfrage | mehrere konfigurierte Positionen bündeln | Angebotsentwurf |
| Checkout | Kunde, Adresse, Lieferung, Zahlung, Einwilligungen | Bestellung und Rechtstextversionen |
| Bestätigung | Nummer, Preis, nächste Schritte | Zustell-/Darstellungsereignis |
| Sicherer Statuslink | Status ohne vollständiges Konto | Zugriff über zufälligen, widerrufbaren Token |
| Kundenkonto | Bestellungen, Proofs, Rechnungen, Wiederbestellung | Konto- und Zugriffshistorie |
| Proof-Freigabe | Korrekturabzug bestätigen oder Änderung verlangen | exakte Version, Zeit, Erklärung, authentifizierter Actor/Token; IP nur risikobasiert |
| Kontakt/Angebot | strukturierte Anfrage statt mailto | Lead und Anhänge |
| FAQ | Dateien, Farben, Größen, Pflege, Lieferzeiten | Inhalt aus Backend |
| Rechtliche Seiten | Impressum, Datenschutz, Cookies, AGB usw. | veröffentlichte Version |
| Reklamation | Bestellnummer, Fehlerart, Bilder | Fall, Kommunikation, Lösung |

## 4.2 Produktdarstellung

Produkte dürfen nicht nur in einem Dropdown erscheinen. Jedes Produkt erhält:

- echtes Produktfoto oder konsistentes Mockup;
- Vorder- und Rückansicht;
- Farbchips mit verständlichem Farbnamen;
- Größen und Größentabelle;
- Stoffzusammensetzung, Pflege- und Sicherheitshinweise;
- Hersteller-/Verantwortlichenangaben, soweit rechtlich erforderlich;
- verfügbare Drucktechniken und Positionen;
- realistische Lieferzeit;
- Preis „ab …“ mit klarer Berechnungsgrundlage;
- Hinweis, ob Bestand geführt oder erst beschafft wird.

## 4.3 Kundenkonto und Gastbestellung

Für den Start ist eine Gastbestellung sinnvoll, damit kein Konto erzwungen wird. Nach der Bestellung erhält der Kunde:

- einen ausreichend zufälligen, widerrufbaren Statuslink;
- eine Bestätigung per E-Mail;
- Zugang zum Proof;
- Download freigegebener Dokumente und Rechnungen;
- optional die Möglichkeit, ein Konto zu aktivieren.

Der Statuslink darf keine fortlaufende Auftragsnummer als einzigen Schutz verwenden. Sensible Änderungen wie Adresse, Freigabe oder Zahlung erfordern erneute E-Mail-Verifikation oder Login.

## 4.4 Mehrsprachigkeit

Luxemburg spricht mehrere Sprachen. Die Architektur sollte Inhalte und Rechtstexte mindestens sprachversionierbar machen. Ein praktikabler Start:

1. Deutsch vollständig;
2. Französisch als nächste öffentliche Sprache;
3. Englisch optional;
4. Luxemburgisch für ausgewählte Marketinginhalte.

Rechtstexte dürfen nicht automatisiert „irgendwie“ übersetzt und ungeprüft veröffentlicht werden. Jede Sprache hat eine eigene geprüfte Version und ein Gültigkeitsdatum.

## 4.5 Barrierefreiheit

Auch wenn eine mögliche Ausnahme für Kleinstunternehmen zu prüfen ist, sollte die Website auf WCAG 2.2 AA ausgelegt werden:

- vollständige Tastaturbedienung;
- sichtbarer Fokus;
- ausreichend starke Kontraste;
- Alternativtexte;
- verständliche Formularfehler;
- keine Information nur über Farbe;
- sinnvolle Überschriften;
- Zoom und mobile Nutzung;
- Drag-and-drop immer mit Schaltflächen-/Tastaturalternative;
- Konfiguratorposition zusätzlich numerisch oder über Presets wählbar.

Die europäische Barrierefreiheitsrichtlinie erfasst seit dem 28. Juni 2025 unter anderem E-Commerce-Dienste; für Kleinstunternehmen im Dienstleistungsbereich bestehen mögliche Ausnahmen, deren konkrete Anwendung in Luxemburg vor Start geprüft werden muss. Unabhängig davon verbessert Barrierefreiheit Conversion und Bedienbarkeit. Siehe [Europäische Kommission zum European Accessibility Act](https://commission.europa.eu/strategy-and-policy/policies/justice-and-fundamental-rights/disability/european-accessibility-act-eaa_en) und [Guichet.lu zur Umsetzung ab 28. Juni 2025](https://guichet.public.lu/en/citoyens/actualites/2025/juillet/04-accessibilite-produits-services.html).

## 4.6 Auffindbarkeit, Leistung und Messung

### SEO-Grundfunktionen

- bearbeitbarer Seitentitel und Beschreibung je Sprache;
- sprechende, stabile URLs;
- Canonical- und hreflang-Angaben;
- XML-Sitemap und kontrollierte robots-Regeln;
- Open-Graph-/Social-Vorschaubilder;
- strukturierte Daten für Organization, Product, Offer und Breadcrumb, nur mit tatsächlich sichtbaren Angaben;
- Alt-Texte und Bildoptimierung;
- Weiterleitungen bei geänderten Produkt-URLs;
- keine Indexierung von Warenkorb, Statuslink, Kundenportal oder Admin.

### Performance

- optimierte Produktbilder in passenden Größen;
- Vorschau-Lazy-Loading;
- Upload nicht über den Hauptseitenprozess blockieren;
- mobile Ladezeit und Core Web Vitals messen;
- Fehler- und Verfügbarkeitsmonitoring ohne unnötige personenbezogene Daten.

### Funnel-Ereignisse

Datensparsame Ereignisse können lauten:

~~~text
product_viewed
configurator_started
product_variant_selected
artwork_upload_started / completed / failed
configuration_saved
quote_requested
checkout_started
order_completed
proof_sent
proof_approved / changes_requested
reorder_started
~~~

Keine E-Mail, Adresse, Namensliste oder Logo-URL gehört in Analytics-Payloads. Operative Kennzahlen wie angenommene Angebote oder Umsatz kommen aus der Geschäftsdatenbank. Optionale Marketinganalyse wird erst nach Einwilligung geladen; technische Betriebsmetriken und consent-freie Messung werden rechtlich und technisch getrennt.

---

# 5. Konfigurator und Preisberechnung

## 5.1 Empfohlener vereinfachter Kundenablauf

Der Endkunde sollte trotz vieler Möglichkeiten nur fünf Hauptschritte sehen:

1. **Produkt wählen** – visuelle Karten statt Dropdown.
2. **Farbe, Größen und Mengen wählen** – Größenmatrix für Sammelbestellungen.
3. **Gestaltung hinzufügen** – Logo/Text, Position, Vorder-/Rückseite.
4. **Optionen prüfen** – Technik, Individualisierung, Express.
5. **Preis und Bestellung** – Zusammenfassung, Lieferzeit, speichern oder bestellen.

Fortgeschrittene Einstellungen bleiben unter „Position fein anpassen“ verborgen. Presets wie „Brust links“, „zentriert groß“, „Nacken“ und „Ärmel“ sind der Standard; Drag-and-drop und Skalierung sind eine zusätzliche Option.

## 5.2 Mehrere Logos und individualisierte Stücke

Das Datenmodell muss mehrere Gestaltungsebenen unterstützen:

- mehrere Logos auf demselben Produkt;
- Vorder- und Rückseite;
- unterschiedliche Positionen;
- mehrere Farben/Lagen;
- Text zusätzlich zum Logo;
- Name und Nummer pro Einzelstück;
- Sponsor, Vereinslogo und Nackenname gleichzeitig;
- eine Größen-/Farbmatrix innerhalb derselben Bestellung.

Bei individueller Personalisierung erhält jedes Stück einen eigenen Unterdatensatz. Eine CSV-Vorlage kann später diese Spalten importieren:

~~~text
position_no,size,color,name,number,role,notes
1,M,navy,Laura,7,Trainer,
2,L,navy,Tom,18,Spieler,
~~~

Der Import muss Zeile für Zeile validiert und vor Auftragserteilung als Vorschau gezeigt werden.

## 5.3 Realistische Vorschau

Eine Online-Vorschau ist ein Planungsbild und kein verbindlicher Farbproof. Damit sie trotzdem glaubwürdig aussieht:

- pro Produkt echte, sauber freigestellte Vorder-/Rückansichten;
- definierte Druckflächen je Produkt, Seite und Größe;
- Logo-Clipping innerhalb des Textilbereichs;
- realistische perspektivische Begrenzung, aber kein falscher 3D-Anspruch;
- Mindest- und Maximalgröße je Technik;
- Warnung bei zu geringer Auflösung;
- Farbüberlagerung nur, wenn das Produktbild dafür vorbereitet ist;
- Positionen in realen Maßen speichern, nicht nur Browserpixel;
- mobile und Desktop-Ansicht aus demselben Designmodell rendern.

Vor Produktion wird immer ein separater Korrekturabzug aus der endgültigen Datei und den Produktionsmaßen erstellt. Die Kundenfreigabe bezieht sich auf genau diese Version.

## 5.4 Datei-Upload und Preflight

### Akzeptierte MVP-Formate

- SVG für Vektorgrafiken;
- PDF für druckfertige/Vektor-Inhalte;
- PNG mit Transparenz;
- JPG nur mit deutlicher Qualitätswarnung.

PSD, AI und andere komplexe Quelldateien können zunächst als „manuelle Prüfung erforderlich“ behandelt werden.

### Serverseitige Prüfungen

- erlaubte Endung und tatsächlicher MIME-/Dateityp;
- maximale Datei- und Pixelgröße;
- Malware-Scan;
- Bildabmessungen und effektive DPI bei gewählter Druckgröße;
- Transparenz und Farbinformation als Hinweis;
- Hash zur Erkennung identischer Uploads;
- sichere Dateinamen; Originalname nur als Metadatum;
- keine direkte öffentliche URL für Kundenlogos oder Produktionsdateien; Download nur über einen autorisierten TPB-Endpunkt beziehungsweise ein kurzlebiges zugriffsgeschütztes Token;
- Quarantäne bis Prüfung abgeschlossen ist;
- automatische Miniaturansicht getrennt vom Original.

### Rechtliche Erklärungen

Vor Absenden muss der Kunde bestätigen:

- dass er die erforderlichen Rechte an Logo, Namen, Bild und Text besitzt;
- dass The Printing Brothers die Datei ausschließlich zur Anfrage, Produktion, Dokumentation und gegebenenfalls vereinbarten Wiederbestellung verarbeiten darf;
- dass verbotene, rechtsverletzende oder technisch ungeeignete Inhalte abgelehnt werden können.

Eine Erklärung schützt nicht automatisch vor allen Ansprüchen. Das Backend braucht zusätzlich einen dokumentierten Prüf-, Sperr- und Löschprozess.

## 5.5 Verbindliche serverseitige Preis-Engine

Die bisherige Formel ist ein guter Ausgangspunkt, benötigt aber eine klare Trennung zwischen Kosten, Aufschlag und Zielmarge.

### Empfohlene Grundkalkulation

~~~text
Direktmaterial je Stück
= Rohling
+ Folie/Veredelungsmaterial
+ Verpackung
+ Fremdleistungen

Arbeitskosten je Stück
= ((Rüstzeit je Los / Losmenge) + Stückzeit je Stück)
   / 60
   × interner Vollkostensatz pro Stunde

Maschinenkosten je Stück
= Maschinenzeit / 60
   × Maschinen-Vollkostensatz

Erwartete Ausschusskosten
= (Direktmaterial + Arbeitskosten + Maschinenkosten) × Ausschussquote

Selbstkosten je Stück
= Direktmaterial
+ Arbeitskosten
+ Maschinenkosten
+ erwartete Ausschusskosten
+ direkt zurechenbare Zahlungs-/Versandkosten

Mindestverkaufspreis bei Zielmarge
= Selbstkosten / (1 - Zielmarge)
~~~

Wichtig: **Aufschlag und Marge sind nicht dasselbe.** 50 % Aufschlag auf Kosten entsprechen nur 33,33 % Marge am Verkaufspreis. Wenn ihr eine echte Zielmarge pflegen wollt, ist die Division durch 1 minus Zielmarge die sauberere Formel.

### Preisbestandteile

- Basisprodukt/Variante;
- Menge und Staffel;
- Technik;
- Anzahl Motive;
- Anzahl Positionen;
- zusätzliche Farben/Lagen;
- individuelle Namen/Nummern;
- Vektorisierung/Dateiaufbereitung;
- einmalige Einrichtungsgebühr pro Motiv;
- Expresszuschlag;
- Versand;
- Rabatt/Gutschein;
- Mindestbestellwert;
- Rundungsregel;
- Steuerstatus.

### Empfohlene Regel für eure bisherigen Staffelpreise

Die bestehende Preisliste kann als veröffentlichter Verkaufspreis weiterbestehen. Die Engine berechnet parallel die interne Untergrenze:

~~~text
veröffentlichter Stückpreis = Preisbuch/Staffel + Zuschläge
interne Preisuntergrenze     = Selbstkosten / (1 - Zielmarge)
effektiver Angebotspreis     = höherer Wert oder genehmigter Override
~~~

Ein Preis unter der Untergrenze erfordert eine berechtigte Person, einen Grund und einen Audit-Eintrag. So bleiben Marktpreise möglich, ohne versehentlich Verlustaufträge anzunehmen.

### Technische Regeln

- die TPB-Preis-Engine rechnet intern deterministisch in Cent/Minor Units; Übergaben an WooCommerce erfolgen über dessen Währungs-/Decimal-APIs;
- Prozente als Basispunkte, zum Beispiel 2500 = 25,00 %;
- Preisregeln mit gültig-ab/gültig-bis;
- jedes Angebot referenziert eine Preisbuchversion;
- bei Annahme wird der vollständige Preis-/Kosten-Snapshot gespeichert;
- Rabatt, Rundung und Mindestwert werden zentral angewendet;
- die UI erhält eine nachvollziehbare Aufschlüsselung;
- das Backend berechnet bei Checkout erneut;
- keine Rechnung übernimmt einen vom Browser gesendeten Gesamtbetrag.

## 5.6 Integration in WooCommerce

WooCommerce übernimmt Warenkorb, Gutscheine, Versand, Steuern, Checkout, Zahlung und Bestellung. Der TPB-Konfigurator bleibt für die druckspezifische Auswahl verantwortlich. Die Grenze wird so umgesetzt:

1. Der Konfigurator speichert serverseitig einen Entwurf und erhält eine zufällige `configuration_id`.
2. Im WooCommerce-Warenkorb stehen nur diese Referenz und eine kundenlesbare Zusammenfassung; vollständige Layer-, Positions- und Dateidaten bleiben in den geschützten TPB-Datensätzen.
3. Beim Hinzufügen zum Warenkorb, bei jeder Neuberechnung und nochmals beim Checkout lädt der TPB-Preisservice den Entwurf und berechnet den Preis neu.
4. Ein fremder oder abgelaufener Entwurf, eine unzulässige Position oder eine fehlende Datei blockiert den Checkout mit verständlicher Fehlermeldung.
5. Beim Erstellen der WooCommerce-Bestellposition werden Produkt-, Konfigurations-, Preis-, Kosten- und Rechtstextversion als Snapshot eingefroren.
6. WooCommerce berechnet abschließend Gutschein, Versand, Steuer und Checkout-Rundung. Der TPB-Preisservice darf diese Summen nicht mit einer parallelen eigenen Checkout-Gesamtsumme überschreiben.

Relevante WooCommerce-Integrationspunkte sind insbesondere Warenkorbvalidierung, Cart-Item-Daten, Preisneuberechnung und das Erzeugen der Bestellposition. Die konkrete Implementierung muss sowohl Wiederherstellung einer Sitzung als auch Doppelklick, Warenkorbänderung und abgelaufene Preisversion sicher behandeln.

Für das MVP wird der klassische WooCommerce-Checkout verwendet, sofern die TPB-Integration mit den Checkout Blocks nicht vollständig über die Store API getestet ist. Blocks werden erst aktiviert, wenn Warenkorb, Upload, Preis, Fehlerausgabe, Zahlung und Bestellsnapshot nachweislich kompatibel sind.

WooCommerce Product Add-Ons kann einfache Zusatzfelder, Aufpreise und Datei-Uploads liefern. Ein fertiger Product-Designer kann Vorder-/Rückansichten bereitstellen. Beide werden jedoch nicht ungeprüft zur Kernabhängigkeit: Mehrere frei verschiebbare Logos, positionsabhängige Druckflächen, Mengenstaffeln, individuelle Namen/Nummern, Proof und Produktionsdaten benötigen weiterhin die eigene TPB-Logik. Ein Fremdplugin wird nur übernommen, wenn Datenexport, privater Upload, HPOS-/Checkout-Kompatibilität, Lizenz, Updatepolitik und Datenschutz überzeugen.

---

# 6. Admin-Backend

Das Backend besteht aus zwei miteinander verbundenen Bereichen. Standardfunktionen werden nicht unnötig nachgebaut:

| WooCommerce nativ verwenden | In der TPB Production Suite ergänzen |
|---|---|
| Produkte und Varianten | Konfigurator und Druckflächen |
| Kunden und Bestellungen | Preisbücher, Kostenuntergrenze und Overrides |
| Gutscheine | Artwork, Preflight und Proof |
| Warenkorb und Checkout | Produktion, Zeit, Ausschuss und QC |
| Zahlungen, Refunds und Versandgrundfunktionen | Material, Lots und erweiterte Reservierungen |
| Basisbestand und Shop-E-Mails | Rechnungsarchiv, Ausgaben, Etiketten, Controlling und Audit |

Die TPB-Seiten werden sauber in die WordPress-/WooCommerce-Navigation eingebunden. Es entsteht kein zweites, widersprüchliches Kunden- oder Bestellbackend.

## 6.1 Hauptnavigation

| Modul | Wichtigste Funktionen |
|---|---|
| Dashboard | Umsatz, Geldeingang, offene Rechnungen, geplante/tatsächliche Marge, fällige Jobs, Lagerwarnungen |
| Anfragen | Leads, unvollständige Konfigurationen, Rückfragen, Angebotskonvertierung |
| Kunden | Privat/Firma, Kontakte, Adressen, Steuer-/Rechnungsdaten, Einwilligungen, Historie |
| Produkte | Produkte, Varianten, Farben, Größen, Bilder, Texte, Hersteller, Pflege/Sicherheit |
| Preise | Preisbücher, Staffeln, Kosten, Zeitwerte, Zuschläge, Mindestmarge, Veröffentlichungen |
| Designs | Uploads, Versionen, technische Prüfung, Rechteerklärung, Sperren |
| Proofs | Korrekturabzüge, Änderungswünsche, Freigaben, Versionen |
| Angebote | Erstellung, Ablaufdatum, PDF, Versand, Annahme/Ablehnung |
| Aufträge | Positionen, Status, Termine, Kundendaten-Snapshot, Verlauf |
| Produktion | Board, Jobs, Schritte, Mitarbeiter, Zeiten, Ausschuss, Nacharbeit |
| Betriebsmittel | Cricut/Plotter, Pressen, Wartung, Kalibrierung, Störung, Maschinenkostensatz |
| Scan | mobilfreundliche Ansicht für Job-, Stück- und Lagercodes |
| Lager | Varianten, Materialien, Bestände, Reservierungen, Bewegungen, Mindestbestand |
| Einkauf | Lieferanten, Bestellungen, Wareneingang, Einkaufspreise, Lieferantenbelege |
| Versand | Pakete, Gewichte, Carrier, Labels, Tracking, Abholung |
| Rechnungen | Entwurf, Finalisierung, PDF/JSON, Versand, Gutschrift, Mahnstatus |
| Zahlungen | Anzahlung, Zahlung, Zuordnung, Rückzahlung, Gebühren |
| Ausgaben | Beleg-Upload, Kategorie, Lieferant, Zuordnung zu Auftrag/Overhead |
| Service | Reklamationen, Rückgaben, Nacharbeit, Gutschrift, Beschwerde-/ADR-Verlauf |
| Etiketten | Vorlagen, Druckerprofile, Warteschlange, Fehler, Neudruck |
| Berichte | Artikel-/Auftragsgewinn, Umsatz, Cashflow, Ausschuss, Zeit, Lager |
| Inhalte | Website-Texte, FAQ, E-Mail-Vorlagen, Banner, Veröffentlichung |
| Rechtliches | Rechtstextentwürfe, Sprachen, Versionen, gültig ab, Annahmen |
| Benutzer | Rollen, Sperrung, Sitzung, MFA-Status |
| Audit | Änderungen, Exporte, Statusereignisse, sicherheitsrelevante Aktionen |
| Einstellungen | Firma, Nummernserien, Steuerstatus, Währung, Zeitzone, Integrationen |

## 6.2 Was dynamisch änderbar sein darf

### Mit Entwurf und Veröffentlichung

- Produkte, Varianten, Bilder und Beschreibungen;
- Farben und Größen;
- Positionen und Techniken;
- Preisbücher und Zuschläge;
- Materialkosten, Zeitwerte, Ausschuss, Zielmarge;
- Lieferzeiten und Mindestbestellwert;
- FAQ und Marketinginhalte;
- E-Mail- und Etikettenvorlagen;
- Rechtstexte und Checkbox-Texte;
- Versand- und Zahlungsoptionen;
- Hersteller-, Textil- und Sicherheitshinweise.

### Nur mit kontrollierter Korrektur

- freigegebene Kunden-Proofs;
- angenommene Angebots-Snapshots;
- ausgestellte Rechnungen;
- verbuchte Zahlungen und Rückzahlungen;
- Lagerbewegungen;
- Arbeitszeit- und Ausschussereignisse;
- Audit- und Druckereignisse.

Diese Datensätze dürfen nicht einfach überschrieben oder gelöscht werden. Fehler werden durch Gegenbuchung, Gutschrift, neue Version oder dokumentierte Korrektur behoben.

## 6.3 Rollen

| Rolle | Typische Rechte |
|---|---|
| Owner | alle Einstellungen, Benutzer, Export, Steuerstatus, Integrationen |
| Admin | Tagesgeschäft und Stammdaten, aber keine Eigentümer-/Sicherheitsübernahme |
| Sales | Kunden, Angebote, Aufträge, Proof-Kommunikation; keine finalen Finanzkorrekturen |
| Production | Produktionsjobs, Scan, Zeit, Material, QC; keine Einkaufspreise/Margen falls nicht nötig |
| Finance | Rechnungen, Zahlungen, Ausgaben, Exporte; keine Produktionsdateiänderung |
| Read-only/Auditor | lesender, protokollierter Zugriff |

Für Preis-Overrides unter Mindestmarge, Gutschriften oberhalb eines festzulegenden Betrags und Änderungen am Steuerstatus ist eine stärkere Berechtigung oder Vier-Augen-Freigabe sinnvoll.

Die Rollen werden technisch als feingranulare WordPress-Capabilities umgesetzt. Beispiele sind `tpb_manage_pricing`, `tpb_view_costs`, `tpb_manage_artwork`, `tpb_manage_production`, `tpb_issue_invoices`, `tpb_manage_legal`, `tpb_reprint_labels` und `tpb_view_audit`. Jeder Adminscreen, REST-Endpunkt, Dateiabruf und Zustandswechsel prüft die passende Capability serverseitig. Ein Menü auszublenden ist keine Zugriffskontrolle.

## 6.4 Dashboard-Kennzahlen

### Verkauf

- Anfragen, versendete und angenommene Angebote;
- Conversion nach Produkt und Kundengruppe;
- Auftragswert und durchschnittlicher Warenkorb;
- Wiederbestellrate.

### Finanzen

- ausgestellter Umsatz;
- eingegangene Zahlungen;
- offene und überfällige Beträge;
- Anzahlungen;
- geplante und tatsächliche Deckungsbeiträge;
- Ausgaben nach Kategorie;
- Steuer-/Schwellenwertwarnung.

### Produktion

- Jobs heute/diese Woche;
- blockiert wegen Datei, Zahlung oder Material;
- Planzeit gegen Istzeit;
- Maschinenzeit, Stillstand und Wartungsbedarf;
- Termintreue;
- Ausschussquote und Nacharbeit;
- Stückzahl je Technik.

### Lager

- verfügbar, reserviert und physisch vorhanden;
- Mindestbestand unterschritten;
- offene Einkaufsbestellungen;
- Lagerwert;
- langsam drehende Varianten.

Umsatz und Geldeingang müssen getrennt angezeigt werden. Eine ausgestellte, unbezahlte Rechnung ist kein eingegangener Cashflow.

---

# 7. Bestell- und Produktionsprozess

## 7.1 Getrennte Statusachsen

WooCommerce behält seinen primären Bestellstatus und die Transaktions-/Refund-Referenzen der angebundenen Zahlungsart. WooCommerce Core besitzt jedoch keine eigenständige Zahlungsachse für Zustände wie teilweise bezahlt oder überfällig. Die kanonische TPB-Zahlungsachse wird deshalb nachvollziehbar aus Gateway-Ereignissen, WooCommerce-Bestellung/Refunds, Zahlungszuordnungen und Rechnungsfälligkeit abgeleitet. Grafik, Proof, Beschaffung, Produktion, Rechnung und Versanddetails bleiben weitere getrennte TPB-Statusachsen. Es werden nicht für jede denkbare Kombination neue WooCommerce-Bestellstatus angelegt. Alle TPB-Statusereignisse referenzieren die WooCommerce-Order-ID und werden idempotent über Woo-Hooks beziehungsweise einen periodischen Abgleich synchronisiert.

Für Vorgänge, die gleichzeitig WooCommerce und mehrere TPB-Tabellen betreffen, wird keine große systemübergreifende Transaktion vorausgesetzt. Zuerst wird ein eindeutiges Ereignis beziehungsweise Outbox-Element gespeichert; wiederholbare Handler führen Folgeschritte aus und ein Abgleich findet verpasste Ereignisse. Dadurch bleiben Doppelklicks, verzögerte Webhooks und kurzzeitige Fehler korrigierbar.

Ein Auftrag besitzt mindestens diese unabhängigen Status:

| Achse | Quelle der Wahrheit | Beispielstatus |
|---|---|---|
| Angebot/kaufmännisch vor Bestellung | TPB Quote | DRAFT, QUOTE_SENT, ACCEPTED, EXPIRED, CANCELLED |
| Bestellung | WooCommerce | PENDING_PAYMENT, PROCESSING, ON_HOLD, COMPLETED, CANCELLED, REFUNDED, FAILED |
| Grafik/Proof | TPB | MISSING, UPLOADED, PREPRESS_REVIEW, PROOF_SENT, CHANGES_REQUESTED, APPROVED, LOCKED |
| Zahlung | TPB-Achse, abgeleitet aus WooCommerce/Gateway-Ereignissen, Refunds, Zuordnungen und Fälligkeit | NOT_DUE, UNPAID, PARTIALLY_PAID, PAID, OVERDUE, REFUNDED |
| Lager/Beschaffung | TPB; Woo-Bestand ist Shop-Projektion | UNALLOCATED, RESERVED, BACKORDERED, ORDERED, PARTIALLY_RECEIVED, RECEIVED, ISSUED |
| Produktion je Job | TPB | BLOCKED, READY, QUEUED, PICKED, CUTTING, WEEDING, PRESSING, FINISHING, QUALITY_CHECK, REWORK, DONE, SCRAPPED |
| Versand/Abholung | Woo-Grunddaten plus TPB-/Carrier-Erweiterung | UNFULFILLED, PACKING, READY_FOR_PICKUP, READY_TO_SHIP, SHIPPED, COLLECTED, DELIVERED, RETURNED |
| Rechnung | TPB | NONE, DRAFT, ISSUED, SENT, PARTIALLY_CREDITED, FULLY_CREDITED |

Damit kann das System beispielsweise korrekt zeigen: „Angebot angenommen, Anzahlung bezahlt, Proof freigegeben, aber zwei Größen fehlen im Lager.“

## 7.2 Ende-zu-Ende-Ablauf

### Pfad A – Angebotsanfrage, empfohlen für das MVP

Die folgende Tabelle beschreibt den anfänglich empfohlenen Ablauf. Er trennt eine unverbindliche Preisschätzung sauber von einem angenommenen Angebot und vermeidet, dass vor einer kommerziellen Einigung unnötig vollständige Proof-Arbeit entsteht.

| Schritt | Aktion | Erzeugte Daten / Sperre |
|---|---|---|
| 1 | Kunde konfiguriert Produkt | Konfigurationsentwurf, Preisversion |
| 2 | Server berechnet Preis | Preisaufschlüsselung und Kalkulationshash |
| 3 | Kunde sendet strukturierte Angebotsanfrage | Kunde/Gastkontakt, Konfiguration, notwendige Erklärungen |
| 4 | Automatische/Manuelle Prüfung | Risiko- und Vollständigkeitsflags |
| 5 | Angebot wird erstellt | Angebotsnummer, PDF, Ablaufdatum, Snapshot |
| 6 | Kunde nimmt an | Annahmeereignis, Rechtstextversionen |
| 7 | Anzahlung wird fällig/bezahlt | Zahlungsforderung und Zahlung |
| 8 | Datei wird technisch geprüft | Artwork-Version, Befund, Kosten für Aufbereitung |
| 9 | Korrekturabzug wird versendet | Proof-Version und unveränderliche Vorschau |
| 10 | Kunde gibt frei | genaue Version, Zeit und Erklärung |
| 11 | Bestand wird reserviert/bestellt | Reservierungen oder Einkaufsbedarf |
| 12 | Produktionsjobs entstehen | Route, Sollzeit, Materialplan, Priorität |
| 13 | Jobetiketten werden gedruckt | Print-Job, Vorlage, Hash, Druckereignis |
| 14 | Produktion wird gescannt | Start/Ende, Mitarbeiter, Gutmenge, Verbrauch |
| 15 | Qualitätskontrolle | Checkliste, Ergebnis, Fotos optional, Nacharbeit |
| 16 | Verpackung/Versand | Paket, Gewicht, Inhalt, Carrier-Label |
| 17 | Rechnung wird finalisiert | Nummer, MySQL-Daten, JSON, PDF, Hash |
| 18 | Zahlung und Lieferung | Zuordnung, Tracking, Abschluss |
| 19 | Auswertung | Plan/Ist-Marge, Zeit, Ausschuss, Wiederbestellung |

### Pfad B – direkter WooCommerce-Checkout, später

Sobald Preise, Verfügbarkeit, Uploadprüfung und Rechtstexte stabil sind, kann ein direkter Kauf ergänzt werden:

~~~text
Konfiguration speichern
→ über configuration_id in den Warenkorb
→ serverseitige Preis-/Vollständigkeitsprüfung
→ WooCommerce-Checkout und Bestellung
→ Zahlung/Anzahlung nach Regel
→ technische Artwork-Prüfung
→ Proof und Kundenfreigabe
→ gemeinsamer Produktionsablauf ab Reservierung
~~~

Auch bei direkter Zahlung startet die Produktion niemals ohne die erforderliche Proof-Freigabe. Ein technisch ungeeignetes Motiv führt in einen geklärten Änderungs-, Aufbereitungs- oder Erstattungsprozess und nicht zu einer stillen Falschproduktion.

## 7.3 Produktionsfreigabe-Gate

Ein Job darf nur auf READY wechseln, wenn:

- Angebot oder Bestellung rechtswirksam angenommen ist;
- erforderliche Anzahlung eingegangen oder ausdrücklich freigegeben ist;
- eine Grafikversion APPROVED und LOCKED ist;
- alle Namen/Nummern vollständig und validiert sind;
- Rohlinge und Material verfügbar oder bewusst übersteuert sind;
- Liefertermin bestätigt ist;
- notwendige Sonderhinweise vorhanden sind.

Jede manuelle Übersteuerung benötigt Rolle, Grund, Zeitstempel und Audit-Eintrag.

## 7.4 Qualitätskontrolle

Die QC-Checkliste sollte pro Technik konfigurierbar sein:

- Produkt, Farbe und Größe korrekt;
- richtige Grafikversion;
- Position und Abmessung geprüft;
- Name/Nummer korrekt;
- Pressparameter/Technik dokumentiert;
- verwendete Maschine, Rezeptversion, Temperatur, Druck und Dauer soweit relevant;
- Haftung und Oberfläche geprüft;
- keine sichtbare Beschädigung;
- Menge vollständig;
- Pflegehinweis beigelegt;
- Verpackung und Auftragszuordnung korrekt.

Bei Fehlern wird nicht nur „nicht bestanden“ gespeichert. Erforderlich sind Grund, betroffene Stückzahl, Material-/Zeitverlust, Foto optional, Verantwortungsart und Nacharbeitsentscheidung.

---

# 8. Lager, Einkauf und Kundenware

## 8.1 Varianten und SKUs

Jede bestandsgeführte Kombination aus Produkt, Farbe und Größe benötigt eine eindeutige SKU, zum Beispiel:

~~~text
TSH-COT-BLK-M
HOO-STD-NVY-L
POL-STD-WHT-XL
~~~

Materialien erhalten eigene SKUs und Einheiten:

- Flexfolie in Zentimeter oder Quadratmeter;
- Flock/Glitzer/Reflex als Rollenmaterial;
- Transferpapier/Bögen;
- Verpackungsbeutel;
- Kartons;
- Etikettenrollen.

Einheiten dürfen nicht vermischt werden. Einkaufseinheit, Lagereinheit und Verbrauchseinheit müssen mit einem Umrechnungsfaktor definiert sein.

Die WooCommerce-Produktvariation hält SKU, verkaufsrelevante Attribute und die für den Shop sichtbare Verfügbarkeit. Sobald das erweiterte TPB-Lager aktiv ist, wird dessen Bewegungsjournal zur fachlichen Quelle für physischen, reservierten und verfügbaren Bestand; die WooCommerce-Bestandszahl ist dann eine daraus synchronisierte Shop-Projektion. Direkte manuelle Bestandskorrekturen in zwei verschiedenen Masken werden gesperrt beziehungsweise klar auf den TPB-Korrekturvorgang umgeleitet.

## 8.2 Lagerbewegungen

Bestand wird nicht direkt überschrieben. Er ergibt sich aus Bewegungen:

~~~text
PURCHASE_RECEIPT        +25
CUSTOMER_GOODS_RECEIPT  +10 in separatem Eigentumsbestand
RESERVATION               0, aber verfügbarer Bestand sinkt
ISSUE_TO_PRODUCTION     -10
RETURN_FROM_PRODUCTION   +1
SCRAP                    -1
RETURN_TO_SUPPLIER       -2
MANUAL_CORRECTION        ±n mit Pflichtgrund
~~~

Zentrale Werte:

~~~text
physisch vorhanden = Summe aller mengenwirksamen Bewegungen
reserviert          = offene Reservierungen
verfügbar           = physisch vorhanden - reserviert
erwartet            = offene bestätigte Einkaufsmenge
~~~

WooCommerce-Bestellung, Storno, Rückzahlung und Ablauf einer Reservierung erzeugen idempotente TPB-Bewegungs-/Reservierungsereignisse. Ein periodischer Abgleich meldet Differenzen zwischen WooCommerce-Projektion und TPB-Journal, statt sie still zu überschreiben.

## 8.3 Einkauf

Ein Einkaufsprozess benötigt:

- Lieferant und Ansprechpartner;
- Lieferantenartikel und interne SKU;
- Einkaufspreis mit Gültigkeitsdatum;
- Mindestmenge und Lieferzeit;
- Bestellung und Positionen;
- erwartetes Lieferdatum;
- Teilwareneingänge;
- Qualitäts-/Mengenabweichung;
- Lieferantenrechnung/Beleg;
- Zuordnung der tatsächlichen Kosten.

Beim Wareneingang entsteht ein Kosten-Lot. Für das MVP ist gleitender Durchschnittspreis ausreichend; ein Lotbezug sollte trotzdem speicherbar sein, insbesondere bei Kundenware oder stark schwankenden Einkaufspreisen.

## 8.4 Kundenware

Kundenware darf niemals mit eigenem Bestand vermischt werden. Beim Eingang werden erfasst:

- Kunde und Auftrag;
- Produktbeschreibung, Marke, Farbe, Größe;
- Stückzahl;
- sichtbarer Zustand und vorhandene Schäden;
- Fotos optional;
- Material-/Pflegeetikett;
- erklärte Eignung beziehungsweise Vorbehalt;
- Übergabedatum und Mitarbeiter;
- eindeutiges Empfangs-/Stücketikett.

Im Auftrag wird dokumentiert, welche Risiken erklärt und akzeptiert wurden. Ein pauschaler Satz „keine Haftung für Materialschäden“ ist rechtlich riskant und darf die zwingende Haftung nicht ausschließen. Besser sind transparente Materialprüfung, schriftlicher Vorbehalt, definierte Testmöglichkeit und ein juristisch geprüfter Haftungsrahmen.

---

# 9. Etiketten, QR-Codes und Drucker

## 9.1 Was tatsächlich benötigt wird

Für den Start braucht The Printing Brothers keinen Roboter, der Etiketten automatisch aufklebt. Sinnvoll sind:

- ein netzwerkfähiger Thermoetikettendrucker;
- ein kabelgebundener USB-2D-Scanner;
- wiederverwendbare Auftragsboxen oder Beutel;
- kleine Lagerplatzetiketten;
- größere Produktions-/Versandetiketten;
- eine Druckwarteschlange im Backend;
- später ein lokaler Print-Agent.

## 9.2 Etikettentypen

| Typ | Empfohlene Größe | Inhalt |
|---|---:|---|
| Produktions-/Jobetikett | ca. 62 × 100 oder 76 × 50 mm | Job, Produkt, Farbe, Größe, Menge, Technik, Positionen, Version, Termin, QR/Code 128 |
| Einzelstücketikett | klein, passend zum Beutel/Anhänger | Stück x/y, Größe, Name, Nummer, Grafikreferenz |
| Lagerfachetikett | ca. 51 × 26 mm | SKU, Variante, Lagerort, Code 128/QR |
| Wareneingang/Los | variabel | Lieferant, SKU, Menge, Datum, Los |
| Verpackungsetikett | 100 × 150 oder kleiner | Paket x/y, Auftrag, Inhalt, Gewicht, Packkontrolle |
| Versandlabel | Carrier-Vorgabe, oft 102 × 152 mm | ausschließlich Original des Versanddienstes |
| QC/Hold | auffällige Farbe/Format | Sperrgrund, Job, Datum, verantwortliche Stelle |

Etiketten gehören auf Boxen, Beutel, Anhänger oder Verpackungen, nicht direkt auf empfindliche Kundenware. Namen auf Einzelstücketiketten sind personenbezogene Daten und müssen kontrolliert verwendet und entsorgt werden.

## 9.3 QR- und Barcode-Regeln

Menschenlesbare Nummern:

~~~text
Auftrag:   ORD-2026-000123
Job:       JOB-2026-000456
Paket:     PKG-2026-000789
Rechnung:  2026-000001
SKU:       TSH-BLK-M
~~~

Intern erhält jeder Datensatz zusätzlich eine UUIDv7 oder ULID. Fortlaufende Nummern sind nicht der einzige Zugriffsschutz.

- **Code 128:** kurze Auftrags-, Job-, Paket- und SKU-Nummern.
- **QR-Code:** zufälliger Token oder interne Scan-URL.
- **GS1-128/GTIN/SSCC:** erst nötig, wenn Handelspartner oder Logistiker dies verlangen.

Nicht in den QR-Code gehören Adresse, E-Mail, Telefonnummer, Preise, Einkaufskosten oder dauerhaft gültige Login-Tokens. Der Scan öffnet nach Authentifizierung genau den passenden Job und zeigt nur erlaubte nächste Aktionen.

## 9.4 Scanbasierter Ablauf

1. Mitarbeiter scannt Jobetikett.
2. System prüft Rolle, Job, Freigaben und aktuellen Status.
3. Oberfläche zeigt nur erlaubte Aktion, zum Beispiel „Entgittern starten“.
4. Start/Ende oder Menge wird bestätigt.
5. Backend schreibt ein unveränderliches Ereignis.
6. Materialverbrauch und Ausschuss werden kontrolliert gebucht.
7. Wiederholter Scan mit demselben Idempotency-Key erzeugt keine Doppelbuchung.
8. Nach bestandener QC wird das Verpackungsetikett freigegeben.

## 9.5 Drucker-Vergleich

| Gerät | Stärken | Einschränkungen | Eignung |
|---|---|---|---|
| Brother QL-820NWBc | 300 dpi, 62 mm, Cutter, USB/LAN/WLAN/Bluetooth | keine üblichen 4×6-Versandlabels; DK-Rollen | kleine Job-/Lagerlabels |
| Brother QL-1110NWBc | bis ca. 103,6 mm, 300 dpi, Cutter, Netzwerk/Funk | Brother-DK-Medien, weniger offene Rohdruckintegration | einfacher Ein-Drucker-Start |
| Brother TD-4420DN | 4 Zoll, Ethernet, normale Rollen, ZPL-Emulation, kostensensibel | 203 dpi, kein WLAN in Grundausstattung | **empfohlene günstige Ein-Drucker-Lösung** |
| Brother TD-4550DNWB | 4 Zoll, 300 dpi, LAN/WLAN/Bluetooth, mehrere Emulationen | höherer Preis | kleine QR-Codes und flexible Verbindung |
| Zebra ZD421d | robuste native ZPL-Integration, 203/300 dpi, viele Optionen | meist teurer; Ausstattung je Variante | **technisch sauberste Integrationslösung** |

Herstellerquellen: [Brother QL-820NWBc](https://www.brother.eu/-/media/files/bsw/produkte-downloads/ql/ql-820nwbc-datasheet_de.pdf?la=de-ch&rev=069df3d0776c43e9be84be0fddf45153), [Brother QL-1110NWBc](https://store.brother.be/fr-be/devices/label-printer/ql/ql1110nwbc), [Brother TD-4D-Serie](https://www.brother.eu/-/media/product-downloads/devices/label-printers/td/td4550dnwbfc/en/datasheet-td-4d-linerless.pdf), [Zebra ZD421](https://www.zebra.com/content/dam/zebra_dam/en/tech-specs/zd421-tech-specs-en-us.pdf).

### Kaufempfehlung

Wenn nur ein Gerät gekauft wird:

- **Preis-/Leistung:** Brother TD-4420DN mit Ethernet;
- **maximal saubere offene Integration:** Zebra ZD421d mit Ethernet;
- **Komfort und 300 dpi mit Herstellerrollen:** Brother QL-1110NWBc.

Vor dem Kauf wird ein Prototyp in den tatsächlich benötigten Größen gedruckt und mit dem vorgesehenen Scanner getestet. Für reine Versandlabels reichen meist 203 dpi; für kleine Stücketiketten und feine QR-Codes sind 300 dpi sicherer.

Ein kabelgebundener USB-2D-Imager wie der [Zebra DS2208](https://www.zebra.com/gb/en/products/scanners/general-purpose-handheld-scanners/ds2200-series/ds2208.html) verhält sich wie eine Tastatur und benötigt im MVP keine komplexe Integration.

Direkt-Thermoetiketten sind für zeitlich begrenzte Produktion und Versand gedacht. Für dauerhaft haltbare oder waschbare Textil-/Pflegeetiketten wären Thermotransfer, geeignete Satin-/Polyestermaterialien und eigene Waschtests nötig.

## 9.6 Warum der Browser nicht genügt

Der Standardbefehl window.print öffnet den Systemdialog und kann Drucker, Format und lautlosen Druck nicht zuverlässig kontrollieren. Siehe [MDN zu window.print](https://developer.mozilla.org/en-US/docs/Web/API/Window/print).

### Stufe 1 – lokales MVP

- Backend rendert PDF oder PNG in exakter Größe;
- Nutzer sieht eine Vorschau;
- Druck über Systemdialog;
- Testmodus speichert nur die Druckdatei;
- Druckjob wird als „Dialog geöffnet“, nicht fälschlich als physisch gedruckt markiert.

### Stufe 2 – QZ Tray

[QZ Tray](https://qz.io/docs/getting-started) kann PDF, PNG und Rohformate wie ZPL/EPL an lokale Drucker senden. Für stilles Drucken müssen Aufrufe signiert werden; private Signierschlüssel gehören ausschließlich ins Backend. Dies ist ein guter Übergang für wenige Arbeitsplätze.

### Stufe 3 – eigener lokaler Print-Agent

~~~text
TPB Production Suite / MySQL
→ freigegebene Print-Job-Warteschlange
→ lokaler Windows-Agent
→ erlaubter Drucker/Format
→ Windows-Spooler oder TCP/ZPL
→ Status und Fehler zurück ans Backend
~~~

Der Agent:

- baut nur ausgehende, authentifizierte Verbindungen auf;
- akzeptiert keine beliebigen Druckbefehle aus dem Browser;
- verwendet eine Allowlist aus Drucker, Format und Kopienzahl;
- verhindert Doppeldruck über Idempotency-Keys;
- protokolliert jeden Neudruck samt Grund;
- meldet QUEUED, CLAIMED, SENT, PRINTED_ASSUMED oder ERROR;
- druckt nach Neustart bestätigte Jobs nicht automatisch erneut.

## 9.7 Datenmodell für Druckjobs

~~~text
print_jobs
- id
- label_type
- entity_type / entity_id
- printer_profile_id
- template_version_id
- requested_copies
- idempotency_key (unique)
- payload_snapshot_json
- render_format
- rendered_asset_id
- rendered_sha256
- status
- requested_by / requested_at
- claimed_at / sent_at / completed_at
- error_code / error_message
- reprint_of_job_id
- reprint_reason

print_job_events
- id
- print_job_id
- event_type
- actor_user_id
- workstation_id
- printer_identifier
- occurred_at_utc
- details_json
~~~

---

# 10. Globale Datenbank und Dateispeicher

## 10.1 Empfehlung: WooCommerce/MySQL plus privater Dateispeicher

Hostinger stellt für WordPress eine MySQL-Datenbank bereit. Sie wird die zentrale Datenbasis für WordPress, WooCommerce und die zusätzlichen TPB-Funktionen. Es wird keine zweite operative Cloudflare-Datenbank parallel geführt.

Die Verantwortlichkeiten sind eindeutig:

| Datenart | Technischer Eigentümer | Regel |
|---|---|---|
| Benutzer und Basisrollen | WordPress | WordPress-APIs und Capabilities verwenden |
| Produkte, Varianten, Kunden, Warenkorb und Bestellungen | WooCommerce | WooCommerce CRUD/API verwenden; HPOS-kompatibel entwickeln |
| Konfiguration, Proof, Produktion, Istkosten, Etiketten, Audit und Archivindex | TPB Production Suite | eigene versionierte Tabellen mit Präfix `{wp_prefix}tpb_` |
| öffentliche Produktbilder | WordPress-Mediathek | nur tatsächlich öffentliche Dateien |
| Logos, Produktionsdateien, Proofs, Rechnungen und Belege | privater Dateibereich | kein direkter öffentlicher Pfad; Download nur nach Berechtigungsprüfung |

Für neue WooCommerce-Installationen ist High-Performance Order Storage der vorgesehene Bestellspeicher. Das TPB-Plugin greift deshalb über `WC_Order`, `WC_Product` und die WooCommerce-Abfrageschnittstellen zu. Es schreibt nicht direkt in `wp_posts`, `wp_postmeta` oder `wp_wc_orders`. Dadurch bleibt es mit HPOS und späteren WooCommerce-Änderungen kompatibel.

Eigene Tabellen sind dort sinnvoll, wo fortlaufend viele strukturierte Produktionsdaten entstehen: Layer, Proof-Versionen, Statusereignisse, Arbeitszeiten, Materialbewegungen, Druckjobs und Auditereignisse. Kleine Einstellungen können WordPress Options beziehungsweise WooCommerce Settings verwenden. Große wachsende Fachdaten gehören nicht in eine einzige Option und nicht unkontrolliert in Post-Meta.

MySQL ist für das erwartete Volumen ausreichend. Die Fachlogik wird trotzdem über klare Repository-/Serviceklassen gekapselt. Erst bei nachgewiesenen Grenzen – beispielsweise mehreren Standorten, sehr hoher Schreiblast oder externer ERP-/BI-Pflicht – wird eine getrennte Datenplattform bewertet.

## 10.2 Logische Datensätze nach Fachgebiet

Die folgenden Namen beschreiben das fachliche Modell. Sie sind nicht automatisch eins zu eins neue SQL-Tabellen. Der Zusatz **WP**, **Woo** oder **TPB** legt fest, welches System den Datensatz besitzt. Dadurch wird verhindert, dass WooCommerce-Bestellungen oder Kunden versehentlich ein zweites Mal als konkurrierende Wahrheit gespeichert werden.

### Identität und Steuerung

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| users / capabilities **(WP)** | E-Mail, Name, Status, Rollen und letzter Login; keine separate Passworttabelle |
| business_settings **(TPB/WP Settings)** | Firma, Adressen, Kontakt, Währung, Zeitzone, Steuerstatus |
| tax_regime_versions **(TPB)** | zeitabhängiges TVA-Regime, Rechtsgrund, gültig ab/bis, Bestätigung |
| tax_threshold_monitors **(TPB)** | Inlands-/EU-Umsatz, Warnstufen, Zeitraum, Bestätigungsstatus |
| number_sequences **(TPB)** | Nummerntyp, Geschäftsjahr, nächster Wert, Sperrversion |
| legal_document_versions **(TPB)** | Typ, Sprache, Inhalt, Version, gültig ab, veröffentlicht |
| content_versions **(WP/TPB)** | öffentliche Inhalte und E-Mail-Texte |
| audit_events **(TPB)** | Actor, Aktion, Objekt, vorher/nachher, Grund, Zeit |
| idempotency_keys **(TPB)** | Schlüssel, Aktion, Ergebnis, Ablaufdatum |
| outbox_events **(TPB)** | ausstehende E-Mail-/PDF-/Webhook-/Druckaktionen |

### Kunden und Datenschutz

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| customers / addresses **(Woo)** | Privat/Firma, Name, Sprache, Kontakt-, Rechnungs- und Lieferadresse |
| customer_consents **(TPB)** | Zweck, Erklärungsversion, Zeitpunkt, Quelle, Widerruf |
| customer_notes **(TPB)** | klassifizierte interne Notizen, Autor, Sichtbarkeit |
| communications **(TPB)** | E-Mail/Portalnachricht, Vorlage, Empfänger, Bezug, Status |
| communication_events **(TPB)** | gesendet, zugestellt, abgewiesen, geöffnet nur falls zulässig |
| access_tokens **(TPB)** | zufälliger Hash, Zweck, Ablauf, Widerruf |
| data_subject_requests **(TPB)** | Auskunft, Korrektur, Löschung, Export, Status |

### Katalog, Lieferanten und Preise

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| products / variations / media **(Woo/WP)** | Produktfamilie, Kategorie, Status, SKU, Farbe, Größe, Grundbestand und öffentliche Bilder |
| product_compliance **(TPB/Woo-Metadaten)** | Fasern, Pflege, Hersteller, Warnungen, Sprachversion |
| suppliers / supplier_products **(TPB)** | Lieferant, Artikelnummer, interne Variante, Preis, Zahlungs-/Lieferbedingungen und Gültigkeit |
| materials **(TPB)** | Folie, Verpackung, Einheit, Lagerführung |
| techniques **(TPB)** | Flex, Flock, Glitzer, Stretch, Reflex |
| placements / product_placements **(TPB)** | Seite, Name, Druckfläche, Größenlimits und erlaubte Positionen je Produkt |
| price_books / price_tiers **(TPB)** | Version, Währung, Gültigkeit, Produkt/Variante, Mengenbereich und Stückpreis |
| cost_versions **(TPB)** | Rohling, Zeit, Satz, Ausschuss, Zielmarge |
| addons **(TPB/Woo)** | Namen, Nummern, zweite Position, Lage, Express usw.; als geprüfte Warenkorb-/Bestellposition abbilden |
| bill_of_material_rules **(TPB)** | erwarteter Materialverbrauch je Technik/Größe |

### Konfiguration, Grafik und Angebot

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| configurations / items / units / layers **(TPB)** | Kunde/Gast, Produkt, Variante, Größe, Farbe, Menge, Namen/Nummern, Layer, Preisversion und Status |
| assets **(TPB)** | privater relativer Speicherpfad, Besitzer, Typ, Größe, SHA-256, Sicherheitsstatus |
| artwork_versions **(TPB)** | Original/bereinigt, Version, Preflight-Ergebnis |
| proofs / proof_approvals **(TPB)** | gerenderter Korrekturabzug, Version, Entscheidung, Erklärung, Actor und Zeit |
| quotes / quote_items **(TPB)** | Nummer, Kunde, Status, Ablaufdatum und Produkt-/Preis-/Kosten-/Steuersnapshot; erst bei Annahme entsteht eine WooCommerce-Bestellung |

TPB-Angebote sind die einzige vorvertragliche Angebotswahrheit. Bei Annahme wird idempotent genau eine WooCommerce-Bestellung erzeugt; Angebot und Woo-Bestellung speichern gegenseitige Referenzen. Woo-Draft-Orders werden nicht parallel als zweite Angebotsquelle verwendet.

### Aufträge und Produktion

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| orders / order_items **(Woo HPOS)** | Nummer, Kunde, Datum, Shopstatus, Produktpositionen, Summen und Zahlungsbezug |
| order_item_units **(TPB)** | einzelne Namen/Nummern/Größen, referenziert Woo-Bestellposition |
| order_terms_acceptance **(TPB)** | AGB-Annahme, Personalisierungs-/Widerrufshinweis und bereitgestellte Dokumentversionen; Datenschutzinformation nur als Zustell-/Anzeigenachweis, nicht als erzwungene Einwilligung |
| order_status_events **(TPB)** | Achse, von, nach, Actor, Grund, Zeit |
| production_jobs / recipes / steps / events **(TPB)** | Jobnummer, Route, Rezept, Sollzeit, Ablauf, Menge, Status und Actor |
| work_logs **(TPB)** | Minuten, Kostensatz-Snapshot, Korrekturgrund |
| equipment / equipment_events **(TPB)** | Plotter/Presse, Kosten, Wartung, Kalibrierung, Reparatur, Störung und Stillstand |
| material_documents **(TPB)** | technische Daten-/Sicherheitsblätter, Charge, Version, Asset |
| quality_checks / waste_events **(TPB)** | Checkliste, Ergebnis, Prüfer, Ausschuss, Material-/Zeitkosten und Nacharbeit |
| complaint_cases / events / returns **(TPB mit Woo-Referenz)** | Reklamation, Kommunikation, Prüfung, Rückgabe, Nacharbeit, Ersatz, Gutschrift und ADR |
| recall_cases / items **(TPB)** | Produktsicherheitsfall, Charge, Sperre, betroffene Varianten/Aufträge/Kunden, Maßnahmen und Meldungen |

### Lager und Einkauf

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| inventory_locations / items / lots **(TPB, mit Woo-Produktreferenz)** | Lagerort, Variante/Material, Einheit, Bewertung, Charge, Kosten und Eigentümer |
| inventory_movements / reservations **(TPB)** | Typ, Menge, Lot, Ort, Auftrag/Job, Actor und Status |
| purchase_orders / items / goods_receipts **(TPB)** | Lieferant, Nummer, Termin, Artikel, Menge, Preis, Eingang, Abweichung und Beleg |
| stock_counts **(TPB)** | Inventur, gezählt, Differenz, Genehmigung |
| packaging_materials / usage **(TPB)** | Materialart, Gewichtseinheit, EPR-Kategorie, Auftrag/Paket, tatsächliches Gewicht und Zielland |

### Finanzen

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| invoices / lines / artifacts / credit_notes **(TPB)** | Typ, Nummer, Status, Seller-/Kundensnapshot, Positionen, Summen, JSON/PDF/UBL, Hash und Referenz |
| payments / refunds **(Woo/Gateway)** | Anbieter, Referenz, Betrag, Status, Datum, Gebühr und Rückzahlung |
| payment_allocations **(TPB)** | Woo-Zahlung auf eine oder mehrere TPB-Rechnungen |
| expenses / expense_allocations **(TPB)** | Lieferant, Datum, Kategorie, Betrag, Steuer, Beleg und Zuordnung zu Auftrag/Overhead |
| accounting_exports / epr_exports **(TPB)** | Zeitraum, Format, Datei, Hash, Materialgruppen/Gewichte und Ersteller |

### Versand und Drucken

| Logischer Datensatz | Zweck / Schlüsselfelder |
|---|---|
| shipments / packages **(Woo plus TPB-Erweiterung)** | Auftrag, Carrier, Service, Status, Tracking, Paketnummer, Maße, Gewicht und Inhalt |
| shipping_labels **(TPB)** | Carrier-Datei, Format, Hash, Storno |
| printer_profiles / label_template_versions **(TPB)** | Gerät, Format, DPI, Standort, Adapter, Typ, Sprache, Vorlage und Gültigkeit |
| print_jobs / events **(TPB)** | Payload, Vorlage, Drucker, Status, Idempotency sowie Druck-/Fehler-/Neudruckverlauf |

## 10.3 Zentrale Beziehungen

~~~mermaid
erDiagram
    WP_USER o|--o{ WC_ORDER : may_place
    WC_ORDER ||--|{ WC_ORDER_ITEM : contains
    WC_PRODUCT ||--o{ WC_ORDER_ITEM : sold_as
    WC_ORDER_ITEM ||--o{ TPB_ORDER_ITEM_UNIT : personalizes
    WC_ORDER_ITEM ||--o{ TPB_PRODUCTION_JOB : creates
    TPB_PRODUCTION_JOB ||--o{ TPB_STEP_EVENT : records
    WC_ORDER ||--o{ TPB_PROOF : requires
    TPB_PROOF ||--o{ TPB_PROOF_APPROVAL : receives
    WC_ORDER ||--o{ TPB_RESERVATION : reserves
    TPB_INVENTORY_ITEM ||--o{ TPB_INVENTORY_MOVEMENT : moves
    WC_ORDER ||--o{ TPB_INVOICE : billed_by
    TPB_INVOICE ||--|{ TPB_INVOICE_LINE : contains
    WC_ORDER ||--o{ TPB_PAYMENT_EVENT : records
    TPB_PAYMENT_EVENT ||--o{ TPB_PAYMENT_ALLOCATION : allocates
    TPB_INVOICE ||--o{ TPB_PAYMENT_ALLOCATION : receives
    WC_ORDER ||--o{ TPB_SHIPMENT : fulfills
    TPB_PRODUCTION_JOB ||--o{ TPB_PRINT_JOB : prints
    TPB_ASSET ||--o{ TPB_ARTWORK_VERSION : versions
~~~

## 10.4 Datentypen und Konventionen

- WordPress-/WooCommerce-Kerndatensätze behalten ihre nativen IDs; TPB-Tabellen verwenden je nach Beziehung BIGINT oder UUIDv7/ULID;
- öffentliche Tokens sind immer zufällig, nicht erratbar und von internen IDs getrennt;
- öffentliche Nummern: separate kontrollierte Sequenz;
- Geld: TPB-intern deterministisch in Minor Units plus ISO-Währung; an WooCommerce ausschließlich über dessen Decimal-/Währungs-APIs übergeben;
- Prozent: Basispunkte;
- Mengen: Decimal/Festkomma mit Einheit;
- Zeit: UTC speichern, Europe/Luxembourg anzeigen;
- Adresse, Kunde, Firma, Produkt und Steuertext auf Belegen als Snapshot;
- Soft-Archive für Stammdaten statt Löschen bei Referenzen;
- JSON nur für versionierte Snapshots/variable Metadaten, nicht als Ersatz für alle Beziehungen;
- jede Datei mit SHA-256, MIME, Größe, abstrakter Speicher-ID beziehungsweise relativem Privatpfad und Sicherheitsstatus;
- jede Datei mit Aufbewahrungsklasse und geplantem Lösch-/Prüfdatum;
- jede Statusänderung mit Actor, Grund, Korrelation und Zeit.

## 10.5 Private Dateistruktur auf Hostinger

Die WordPress-Mediathek ist für öffentliche Produktbilder geeignet, nicht als Standardablage für vertrauliche Kundenlogos, Namenslisten, Produktionsdateien, Rechnungen oder Lieferantenbelege. Diese Dateien liegen in einem vom TPB-Plugin verwalteten privaten Verzeichnis außerhalb des direkt auslieferbaren `public_html`-Bereichs. Falls die konkrete Hostinger-Verzeichnisstruktur dies anders verlangt, muss der Webzugriff technisch blockiert und mit einem anonymen Browser-Test geprüft werden.

Die Datenbank speichert niemals einen fest eingebauten absoluten Serverpfad, sondern eine Speicher-ID und einen relativen Schlüssel. So kann später ohne Änderung aller Geschäftsdaten auf einen externen EU-Objektspeicher gewechselt werden.

~~~text
private/tpb/artwork/{customer-id}/{asset-id}/original
private/tpb/artwork/{customer-id}/{asset-id}/preview.png
private/tpb/proofs/{order-number}/v001/proof.pdf
private/tpb/orders/{order-number}/production/{version}/...
private/tpb/invoices/2026/{invoice-number}/invoice.pdf
private/tpb/invoices/2026/{invoice-number}/invoice.json
private/tpb/invoices/2026/{invoice-number}/invoice.ubl.xml
private/tpb/expenses/2026/08/{expense-id}/receipt.pdf
private/tpb/shipping/{order-number}/{shipment-id}/carrier-label.pdf
private/tpb/labels/{job-number}/{print-job-id}.pdf
private/tpb/exports/accounting/2026-08/{export-id}.zip
{document-root}/wp-content/uploads/tpb-products/...   # nur bewusst öffentliche Medien
~~~

Private Downloads laufen über einen WordPress-/WooCommerce-Endpunkt, der Anmeldung, Rolle, Auftragszuordnung und gegebenenfalls ein kurzlebiges signiertes Token prüft. Der Originaldateiname wird nicht als Zugriffsgeheimnis betrachtet. Verzeichnislisting ist deaktiviert; Skriptausführung in Uploadverzeichnissen ist blockiert.

## 10.6 Backups und Wiederherstellung

Hostinger Business bietet tägliche Backups, diese ersetzen aber weder ein zehnjähriges Rechnungsarchiv noch eine unabhängige Sicherung. Nach Hostingers aktueller Dokumentation werden tägliche Backups sieben Tage und wöchentliche Backups sechs Wochen vorgehalten. Deshalb dürfen sie nicht die einzige Wiederherstellungsstrategie sein. Von Drittanbieter-Backup-Plugins innerhalb der Website erzeugte Backup-Archive können von Hostingers eigener Sicherung ausgeschlossen sein und müssen tatsächlich an ein externes Ziel übertragen werden.

Empfehlung:

- täglicher automatischer MySQL-Export;
- tägliche Sicherung des privaten TPB-Dateibereichs und der öffentlichen Produktmedien;
- Quellcode und Datenbankmigrationen versioniert in Git, jedoch niemals Kundendaten oder Secrets;
- wöchentliches verschlüsseltes Backup auf ein vom Hosting getrenntes Ziel;
- monatlicher zusätzlicher Wiederherstellungspunkt mit klar begrenzter Rotation außerhalb desselben Hostinger-Kontos;
- getrenntes zehnjähriges Rechnungs-/Buchungsarchiv, das nur die erforderlichen Belege und Nachweise enthält – keine pauschalen Warenkörbe, Logos oder Namenslisten;
- finalisierte Rechnungsartefakte mit Hash, nicht regulär editierbarem Anwendungsstatus und restriktiven Dateirechten;
- getrennte Verschlüsselungsschlüssel und dokumentierte Wiederherstellung;
- vierteljährlicher Restore-Test;
- Audit-Alarm, wenn Backup oder Export fehlschlägt;
- Zielwert zunächst RPO 24 Stunden und RTO 8 Stunden, später nach Geschäftsbedarf anpassen.

Ein Backup gilt erst als vorhanden, wenn eine Wiederherstellung getestet wurde. Vor jedem größeren WordPress-, WooCommerce-, Theme- oder Plugin-Update wird zusätzlich ein manueller Wiederherstellungspunkt angelegt. Staging-Backups bleiben von Produktionsbackups getrennt. Sobald das Dateivolumen oder die Anforderungen an Unveränderbarkeit steigen, wird ein externer EU-Objektspeicher mit Versionierung beziehungsweise Retention-Lock ergänzt; dies ist eine spätere Option und keine Voraussetzung für den MVP.

Wichtig: Normaler Hostinger-Dateispeicher ist kein WORM-Archiv. Hash, Auditlog, restriktive Rechte und der Status `ISSUED` machen Manipulationen erkennbar und verhindern reguläre Bearbeitung, aber nicht jede technisch mögliche Änderung durch einen privilegierten Serverzugriff. Wenn echte technische Unveränderbarkeit verlangt wird, muss ein separates Archiv mit Retention Lock/WORM eingesetzt werden.

Downloadlinks für Hostinger-Backups gehen nach aktueller Dokumentation an die registrierte E-Mail-Adresse des Kontoinhabers. Dieses Postfach wird daher mit MFA geschützt und nicht als unkontrolliertes Gemeinschaftskonto verwendet.

---

# 11. Rechnungen, Zahlungen, Ausgaben und Gewinn

## 11.1 Geschäftsvorgänge sauber trennen

| Objekt | Bedeutung |
|---|---|
| Konfiguration | unverbindlicher Produkt-/Designentwurf |
| Angebot | befristeter kommerzieller Vorschlag |
| Auftrag | angenommene Verpflichtung und Produktionsgrundlage |
| Rechnung | ausgestellter Buchungs-/Zahlungsbeleg |
| Zahlung | tatsächlicher Geldfluss |
| Gutschrift/Storno | dokumentierte Korrektur einer ausgestellten Rechnung |
| Ausgabe | betrieblicher Aufwand mit Beleg |

Eine Zahlung ändert nicht den Rechnungsinhalt. Sie wird der Rechnung zugeordnet. Eine Anzahlung kann mehreren späteren Dokumenten zugeordnet werden; die genaue steuerliche Verbuchung wird mit dem Buchhalter festgelegt.

## 11.2 Lebenszyklus einer Rechnung

1. Entwurf mit interner ID.
2. Vollständigkeits- und Summenprüfung.
3. Nummer wird in einer Datenbanktransaktion vergeben.
4. Verkäufer-, Kunden-, Steuer- und Positionsdaten werden eingefroren.
5. Kanonischer JSON-Snapshot wird erzeugt.
6. PDF wird aus demselben Snapshot gerendert.
7. SHA-256 der Artefakte wird gespeichert.
8. Status wechselt unwiderruflich auf ISSUED.
9. Versand per E-Mail/Portal wird als separates Ereignis protokolliert.
10. Korrektur nur durch Gutschrift/Storno und gegebenenfalls neue Rechnung.

Entwurfsnummern sind keine Rechnungsnummern. Eine einmal vergebene Nummer wird nicht für ein anderes Dokument wiederverwendet.

Bei elektronischen Rechnungen außerhalb der verpflichtenden B2G-Verfahren wird die Akzeptanz beziehungsweise vereinbarte elektronische Zustellung des Empfängers dokumentiert. Während der gesamten Aufbewahrung müssen Herkunft, inhaltliche Unversehrtheit und Lesbarkeit gewährleistet bleiben. PDF/A kann dafür sinnvoll sein, ist hier aber nicht als gesetzlich einzig zulässiges Format vorausgesetzt.

### 11.2.1 Zahlungsanbieter in WooCommerce

Der konkrete Zahlungsanbieter bleibt eine Geschäftsentscheidung. Bevorzugt wird eine etablierte WooCommerce-Erweiterung mit gehosteter Weiterleitung oder gehosteten Zahlungsfeldern. The Printing Brothers speichert keine vollständige Kartennummer und keine Kartenprüfnummer. Lokal verbleiben nur die erforderliche Zahlungsreferenz beziehungsweise ein Token, Status, Betrag, Zeit, Gebühr und – falls wirklich erforderlich – begrenzte Anzeigeinformationen wie die letzten vier Stellen.

Zahlungswebhooks werden signiert geprüft, gegen Wiederholung geschützt und idempotent verarbeitet. Sie aktualisieren die WooCommerce-Bestellung beziehungsweise den Refund und erzeugen ein normalisiertes TPB-Zahlungsereignis für Rechnungszuordnung und abgeleitete Zahlungsachse. Fehler- und Diagnoseprotokolle redigieren personenbezogene sowie Zahlungsdaten und erhalten kurze Löschfristen. Eine Zahlung finalisiert nicht stillschweigend eine fachlich noch ungeprüfte Rechnung oder einen noch nicht freigegebenen Produktionsjob.

Vor Auswahl werden Gebühren, SEPA/Kartenunterstützung, Rückzahlungen, Anzahlungen, Luxemburg-/EU-Verfügbarkeit, Pluginpflege, Datenübermittlung, Cookies, DPA/Verantwortlichenrolle und Sandbox geprüft. Die Nutzung eines PCI-konformen Zahlungsdienstes macht die gesamte WordPress-Seite nicht automatisch sicher oder PCI-konform; Theme, Plugins, Benutzerkonten und Checkout bleiben eigene Verantwortung.

## 11.3 Archivformate

### Relationale Daten

Erlauben Auswertung und Verknüpfung mit Auftrag, Zahlung und Kosten.

### Kanonischer JSON-Snapshot

Beispielstruktur:

~~~json
{
  "schemaVersion": "1.0",
  "documentType": "INVOICE",
  "invoiceNumber": "2026-000001",
  "issuedAt": "2026-08-12T13:15:00Z",
  "currency": "EUR",
  "seller": {
    "legalName": "[nach Gründung]",
    "address": "[nach Gründung]",
    "rcsNumber": "[falls anwendbar]",
    "businessPermitNumber": "[nach Erteilung]",
    "vatStatus": "SME_EXEMPTION_ART_57BIS"
  },
  "customer": {
    "type": "BUSINESS",
    "name": "Beispielverein",
    "billingAddress": "Snapshot"
  },
  "lines": [
    {
      "sku": "TSH-COT-BLK-M",
      "description": "T-Shirt, Flex einfarbig, Brust links",
      "quantity": 20,
      "unitGrossCents": 1300,
      "lineGrossCents": 26000
    }
  ],
  "totals": {
    "netCents": 26000,
    "taxCents": 0,
    "grossCents": 26000
  },
  "taxLegend": "TVA non applicable – Article 57bis de la loi modifiée du 12 février 1979"
}
~~~

Bezahlter und offener Betrag gehören nicht in diesen unveränderlichen Rechnungssnapshot, weil Zahlungen später eintreffen oder zurückgezahlt werden können. Sie werden aktuell aus Zahlungsereignissen und Rechnungszuordnungen berechnet. Eine bereits bei Ausstellung verrechnete Anzahlung darf stattdessen ausdrücklich als `prepaymentAppliedAtIssueCents` eingefroren werden.

### PDF

Menschenlesbare, druckbare Darstellung. Das PDF wird nicht aus später veränderten Live-Daten neu erzeugt, sondern aus dem gespeicherten Snapshot.

### UBL/Peppol später

Für öffentliche Aufträge in Luxemburg sind strukturierte E-Rechnungen relevant. Das Datenmodell soll auf EN 16931/Peppol BIS Billing 3.0 abbildbar sein. Ein PDF allein ist keine strukturierte E-Rechnung. Die Übermittlung über Peppol oder MyGuichet wird in einer späteren Phase integriert oder über einen Dienstleister/Buchhalter abgewickelt. Siehe [Guichet.lu zur elektronischen Rechnung bei öffentlichen Aufträgen](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/marche-public-concession/facturation/transmission-facture-electronique-marche-public-contrat-concession.html).

## 11.4 Rechnungsdaten

Das System sollte für jede Rechnung die vollständige Variante unterstützen, auch wenn im Einzelfall eine vereinfachte Rechnung zulässig wäre:

- vollständiger Verkäufername, Rechtsform und Anschrift;
- RCS-Nummer, Niederlassungsgenehmigung und TVA-Nummer, soweit anwendbar;
- Kunde und Rechnungsadresse;
- eindeutige chronologische Nummer;
- Ausstellungsdatum;
- Liefer-/Leistungsdatum;
- klare Bezeichnung, Menge und Einzelpreis;
- Rabatt;
- Netto-, Steuer- und Bruttobeträge;
- Steuersatz oder rechtlicher Befreiungshinweis;
- Zahlungsziel, Bank-/Zahlungsreferenz;
- Bezug zu Angebot/Auftrag;
- Gutschriftbezug bei Korrektur.

Offizielle Übersichten: [Guichet.lu – Rechnung](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/facturation/encaissement/facture.html) und [AED – Pflichtangaben](https://pfi.public.lu/fr/professionnel/tva/en-cours-activite-economique/que-doivent-contenir-factures.html).

## 11.5 TVA und Kleinunternehmerregelung

Nach der offiziellen luxemburgischen Steuerverwaltung gilt seit 1. Januar 2025 für die nationale Kleinunternehmerregelung grundsätzlich eine Umsatzschwelle von 50.000 EUR mit einer Toleranz von 10 % bis 55.000 EUR im Überschreitungsjahr. Für die grenzüberschreitende EU-Regelung gilt zusätzlich eine unionsweite Schwelle von 100.000 EUR und das jeweilige nationale Verfahren. Der konkrete Status muss bei Gründung und bei grenzüberschreitenden Verkäufen fachlich geprüft werden. Quellen: [AED – régime de franchise](https://pfi.public.lu/fr/professionnel/tva/sme.html) und [Guichet.lu – grenzüberschreitende Kleinunternehmerregelung](https://guichet.public.lu/fr/entreprises/fiscalite/impots-benefices/tva/regime-franchise/franchise-transfrontalier.html).

Wenn die nationale Befreiung anwendbar ist, nennt die Steuerverwaltung als Rechnungsangabe:

> TVA non applicable – Article 57bis de la loi modifiée du 12 février 1979

Siehe [AED – activité supplémentaire/faible chiffre d’affaires](https://pfi.public.lu/fr/citoyen/tva/activite-supplementaire-faible-chiffre-affaires.html).

Die bisherige verkürzte Formulierung „TVA non applicable – art. 57bis“ sollte vor Rechnungsbetrieb durch die vollständige offizielle Form ersetzt werden.

Der Steuerstatus ist:

- eine versionierte Unternehmenseinstellung;
- Bestandteil jedes Rechnungs-Snapshots;
- nicht automatisch allein aufgrund eines Dashboardwerts umzuschalten;
- mit Warnungen bei Annäherung an Schwellen zu überwachen;
- nach Bestätigung durch Buchhalter/Steuerverwaltung zu ändern.

Das Dashboard erhält konfigurierbare Warnstufen, zum Beispiel bei 45.000, 49.000, 50.000 und 55.000 EUR Inlandsumsatz sowie getrennte Zähler für EU-Umsätze. Warnungen lösen eine Prüfung aus, aber niemals automatisch einen Wechsel des Steuerregimes oder eine rückwirkende Rechnungsänderung.

## 11.6 Ausgaben und Belege

Jede Ausgabe erhält:

- Lieferant;
- Beleg-/Rechnungsnummer;
- Belegdatum und Zahlungsdatum;
- Kategorie;
- Betrag und Währung;
- Steuerbetrag/-behandlung;
- Zahlungsart;
- Beleg im privaten Dateiarchiv mit gespeichertem SHA-256;
- Zuordnung zu Auftrag, Produkt, Einkauf oder Overhead;
- Freigabestatus;
- Ersteller und Änderungsverlauf.

Beispiele für Kategorien:

- Rohlinge;
- Folien/Verbrauchsmaterial;
- Verpackung;
- Versand;
- Maschinen/Wartung;
- Software;
- Marketing;
- Zahlungsgebühren;
- Miete/Strom;
- Beratung/Buchhaltung;
- sonstiger Overhead.

Ein Lieferantenbeleg darf nicht nur als Bild existieren. Die wichtigsten Felder werden strukturiert erfasst, damit Auswertungen möglich sind.

## 11.7 Gewinn pro Artikel und Auftrag

### Geplanter Deckungsbeitrag

~~~text
geplanter Erlös
- geplanter Rohling
- geplantes Veredelungsmaterial
- geplante Arbeitskosten
- geplante Maschinenkosten
- geplanter Ausschuss
- geplante direkte Gebühren
= geplanter Deckungsbeitrag
~~~

### Tatsächlicher Deckungsbeitrag

~~~text
tatsächlicher Erlös nach Rabatt/Gutschrift
- tatsächliche Rohlingkosten
- tatsächlicher Materialverbrauch
- tatsächliche Arbeitszeit × eingefrorener Kostensatz
- tatsächliche Maschinenzeit × eingefrorener Maschinenkostensatz
- Fremdleistungen
- tatsächlicher Ausschuss/Nacharbeit
- Verpackung
- zugeordnete Zahlungs- und Versandgebühren
= tatsächlicher Deckungsbeitrag
~~~

### Betriebsergebnis

~~~text
tatsächlicher Deckungsbeitrag
- nachvollziehbar zugeordneter Overhead
= kalkulatorisches Betriebsergebnis
~~~

Deckungsbeitrag und Ergebnis nach Overhead werden getrennt gezeigt. Sonst ist nicht sichtbar, ob ein Produkt operativ profitabel ist oder nur durch eine gewählte Gemeinkostenverteilung schlecht erscheint.

### Auswertungsdimensionen

- Produktfamilie und SKU;
- Technik;
- Position;
- Mengenstaffel;
- Kunde/Kundengruppe;
- Auftrag;
- Mitarbeiter/Produktionsroute;
- Woche/Monat/Quartal;
- Neukunde/Wiederbestellung;
- Express/Normal;
- Eigenware/Kundenware.

## 11.8 Buchhaltungsexporte

Mindestens separate CSV-Dateien:

1. Rechnungen;
2. Rechnungspositionen;
3. Gutschriften;
4. Zahlungen und Zuordnungen;
5. Ausgaben;
6. Lieferanten;
7. offene Posten.

Jeder Export erhält Zeitraum, Schemasversion, Ersteller, Zeit, SHA-256 und eine feste Spaltenbeschreibung. Exporte werden nicht still überschrieben.

Später können ergänzt werden:

- PCN-kompatible Kontenzuordnung nach Vorgabe des Buchhalters;
- UBL/Peppol;
- direkter Export in die gewählte Buchhaltungssoftware;
- Bankabgleich.

---

# 12. Rechtliche Anforderungen in Luxemburg

## 12.1 Priorität und Verantwortlichkeit

Vor dem öffentlichen Verkauf müssen die rechtlichen Inhalte vollständig sein. Die Unternehmensdaten stehen heute noch nicht fest; deshalb dürfen im Code keine erfundenen Namen, Nummern oder Adressen veröffentlicht werden.

Im Backend wird eine zentrale, versionierte Unternehmensakte angelegt:

~~~text
legal_name
legal_form
trade_name
registered_address
service_address
RCS_number
VAT_ID
business_authorisation_number
business_authorisation_authority
business_authorisation_2D_code_asset
public_email
public_phone
complaint_email
bank_details
responsible_publisher
hosting_provider
~~~

Website, Angebote, E-Mails und Rechnungen beziehen die Angaben aus einer freigegebenen Version. Eine Änderung wirkt nur für neue Dokumente; alte Rechnungen behalten ihren Snapshot.

## 12.2 Benötigte rechtliche Seiten

| Seite | Inhalt |
|---|---|
| Impressum / Mentions légales | Anbieter, Rechtsform, Sitz, Kontakt, Register, Genehmigung und TVA |
| Datenschutz | Zwecke, Rechtsgrundlagen, Hosting/Empfänger, Transfers, Fristen, Rechte und CNPD |
| Cookie-Information/Einstellungen | notwendige und optionale Dienste, Einwilligung, Widerruf |
| AGB B2C | Vertrag, Preise, Zahlung, Lieferung, Personalisierung, Gewährleistung, Beschwerden |
| AGB B2B | getrennte kommerzielle Regeln, keine Vermischung mit Verbraucherrechten |
| Widerrufsbelehrung und Formular | 14-Tage-Grundsatz und korrekte Ausnahme je personalisiertem Produkt |
| Versand und Zahlung | Gebiete, Methoden, Kosten, Fristen, Abholung |
| Druck- und Dateihinweise | Dateiformate, Proof, Farben, Toleranzen, Aufbereitung |
| Kundenware | Annahmeprüfung, dokumentierte Risiken, nicht pauschale Haftungsfreiheit |
| Reklamation/ADR | interner Prozess und zuständige Verbraucherschlichtung |
| Barrierefreiheit | Status/Erklärung, Kontakt für Barrieren, soweit erforderlich/sinnvoll |

Alle Seiten benötigen Sprache, Versionsnummer, Entwurfsstatus, gültig-ab, Freigebende Person und Veröffentlichungszeit.

## 12.3 Impressum

Artikel 5 des luxemburgischen Gesetzes über den elektronischen Geschäftsverkehr verlangt leicht, unmittelbar und ständig zugängliche Anbieterinformationen. Das Impressum sollte nach Gründung mindestens enthalten:

- vollständige Firma beziehungsweise Name;
- Rechtsform;
- geografische Niederlassungs-/Sitzanschrift;
- E-Mail und weitere Angaben für schnelle direkte Kommunikation;
- RCS-Nummer, sofern eingetragen;
- TVA-Identifikationsnummer, sofern vorhanden;
- Nummer der Niederlassungsgenehmigung und zuständige Stelle;
- verantwortlicher Herausgeber/Betreiber;
- Hosting-Anbieter mit Kontaktanschrift;
- bei reglementierten Tätigkeiten zusätzliche Berufsangaben, falls einschlägig.

Quelle: [Luxemburger Gesetz über den elektronischen Geschäftsverkehr, Art. 5](https://legilux.public.lu/eli/etat/leg/loi/2000/08/14/n8).

Guichet.lu weist außerdem darauf hin, dass der zur autorisation d’établissement gehörende 2D-Code auf Website, E-Mails, Angeboten und Rechnungen angezeigt werden muss. Das Backend braucht deshalb ein freigegebenes Bild-/Codefeld und darf den Code nicht aus einer alten Datei kopieren. Quelle: [Guichet.lu – Niederlassungsgenehmigung](https://guichet.public.lu/fr/entreprises/creation-developpement/autorisation-etablissement/autorisation-honorabilite/autorisation-etablissement.html).

## 12.4 Preise und Bestellabschluss

Verbraucherpreise müssen klar, gut lesbar, eindeutig zuordenbar, in Euro und grundsätzlich als Endpreise angezeigt werden. Kann ein genauer Preis noch nicht berechnet werden, muss mindestens die nachvollziehbare Berechnungsmethode erklärt werden. Quelle: [Guichet.lu – Preisangaben](https://guichet.public.lu/fr/citoyens/justice/protection-consommateur/indication-prix/indication-produits-services.html).

Der Checkout zeigt unmittelbar vor Bestellung:

- Produkt, Variante, Farbe, Größen und Mengen;
- jede Gestaltung und Position;
- individuelle Namen/Nummern;
- Einrichtungs-, Datei- und Expresskosten;
- Versand und weitere Gebühren;
- vollständigen Endbetrag und Steuerhinweis;
- Zahlungsmethode;
- Liefer- oder Leistungszeit;
- Personalisierungs- und Widerrufshinweis;
- Links zu speicherbaren Vertragsinformationen.

Der verbindliche Button sollte eindeutig „Zahlungspflichtig bestellen“ lauten. Keine kostenpflichtige Option darf vorausgewählt sein. Lieferbeschränkungen und akzeptierte Zahlungsmethoden werden zu Beginn des Checkouts gezeigt. Vor Absenden kann der Kunde Eingabefehler korrigieren.

Die Bestätigung wird unverzüglich per E-Mail beziehungsweise als speicherbares PDF auf einem dauerhaften Datenträger bereitgestellt. Quellen: [Guichet.lu – Abschluss eines Fernabsatzvertrags](https://guichet.public.lu/fr/citoyens/justice/protection-consommateur/contrats-distance/conclusion-contrat-distance.html), [Guichet.lu – Fernabsatz B2C](https://guichet.public.lu/fr/entreprises/commerce/pratiques-commerciales/vente/a-distance-b2c.html) und [Your Europe – Fernabsatz](https://europa.eu/youreurope/business/selling-in-eu/selling-goods-services/ecommerce-distance-selling/index_en.htm).

Ein Live-Rechner muss klar zwischen diesen Zuständen unterscheiden:

- unverbindliche Preisindikation;
- Angebot anfragen;
- verbindliches Angebot;
- zahlungspflichtige Bestellung.

## 12.5 Widerruf bei personalisierten Produkten

Grundsätzlich haben Verbraucher bei Fernabsatzverträgen 14 Tage Widerrufsrecht. Fehlende oder fehlerhafte Information kann die Frist um bis zu zwölf Monate verlängern.

Eine Ausnahme besteht für Waren, die nach Kundenspezifikation angefertigt oder eindeutig personalisiert werden. Daraus folgt:

- Der Ausschluss darf nur auf die tatsächlich personalisierte Ware angewendet werden.
- Eine pauschale Aussage „Bedruckte Artikel sind vom Umtausch ausgeschlossen“ ist zu weit.
- Nicht personalisierte Standardware kann weiterhin dem Widerrufsrecht unterliegen.
- Der konkrete Hinweis erscheint auf Produkt-/Konfigurationsseite und nochmals vor Bestellung.
- Das Backend speichert die angezeigte Hinweisversion und Annahme.
- Für widerrufsfähige Waren werden Belehrung und Musterformular bereitgestellt.

Die Ausnahme vom Widerruf beseitigt **nicht** die gesetzlichen Rechte bei Mängeln oder fehlender Vertragsmäßigkeit. Quelle: [Guichet.lu – Fernabsatz B2C](https://guichet.public.lu/fr/entreprises/commerce/pratiques-commerciales/vente/a-distance-b2c.html).

## 12.6 Gesetzliche Konformitätsgarantie

Bei B2C-Verkäufen gilt grundsätzlich die zweijährige gesetzliche Konformitätsgarantie. Sie kann nicht durch AGB wie „keine Reklamation nach Freigabe“ ausgeschlossen werden. Eine Proof-Freigabe dokumentiert vereinbarte Schreibweise, Größe, Position und Gestaltung, schützt aber nicht vor eigener Falschproduktion, Materialmangel oder sonstiger fehlender Vertragsmäßigkeit. Quelle: [Guichet.lu – gesetzliche Konformitätsgarantie](https://guichet.public.lu/fr/entreprises/commerce/pratiques-commerciales/vente/garantie-conformite-application.html).

Ab **27. September 2026** wird ein harmonisierter EU-Hinweis auf die gesetzliche Gewährleistung relevant. Diese Anforderung muss vor dem Datum nochmals anhand der luxemburgischen Umsetzung geprüft und rechtzeitig in Produkt-/Checkoutdarstellung eingeplant werden. Quelle: [EU-Kommission – Legal Guarantee Notice](https://europa.eu/youreurope/business/selling-in-eu/consumer-contracts-guarantees/eu-legal-guarantee-notice-and-garan-label/indexamp_en.htm).

## 12.7 Übliche Printshop-Regeln für die AGB

Die folgenden Punkte gehören in juristisch geprüfte B2C- und gegebenenfalls separate B2B-AGB:

### Vertrag und Zahlung

- wann ein Vertrag zustande kommt;
- Dauer eines Angebots;
- Mindestbestellwert;
- Fälligkeit und Zahlungsarten;
- Anzahlung ab einem festgelegten Auftragswert;
- Produktionsbeginn erst nach definierten Freigaben;
- Umgang mit Preisfehlern und offensichtlichen Irrtümern.

### Datei und Korrekturabzug

- zulässige Formate und technische Mindestanforderungen;
- was die Dateiprüfung umfasst und was nicht;
- Gebühren für Vektorisierung/Neuzeichnung;
- genaue Bedeutung der Proof-Freigabe;
- neue Freigabe bei jeder geänderten Version;
- Archivdauer und Bedingungen für Wiederbestellung.

### Farbe, Position und Material

- Bildschirme und Textiloberflächen können Farben unterschiedlich darstellen;
- Sonderfarben/Materialchargen können abweichen;
- Position und Größe werden innerhalb zuvor getesteter, sachlich begründeter Toleranzen produziert;
- keine beliebigen Prozent-/Millimeterwerte aus fremden AGB kopieren;
- Toleranzen dürfen grobe Fehler oder fehlende Vertragsmäßigkeit nicht „legalisieren“;
- Pflege-/Waschanleitung und notwendige Wartezeit vor der ersten Wäsche;
- Reflexfolie nicht als zertifizierte Sicherheits- oder PSA-Funktion bewerben, wenn keine entsprechende Produktzertifizierung vorliegt.

### Kundenware

- Eingangszustand, Material und vorhandene Schäden dokumentieren;
- ungeeignete Materialien oder Beschichtungen ablehnen;
- Testpressung nach Vereinbarung;
- bekannte Bearbeitungsrisiken transparent erklären;
- kein pauschaler Haftungsausschluss auch für eigene Fehler;
- Rückgabe, Reststücke und nicht verbrauchte Ware regeln.

### Rechte an Inhalten

- Kunde versichert erforderliche Urheber-, Marken-, Bild- und Namensrechte;
- The Printing Brothers darf Nachweise verlangen;
- offensichtlich rechtswidrige oder diskriminierende Inhalte können abgelehnt werden;
- Sperr-/Takedown-Prozess;
- keine Referenz-/Social-Media-Nutzung ohne gesonderte Rechtsgrundlage oder Einwilligung.

Quellen: [Guichet.lu – Marken](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/propriete-intellectuelle/propriete-industrielle/marque.html) und [Guichet.lu – Urheberrecht](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/propriete-intellectuelle/droits-auteur/defendre-droits-auteurs-droits-voisin.html).

### Lieferung und Reklamation

- Liefergebiet, Versand/Abholung und Gefahrübergang;
- Lieferzeit beginnt erst nach klar definiertem Gate;
- Teillieferung nur nach zulässiger Vereinbarung;
- Reklamationsweg und erforderliche Informationen;
- gesetzliche Rechte bleiben unberührt;
- höhere Gewalt in angemessenem, juristisch geprüftem Umfang;
- interne und externe Streitbeilegung.

Wenn keine andere Lieferfrist vereinbart ist, nennt die Fernabsatzinformation grundsätzlich eine Lieferung spätestens innerhalb von 30 Tagen. Die von The Printing Brothers genannten 10–14 Werktage müssen daher klar an Freigabe, Zahlung und Materialverfügbarkeit geknüpft und realistisch eingehalten werden.

## 12.8 Datenschutz bei Bestellungen, Logos und Namenslisten

Die Datenschutzerklärung muss pro Zweck erklären:

- Verantwortlicher und Kontakt;
- Kategorien personenbezogener Daten;
- Zweck und Rechtsgrundlage;
- Empfänger/Auftragsverarbeiter;
- mögliche Drittlandübermittlung und Schutzmechanismus;
- konkrete oder bestimmbare Speicherdauer;
- Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch und Übertragbarkeit;
- Beschwerderecht bei der CNPD;
- Pflicht/Optionalität der Angabe;
- automatisierte Entscheidungen, falls vorhanden.

Quelle: [CNPD – Informationspflichten nach Art. 13 DSGVO](https://cnpd.public.lu/fr/legislation/droit-europ/union-europeenne/rgpd/chapitre-3.html).

Typische Rechtsgrundlagen:

| Verarbeitung | Typische Grundlage, final prüfen |
|---|---|
| Anfrage, Angebot, Bestellung | Vertrag/vorvertragliche Maßnahmen |
| Produktionsdatei und Namensliste | Vertrag, auf notwendige Nutzung begrenzt |
| Rechnung und Buchungsnachweis | gesetzliche Pflicht |
| Sicherheitsprotokoll/Betrugsprävention | berechtigtes Interesse mit dokumentierter Abwägung |
| Newsletter | Einwilligung oder enge Bestandskundenregel mit Opt-out, fachlich prüfen |
| Marketing-/Analyse-Cookies | vorherige Einwilligung |

Für regelmäßig verarbeitete Kunden-/Bestelldaten ist ein Verzeichnis der Verarbeitungstätigkeiten sinnvoll und regelmäßig praktisch erforderlich; die Kleinunternehmensausnahme greift nicht pauschal bei regelmäßiger Verarbeitung. Quelle: [CNPD – Verarbeitungsverzeichnis](https://cnpd.public.lu/fr/professionnels/obligations/obligations-rgpd/registre.html).

Mit Hosting-, Mail-, Zahlungs-, Analyse-, Support- und Versanddienstleistern werden Rollen, Auftragsverarbeitungsverträge und Unterauftragnehmer dokumentiert.

Bei Namenslisten von Vereinen, Schulen oder Firmen wird die Datenschutzrolle pro Auftragsart geprüft. Wenn der Auftraggeber Namen seiner Mitglieder, Schüler oder Mitarbeiter ausschließlich zur Personalisierung übermittelt und Zweck sowie Mittel vorgibt, kann The Printing Brothers für diese Verarbeitung Auftragsverarbeiter sein. Dann wird vor der Übermittlung gegebenenfalls ein Vertrag nach Art. 28 DSGVO mit Weisungen, Unterauftragnehmern, Sicherheit, Vorfallprozess sowie Löschung/Rückgabe geschlossen. Eine direkte Bestellung der betroffenen Person ist davon zu unterscheiden.

AGB-Annahme, bereitgestellte Datenschutzerklärung und echte Einwilligung werden technisch getrennt. Die Datenschutzerklärung wird nachweisbar angezeigt beziehungsweise bereitgestellt, aber ihre „Zustimmung“ ist keine Voraussetzung für die zur Bestellung notwendige Datenverarbeitung. Freiwillige Einwilligungen wie Newsletter oder optionales Tracking erhalten eigene Checkboxen und Datensätze und können unabhängig widerrufen werden.

### 12.8.1 Hosting-, WordPress- und Plugin-Governance

Durch WordPress/WooCommerce entstehen zusätzliche Datenflüsse, die vor jeder Plugin-Aktivierung geprüft werden. Ein rein lokal laufendes Plugin macht seinen Hersteller nicht automatisch zum Auftragsverarbeiter. Sobald Telemetrie, Cloud-API, Lizenzdienst, externer Support, Webfont, Zahlungsdienst oder SaaS Kundendaten empfängt, muss der Dienst gesondert bewertet und dokumentiert werden.

| Dienstklasse | Typische Daten/Zweck | Vor Freigabe dokumentieren |
|---|---|---|
| Hostinger Hosting, MySQL, Backup, CDN/WAF und gegebenenfalls E-Mail | Website-, Bestell-, Datei-, Log- und Sicherungsdaten | Hostinger als Auftragsverarbeiter für Shopdaten von eigener Verantwortlichkeit für Konto-/Abrechnungs-/Sicherheitsdaten trennen; konkrete Vertragsgesellschaft, DPA/AVV-Version, Standorte, Unterauftragnehmer, Transfergrundlage und Exit |
| WordPress/WooCommerce Core | lokal gespeicherte Website- und Shopdaten | Core ist keine eigene juristische Empfängerstelle; tatsächlich kontaktierte WordPress.org-/Automattic-/Update-/Marketplace-Endpunkte im Netzwerkinventar prüfen |
| WooCommerce.com/WordPress.com | Konto, Lizenzen, Updates und gegebenenfalls Synchronisation | Zweck, übertragene Daten, Anbieterrolle, Verbindung und Widerruf |
| Zahlungsanbieter | Kundendaten, Betrag, Zahlungskennung, Betrugsprävention/KYC | Rolle kann teilweise eigenständiger Verantwortlicher sein; Vertrag, Cookies, Transfers und Löschung prüfen |
| Plugin-SaaS, Vektorisierung, Mockup, Virenscan, KI oder Cloud-API | abhängig vom Plugin bis hin zu Logos/Namenslisten | DPA, Unterauftragnehmer, Training/Weiterverwendung, Aufbewahrung, Telemetrie und Exit |
| externer Backupspeicher | verschlüsselte Datenbank- und Dateisicherungen | DPA, Region, Verschlüsselung, Zugriff, Rotation und Löschung |

Für Hostinger werden die zum echten Konto gehörenden Vertragsunterlagen und das aktuelle [Data Processing Addendum](https://www.hostinger.com/legal/dpa) versioniert archiviert. Ein EWR-Serverstandort wird bevorzugt, garantiert aber nicht automatisch, dass Support, CDN, Backups und alle Unterauftragnehmer ausschließlich im EWR arbeiten. Die tatsächlichen Dienste und Transfers werden anhand des konkreten Kontos geprüft. Vor Vertragsende müssen Daten rechtzeitig exportiert und der Lösch-/Exit-Prozess dokumentiert werden.

Eine verantwortliche Person überwacht Vertrags-, DPA- und Unterauftragnehmeränderungen von Hostinger und anderen kritischen Diensten. Eingang, Prüffrist, Bewertung, eventueller Einwand und Entscheidung werden protokolliert; die Überwachung darf wegen kurzer vertraglicher Reaktionsfristen nicht nur einmal jährlich stattfinden.

Vor Installation jedes Fremdplugins wird ein Freigabeblatt geführt:

- Anbieter, Version, Lizenz, Geschäftszweck und Updatequelle;
- Zugriff auf Kunden, Bestellungen, Logos, Rechnungen und eigene Datenbanktabellen;
- externe APIs, Skripte, iframes, Fonts, Cookies, Local Storage und Telemetrie;
- Logs, Aufbewahrung, Supportzugriff und Verhalten bei Deinstallation;
- DPA, Unterauftragnehmer und Drittlandtransfer, falls externe Verarbeitung stattfindet;
- Unterstützung von WooCommerce HPOS/CRUD, Checkout, WordPress-Exporter/Eraser und Sicherheitsupdates;
- getestete Konflikte mit TPB Theme, TPB Production Suite, Cache und Zahlungsplugin.

[Automatisch von Hostinger installierte WordPress-Hilfs-/Onboarding-Plugins](https://www.hostinger.com/support/6824127-what-is-the-hostinger-wordpress-plugin/) werden inventarisiert, geprüft und bei fehlendem Bedarf entfernt. WooCommerce-Nutzungsstatistik und [Order Attribution](https://woocommerce.com/document/order-attribution-tracking/) bleiben im datensparsamen MVP deaktiviert. Order Attribution kann Referrer-/UTM-/Gerätedaten über zusätzliche Cookies erfassen und wird nur nach eigener Zweckentscheidung, korrekter Einwilligung, Cookie-Inventar und Datenschutzerklärung aktiviert. KI-Funktionen erhalten keine Logos, Namenslisten oder Auftragsdaten, bevor Anbieter, Zweck, Speicherung, Training, DPA und Transfers geklärt sind.

## 12.9 Cookies und Analytics

Der einfachste datenschutzfreundliche Start ist:

- nur technisch notwendige Cookies für Warenkorb, Auth, Sicherheit und Cookiepräferenz;
- serverseitige, datensparsame Betriebsmetriken;
- kein Werbe-/Cross-Site-Tracking im MVP.

WooCommerce-Warenkorb-, Sitzungs-, Login- und Sicherheitscookies können für die ausdrücklich angeforderte Shopfunktion notwendig sein. WooCommerce Order Attribution, Reichweitenmessung, Werbepixel, externe Videos/Maps und Social-Media-Skripte gehören nicht automatisch in diese Kategorie und bleiben zunächst deaktiviert. Externe Payment-Skripte werden möglichst erst im Checkout beziehungsweise nach Auswahl der Zahlungsmethode geladen; Express-Payment-Schaltflächen auf Produkt- oder Warenkorbseiten werden wegen möglicher früher Datenübertragung gesondert geprüft.

Wenn später Analytics, Marketing oder Social-Media-Tracking hinzukommen:

- kein Setzen vor aktiver Zustimmung;
- „Ablehnen“ genauso einfach wie „Akzeptieren“;
- keine vorausgewählten Kategorien;
- Nachweis von Version und Auswahl;
- jederzeit erreichbarer Widerruf;
- Skripte technisch blockieren, nicht nur textlich versprechen;
- Einwilligung regelmäßig erneuern; die CNPD nennt üblicherweise maximal zwölf Monate.

Nach jeder Plugin-/Theme-Aktivierung und nach größeren Updates wird das Cookie-/Local-Storage-/Netzwerk-Inventar erneut geprüft. Ein Cookie-Plugin allein ist kein Compliance-Nachweis: Es muss nicht notwendige Skripte tatsächlich bis zur Einwilligung blockieren. „Zustimmung durch Scrollen“, bloßes Weiternutzen oder ein erschwertes Ablehnen wird nicht verwendet.

Quelle: [CNPD – Cookie-Grundsätze](https://cnpd.public.lu/fr/dossiers-thematiques/cookies0/cookies/principes-applicables.html).

## 12.10 Marketing-E-Mails

Das Gesetz über elektronischen Geschäftsverkehr regelt kommerzielle Kommunikation. Für Werbung an natürliche Personen ist grundsätzlich vorherige Einwilligung relevant. Für bestehende Kunden kann unter Bedingungen Werbung für ähnliche eigene Produkte möglich sein, wenn bei Erhebung und in jeder Nachricht ein einfacher kostenloser Widerspruch angeboten wird. Newsletter-Einwilligung darf nicht Voraussetzung für den Kauf sein. Quelle: [Luxemburger Gesetz über den elektronischen Geschäftsverkehr, Art. 48](https://legilux.public.lu/eli/etat/leg/loi/2000/08/14/n8).

Im Backend:

- Einwilligungsquelle und Wortlaut;
- Zeitpunkt und Double-opt-in-Ereignis;
- Zweck/Kanal;
- Widerruf/Sperrliste;
- niemals Marketing an operative Abmeldung senden.

## 12.11 Textilkennzeichnung

Bei Textilien mit mindestens 80 Gewichtsprozent Textilfasern muss die vollständige Faserzusammensetzung korrekt gekennzeichnet und beim Onlineverkauf vor dem Kauf sichtbar gemacht werden. Ursprüngliche Hersteller-/Faseretiketten dürfen nicht entfernt, verdeckt oder verfälscht werden. Wer unter eigener Marke anbietet oder Etiketten verändert, kann regulatorisch eine Herstellerrolle übernehmen.

Quellen: [EU-Verordnung 1007/2011](https://eur-lex.europa.eu/eli/reg/2011/1007/oj) und [Your Europe – Textilkennzeichnung](https://europa.eu/youreurope/business/product-rules-compliance/textiles-and-footwear/textile-label/index_de.htm).

Im Produkt-Backend:

- Faserzusammensetzung je Sprache;
- Herstellerangaben;
- Pflegeinformation;
- Quelle/Lieferantenbeleg;
- Bild des physischen Etiketts;
- Änderungs-/Prüfdatum.

Die konkrete Sprachpflicht für Luxemburg ist vor Start mit ILNAS beziehungsweise fachkundiger Stelle zu bestätigen. Deutsch und Französisch parallel sind praktisch sinnvoll, ersetzen diese Prüfung aber nicht.

## 12.12 Allgemeine Produktsicherheit (GPSR)

Artikel 19 der EU-Produktsicherheitsverordnung verlangt bei Onlineangeboten insbesondere:

- Herstellername/Handelsname;
- Post- und elektronische Adresse des Herstellers;
- bei Hersteller außerhalb der EU die verantwortliche Person in der EU;
- Produktidentifikation einschließlich Bild, Typ und Kennung;
- notwendige Warn- und Sicherheitsinformationen.

Quelle: [EU-Verordnung 2023/988 über die allgemeine Produktsicherheit](https://eur-lex.europa.eu/eli/reg/2023/988/oj/deu?locale=de).

Backendfelder:

- Hersteller und EU-Verantwortlicher;
- Lieferant und Artikelnummer;
- interne SKU;
- Charge/Wareneingang;
- Bezugsrechnung;
- Faser-/Pflege-/Warnhinweise;
- Sicherheitsunterlagen;
- verwendete Veredelungsmaterialcharge;
- betroffene Aufträge/Kunden;
- Sperr-/Rückrufstatus.

Das System braucht einen Rückrufprozess: Charge suchen, Bestand sperren, betroffene Aufträge bestimmen, Kontakt-/Maßnahmenverlauf dokumentieren.

Ob eine konkrete Veredelung eine wesentliche sicherheitsrelevante Veränderung darstellt, ist produktbezogen zu bewerten. Reflexmaterial darf ohne entsprechende Prüfung nicht als zertifizierte Schutz- oder Sicherheitsausrüstung vermarktet werden.

## 12.13 Verpackungen und EPR

Ein Unternehmen, das Produkte professionell verpackt oder verpackte Produkte erstmals in Luxemburg in Verkehr bringt, kann als Verpackungsverantwortlicher unter die erweiterte Herstellerverantwortung fallen. Die genaue Rolle hängt von Einkauf, Import, Befüllung und Vertrieb ab und muss mit AEV/Valorlux geklärt werden.

Das Backend sollte schon ab Start pro Auftrag erfassen:

- Karton/Papier in Gramm;
- Kunststoffbeutel/Mailer in Gramm;
- Füllmaterial;
- Klebeband/sonstige Verpackung;
- Verkaufs- und Transportverpackung;
- Zielland;
- Lieferant und Materialtyp.

Damit lassen sich spätere Meldungen erzeugen. Quelle: [Administration de l’environnement – Verpackungen und Verpackungsabfälle](https://environnement.public.lu/fr/emweltprozeduren/Autorisations/Gestion_des_dechets_et_ressources/Emballages_et_dechets_demballages.html).

## 12.14 Beschwerden, ADR und eingestellte ODR-Plattform

Das Backend benötigt einen Beschwerdefall mit:

- Auftrag/Rechnung;
- Problemtyp;
- Meldedatum;
- Fotos/Dateien;
- Kommunikation;
- gesetzte Fristen;
- Lösung: Nacharbeit, Ersatz, Gutschrift, Ablehnung mit Begründung;
- Abschluss und Kosten.

Wenn eine Verbraucherbeschwerde intern nicht gelöst wird, sind Informationen über die zuständige ADR-Stelle und die Teilnahmeentscheidung auf einem dauerhaften Datenträger relevant. Als allgemeine Luxemburger Stelle kommt der kostenlose Service national du Médiateur de la consommation in Betracht. Quelle: [Guichet.lu – Médiateur de la consommation](https://guichet.public.lu/fr/citoyens/justice/protection-consommateur/reglement-extra-judicaire-litige-consommation/mediateur-consommation.html).

**Keinen alten Link zur EU-ODR-Plattform übernehmen.** Die Plattform wurde am 20. Juli 2025 eingestellt; neue Beschwerden waren bereits seit 20. März 2025 nicht mehr möglich. Quellen: [EU-Kommission – Abschaltung der ODR-Plattform](https://consumer-redress.ec.europa.eu/site-relocation_en) und [EU-Verordnung 2024/3228](https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX%3A32024R3228).

## 12.15 Rechtstexte technisch versionieren

Jede veröffentlichte Version speichert:

~~~text
document_type
language
version
content_hash
valid_from
valid_until
status: DRAFT / REVIEW / PUBLISHED / RETIRED
approved_by
approved_at
source_notes
~~~

Bei Bestellung werden nicht nur Checkboxen gespeichert, sondern:

- Dokumenttyp und Version;
- Hash/Archivkopie;
- angezeigter Personalisierungshinweis;
- Zeitpunkt;
- Actor/Kunde;
- Bestell- und Preis-Snapshot.

Eine neue AGB-Version verändert keine alte Bestellung.

---

# 13. Datenschutz, Sicherheit und Aufbewahrung

## 13.1 Wichtigste Sicherheitsmaßnahmen

### Identität und Rollen

- Besitzerkonten explizit einrichten;
- das aktuelle Prototyp-Muster „erster Schreibzugriff wird Admin“ nicht in WordPress übernehmen;
- serverseitige Capability-Prüfung mit `current_user_can()` auf jeder Adminseite, Mutation und jedem privaten Download;
- MFA für Owner, Admin und Finance;
- kurze Admin-Sitzungen und erneute Bestätigung für kritische Aktionen;
- sofortige Sperre und Sitzungswiderruf bei Austritt;
- keine gemeinsam genutzten Admin-Konten.

### Anwendung

- Laufzeitvalidierung aller API-Eingaben, danach typgerechte Bereinigung; Ausgaben kontextgerecht und möglichst spät escapen;
- WordPress-Nonces gegen CSRF verwenden, aber niemals als Ersatz für eine Berechtigungsprüfung;
- jeder REST-Endpunkt erhält eine konkrete `permission_callback`;
- sichere Security-Header und Content Security Policy;
- Rate Limiting für Login, Anfrage, Upload, Statuslink und Checkout;
- idempotente Checkout-, Zahlungs-, Scan- und Druckendpunkte;
- zentrale Fehlerbehandlung ohne interne Daten im Browser;
- Transaktionen für Nummern, Lager und Finalisierung;
- eigene SQL-Abfragen ausschließlich vorbereitet, zum Beispiel über `$wpdb->prepare()`;
- WooCommerce-Bestellungen ausschließlich über CRUD-APIs und HPOS-kompatibel verarbeiten;
- WordPress-Dateieditor deaktivieren;
- Cache/CDN für Warenkorb, Checkout, Konto, Admin, Proof-/Statuslinks, private Downloads und TPB-REST-Endpunkte ausschließen;
- WordPress-, WooCommerce-, PHP-, Theme- und Plugin-Updates zuerst auf Staging prüfen;
- Print-Agent mit widerrufbarem, eng begrenztem Gerätetoken statt menschlichem Adminpasswort;
- Abhängigkeitsupdates und regelmäßige Sicherheitsprüfung.

### Dateien

- privater Dateispeicher außerhalb des öffentlichen Webzugriffs beziehungsweise geschützter Download-Endpunkt;
- Upload-Quarantäne und Malware-Prüfung;
- erlaubte Typen/Größen;
- kurzlebige autorisierte Downloads;
- zufällige Objekt-IDs;
- keine aktiven SVG-/HTML-Dateien ungefiltert im Browser ausführen;
- Vorschau aus sicher gerendertem Rasterbild;
- Hash und Versionshistorie;
- automatische Löschjobs nach Aufbewahrungsregel.

### Finanzen

- keine Kartendaten selbst speichern;
- gehostete Zahlungsseite/Token eines Zahlungsanbieters;
- keine Kartenprüfnummer, vollständige Kartennummer oder ungefilterten Payment-Payloads in WordPress, MySQL, Logs oder Backups;
- signierte Webhooks und Replay-Schutz;
- Gutschriften, Rabatte und Rückzahlungen rollenbasiert;
- ausgestellte Rechnung im Backend nicht regulär editierbar; PDF/JSON hashgesichert und jede Abweichung nachweisbar;
- Exporte mit Hash und Audit.

## 13.2 Datenschutzfreundliche Datentrennung

| Datenart | Zugriff |
|---|---|
| Kundendaten und Adressen | Sales, Admin, Finance soweit erforderlich |
| Produktionsname/-nummer | Production nur für betroffene Jobs |
| Original-Logos | Prepress/Production, Admin |
| Einkaufspreise/Margen | Owner, Finance, gegebenenfalls Admin |
| Zahlungsreferenzen | Finance, Owner |
| Rechnungs-PDFs | Kunde und Finance/Admin |
| Audit-/Sicherheitslogs | Owner/autorisiertes Audit |

Ein Mitarbeiter in der Produktion benötigt normalerweise keine Rechnungsadresse oder interne Marge.

## 13.3 Vorschlag für ein Lösch- und Aufbewahrungskonzept

Die folgenden operativen Fristen sind **Vorschläge**, die vor Veröffentlichung mit Rechtsberatung, Buchhaltung und tatsächlichem Geschäftszweck bestätigt werden:

| Daten | Vorschlag / Rechtsgrund |
|---|---|
| ausgestellte/erhaltene Rechnungen, Gutschriften und relevante Buchungsbelege | 10 Jahre entsprechend luxemburgischen Buchungs-/TVA-Pflichten |
| kanonischer Rechnungs-JSON und Hash | gleich lange wie Rechnung |
| abgebrochener Warenkorb ohne Bestellung | 30 Tage |
| unverbindliche Anfrage ohne Auftrag | 12–24 Monate, abhängig von Nachfass-/Anspruchsbedarf |
| Upload zu abgebrochener Anfrage | 30–90 Tage |
| Produktions-Quelldatei nach Auftrag | definierte kurze Frist oder ausdrückliche Wiederbestellvereinbarung; nicht automatisch 10 Jahre |
| Proof/Freigaben | zusammen mit Vertragsnachweis nach geprüfter Anspruchs-/Aufbewahrungsfrist |
| Namens-/Nummernlisten | nach Produktion und Reklamationsfrist minimieren oder löschen |
| Marketingeinwilligung/-widerruf | solange nötig, um Einwilligung oder Sperre nachzuweisen |
| Sicherheits-/Auditlogs | risikobasiert, zum Beispiel 180–365 Tage; kritische Finanzereignisse länger im Fachjournal |
| Backups | rotierend; Löschung muss zeitversetzt auch Backups erreichen, soweit keine Pflicht entgegensteht |

Rechnungsbezogene Daten dürfen aufgrund gesetzlicher Pflichten länger gespeichert werden; das rechtfertigt nicht automatisch dieselbe Dauer für jedes Logo oder jede Namensliste. Zur zehnjährigen Rechnungsaufbewahrung siehe [AED – Pflichten von Steuerpflichtigen](https://pfi.public.lu/fr/professionnel/tva/lancement-activite-economique/obligations-assujetti.html) und [CNPD – gesetzliche Aufbewahrung](https://cnpd.public.lu/fr/dossiers-thematiques/psp/duree-conservation-donnes-service-paiement/base-liceite-conservation.html).

In WooCommerce werden unter **Konten & Datenschutz** konkrete Fristen für inaktive Konten sowie ausstehende, fehlgeschlagene, stornierte und abgeschlossene Bestellungen gesetzt. Leere Felder dürfen nicht unbemerkt zu unbegrenzter Speicherung führen. Steuerlich erforderliche Rechnungsdaten werden technisch von Kundenkonto, Logo, Warenkorb, Marketingdaten und kurzlebigen Logs getrennt.

Die WordPress-Datenexport- und Löschwerkzeuge erfassen TPB-Tabellen, private Dateien und externe Plugin-/SaaS-Daten nur, wenn das TPB-Plugin passende Exporter-/Eraser-Schnittstellen implementiert. Vor Livegang wird deshalb ein kompletter Auskunfts- und Löschtest durchgeführt. Die zentrale Suche muss über E-Mail/Kunden-ID auch Uploads, Proofs, CSV-Exporte, Diagnose-/Zahlungslogs, E-Mail-Outboxen und externe Dienste finden.

Bei einer Wiederherstellung aus Backup werden später erfolgte Kontosperren, Tokenwiderrufe, Einwilligungswiderrufe und ausgeführte Löschungen erneut abgeglichen. Gelöschte Daten dürfen in isolierten rotierenden Backups nur bis zum Ablauf der dokumentierten Sicherungsfrist verbleiben und nicht wieder in den normalen Betrieb zurückkehren.

## 13.4 Datenschutzvorfälle

Interner Ablauf:

1. Vorfall melden und Zeitpunkt dokumentieren.
2. Zugriff/Leck eindämmen, Beweise erhalten.
3. Betroffene Daten, Personen und Folgen bewerten.
4. Verantwortliche Person und Rechtsberatung einschalten.
5. Wenn voraussichtlich ein Risiko besteht, CNPD grundsätzlich binnen 72 Stunden informieren.
6. Bei hohem Risiko betroffene Personen verständlich informieren.
7. Maßnahmen und Entscheidung vollständig dokumentieren.
8. Ursache beheben und Wirksamkeit prüfen.

Quelle: [CNPD – Meldung von Datenschutzverletzungen](https://cnpd.public.lu/en/professionnels/obligations/violation-de-donnees/violation-donnees-rgpd.html).

## 13.5 Auditlog

Ein Audit-Ereignis enthält:

~~~text
id
aggregate_type / aggregate_id
event_type
from_state / to_state
actor_user_id / actor_role
workstation_id
occurred_at_utc
reason_code
correlation_id
before_hash / after_hash
metadata_json
~~~

Besonders zu protokollieren:

- Login, Rollenänderung, Sperre;
- Produkt-/Preisveröffentlichung;
- Nachlass unter Mindestpreis;
- Proof-Freigabe und -Widerruf;
- Produktionsübersteuerung;
- Lagerkorrektur;
- Rechnungsausstellung;
- Gutschrift/Rückzahlung;
- Export;
- Dateiabruf besonders sensibler Daten;
- Druck und Neudruck.

Das Auditlog ist append-only. Sensible Nutzdaten werden nicht unnötig komplett hineinkopiert; Hashes und gezielte Metadaten reichen häufig.

---

# 14. Lokale Entwicklung, Staging und Veröffentlichung

## 14.1 Lokale PHP-/WordPress-Umgebung

Mit der neuen Zielarchitektur ist XAMPP grundsätzlich möglich, weil WordPress, WooCommerce und das eigene Plugin auf PHP und MySQL basieren. Für einen unkomplizierten Start gelten diese Optionen:

| Werkzeug | Eignung | Empfehlung |
|---|---|---|
| LocalWP | sehr einfache WordPress-Installation, SSL und lokale Domains | einfachster Einstieg für dieses Projekt |
| Laragon | übersichtliche PHP-/MySQL-/Composer-Umgebung unter Windows | gut, wenn auch andere PHP-Projekte entwickelt werden |
| XAMPP | funktioniert mit Apache, PHP und MySQL/MariaDB | verwendbar, aber WordPress und lokale Domains benötigen mehr Handarbeit |

Benötigt werden lokal:

- eine mit Hostinger kompatible PHP-Version; bevorzugt PHP 8.3, sofern alle finalen Plugins diese Version unterstützen;
- MySQL oder MariaDB;
- Composer 2 für PHP-Abhängigkeiten und Qualitätstools;
- Git;
- optional Node.js nur zum Bauen von CSS/JavaScript, nicht als Produktionsserver;
- eine lokale WordPress-Installation mit WooCommerce;
- ein E-Mail-Fänger statt echtem Versand;
- Zahlungsanbieter ausschließlich im Sandbox-/Testmodus.

Die tägliche Entwicklung findet nicht im WordPress-Dateieditor und nicht direkt auf Hostinger Production statt.

## 14.2 Strikt getrennte Umgebungen

| Umgebung | Daten | Zweck | Besonderheiten |
|---|---|---|---|
| Local | ausschließlich synthetische Seeds/Testdateien | tägliche Entwicklung und Drucksimulation | kein Zugriff auf Produktionsschlüssel oder echte Kundendaten |
| Staging | künstliche oder ausdrücklich minimierte Testdaten | Abnahme, Plugin-/Theme-Updates, Migrationen und Integrationen | Zugangsschutz, `noindex`, Testzahlung, E-Mail-Sandbox, keine Live-Webhooks |
| Production | echte Geschäftsdaten | öffentlicher Shop und Backend | Änderungen nur als geprüfte Freigabe mit Backup und Rollbackplan |

Alle drei Umgebungen haben eigene Datenbanken, Uploadverzeichnisse, Secrets, Zahlungszugänge, Webhook-Schlüssel und Mailziele. Eine vollständige Kopie der Produktionsdatenbank wird nicht routinemäßig auf Entwicklerrechner oder Staging kopiert. Falls ein echter Fehler nur mit realen Strukturen reproduzierbar ist, werden die benötigten Daten minimiert, pseudonymisiert, verschlüsselt und nach einem festgelegten Termin gelöscht.

## 14.3 Projekt- und Git-Struktur

Der bestehende Next.js-Prototyp wird zunächst nicht überschrieben. Die WordPress-Implementierung entsteht parallel in einem klar abgegrenzten Projektbereich beziehungsweise in einem neuen Repository. Versioniert werden vor allem der eigene Code und die dazugehörigen Tests:

~~~text
the-printing-brothers/
  docs/
  wordpress/
    wp-content/
      themes/
        tpb-theme/
      plugins/
        tpb-production-suite/
  tests/
    unit/
    integration/
    e2e/
    fixtures/
  tools/
    local-print-agent/
  composer.json
  composer.lock
  .env.example
~~~

Nicht in Git gehören:

- `wp-config.php`, echte `.env`-Dateien und Zugangsdaten;
- WordPress-Coredateien, wenn sie durch Hostinger/WordPress verwaltet werden;
- fremde Plugin-ZIP-Dateien ohne geklärte Lizenz;
- `wp-content/uploads`, Kundenlogos, Rechnungen, Belege und Backups;
- lokale Datenbank-Dumps;
- Cache, Logs und generierte temporäre Dateien.

Für jedes eingesetzte Fremdplugin werden Name, Anbieter, Lizenz, Version, Datenzugriffe, Updatequelle und Freigabestatus dokumentiert. Ein Plugin wird nicht nur deshalb installiert, weil es eine ähnliche Funktion verspricht.

## 14.4 Lokaler Referenzaufbau

~~~text
WordPress + TPB Theme
WooCommerce mit Beispielprodukten und Testbestellungen
TPB Production Suite
lokale MySQL-/MariaDB-Datenbank
privater lokaler TPB-Dateibereich
Seed-Daten und Testlogos ohne Personenbezug
E-Mail-Fänger statt realem Versand
Zahlungsanbieter-Sandbox
PDF-/Label-Renderer
Print-Mock, der Dateien statt Papier erzeugt
optional QZ Tray/Testdrucker auf Windows
~~~

Für den ersten Durchlauf wird kein echter Drucker benötigt. Der Print-Mock erzeugt PDFs/PNGs in exakt 203 und 300 dpi. Erst nach visueller und scannerbasierter Prüfung wird Hardware angebunden.

## 14.5 Code-, Datenbank- und Updateprozess

1. Bestehenden Prototyp und neue WordPress-Implementierung getrennt halten.
2. Einen überprüften lokalen Ausgangsstand versionieren.
3. Änderungen in kleinen, nachvollziehbaren Codepaketen entwickeln.
4. Datenbankänderungen des TPB-Plugins über `tpb_db_version`, `dbDelta()` für geeignete Schemaänderungen und explizite idempotente Datenmigrationen ausführen; keine spontanen SQL-Änderungen in Production.
5. Bestellungen nur über WooCommerce-CRUD-APIs lesen und schreiben; HPOS-Kompatibilität deklarieren und testen.
6. Seeds, Tests und Datenbankmigration gemeinsam aktualisieren.
7. Lokal Preis-, Checkout-, Upload-, Proof-, Produktions-, Rechnungs- und Berechtigungstests ausführen.
8. Auf Staging mit der vorgesehenen WordPress-, WooCommerce-, PHP-, Theme- und Plugin-Kombination prüfen.
9. Vor Freigabe ein Datenbank- und Dateibackup anlegen und den Rollbackweg festhalten.
10. Nur den geprüften Code veröffentlichen; Live-Daten und Live-Uploads bleiben unangetastet.

Größere WordPress-, WooCommerce- oder Fremdplugin-Updates werden nicht ungeprüft automatisch auf Production aktiviert. Sicherheitsupdates werden zeitnah zuerst auf Staging getestet. Das TPB-Plugin verändert niemals WordPress-, WooCommerce- oder Fremdplugin-Kerndateien.

Das Tabellenpräfix wird immer dynamisch über `$wpdb->prefix` ermittelt und niemals als `wp_` fest eingebaut. Deaktivierung, Theme-Wechsel oder ein normales Plugin-Update löschen keine TPB-Geschäftsdaten. Eine endgültige Datenlöschung erfordert einen separaten, ausdrücklich bestätigten und protokollierten Vorgang.

## 14.6 Hostinger-Ersteinrichtung

Empfohlener Zielplan ist Hostinger Business. Laravel würde zwar ebenfalls auf Hostinger Web/Cloud laufen, wird für die aktuell empfohlene WooCommerce-Lösung aber nicht zusätzlich benötigt. Die einmalige Einrichtung umfasst:

1. WordPress und WooCommerce auf einer geschützten Staging-Domain installieren;
2. einen EWR-Serverstandort wählen, soweit im konkreten Tarif verfügbar, und Vertragsgesellschaft/DPA dokumentieren;
3. PHP-Version und benötigte Erweiterungen festlegen;
4. separate MySQL-Datenbank, Datenbanknutzer und starke Secrets einrichten;
5. TPB Theme und TPB Production Suite installieren;
6. privaten Dateibereich und dessen Webschutz mit anonymem Zugriffstest prüfen;
7. einen echten Hostinger-Cronjob für Scheduler/Queue einrichten;
8. Transaktions-E-Mail mit SPF, DKIM und DMARC testen;
9. HTTPS, Cache-Ausnahmen, Firewall/Schutz, Zwei-Faktor-Anmeldung und Rollen konfigurieren;
10. Backup-Download und vollständige Wiederherstellung testen.

Hostinger Business stellt eine WordPress-Staging-Funktion bereit. Sobald der Shop live Bestellungen annimmt, darf „Staging nach Live veröffentlichen“ jedoch nicht als normaler Updateweg verwendet werden, wenn dabei die Live-Datenbank oder Uploads ersetzt würden. Sonst könnten neue Bestellungen, Zahlungen, Uploads oder Löschungen verloren gehen.

## 14.7 Veröffentlichung über GitHub und Hostinger

Hostinger kann GitHub-Repositories für PHP-Projekte über hPanel verbinden und manuell oder automatisch bereitstellen. Für The Printing Brothers wird die Automatik zunächst nur auf Staging verwendet. Der sichere Ablauf lautet:

~~~text
lokal entwickeln
→ lokale Tests
→ Code zu GitHub
→ Hostinger Staging aktualisieren
→ Abnahme und Backup
→ freigegebenen Code nach Production
→ Migrations-/Gesundheitsprüfung
→ Bestell-, Upload-, Checkout- und Cron-Smoke-Test
~~~

Veröffentlicht werden Theme, TPB-Plugin und notwendige gebaute Assets. Nicht übertragen oder überschrieben werden die Produktionsdatenbank, `wp-content/uploads`, der private TPB-Dateibereich, Rechnungsarchive, Secrets oder Hostinger-Konfigurationen.

Vor Aktivierung der Git-Verbindung wird der Zielpfad mit einer leeren Testseite geprüft. Hostinger weist darauf hin, dass ein Wechsel des verbundenen Repositorys Dateien im Zielverzeichnis überschreiben kann. Deshalb wird kein vollständiges Projekt unkontrolliert auf das gesamte `public_html` losgelassen. Je nach endgültiger Repository-Struktur werden entweder getrennte Theme-/Plugin-Zielpfade oder ein kontrolliertes Releasepaket verwendet. Ein Push zu GitHub aktualisiert Staging; Production wird bewusst mit einer freigegebenen Version angestoßen.

Für eine neue Plugin-Version gilt:

- vorwärtskompatible Datenbankänderung möglichst vor der neuen UI aktivieren;
- Migrationen versioniert, idempotent und nach Abbruch sicher fortsetzbar ausführen; geeignete Datenänderungen laufen transaktional, DDL-/`dbDelta()`-Schritte wegen möglicher impliziter Commits phasenweise mit Backup, Fortschrittsmarke und Gesundheitsprüfung;
- bei Fehlern keine halbfertige Schema-Version markieren;
- `tpb_db_version` erst nach vollständigem Erfolg erhöhen; eigene Geschäftstabellen mit InnoDB, sinnvollen Fremdschlüsseln/Indizes sowie Unique Constraints für kritische Nummern anlegen;
- Rechnungs-/Nummernsequenzen durch Zeilensperre und Unique Constraint gegen Parallelzugriff schützen;
- Code-Rollback und Daten-Restore getrennt planen;
- Cache leeren und PHP-/WordPress-/WooCommerce-Versionen protokollieren;
- nach dem Update eine kleine feste Prüfliste ausführen.

Ein reproduzierbares Release wird aus `composer.lock` gebaut. `composer install --no-dev --prefer-dist --optimize-autoloader` läuft lokal oder in einer kontrollierten CI-Umgebung; `composer update` läuft niemals auf Production. Das fertige Releasepaket enthält `vendor/` und alle gebauten CSS-/JavaScript-Dateien – alternativ muss ein verlässlich getesteter Post-Deploy-Installationsschritt existieren. Exakt dasselbe versionierte Artefakt wird zuerst auf Staging und anschließend auf Production verwendet. Releaseversion, Prüfsumme und Versionen aller zwingenden Fremdplugins stehen in einem Manifest.

Die bestehende Sites-/Cloudflare-Seite bleibt während der gesamten Migration als sichtbarer Prototyp erhalten. Die Domain wird erst auf Hostinger umgestellt, wenn Staging vollständig abgenommen, die letzte Datengrundlage vorbereitet und der Rückfallweg getestet ist.

## 14.8 Wie schwierig ist ein späteres Update?

| Vorgang | Erwartete Schwierigkeit | Warum |
|---|---:|---|
| erste Hostinger-/WordPress-Einrichtung | etwa 5 von 10 | Datenbank, private Dateien, Domain, Cron, Mail, Backups und Rechte werden einmal sauber eingerichtet |
| normale Änderung an Text, Preis oder Produkt im Backend | etwa 1 von 10 | erfolgt ohne Code direkt in WordPress/WooCommerce/TPB-Admin |
| geprüftes Theme-/Plugin-Update | etwa 2 von 10 | lokal testen, nach GitHub, Staging prüfen, freigeben |
| Datenbankmigration oder Zahlungsänderung | etwa 4–6 von 10 | benötigt Backup, Testfälle und kontrollierte Freigabe |

Ziel ist, dass der spätere Routineprozess aus wenigen klaren Schritten besteht. „Ein Klick direkt auf Live“ wird bewusst vermieden, weil ein Shop Bestellungen und Rechnungen enthält. Einfach bedeutet hier wiederholbar und sicher, nicht unkontrolliert.

## 14.9 Secrets

- lokale Geheimnisse nur in einer ignorierten Konfigurationsdatei;
- eine secrets-freie `.env.example` beziehungsweise Installationscheckliste mit Feldnamen und Erklärung;
- `wp-config.php`, WordPress-Salts und Hostinger-Zugangsdaten niemals in Git;
- keine Produktionsschlüssel in Testdaten, Screenshots oder Logs;
- getrennte Schlüssel pro Umgebung;
- Zahlungs-, Mail-, Backup-, Download- und Print-Signierschlüssel getrennt;
- Zwei-Faktor-Anmeldung für Hostinger, WordPress-Owner, GitHub und das Backupziel;
- individuelle statt gemeinsam genutzter Administratorkonten;
- zeitlich begrenzter externer Supportzugriff nach minimal notwendigen Rechten.

## 14.10 Lokale Seed-Szenarien

Mindestens:

1. Privatkunde, 20 T-Shirts, ein Logo;
2. Verein, mehrere Größen, Logo vorne, Sponsor hinten, Namen im Nacken;
3. Kundenware mit Eingangsfotos;
4. Auftrag mit fehlendem Material;
5. Proof-Änderung v1 zu v2;
6. Teilzahlung/Anzahlung;
7. Ausschuss und Nacharbeit;
8. Teillieferung;
9. ausgestellte Rechnung plus Gutschrift;
10. grenzüberschreitender Testkunde ohne echte Daten;
11. öffentlicher Auftrag mit UBL-Testexport;
12. Druckerfehler und begründeter Neudruck.

## 14.11 Migration und Domain-Umschaltung

1. Den bestehenden Next.js/Sites-Prototyp als unveränderten Referenzstand sichern.
2. Texte, Design-Tokens, Produktbilder, Mockups, Preisregeln und gewünschte Interaktionen inventarisieren.
3. WooCommerce-Produkte/Varianten und TPB-Preisregeln über einen wiederholbaren Import einspielen.
4. Keine D1-Testkunden, Testaufträge oder Browser-Uploads automatisch als echte Geschäftsdaten übernehmen.
5. WordPress-Version lokal vollständig entwickeln und mit synthetischen Daten testen.
6. Hostinger-Staging einrichten, absichern und den kompletten Referenzauftrag ausführen.
7. URLs, SEO-Titel, Metadaten, Weiterleitungen, Impressum, Datenschutz und Checkout prüfen.
8. Proof, Etikett, Rechnung, E-Mail, Zahlungssandbox, Backup und Restore abnehmen.
9. Ein kurzes Umschaltfenster, DNS-Schritte und einen klaren Rückfallplan festlegen.
10. Erst danach die Domain auf Hostinger umstellen; kein paralleles Schreiben in D1 und MySQL einführen.
11. Den alten Prototyp für eine begrenzte Rückfallfrist erhalten, danach Zugang und Daten kontrolliert archivieren beziehungsweise entfernen.

Vor der Umschaltung wird geprüft, ob zwischenzeitlich reale Anfragen auf der alten Seite eingegangen sind. Solche Vorgänge werden bewusst und protokolliert übernommen; eine pauschale Datenbankkopie findet nicht statt.

---

# 15. Umsetzungsphasen und Prioritäten

## 15.1 P0, P1 und später

| Priorität | Umfang |
|---|---|
| P0 – vor Erfassung echter Kundendaten | WordPress/WooCommerce-Basis, Rollen/Capabilities, MySQL, privater Dateispeicher, serverseitige Preise, Datenschutz/Rechtstexte, Audit und Backups |
| P1 – vor erstem bezahlten Liveauftrag | Kunden, Angebote, Aufträge, Proofs, grundlegendes Lager/Produktion, Rechnungen, Zahlungen, Reklamation und Restore-Test |
| P2 – Automatisierung | Print-Agent, Carrier-/Payment-Webhooks, Erinnerungen, E-Mail-Automation, Buchhaltungsexport |
| P3 – Ausbau | Peppol-Senden, Lieferantenintegration, erweiterte BI, mehrere Standorte, mobile Scanner-App |

## 15.2 Phase 0 – Sicheres Fundament

**Lieferumfang**

- überprüfter lokaler Git-Basisstand und parallel erhaltener Next.js-Prototyp;
- lokale WordPress-/WooCommerce-Basis, TPB Theme und Plugin-Skelett;
- Composer-/PHP-Abhängigkeiten festlegen; JavaScript ohne unnötige Buildkette;
- versionierte TPB-Schemamigrationen in MySQL;
- WooCommerce-CRUD- und HPOS-Kompatibilität;
- explizite Owner-Einrichtung und Capability-Modell;
- Auditlog und Idempotency;
- Business-/Steuereinstellungen;
- geschützter privater Dateispeicher und sichere Asset-Schicht;
- Hostinger-Staging mit getrennten Secrets und Testdiensten;
- lokale Seeds und neue Testbasis;
- Backup-/Restore-Prototyp.

**Fertig, wenn**

- kein unbekannter Nutzer Admin werden kann;
- lokale Umgebung ohne Produktionszugriff läuft;
- Beispielmigration auf leerer und bestehender DB funktioniert;
- Datei kann privat hochgeladen, geprüft und autorisiert geladen werden;
- Audit- und Restore-Test bestehen.

## 15.3 Phase 1 – Katalog, Preis und gespeicherter Konfigurator

**Lieferumfang**

- WooCommerce-Produkte, Varianten, Größen, Farben, Hersteller-/Textildaten;
- Preisbücher, Kosten-/Zeitversionen und zentrale Preis-Engine;
- Produktkarten und verbesserte Mockups;
- Größenmatrix;
- mehrere Logos/Positionen;
- gespeicherte Konfigurationsentwürfe;
- WooCommerce-Warenkorbintegration über `configuration_id` und serverseitige Neuberechnung;
- Upload und Preflight;
- nachvollziehbare Preisaufschlüsselung.

**Fertig, wenn**

- dieselbe Eingabe in UI und API denselben Preis ergibt;
- Manipulation des Browserpreises wirkungslos ist;
- Reload/anderes Gerät einen gespeicherten Entwurf wiederherstellen kann;
- alte Snapshots nach Preisänderung unverändert bleiben;
- Gutschein, Versand und Rundung in WooCommerce gemeinsam mit allen TPB-Aufpreisen stimmen.

## 15.4 Phase 2 – Angebot, Auftrag, Proof und Kundenportal

**Lieferumfang**

- WooCommerce-Kunden/Adressen und TPB-Leads;
- Angebote und PDFs;
- Annahme mit Rechtstext-Snapshot;
- WooCommerce-Bestellungen, TPB-Statusachsen und Gaststatuslink;
- Artwork-Versionen;
- Korrekturabzug und Änderungsrunde;
- nachweisbare Freigabe;
- Statuskommunikation.

**Fertig, wenn**

- ein kompletter Beispielauftrag ohne E-Mail-Schattenprozess abläuft;
- exakt die freigegebene Grafik gesperrt wird;
- Kunde Bestätigung und Proof dauerhaft speichern kann;
- jede Änderung nachvollziehbar ist.

## 15.5 Phase 3 – Produktion, Lager und Etiketten

**Lieferumfang**

- SKUs, Orte, Lots, Bewegungen und Reservierungen;
- Einkauf/Wareneingang;
- Produktionsjobs und Routen;
- Zeiten, Verbrauch, Ausschuss und QC;
- Job-/Stück-/Lager-/Paketetiketten;
- QR-Scanansicht;
- Print-Job-Warteschlange;
- zunächst PDF/Systemdialog.

**Fertig, wenn**

- verfügbarer Bestand aus Bewegungen reproduzierbar ist;
- Produktion ohne Pflichtfreigabe blockiert;
- doppelter Scan keine Doppelbuchung erzeugt;
- Etiketten auf 203/300 dpi lesbar sind;
- Plan- und Istkosten je Auftragsposition vorliegen.

## 15.6 Phase 4 – Rechnungen und Controlling

**Lieferumfang**

- Nummernserien;
- Rechnung/Gutschrift;
- MySQL + JSON + PDF + Hash;
- Zahlungen, Anzahlungen, Rückzahlungen;
- Ausgaben und Belege;
- offene Posten;
- Deckungsbeitrag und Plan/Ist;
- CSV-Exporte;
- 10-Jahres-Archivkonzept.

**Fertig, wenn**

- parallele Rechnungsausstellung keine doppelte Nummer erzeugt;
- ausgestellte Rechnung nicht editierbar ist;
- Gutschrift korrekt referenziert;
- PDF aus Snapshot reproduzierbar ist;
- Zahlung und Umsatz getrennt stimmen;
- Buchhalter einen Testexport akzeptiert.

## 15.7 Phase 5 – Automatisierung

**Lieferumfang**

- QZ Tray oder eigener Print-Agent;
- Versandadapter und manueller PDF-Fallback;
- Zahlungswebhooks;
- E-Mail-Outbox, Action Scheduler/Hostinger-Cron und Erinnerungen;
- Bestands-/Umsatzschwellenwarnungen;
- geplante Backups;
- UBL-Export und gegebenenfalls Peppol-Partner.

Ein öffentlicher bezahlter Verkauf startet erst, wenn die für das konkrete Angebot benötigten Funktionen aus den Phasen 0 bis 4 einschließlich Rechnung, Zahlung, Reklamation, Datenschutz und Restore-Test abgenommen sind. Die Phasennummer ist keine Erlaubnis für einen vorzeitigen Livebetrieb.

## 15.8 Empfohlener erster vertikaler Entwicklungsabschnitt

Nicht zuerst 50 leere Adminseiten bauen. Der erste Abschnitt sollte einen einzigen echten Weg vollständig lokal abbilden:

~~~text
Produkt + Variante
→ serverseitiger Preis
→ gespeicherte Konfiguration mit Logo
→ Kunde
→ Angebot
→ Annahme
→ Proof v1
→ Freigabe
→ Produktionsjob
→ PDF-Jobetikett
→ QC
→ Rechnungssnapshot
→ Plan-/Ist-Deckungsbeitrag
~~~

Danach wird derselbe stabile Musterweg auf Lager, Zahlungen, mehrere Positionen und Automatisierung erweitert.

---

# 16. Tests und Freigabekriterien

## 16.1 Preis- und Finanztests

- jede Mengenstaffel an den Grenzen, zum Beispiel 4/5, 9/10, 24/25, 49/50;
- Einrichtungsgebühr entfällt korrekt ab 10 Stück;
- mehrere Motive und Positionen werden richtig berechnet;
- Name/Nummer je betroffenem Stück;
- zweite Farbe/Lage;
- Express-Prozent nach klarer Rundungsregel;
- Mindestbestellwert;
- Rabatt unter Mindestmarge benötigt Freigabe;
- Cent-Rundung und Summen;
- Preisbuchwechsel verändert alte Aufträge nicht;
- TVA-Befreiung zeigt keinen Steuerbetrag und den korrekten Hinweis;
- Teilzahlung, Überzahlung, Rückzahlung und Gutschrift;
- Gewinn mit Istzeit, Ausschuss und Gebühren.

## 16.2 Prozess- und Datenbanktests

- jeder erlaubte und verbotene Statusübergang;
- Produktion ohne Proof/Anzahlung/Material;
- konkurrierende Bestandsreservierung;
- Inventurkorrektur mit Pflichtgrund;
- doppelte Webhooks, Scans und Druckjobs;
- parallele Nummernvergabe;
- Auftragsstorno nach Reservierung;
- Nacharbeit und Ausschuss;
- Teillieferung;
- Archiv/Soft Delete;
- Migration und Roll-forward.

## 16.3 Datei- und Sicherheitsprüfungen

- falsche Dateiendung/MIME;
- sehr große Datei;
- SVG mit aktiven Inhalten;
- Malware-Testdatei in sicherer Testumgebung;
- abgelaufener/erratener Statuslink;
- Zugriff Production auf fremden Auftrag;
- Rollenmatrix;
- CSRF und Rate Limit;
- persönliche Daten in Logs/QR-Codes;
- signierte Links nach Ablauf;
- Nutzer-/Sitzungssperre.

## 16.4 Rechnungs- und Archivtests

- PDF und JSON enthalten identische Summen;
- Hash bleibt unverändert;
- finalisierte Rechnung nicht überschreibbar;
- Gutschrift statt Löschen;
- vollständige Pflichtfelder;
- Sonderzeichen in DE/FR/LU-Namen;
- zehnjährige Retention-Regel nur auf passende Präfixe;
- täglicher Export;
- Wiederherstellung in isolierter Umgebung;
- UBL-Testdatei gegen Validator, sobald umgesetzt.

## 16.5 Drucktests

- 51 × 26, 62 × 100 und 102 × 152 mm;
- 203 und 300 dpi;
- Code 128 und QR mit mehreren Scannern/Smartphones;
- lange Produktnamen und Akzente;
- Name/Nummer ohne abgeschnittene Zeichen;
- Drucker offline;
- Papier leer;
- Agent-Neustart;
- Neudruck mit Grund;
- falscher Drucker/Format wird blockiert;
- Carrier-Label unverändert;
- physische Zuordnung Jobetikett zu Box/Beutel.

## 16.6 Nutzer- und Rechtsprüfung

- mobiler Checkout;
- Tastaturbedienung;
- Screenreader-Grundprüfung;
- klare Fehlermeldungen;
- Ablehnen/Akzeptieren von Cookies gleich einfach;
- Widerrufsausnahme nur bei personalisierter Ware;
- „Zahlungspflichtig bestellen“;
- speicherbare Bestätigung;
- Rechtstextversion im Auftrag;
- Impressumsdaten und 2D-Code;
- Produkt-/Textil-/GPSR-Felder;
- Beschwerde- und ADR-Prozess;
- keine alte ODR-Verlinkung.

## 16.7 WordPress-, WooCommerce- und Deploymenttests

- TPB-Plugin-Aktivierung auf leerer und bereits genutzter WordPress-Datenbank;
- Upgrade jeder unterstützten `tpb_db_version` und wiederholte, folgenlose Ausführung derselben Migration;
- HPOS-Kompatibilität und Bestellzugriff ausschließlich über WooCommerce-CRUD;
- klassischer Checkout vollständig; Checkout Blocks nur aktivieren, wenn Store-API-Integration nachgewiesen ist;
- Warenkorb-/Session-Wiederherstellung mit gespeicherter `configuration_id`;
- Gutscheine, Versand, Steuer, Rückerstattung und Rundung gemeinsam mit TPB-Aufpreisen;
- Capability-, Nonce-, REST-Permission-, CSRF- und gespeicherte-XSS-Tests;
- Testmatrix der freigegebenen PHP-, WordPress-, WooCommerce-, Theme- und Plugin-Versionen;
- Action Scheduler/Cron bei verspäteten, doppelten und fehlgeschlagenen Jobs;
- Cache-Ausnahmen für Warenkorb, Checkout, Konto, Admin, Proof und private Downloads;
- Hostinger-PHP-Limits für Uploadgröße, Arbeitsspeicher und Laufzeit mit realistischen Dateien;
- Plugin-Deaktivierung oder Theme-Wechsel löscht keine Geschäftsdaten;
- Code-Deployment enthält keinen lokalen Datenbankdump, Uploadordner, privaten Dateibestand oder Secret;
- vollständiger Hostinger- und Offsite-Restore in isolierter Umgebung;
- Smoke-Test nach jeder Veröffentlichung: Startseite, Konfigurator, Upload, Warenkorb, Checkout, Admin, Cron und privater Download.

---

# 17. Offene Geschäftsentscheidungen

Fest steht: Hostinger bleibt der Zielhoster, lokal wird vor jedem Staging-/Produktionsschritt entwickelt, und der bisherige Prototyp bleibt bis zur Abnahme erhalten. Die Hauptempfehlung lautet Hostinger Business mit selbst gehostetem WordPress/WooCommerce, MySQL, TPB Theme, TPB Production Suite in PHP und schlankem JavaScript für den Konfigurator; diese konkrete Plattformwahl wird vor dem Code-Umbau noch einmal ausdrücklich bestätigt.

Diese Fragen blockieren das Systemkonzept nicht, müssen aber vor der jeweiligen Implementierungsphase entschieden werden:

| Entscheidung | Empfohlener Start |
|---|---|
| Verkauf an B2C, B2B oder beide | beide unterstützen, Rechtstexte und Checkout klar trennen |
| Sprachen | Deutsch zuerst, Datenmodell sofort mehrsprachig; Französisch als nächste Pflichtversion |
| Steuerstatus | Art. 57bis nur nach offizieller/fachlicher Bestätigung aktivieren |
| Zahlungsanbieter | Anbieter mit gehostetem Checkout, SEPA/Karte; keine Kartendaten selbst |
| Transaktions-E-Mail | konkreten SMTP/API-Anbieter, Absenderdomain, DPA und Zustelltests festlegen |
| Fremdplugins | minimale finale Liste samt Lizenz, HPOS-/Checkout-Kompatibilität, Datenschutz und Updateverantwortung freigeben |
| Lokale Umgebung | LocalWP als einfachster Start; Laragon oder XAMPP möglich, danach eine Variante verbindlich festlegen |
| Rechnungszeitpunkt | mit Fiduciaire festlegen, besonders bei Anzahlungen |
| Versand | Abholung + ein Carrier; manueller Label-PDF-Import als Fallback |
| Etikettendrucker | Brother TD-4420DN oder Zebra ZD421d nach Testdruck |
| Scanner | kabelgebundener USB-2D-Imager |
| Lagerbewertung | gleitender Durchschnitt im MVP, Lots trotzdem speichern |
| Aufbewahrung Logos | kurze Standardfrist; längere Wiederbestellung nur transparent vereinbaren |
| Produkt-/Positionstoleranzen | intern testen, dokumentieren und juristisch prüfen |
| Buchhaltungsexport | Spalten/Konten mit gewähltem Fiduciaire abstimmen |
| E-Rechnung | UBL-fähiges Modell sofort; Senden erst bei öffentlichem Kundenbedarf |
| Verpackungs-EPR | Rolle mit AEV/Valorlux vor Versandstart klären |
| Hosting/Datenschutz | konkrete Hostinger-Vertragsgesellschaft, EWR-Serverstandort, DPA, Unterauftragnehmer, Support-/Backupstandorte und Offsite-Archiv prüfen |

## 17.1 Vor dem ersten Code-Umbau bestätigen

- rechtliche Firmenbezeichnung; der Markenname **The Printing Brothers** steht fest;
- wer Owner-Zugriff erhält;
- ob beide Gründer getrennte Konten benötigen;
- welche Produkte tatsächlich im MVP verkauft werden;
- Eigenware, Kundenware oder beides;
- Abholung, lokaler Versand, EU-Versand;
- welche zwei Testaufträge als Referenz dienen;
- ob zuerst Angebot oder direkter Checkout im Mittelpunkt steht;
- WordPress/WooCommerce-Hauptempfehlung gegenüber Laravel/Next.js endgültig bestätigen;
- konkreter Hostinger-Tarif/Kontovertrag, Serverstandort und Staging-Domain;
- erste freigegebene Pluginliste und Zahlungs-Sandbox.

Die risikoärmste MVP-Variante ist: Kunde konfiguriert, erhält einen serverberechneten Richtpreis und sendet eine strukturierte Angebotsanfrage. Nach Vollständigkeitsprüfung erhält er das Angebot; mit Annahme entsteht die WooCommerce-Bestellung. Nach gegebenenfalls erforderlicher Anzahlung folgen vollständige Artwork-Prüfung, Proof und Freigabe. Direkter vollautomatischer Checkout folgt, sobald Preise, Dateiprüfung, Lieferfähigkeit und Rechtstexte stabil sind.


## 17.2 Entscheidungsvorlage Plattform (Ergänzung v1.2)

Vor dem ersten Code-Umbau werden diese Fragen schriftlich beantwortet und mit Datum festgehalten:

1. Reicht Pfad A (Angebotsweg) für die ersten 6–12 Monate aus? Erwartetes Anfangsgeschäft sind Vereine, Firmen und Sammelbestellungen – typischerweise Angebot plus Anzahlung, kein Spontankauf.
2. Ist mindestens ein Gründer bereit, WordPress-/WooCommerce-Plugin-Entwicklung dauerhaft zu lernen und zu pflegen (Updates, Kompatibilität, Sicherheitsmeldungen)?
3. Welche Zahlungsarten braucht der Start wirklich? Überweisung mit strukturierter Referenz plus eine gehostete Onlinezahlung decken das Anfangsgeschäft meist vollständig ab.
4. Ergebnis des Vergleichs-Spikes aus 1.4, falls durchgeführt (Dauer, Eindruck, Blocker je Variante).
5. Getroffene Entscheidung mit Datum und Bestätigung beider Gründer – sowie die Festlegung, dass ein späterer Wechsel nur anhand dokumentierter Probleme erfolgt, analog zur Laravel-Regel in 1.1.

---

# 18. Offizielle Quellen

## 18.1 Luxemburg – E-Commerce und Verbraucher

- [Luxemburger Gesetz über den elektronischen Geschäftsverkehr](https://legilux.public.lu/eli/etat/leg/loi/2000/08/14/n8)
- [Guichet.lu – Fernabsatz B2C](https://guichet.public.lu/fr/entreprises/commerce/pratiques-commerciales/vente/a-distance-b2c.html)
- [Guichet.lu – Abschluss eines Fernabsatzvertrags](https://guichet.public.lu/fr/citoyens/justice/protection-consommateur/contrats-distance/conclusion-contrat-distance.html)
- [Guichet.lu – Preisangaben](https://guichet.public.lu/fr/citoyens/justice/protection-consommateur/indication-prix/indication-produits-services.html)
- [Guichet.lu – gesetzliche Konformitätsgarantie](https://guichet.public.lu/fr/entreprises/commerce/pratiques-commerciales/vente/garantie-conformite-application.html)
- [Guichet.lu – Médiateur de la consommation](https://guichet.public.lu/fr/citoyens/justice/protection-consommateur/reglement-extra-judicaire-litige-consommation/mediateur-consommation.html)
- [EU-Kommission – Abschaltung der ODR-Plattform](https://consumer-redress.ec.europa.eu/site-relocation_en)
- [EU-Verordnung 2024/3228 zur ODR-Plattform](https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX%3A32024R3228)

## 18.2 Luxemburg – Unternehmen, TVA und Rechnungen

- [Guichet.lu – Niederlassungsgenehmigung](https://guichet.public.lu/fr/entreprises/creation-developpement/autorisation-etablissement/autorisation-honorabilite/autorisation-etablissement.html)
- [Guichet.lu – Rechnungsstellung](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/facturation/encaissement/facture.html)
- [AED/PFI – Pflichtangaben auf Rechnungen](https://pfi.public.lu/fr/professionnel/tva/en-cours-activite-economique/que-doivent-contenir-factures.html)
- [AED/PFI – Kleinunternehmer-/PME-Regime](https://pfi.public.lu/fr/professionnel/tva/sme.html)
- [AED/PFI – Art. 57bis und Rechnungsvermerk](https://pfi.public.lu/fr/citoyen/tva/activite-supplementaire-faible-chiffre-affaires.html)
- [Luxemburger TVA-Gesetz, Fassung 2026](https://pfi.public.lu/dam-assets/pdf/legislation/tva/loi/loi-tva-2026-01-01.pdf)
- [Guichet.lu – grenzüberschreitende KMU-Befreiung](https://guichet.public.lu/fr/entreprises/fiscalite/impots-benefices/tva/regime-franchise/franchise-transfrontalier.html)
- [Guichet.lu – B2G-E-Rechnung](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/marche-public-concession/facturation/transmission-facture-electronique-marche-public-contrat-concession.html)
- [eFacturation Luxembourg](https://efacturation.public.lu/fr.html)
- [Guichet.lu – Buchführungspflichten](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/gestion-financiere-comptabilite/enregistrement-comptable/obligations-comptables.html)

## 18.3 Datenschutz

- [CNPD – Art. 13 DSGVO](https://cnpd.public.lu/fr/legislation/droit-europ/union-europeenne/rgpd/chapitre-3.html)
- [CNPD – Rechtmäßigkeit der Verarbeitung](https://cnpd.public.lu/fr/professionnels/obligations/obligations-rgpd/liceite.html)
- [CNPD – Verzeichnis der Verarbeitungstätigkeiten](https://cnpd.public.lu/fr/professionnels/obligations/obligations-rgpd/registre.html)
- [CNPD – Cookie-Grundsätze](https://cnpd.public.lu/fr/dossiers-thematiques/cookies0/cookies/principes-applicables.html)
- [CNPD – Meldung von Datenschutzverletzungen](https://cnpd.public.lu/en/professionnels/obligations/violation-de-donnees/violation-donnees-rgpd.html)
- [CNPD – gesetzliche Aufbewahrung von Rechnungsdaten](https://cnpd.public.lu/fr/dossiers-thematiques/psp/duree-conservation-donnes-service-paiement/base-liceite-conservation.html)
- [EDPB – Verantwortlicher und Auftragsverarbeiter](https://www.edpb.europa.eu/sme/learn-the-basics/data-controller-or-data-processor_en)

## 18.4 Produkte, Textilien, geistiges Eigentum und Verpackung

- [EU-Verordnung 1007/2011 – Textilfaserbezeichnungen](https://eur-lex.europa.eu/eli/reg/2011/1007/oj)
- [Your Europe – Textilkennzeichnung](https://europa.eu/youreurope/business/product-rules-compliance/textiles-and-footwear/textile-label/index_de.htm)
- [EU-Verordnung 2023/988 – allgemeine Produktsicherheit](https://eur-lex.europa.eu/eli/reg/2023/988/oj/deu?locale=de)
- [Guichet.lu – Marken](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/propriete-intellectuelle/propriete-industrielle/marque.html)
- [Guichet.lu – Urheberrecht](https://guichet.public.lu/fr/entreprises/gestion-juridique-comptabilite/propriete-intellectuelle/droits-auteur/defendre-droits-auteurs-droits-voisin.html)
- [Administration de l’environnement – Verpackungen](https://environnement.public.lu/fr/emweltprozeduren/Autorisations/Gestion_des_dechets_et_ressources/Emballages_et_dechets_demballages.html)
- [EU-Kommission – European Accessibility Act](https://commission.europa.eu/strategy-and-policy/policies/justice-and-fundamental-rights/disability/european-accessibility-act-eaa_en)

## 18.5 Technik, Speicherung und Etikettendruck

**Hostinger und Veröffentlichung**

- [Hostinger – GitHub-/Git-Deployment für PHP-Projekte](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/)
- [Hostinger – WordPress-Staging](https://www.hostinger.com/support/5720286-how-to-create-a-wordpress-staging-environment-in-hostinger/)
- [Hostinger – Backup-Download und Aufbewahrungsfenster](https://www.hostinger.com/support/5981435-how-to-download-backups-at-hostinger/)
- [Hostinger – von Backups ausgeschlossene Plugin-Archive](https://www.hostinger.com/support/which-files-are-excluded-from-hostinger-backups/)
- [Hostinger – Cronjobs](https://support.hostinger.com/en/articles/1583465-how-to-set-up-a-cron-job-at-hostinger)
- [Hostinger – Composer](https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/)
- [Hostinger – unterstützte Programmiersprachen und Frameworks](https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/)
- [Hostinger – PHP-Version ändern](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/)
- [Hostinger – Website Builder nach WordPress exportieren und Einschränkungen](https://www.hostinger.com/support/6572573-hostinger-website-builder-how-to-export-content-to-wordpress/)
- [Hostinger – automatisch installierte WordPress-Plugins](https://www.hostinger.com/support/6824127-what-is-the-hostinger-wordpress-plugin/)
- [Hostinger – Data Processing Addendum](https://www.hostinger.com/legal/dpa)
- [Hostinger – Serverstandorte](https://support.hostinger.com/en/articles/1583267-where-are-hostinger-servers-located)

**WordPress und WooCommerce**

- [WordPress – Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress – Tabellen in Plugins erstellen und aktualisieren](https://developer.wordpress.org/plugins/creating-tables-with-plugins/)
- [WordPress – Rollen und Capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/)
- [WordPress – Nonces](https://developer.wordpress.org/apis/security/nonces/)
- [WordPress – Eingaben bereinigen](https://developer.wordpress.org/apis/security/sanitizing/)
- [WordPress – Ausgaben escapen](https://developer.wordpress.org/apis/security/escaping/)
- [WordPress – Privacy im Plugin Handbook](https://developer.wordpress.org/plugins/privacy/)
- [WooCommerce – APIs](https://developer.woocommerce.com/docs/apis/)
- [WooCommerce – High-Performance Order Storage](https://developer.woocommerce.com/docs/features/high-performance-order-storage)
- [WooCommerce – HPOS-Kompatibilität für Erweiterungen](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/)
- [WooCommerce – Kompatibilität und Interoperabilität von Erweiterungen](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/compatibility)
- [WooCommerce – Bestellungen verwalten](https://woocommerce.com/document/managing-orders/)
- [WooCommerce – Product Add-Ons](https://woocommerce.com/document/product-add-ons/)
- [WooCommerce – Custom Product Designer](https://woocommerce.com/document/custom-product-designer/)
- [WooCommerce – personenbezogene Daten aus Bestellungen entfernen](https://woocommerce.com/document/managing-orders/removing-personal-data-from-orders/)
- [WooCommerce – Order Attribution](https://woocommerce.com/document/order-attribution-tracking/)
- [WooCommerce – Datenschutz bei Zahlungserweiterungen](https://woocommerce.com/document/privacy-payments/)
- [Action Scheduler – Hintergrundjobs für WordPress/WooCommerce](https://actionscheduler.org/)

**Dateien, Druck und Identifikation**

- [OWASP – File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [MDN – window.print](https://developer.mozilla.org/en-US/docs/Web/API/Window/print)
- [QZ Tray – Getting Started](https://qz.io/docs/getting-started)
- [QZ Tray – Raw Printing](https://qz.io/docs/raw)
- [Brother – QL-1110NWBc](https://store.brother.be/fr-be/devices/label-printer/ql/ql1110nwbc)
- [Brother – TD-4D-Serie](https://www.brother.eu/-/media/product-downloads/devices/label-printers/td/td4550dnwbfc/en/datasheet-td-4d-linerless.pdf)
- [Zebra – ZD421 Spezifikationen](https://www.zebra.com/content/dam/zebra_dam/en/tech-specs/zd421-tech-specs-en-us.pdf)
- [Zebra – DS2208 Scanner](https://www.zebra.com/gb/en/products/scanners/general-purpose-handheld-scanners/ds2200-series/ds2208.html)
- [GS1 – Traceability Standard](https://www.gs1.org/standards/traceability)


---

# 19. Aufwand, Zeitplan und Kosten

*Ergänzt in Version 1.2. Das Konzept beschreibt den Zielzustand, aber bisher keinen Aufwand. Für zwei Gründer im Nebenerwerb ist das die größte praktische Gefahr: Der beschriebene Endausbau entspricht einem kleinen ERP. Dieser Abschnitt setzt bewusst grobe Rahmen, damit Umfangsentscheidungen ehrlich getroffen werden.*

## 19.1 Annahmen

- Entwicklung abends und am Wochenende, etwa 8–12 produktive Stunden pro Woche;
- intensive Nutzung KI-gestützter Entwicklung mit klaren Handoff-Dokumenten je Phase;
- eine Person entwickelt schwerpunktmäßig, die zweite testet, pflegt Inhalte und liefert Fachvorgaben (Produkte, Preise, Texte, Prozesse);
- die Schätzungen umfassen Entwicklung, Test und Korrektur, aber nicht Rechtstexte, Fotografie, Produktdaten oder Behördengänge.

## 19.2 Grobe Aufwandsrahmen bis zum verkaufsfähigen Pfad-A-MVP

| Phase | Umfang (Kurzform) | TPB Pure | WooCommerce-Variante |
|---|---|---:|---:|
| Phase 0 | Fundament, Rollen, Migrationen, privater Dateispeicher, Audit | 40–60 h | 60–90 h |
| Phase 1 | Katalog, Preis-Engine, gespeicherter Konfigurator, Upload/Preflight | 60–90 h | 70–110 h |
| Phase 2 | Angebot, Auftrag, Proof, Statuslink | 60–90 h | 60–100 h |
| Phase 3 | Produktion, Basis-Lager, Etiketten als PDF | 50–80 h | 50–80 h |
| Phase 4 | Rechnung, Zahlungserfassung, Ausgaben, Plan/Ist | 40–70 h | 40–70 h |
| **Summe** | **verkaufsfähiger Pfad-A-MVP** | **ca. 250–390 h** | **ca. 280–450 h** |

Bei 10 Stunden pro Woche entspricht das grob **6–9 Monaten** (TPB Pure) beziehungsweise **7–11 Monaten** (WooCommerce, inklusive Einarbeitung). Die Zahlen sind Planungsrahmen, keine Zusage. Ihre wichtigste Aussage: Jede zusätzliche Funktion vor dem ersten echten Auftrag kostet Wochen, nicht Stunden.

## 19.3 Konsequenzen für den Umfang

Der vertikale Referenzpfad aus 15.8 ist nicht nur der erste Entwicklungsabschnitt, sondern der Maßstab: Was er für den ersten echten Auftrag nicht braucht, wird verschoben.

**Streichkandidaten für die ersten Aufträge** (auch innerhalb von P0–P4):

- CSV-Namensimport – die ersten Namenslisten werden manuell erfasst;
- Kundenkonto – der Statuslink aus 4.3 genügt;
- Einkauf/Wareneingang als eigenes Modul – anfangs genügen Bestandskorrektur mit Pflichtgrund und Beleg-Upload;
- Reklamationsmodul – für die ersten Aufträge genügt ein strukturierter E-Mail-Prozess mit Ablage im privaten Dateibereich;
- Berichte über Dashboard-Minimum und Plan/Ist-Deckungsbeitrag hinaus.

**Nicht streichbar, auch nicht für Auftrag Nummer 1:** serverseitiger Preis, Snapshots, transaktionale Rechnungsnummern, privater Dateispeicher, geprüfte Rechtstexte, Audit-Grundlage und getestete Backups – das entspricht der P0-Definition aus 15.1.

## 19.4 Kostenpositionen

Vor dem Start werden konkrete Beträge eingeholt und als Ausgabenplan festgehalten:

- Hostinger-Tarif (empfohlen Business) und Domain(s);
- Etikettendrucker samt Rollenmaterial und USB-2D-Scanner (Kapitel 9) – Kauf erst nach lokalem PDF-Testdruck;
- Zahlungsanbieter: prozentuale Gebühr plus Fixbetrag je Transaktion; als direkte Gebühr in die Preis-Engine aufnehmen, wie in 5.5 vorgesehen;
- Transaktions-E-Mail-Dienst mit eigener Absenderdomain;
- gegebenenfalls kostenpflichtige Plugins/Lizenzen (nur WooCommerce-Variante);
- Fiduciaire und Rechtsberatung für AGB, Datenschutz, Steuerstatus und Kundenware-Klauseln – fest einplanen, nicht optional;
- Rücklage für Muster, Fehldrucke und Testmaterial.

## 19.5 Empfohlene erste 90 Tage

1. **Woche 1–2:** Entscheidungsvorlage 17.2 ausfüllen, gegebenenfalls Vergleichs-Spike aus 1.4; Git-Basisstand sichern; lokale Umgebung aufsetzen.
2. **Woche 3–8:** Phase 0 vollständig umsetzen. Parallel und mit Vorrang: Fiduciaire-Termin (Steuerstatus, Rechnungszeitpunkt, Exportformat) und Rechtstexte beauftragen – juristische Vorlaufzeiten sind länger als technische.
3. **Woche 9–12:** Vertikalen Referenzpfad (15.8) beginnen, bis lokal ein vollständiger Rechnungs-Snapshot erzeugt wird; Etiketten als PDF in 203 und 300 dpi testdrucken; ersten Backup-/Restore-Test bestehen.
4. **Meilenstein Tag 90:** Der komplette Referenzauftrag ist lokal durchspielbar. Erst danach folgen Hostinger-Staging und der Hardwarekauf.

Zusätzlich wird der Termin **27. September 2026** (harmonisierter EU-Gewährleistungshinweis, siehe 12.6) als fester Prüfpunkt in den Plan aufgenommen, da er mitten in die Umsetzungszeit fällt.


---

# 20. Betriebsvollständigkeit – das System als Firma

*Ergänzt in Version 1.3. Leitsatz: Jede wiederkehrende betriebliche Tätigkeit hat einen definierten Ort im System; was nicht im System stattfindet, hat eine definierte Schnittstelle dorthin. Nebenlisten (Excel, Notizzettel, Chatverläufe) sind keine zulässigen Datenorte – entsteht eine, ist das ein Systemmangel und wird als Anforderung erfasst, nicht geduldet. Die Streichliste aus 19.3 bleibt gültig: 20.2 und die Basis von 20.6 werden vor dem Go-live gebaut, der Rest planmäßig danach (Zuordnung in 20.9).*

## 20.1 Änderungsaufträge (Nachträge)

Der häufigste Realfall nach Annahme: „Noch zwei Shirts in L dazu.“ Snapshots bleiben unantastbar; Änderungen laufen ausschließlich über ein **Nachtragsangebot**, das die bestehende Bestellung referenziert:

1. Aus der Auftragsansicht wird die Konfiguration als neuer, bearbeitbarer Entwurf geklont und mit dem Auftrag verknüpft.
2. Der Nachtrag wird zum **aktuell veröffentlichten Preisbuch** bepreist (nicht zum alten – einfach, fair und ohne Sonderpfad; Ausnahme nur als bewusste manuelle Entscheidung mit Auditgrund).
3. Annahme per Token wie beim Hauptangebot; sie erzeugt **keine neue Bestellung**, sondern hängt zusätzliche Positionen an die bestehende an. Ursprüngliche Angebots- und Rechnungs-Snapshots bleiben binär unverändert; abgerechnet wird über die nächste Rechnung.
4. Betrifft der Nachtrag ein Motiv mit bereits fixierter Druckfreigabe, durchlaufen die betroffenen Positionen einen neuen Proof-Zyklus.
5. **Minderungen** (Positionen streichen) sind nur vor Produktionsstart zulässig; finanzieller Ausgleich läuft über den Gutschrift-Prozess aus Kapitel 11.
6. Kleinkorrekturen (z. B. Tippfehler in einem Namen) sind vor der Druckfreigabe direkte, auditierte Änderungen; danach gilt Punkt 4.

Ohne diesen definierten Weg landen Änderungswünsche garantiert in formlosen E-Mails – genau der Schattenprozess, den dieses Konzept verhindern soll.

## 20.2 Anzahlungsaufforderung

`Anzahlung erforderlich` existiert als Regel (7.3); es fehlte das Dokument, mit dem das Geld angefordert wird. Festlegung:

- Eigenes Dokument **„Zahlungsaufforderung (Anzahlung)“** mit eigener Nummernsequenz (Format `ZA-JJJJ-nnnnnn`) – ausdrücklich **keine Rechnung**: keine Nummer aus der Rechnungssequenz, keine Rechnungsangaben-Pflichtteile, klarer Hinweis, dass die Rechnung nach Leistung folgt.
- Inhalt: Betrag, Fälligkeit, Bankverbindung, **strukturierte Referenz = Auftragsnummer** (Grundlage für den Abgleich in 20.3), Auftragsbezug.
- Auslösung automatisch bei Auftragsbestätigung mit Anzahlungsbetrag > 0 (PDF + E-Mail über die Outbox); das Produktionsfreigabe-Gate aus 7.3 bleibt unverändert.
- Die steuerliche Einordnung und der exakte Wortlaut („keine Rechnung im Sinne von …“) werden von der Fiduciaire freigegeben – dieser Punkt gehört auf die Liste aus Kapitel 17 (Themenkreis „Rechnungszeitpunkt“).

## 20.3 Bankabgleich (Import und Zuordnung)

Die wöchentlich häufigste Verwaltungstätigkeit wird halbautomatisiert:

- **Import** des Kontoauszugs als CSV (Spaltenmapping der Hausbank) oder CAMT.053-XML. Die Originaldatei wird als Beleg im privaten Speicher abgelegt; doppelter Import derselben Datei und doppelte Einzelzeilen werden technisch verhindert.
- **Zuordnungsvorschläge:** exakte Auftrags-/Rechnungsnummer im Verwendungszweck ⇒ Vorschlag mit hoher Konfidenz; exakter Betrag plus Namensähnlichkeit ⇒ Vorschlag mit niedriger Konfidenz. **Nichts wird ohne Bestätigung gebucht.** Eine Bestätigung erzeugt Zahlung und Rechnungszuordnung in einem Schritt und aktualisiert die Zahlungsachse.
- **Ausgehende Zeilen** werden zu Ausgaben-Vorschlägen (Kategorie, optionaler Auftragsbezug, Beleg nachreichbar) – damit ist auch die Ausgabenseite aus Kapitel 11 ohne Nebenliste vollständig.
- Nicht zuordenbare Zeilen bleiben in einer sichtbaren „Offen“-Warteschlange auf dem Dashboard, bis sie zugeordnet oder begründet ignoriert werden.

Voraussetzung ist das Geschäftskonto (20.7) und die konsequente strukturierte Referenz auf allen Zahlungsaufforderungen und Rechnungen.

## 20.4 Kapazitätsplanung

Das System kennt Soll-Minuten je Job; es fehlte der Abgleich mit der real verfügbaren Zeit:

- Einstellung „verfügbare Produktionsminuten pro Woche“ (Geschäftsentscheidung, anfangs realistisch niedrig ansetzen).
- Wochenansicht: Summe der geplanten Minuten aller Jobs je Fälligkeitswoche gegen die verfügbaren Minuten, mit Ampel.
- Bei der Terminvergabe im Angebot/Auftrag wird die Auslastung der Zielwoche angezeigt. Es wird **nicht automatisch blockiert** – die Entscheidung bleibt beim Menschen, aber sie fällt sehenden Auges. Gebrochene Terminzusagen sind der teuerste Fehler eines kleinen Betriebs.

## 20.5 Wiedervorlage und Mahnwesen

- **Wiedervorlage:** konfigurierbare Regeln, z. B. „Angebot läuft in X Tagen ab und ist unbeantwortet“ ⇒ interner Aufgabenpunkt und optional eine freundliche Erinnerungsmail an den Kunden.
- **Mahnwesen:** „Rechnung X Tage überfällig“ ⇒ Zahlungserinnerung; weitere Y Tage ⇒ Mahnung Stufe 1. Textbausteine werden juristisch geprüft (inkl. Hinweis auf mögliche Verzugsfolgen); jede versendete Stufe wird an der Rechnung protokolliert und kann systemseitig nicht doppelt versendet werden. Eskalation darüber hinaus bleibt manuelle Entscheidung.
- **Tagesliste** auf dem Dashboard als operatives Cockpit: ablaufende Angebote, Proofs ohne Antwort, überfällige Rechnungen, offene Bankzeilen, blockierte Jobs. Ziel: Der Arbeitstag beginnt mit dieser Liste, nicht mit dem Postfach.

## 20.6 Healthcheck, Monitoring und Vertretbarkeit

- **Täglicher Healthcheck** (Cron): letztes erfolgreiches Backup, fehlgeschlagene Hintergrundjobs, Speicherplatz, hängende Quarantäne-Dateien. E-Mail an den Owner nur bei Problemen, dazu eine kurze Montags-Zusammenfassung („alles grün“ muss man auch mal lesen).
- **Externes Uptime-Monitoring** auf einen `/health`-Endpunkt ohne sensible Daten (kostenloser Dienst genügt).
- **Vertretbarkeit:** `RUNBOOK.md` mit den zehn wichtigsten Abläufen in Schrittform („Auftrag ohne den Entwickler abschließen“). Abnahmekriterium ist der **Vertretungstest**: Der zweite Gründer führt einen kompletten Referenzauftrag inklusive Nachtrag nur mit dem Runbook durch; jeder Stolperer ist ein UX-Befund und wird behoben.
- **Kontenbesetzung:** beide Gründer mit eigenen Konten (nie geteilt); Vorschlag: einer als `owner`, der zweite als `admin` + `finance`, damit im Vertretungsfall auch Rechnungen ausgestellt werden können. Festlegung wird in 17 dokumentiert.

## 20.7 Gründung und Pflichten außerhalb des Systems (Checkliste)

Diese Punkte laufen parallel zur Entwicklung und haben längere Vorlaufzeiten als jeder Code. Reihenfolge und Details vor Start mit dem House of Entrepreneurship (Chambre de Commerce) bzw. der Chambre des Métiers verifizieren – hier werden bewusst keine Fristen oder Gebühren behauptet.

| Punkt | Anmerkung | Zuständig | Status/Datum |
|---|---|---|---|
| Einordnung der Tätigkeit | Klären, ob Textilveredelung als Handwerk gilt (Chambre des Métiers) – beeinflusst die Niederlassungsgenehmigung | | |
| Autorisation d'établissement | Antrag über MyGuichet/House of Entrepreneurship | | |
| Rechtsform | Entreprise individuelle vs. SARL-S/SARL **mit Fiduciaire abwägen – Haftungsfrage, da Kundenware bedruckt wird** | | |
| RCS/LBR-Eintragung | je nach Rechtsform | | |
| AED-Anmeldung | inkl. Erklärung zur Kleinunternehmerregelung Art. 57bis (Franchise gilt nicht automatisch, sondern wird erklärt) | | |
| ACD / direkte Steuern | Einordnung mit Fiduciaire | | |
| CCSS-Anmeldung | Sozialversicherung Selbstständige | | |
| Geschäftskonto | Voraussetzung für 20.3; strukturierte Referenzen testen | | |
| Betriebshaftpflicht | **Pflichtprogramm** (fremde Ware, Textilverkauf); zusätzlich Geräte-/Inhaltsversicherung prüfen | | |
| Rechtstexte & Verträge | AGB, Datenschutz, Kundenware-Klauseln (12) – beauftragt in Woche 1–2 (19.5) | | |
| Stammdaten ins System | Seller-Snapshot-Felder nach Gründung befüllen (12.1) – **blockiert den Go-live** | | |

## 20.8 Bewusst außerhalb – mit definierter Schnittstelle

Vier Bereiche bleiben dauerhaft außerhalb des Systems, docken aber definiert an: die **Fiduciaire** (Abschluss und Steuererklärungen; Schnittstelle: Exporte und Belegablage aus Kapitel 11), die **Bank** (Geld fließt dort; Schnittstelle: Import 20.3), **Lohn** (nur relevant, falls je Personal eingestellt wird; dann extern), **Rechtsberatung** (Texte und Einzelfälle; Schnittstelle: versionierte Rechtstexte aus 12.7). Mit diesen vier Andockpunkten gilt: Die operative Firma läuft vollständig im System.

## 20.9 Zuordnung zur Umsetzung

| Baustein | Meilenstein (TPB-Pure-PROJECT.md v1.2) |
|---|---|
| 20.2 Anzahlungsaufforderung | M3 (mit dem ersten annehmbaren Angebot) |
| Ausgabenerfassung (20.3, Teilbaustein) | M6 |
| 20.6 Healthcheck, /health, Runbook-Grundgerüst | M7 (vor Go-live) |
| 20.3 Bankimport & Matching, 20.5 Wiedervorlage/Mahnwesen, Tagesliste | M8 |
| 20.1 Nachträge, 20.4 Kapazität, Vertretungstest | M9 |
| 20.10 Shop-Checkout mit Vollzahlung + automatischer Rechnung | M6b (zwischen M6 und M7, im Go-live enthalten) |
| 20.11 Standardprodukte (Hausdesigns) | M2 (Produktseite + Preislogik) – danach in allen Pfaden enthalten |
| 20.7 Gründungs-Checkliste | parallel ab Woche 1 (19.5) |


## 20.10 Direktkauf ab Go-live (Ergänzung v1.4)

Entscheidung: Der Shop (Pfad B) geht **zusammen mit** dem Angebotsweg live. Beide Pfade sind Eingänge in dieselbe Auftrags-Pipeline – der Shop für Privat- und Kleinaufträge, der Angebotsweg für Vereine, Firmen und Sammelbestellungen. Festlegungen:

- **Vollzahlung im Checkout.** Shop-Bestellungen werden zu 100 % vorab über eine gehostete Zahlungsseite bezahlt. Konsequenzen: keine Forderungen und kein Mahnwesen für Shop-Aufträge (20.5 betrifft dann nur den Angebotsweg), keine Anzahlungsaufforderung (20.2 bleibt Pfad-A-Instrument), Produktionsfreigabe hängt schlicht an „bezahlt“.
- **Automatische Rechnung – bei Zahlungseingang, nicht beim Bestellklick.** Erst der bestätigte Zahlungseingang stellt die Rechnung über den regulären Ausstellungsprozess (11.2) aus und markiert sie sofort als bezahlt. So entstehen keine Rechnungen für abgebrochene Checkouts und keine Stornolücken in der Nummernfolge. Die steuerliche Einordnung (Rechnungsstellung bei Vorauszahlung unter Art. 57bis) bestätigt die Fiduciaire.
- **Kein separater Warenkorb.** Die Konfiguration mit ihren Positionen ist der Warenkorb; der Checkout ist ein Formular mit Zusammenfassung, Rechtserklärungen und Weiterleitung. Kartendaten berühren das System nie.
- **Zahlungsanbieter über eine Abstraktionsschicht**, erster Adapter nach Entscheidungsblatt (Kriterien: gehostete Zahlungsseite, signierte Webhooks, Gebühren je Zahlart, Auszahlungsrhythmus, in Luxemburg relevante Zahlarten). Die Zahlungsgebühr wird als direkte Gebühr in die Preis-Engine aufgenommen (5.5). Wichtig: Das Anbieter-Onboarding (KYC) setzt die **gegründete Firma und das Geschäftskonto voraus** (20.7) – das blockiert den Shop-Go-live, nicht die Entwicklung (Testmodus).
- **Verbraucherpflichten** aus Kapitel 12 gelten im Checkout vollständig: Button-Lösung, transparente Endpreise, versionierte Rechtstexte, gesonderte Kenntnisnahme des Widerrufs-Erlöschens bei personalisierter Ware, Bestellbestätigung mit Vertragsinhalt.
- **Erstattungen** laufen im ersten Ausbau manuell (Anbieter-Backend) plus Gutschrift im System; ein automatischer Refund-Flow ist bewusst spätere Ausbaustufe.
- Zusätzlicher Aufwand grob **40–70 Stunden** (Meilenstein M6b in TPB-Pure-PROJECT.md v1.3); die 90-Tage-Reihenfolge aus 19.5 bleibt unverändert.


## 20.11 Zwei Produkttypen: Standardprodukte und Konfigurator-Produkte (Ergänzung v1.5)

Das Sortiment besteht aus zwei Typen, die überall gemeinsam funktionieren:

- **Konfigurator-Produkte:** Der Kunde gestaltet selbst (Logo/Text, Platzierung, Personalisierung). Voller Ablauf inklusive Proof und Kundenfreigabe.
- **Standardprodukte:** fertige Artikel mit **Hausdesign** (die Entwürfe aus dem bisherigen Prototyp werden hierzu das Startsortiment) oder unbedruckte Artikel. Der Kunde wählt nur Variante (Farbe/Größe) und Menge. Die Druckdefinition (Design, Platzierung, Maße, Technik, Versionsnummer) liegt im Katalog, nicht in der Bestellung – bei Bestellung wird sie in den Auftrag eingefroren.

Festlegungen:

1. **Ein Bestellentwurf für beides.** Standard- und Konfigurator-Positionen liegen im selben Entwurf und damit im selben Angebot, Checkout und Auftrag. Der reale Vereinsfall „30 gestaltete Trikots plus 5 Standard-Caps“ ist eine einzige Bestellung, ein Proof-Zyklus (nur für die Trikots), eine Rechnung.
2. **Kein Proof für Standardartikel.** Das Hausdesign ist vorab freigegeben; ein Auftrag, der nur Standardartikel enthält, kann nach Zahlungseingang bzw. Freigabe-Gate sofort in Produktion. Das macht Standardprodukte zum schnellsten Umsatzpfad des Shops.
3. **Einfache Preislogik.** Standardartikel haben reine Staffelpreise ohne Einrichtungs-, Technik- oder Personalisierungszuschläge; die Designkosten stecken in der Kalkulation (Kostenversion) des Produkts. Mindestbestellwert und Express gelten über die Gesamtbestellung.
4. **Produktion wie gehabt.** Auch Standardartikel erzeugen Produktionsjobs und Etiketten (made to order); das Etikett nennt Designname und Designversion. Eine spätere Ausbaustufe „ab Lager“ (vorproduzierte Bestseller, Versand ohne Produktionsjob) dockt am Lagermodul aus Kapitel 8 an und ist bewusst nicht Teil des ersten Ausbaus.
5. **Pflegeprozess:** Neue Hausdesigns sind Katalogpflege (Design-Asset, Platzierung, Maße, Preisstaffel) und damit die natürliche Daueraufgabe des zweiten Gründers – ohne Entwicklereinsatz erweiterbar.

---

## Schlussentscheidung

Die bestehende Website unter dem bisherigen Arbeitstitel MOMNT Studio bleibt eine gute visuelle und fachliche Vorlage. Als Produktionsplattform wird aktuell eine selbst gehostete WordPress-/WooCommerce-Anwendung auf Hostinger empfohlen; der Business-Tarif ist wegen Staging, täglichen Backups und Ressourcen sinnvoll, aber noch zu bestätigen. Das TPB Theme übernimmt die Gestaltung; die TPB Production Suite kapselt Konfigurator, Preis-Engine, Dateien, Proofs, Produktion, Istkosten, Rechnungsarchiv, Etiketten und Audit. PHP/MySQL bleiben damit nahe an den vorhandenen Kenntnissen, während WooCommerce den Standard-Shopkern liefert.

Der nächste sinnvolle Schritt ist ein paralleles lokales WordPress-Grundprojekt, ohne die bestehende Seite zu überschreiben. Zuerst entstehen ein sicheres Plugin-Skelett, Capability-Modell, MySQL-Schemaversionierung, privater Dateispeicher und serverseitige Preisberechnung. Danach wird ein kompletter Referenzauftrag von der Konfiguration über Proof und Produktion bis zu Etikett, Rechnung und tatsächlichem Gewinn umgesetzt. Erst nach erfolgreichem lokalen Test, Hostinger-Staging, Backup-/Restore-Test und ausdrücklicher Freigabe wird die Domain umgeschaltet.

So entsteht ein System, das nicht nur Bestellungen annimmt, sondern jeden Auftrag von der ersten Idee bis zum tatsächlichen Gewinn nachvollziehbar macht.
