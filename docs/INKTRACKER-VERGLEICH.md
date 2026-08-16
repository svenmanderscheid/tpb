# InkTracker (inktracker.app) – Vergleich & Adaptions-Vorschläge

*Erstellt 2026-08-16 auf Owner-Wunsch. Recherchebasis: öffentliche Marketing-/Tool-Seiten (Start, /compare, /tools, /tools/screen-printing-price-calculator, /blog/how-to-price-a-screen-printing-job, /support). Die eigentliche App liegt hinter Login/Trial – Aussagen zum App-Inneren sind aus Marketing/Blog/Preisrechner abgeleitet und als **unbestätigt** markiert.*

## Kernbefund

InkTracker ist **kein** grafischer Design-/Mockup-Konfigurator (kein Drag-&-Drop, kein Live-Mockup auf dem Shirt, keine mm-Platzierung – anders als InkSoft/DecoNetwork). Seine Stärke, die als „sehr guter Konfigurator" wahrgenommen wird, ist ein **schlanker, einbettbarer Quote-Wizard mit sehr transparenter, live aktualisierter Preislogik**.

**TPB Pure ist funktional breiter und tiefer** (echter Grafik-Konfigurator mit mm-Platzierung, Lager, Standardprodukte, unveränderliche Snapshots/Audit, LU-Steuerlogik). InkTracker gewinnt in genau zwei Punkten:
1. **Live-Rohlingspreise vom Lieferanten** (S&S Activewear, AS Colour) statt manueller Preisliste.
2. **UX-Schlankheit der Preisbildung**: chart-first, itemisierter Live-Breakdown, Preis pro Stück, Setup-Umlage sichtbar.

## Adaptions-Backlog (priorisiert, je Meilenstein)

| # | Idee | Meilenstein | Aufwand | Neu? |
|---|---|---|---|---|
| 1 | **Kundensichtbarer Preis-Breakdown** im Konfigurator (Print, Zweitposition, Rohling, Setup-Umlage, Preis/Stück) – die Engine liefert den Breakdown bereits (§6), nur UI | M2 | S | UI-Anforderung |
| 2 | **Setup-Umlage bei Kleinmengen sichtbar** + Hinweis „+X Stück → −Y €/Stück" (nutzt `SETUP_FEE_WAIVER_QTY` + Tier-Grenzen), Upsell zur nächsten Staffel | M2/M3 | S | UX neu |
| 3 | **Staffel-Matrix (Menge × Optionen) als Vorschau** aus dem publizierten Preisbuch, vor dem Konfigurieren | M2 | M | neu |
| 4 | **CSV-Import Lieferanten-Preisliste → neue `cost_version` mit Diff-Vorschau** (kein Runtime-Netzcall, §14.9-konform; passt in `cost_versions`/`cost_items`) | M1-Datenpflege / Betrieb | L | neu |
| 5 | **Teilbarer/vorbefüllter Konfigurator-Deep-Link** (Entwurf via `public_id` existiert bereits) – für Clubs/Broker bewusst als Teilen-Flow | M6b | S–M | teilw. vorhanden |
| 6 | **Leichtes Vereins-/Broker-Portal** (wiederkehrende Kunden sehen eigene Angebote/Aufträge) – passt zur Club-Zielgruppe des Angebotswegs | M8+ | L | neu, **Owner fragen** |
| 7 | **Marge-statt-Aufschlag** – TPB nutzt bereits `floor = ceil_div(selbst×10000, 10000−margin_bps)` → kein Handlungsbedarf, nur Bestätigung | M1 | – | bereits korrekt |

**Nicht adaptieren:** QuickBooks-Sync (TPB hat bewussten eigenen Bankabgleich M8 + Art.-57bis-Logik); ein grafisches Mockup-Tool gibt es bei InkTracker gar nicht zu kopieren – TPBs M2-Plan ist hier bereits überlegen.

## Offene Rückfragen an den Owner

- **Welchen konkreten Screen** von InkTracker empfindest du als vorbildlich? Belegbar ist ein Preis-Wizard, **kein** visueller Designer. Falls du einen grafischen Designer meinst, könnte eine Verwechslung mit InkSoft/DecoNetwork vorliegen. Das bestimmt, ob wir M2-UX (Preis-Transparenz) oder etwas anderes verstärken.
- **Broker-/Vereinsportal** (#6) überhaupt gewünscht, oder reicht der Angebotsweg über E-Mail-Links?
- **Lieferanten-Preisimport** (#4): welche LU/EU-Lieferanten, welches Exportformat (CSV-Spalten)?
