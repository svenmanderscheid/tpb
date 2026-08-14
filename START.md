# START.md – Erste Session: Umgebung einrichten und Meilenstein M0 beginnen

**An Claude Code:** Diese Datei ist die Anweisung für die allererste Session im Projekt The Printing Brothers. Arbeite sie Schritt für Schritt ab. Lies zuerst `CLAUDE.md` und `TPB-Pure-PROJECT.md` (§3 Konventionen und §14 Arbeitsregeln sind verbindlich). Danach: Abschnitte A bis E in dieser Reihenfolge.

**Umgebung:** Windows mit XAMPP unter `C:\xampp`. Dieses Verzeichnis ist `C:\xampp\htdocs\TPB`. Shell-Befehle über PowerShell ausführen. Arbeite ausschließlich in diesem Verzeichnis und den genannten XAMPP-Konfigurationsdateien – fasse keine anderen Projekte unter `htdocs` an.

---

## A. Bestandsaufnahme (nichts ändern, nur prüfen)

Prüfe, ob diese Dateien vorhanden sind:

```text
CLAUDE.md
TPB-Pure-PROJECT.md
.gitignore
.env.example
docs/The-Printing-Brothers-Systemkonzept-v1.5.md
docs/OFFENE-FRAGEN.md
docs/CHANGELOG.md
docs/DECISIONS.md
```

Fehlt etwas davon: **stoppen** und den Nutzer bitten, die Dateien aus `tpb-init.zip` in dieses Verzeichnis zu entpacken. Nicht versuchen, die Inhalte zu rekonstruieren.

Prüfe außerdem die Werkzeuge und berichte die Versionen:

```powershell
git --version
php -v          # falls nicht im PATH: C:\xampp\php\php.exe -v
composer -V
```

Fehlt **git**: stoppen, Nutzer installieren lassen. Fehlt **composer**: den Nutzer fragen, ob du es installieren sollst (z. B. `winget install Composer.Composer`) oder ob er den Installer von getcomposer.org nutzt – ohne Composer kein M0. Fehlt nur der PATH-Eintrag für PHP: mit dem vollen Pfad `C:\xampp\php\php.exe` weiterarbeiten.

## B. Git-Repository

Remote: `git@github.com:svenmanderscheid/tpb.git` (das Repo existiert und ist leer).

1. Wenn kein `.git` vorhanden: `git init`, dann `git remote add origin git@github.com:svenmanderscheid/tpb.git`. Schlägt SSH später beim Push fehl, auf `https://github.com/svenmanderscheid/tpb.git` wechseln.
2. `git branch -M main`
3. Leere Ordner anlegen: `public_html\` und `private\` (private/ steht in `.gitignore` und bleibt lokal).
4. Erster Commit: `git add -A` → `git commit -m "docs: Projektgrundlage (PROJECT.md v1.4, Systemkonzept v1.5)"`.
   Schlägt der Commit mangels Git-Identität fehl, frage den Nutzer nach Name/E-Mail und setze sie mit `git config user.name` / `git config user.email` (nur lokal für dieses Repo).
5. `git push -u origin main`. Wenn der Push an der Authentifizierung scheitert: **nicht blockieren** – dem Nutzer sagen, dass er einmal in GitHub Desktop „Push origin" klickt, und mit Abschnitt C weitermachen.

## C. Lokale Umgebung

**C1 – Datenbanken** (MySQL muss im XAMPP Control Panel laufen; wenn nicht erreichbar, Nutzer bitten, es zu starten, dann erneut versuchen):

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS tpb_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS tpb_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**C2 – Apache VirtualHost** in `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

- Zuerst eine Sicherungskopie der Datei anlegen (`httpd-vhosts.conf.bak-<Datum>`).
- Nur ergänzen, wenn `tpb.local` dort noch nicht vorkommt.
- Enthält die Datei noch **keinen** aktiven `<VirtualHost>`-Block (Kommentare zählen nicht), zuerst diesen Default-Block anhängen, damit `localhost`/phpMyAdmin weiter funktionieren:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"
</VirtualHost>
```

- Danach den TPB-Block anhängen:

```apache
<VirtualHost *:80>
    ServerName tpb.local
    DocumentRoot "C:/xampp/htdocs/TPB/public_html"
    <Directory "C:/xampp/htdocs/TPB/public_html">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**C3 – hosts-Eintrag** (braucht Administratorrechte – **nicht versuchen, das zu umgehen**): Gib dem Nutzer diesen Befehl für eine als Administrator geöffnete PowerShell und warte auf seine Bestätigung:

```powershell
Add-Content C:\Windows\System32\drivers\etc\hosts "`r`n127.0.0.1 tpb.local"
```

**C4 – Abschluss:** Nutzer bitten, Apache im XAMPP Control Panel neu zu starten. `.env` aus `.env.example` anlegen (XAMPP-Standard: DB-User `root`, leeres Passwort, `APP_ENV=local`, `MAIL_DRIVER=file`).

## D. Setup abschließen

- Kurzer Statusbericht an den Nutzer: was erledigt ist, was offen bleibt (z. B. hosts-Eintrag, Push, Composer).
- Eintrag in `docs/CHANGELOG.md` unter dem heutigen Datum: „Lokale Umgebung eingerichtet (Git, DBs tpb_dev/tpb_test, VirtualHost tpb.local)".
- Diese Datei (`START.md`) nach erfolgreichem Setup nach `docs/archive/START.md` verschieben – sie wird nicht mehr gebraucht.

## E. Meilenstein M0 starten

Erst wenn A–D erledigt sind (offene Punkte, die M0 nicht blockieren – wie der hosts-Eintrag – dürfen offen bleiben):

Führe jetzt den Auftrag aus **Anhang A der `TPB-Pure-PROJECT.md`** aus. Zusammengefasst:

- Branch `m0-fundament` anlegen.
- Reihenfolge: Projektskelett + Composer → Core-Module (§4) → `migrations/001_init.sql` exakt wie in §5.2 → `cli/migrate.php`, `cli/user_create.php`, `cli/backup.php` → Auth/Login + leeres Admin-Dashboard → Asset-Upload/Preflight/Download (§8) → Outbox + Worker (§10) → Tests (§13) → Seeds-Grundgerüst.
- Fehlende Fachwerte: Platzhalter + Eintrag in `docs/OFFENE-FRAGEN.md` – **niemals erfinden**.
- **Beginne mit einem Umsetzungsplan (maximal 15 Zeilen) und warte auf das OK des Nutzers, bevor du Code schreibst.**
- Am Ende: DoD-Selbstprüfung M0 (PROJECT.md §12) als Checkliste ausgeben, `CHANGELOG.md` aktualisieren.

---

*Für den Nutzer: Diese Datei einfach in `C:\xampp\htdocs\TPB` legen, dort ein Terminal öffnen, `claude` starten und schreiben: „Lies START.md und arbeite sie ab."*
