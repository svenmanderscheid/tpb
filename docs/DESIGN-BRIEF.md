# Design-Brief für externe Gestaltung (Storefront + Admin)

Ziel: eine **eigene Markenidentität** für den Kunden-Shop von *The Printing Brothers* – weg vom
neutralen KI-Default hin zu etwas Wiedererkennbarem. Dieser Brief ist zum Weitergeben an ein
Design-Tool/eine Design-KI (v0, Figma-AI, Lovable, ChatGPT o. ä.) **oder** an eine:n Designer:in.

> **Wichtig für die Rückgabe an Claude Code:** Bitte am Ende dieses Dokuments die Abschnitte
> „Technische Muss-Regeln" und „Was ich zurückbrauche" beachten – sonst lässt sich das Ergebnis
> nicht in unseren Stack einbauen.

---

## 1. Was feststeht (Projektfakten)

- **Name:** The Printing Brothers (Kürzel TPB)
- **Was:** Individuell bedruckte Textilien (Siebdruck/Textildruck) aus **Luxemburg**
- **Produkte:** T-Shirts und Hoodies, Größen S–XXL, verschiedene Farben
- **Zwei Wege:**
  - *Einzelbestellung* → im **Konfigurator** gestalten, Preis sofort sehen, sofort bezahlen
  - *Vereine / Großbestellung* → **Angebot anfragen** (persönliches Angebot)
- **Herzstück:** der **Konfigurator** mit Live-Vorschau (Farbe, Größe, Motivposition)
- **Versand:** national Post Luxembourg (gratis ab 50 €), international DHL
- **Technik:** framework-frei, sehr schnell, datenschutzfreundlich (keine Fremd-Tracker)

## 2. Was DU noch festlegen solltest (bitte ausfüllen, nicht raten lassen)

- [ ] **Logo** (SVG bevorzugt) – oder Auftrag an die KI, 2–3 Logo-Vorschläge zu machen
- [ ] **Markenfarben** (1 Hauptfarbe + 1 Akzent, als Hex)
- [ ] **Schrift** (eine Headline- + eine Fließtext-Schrift; muss lokal hostbar sein, siehe Regeln)
- [ ] **Tonalität / Claim:** Wie klingen wir? (z. B. handwerklich-nahbar, jung-frech, premium-schlicht?)
- [ ] **USPs / „Warum bei uns":** 3–4 Punkte (z. B. echtes Handwerk, schnelle Lieferung, Beratung, Vereinsprofi)
- [ ] **Fotos:** eigene Produktfotos / Werkstatt / gedruckte Beispiele vorhanden? (stark empfohlen)

## 3. Die vier Dinge, die dem aktuellen Design fehlen (= die Aufgabe)

1. **Identität/Persönlichkeit** – eigenes Logo, Farbwelt, charaktervolle Schrift (der größte Hebel).
2. **Echtheit** – echte Produktfotos / gedruckte Beispiele / Gesichter statt grauer Silhouetten.
3. **Story & Ton** – Texte mit Charakter: Brüder, Luxemburg, Handarbeit, Tempo.
4. **Wiedererkennungs-Element** – ein signature Detail (Muster, Illustration, Print-Akzent).

## 4. Die zu gestaltenden Screens (bitte alle abdecken)

**Kundenseite (öffentlich):**
- Startseite (Hero + Produktübersicht)
- Produktliste
- **Konfigurator** (Formular links, Vorschau + Preis rechts) ← wichtigster Screen
- Kasse / Checkout (Formular + Bestellübersicht)
- Bezahlseite
- Angebot-Anfrage-Formular (Vereine)
- Statusseiten (Danke, Angebot angenommen, Zahlung bestätigt, Link ungültig)
- Rechtstexte

**Backoffice/Admin (intern, darf ein eigenes, ruhigeres Look&Feel haben):**
- Dashboard „Heute", Auftrags-/Produktions-/Lager-/Finanz-Listen (Tabellen, Badges, KPI-Kacheln, einfache Charts)

