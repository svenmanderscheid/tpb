# Changelog

## 2026-08-14 – Projektgrundlage
- Initialer Commit: CLAUDE.md, TPB-Pure-PROJECT.md v1.4, Systemkonzept v1.5, Begleitdateien
- Noch kein Code – nächster Schritt: Meilenstein M0 (Branch `m0-fundament`, Kickoff-Prompt in PROJECT.md Anhang A)

## 2026-08-14 – Lokale Umgebung eingerichtet
- Branch: `main` (Setup vor M0)
- Git-Repo initialisiert, Remote `origin` gesetzt, erster Commit; Push offen (SSH-Host-Key-Verifikation schlug fehl → manuell via GitHub Desktop)
- Datenbanken `tpb_dev` und `tpb_test` (utf8mb4/unicode_ci) angelegt
- Apache-VirtualHost `tpb.local` → `public_html/` ergänzt (Backup `httpd-vhosts.conf.bak-2026-08-14`); hosts-Eintrag `127.0.0.1 tpb.local` gesetzt
- `.env` aus `.env.example` erstellt (XAMPP-Standard: root/leer, `APP_ENV=local`, `MAIL_DRIVER=file`)
- Composer 2.10.2 installiert (offizieller getcomposer.org-Installer, Signatur verifiziert; winget-Paket nicht mehr verfügbar) nach `C:\xampp\php`, Wrapper `composer.bat`, `C:\xampp\php` im User-PATH
- Umgebung: PHP 8.2.12 (laut PROJECT.md §3 zulässiger Fallback für 8.3 – keine Syntax > 8.2 ohne Rückfrage)
- Offen: `git push` (Auth), Apache-Neustart durch Nutzer für aktive `tpb.local`-Auflösung
