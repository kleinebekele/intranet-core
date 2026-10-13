{{--
    Wartungsseite (503) – während eines Deploys (deploy.sh, `artisan down --render`).
    Bewusst eigenständig mit eingebettetem CSS: Sie wird beim Abschalten einmal
    vorgerendert und muss auch dann stehen, wenn Assets gerade neu gebaut werden.
    Der Browser lädt nach 15 Sekunden selbst neu (Refresh-Kopfzeile + Skript als
    Rückfall) und landet wieder auf der gewünschten Seite.
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wird aktualisiert …</title>
    <style>
        :root { --hg: #f3f4f6; --karte: #ffffff; --text: #111827; --leise: #6b7280; --akzent: #4f46e5; }
        @media (prefers-color-scheme: dark) {
            :root { --hg: #111827; --karte: #1f2937; --text: #f9fafb; --leise: #9ca3af; --akzent: #818cf8; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
               background: var(--hg); color: var(--text); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .karte { width: 100%; max-width: 30rem; background: var(--karte); border-radius: 12px; padding: 32px;
                 box-shadow: 0 1px 3px rgb(0 0 0 / 0.1); text-align: center; }
        h1 { margin: 16px 0 8px; font-size: 1.25rem; }
        p { margin: 0; color: var(--leise); line-height: 1.6; }
        .kreis { width: 40px; height: 40px; margin: 0 auto; border-radius: 50%; border: 4px solid var(--hg);
                 border-top-color: var(--akzent); animation: drehen 1s linear infinite; }
        @keyframes drehen { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="karte">
        <div class="kreis" aria-hidden="true"></div>
        <h1>Das Intranet wird gerade aktualisiert</h1>
        <p>Das dauert nur wenige Sekunden. Die Seite lädt gleich von selbst neu.</p>
    </div>
    <script>setTimeout(function () { location.reload(); }, 15000);</script>
</body>
</html>