## 5. Technische Muss-Regeln (sonst nicht integrierbar!)

Unser Stack: **PHP-Server-Views + Vanilla-JS, kein Build-Step, strikte Content-Security-Policy
`default-src 'self'`.** Das Design muss deshalb:

- **Self-contained CSS** liefern (eine `.css`-Datei). **Kein** Tailwind-Build, **kein** SCSS, das
  erst kompiliert werden muss – oder als bereits **kompiliertes, statisches CSS**.
- **Keine externen Ressourcen:** keine Google Fonts per `<link>`, kein CDN, keine Fremd-Skripte,
  keine Remote-Bilder. **Schriften lokal** als Datei (woff2) einbinden. Bilder lokal ablegen.
- **Keine Inline-`style="…"`-Attribute und keine Inline-`<script>`** (verstößt gegen CSP).
  Alles über die CSS-Datei bzw. externe JS-Datei.
- **Kein React/Vue/Framework** für die Seiten. Reine HTML-Struktur + CSS. (Interaktion = Vanilla JS,
  das baue ich ein.)
- **Responsiv** (Mobile-first ok), **hell** für den Shop; Admin darf dunkel bleiben.

## 6. Was ich (Claude Code) zurückbrauche – je nach Weg

**Am besten (Variante A):** statische **HTML+CSS-Mockups** der Screens (self-contained, Regeln §5).
Ich übernehme CSS in `site.css`/`admin.css` und mappe das HTML auf unsere PHP-Views 1:1.

**Auch gut (Variante B):** ein **Style-Guide** – Logo (SVG), Farb-Hex, Schriftdateien (woff2),
Buttons/Karten/Formulare als Bild oder Figma-Link. Ich implementiere die Screens dann selbst.

**Zur Not (Variante C):** nur **Screenshots/Bilder** der gewünschten Optik. Ich baue es nach
(etwas mehr Interpretation, aber machbar).

> Wenn das Tool React/Tailwind ausgibt: kein Problem – schick es trotzdem, ich **portiere** es auf
> unser CSS und entferne die Build-Abhängigkeiten.

## 7. Fertiger Prompt zum Kopieren (für die Design-KI)

```
Entwirf eine Marken- und Web-Design-Identität für „The Printing Brothers", einen
Textildruck-/Siebdruck-Shop aus Luxemburg (T-Shirts & Hoodies, individuell bedruckt).
Zielgruppe: Privatkund:innen für Einzelstücke UND Vereine für Großbestellungen.
Kernfeature ist ein Online-Konfigurator mit Live-Vorschau.

Liefere:
1) 2–3 Logo-Vorschläge (als SVG).
2) Eine Farbpalette (1 Hauptfarbe + 1 Akzent + Neutrals, als Hex).
3) Zwei Schriften (Headline + Fließtext), die frei/lokal hostbar sind (woff2), keine reinen
   Google-Fonts-CDN-Lösungen.
4) Design für diese Screens als self-contained HTML + CSS (eine CSS-Datei, KEIN Tailwind-Build,
   KEINE externen Fonts/CDN/Skripte, KEINE Inline-Styles, KEIN React):
   - Startseite mit Hero + Produktübersicht
   - Konfigurator (Formular links, Produkt-Vorschau + Preis rechts)
   - Checkout (Formular + Bestellübersicht)
   Stil: hell, vertrauenswürdig, handwerklich, mit einem wiedererkennbaren Detail (Muster/
   Print-Akzent). Ton: [HIER DEINE TONALITÄT EINSETZEN, z. B. „handwerklich-nahbar, jung"].
   USPs, die sichtbar werden sollen: [HIER 3–4 PUNKTE EINSETZEN].
Beachte: strikte Content-Security-Policy default-src 'self' – deshalb alles self-contained,
lokal, ohne Inline-Style/Script.
```

*(Ersetze die [Platzhalter] durch deine Tonalität und USPs, bevor du den Prompt abschickst.)*
