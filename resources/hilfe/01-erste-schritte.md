---
titel: Erste Schritte im Intranet
route: dashboard
kategorie: Grundlagen
position: 1
---

Das Intranet ist eine Sammlung von Modulen. Was Sie davon sehen, hängt an Ihren Rollen –
darum sieht die Startseite nicht bei allen gleich aus.

## Die Startseite

Auf der Startseite liegt für jedes Modul, das Sie benutzen dürfen, eine Kachel. Ein Klick
darauf öffnet die Startseite dieses Moduls.

Links begleitet Sie die **Seitenleiste** durch das Modul, in dem Sie gerade sind: Sie zeigt
dessen Unterseiten. Ganz unten in der Seitenleiste liegt Ihr Zugang zur Verwaltung, sofern
Sie einer sind.

## Der Fragezeichen-Knopf

Oben rechts in der Kopfzeile taucht auf vielen Seiten ein Fragezeichen auf. Es erscheint
genau dann, wenn es zu **dieser** Seite eine Anleitung gibt, und führt direkt dorthin – Sie
müssen also nicht erst im Wiki suchen.

Fehlt das Fragezeichen, gibt es zu der Seite noch keinen Text. Sagen Sie in dem Fall gern
Bescheid, das ist die beste Quelle für neue Anleitungen.

## Suchen statt blättern

Alle Anleitungen und Beiträge stehen im **Wiki**. Dort oben ins Suchfeld tippen ist meist
schneller, als sich durch die Kategorien zu klicken. Gefunden wird auch der Text *innerhalb*
einer Anleitung, nicht nur die Überschrift.

## Was Sie nicht sehen

Es kann sein, dass eine Anleitung bei Ihnen kürzer ist als bei einem Kollegen: Einzelne
Absätze lassen sich auf bestimmte Rollen begrenzen. Das ist Absicht und kein Fehler – so
steht in einer gemeinsamen Anleitung der Verwaltungsteil nur bei denen, die ihn brauchen.

## Für Administratoren

rollen: admin

Neue Module kommen über `composer require` auf den Server und werden mit
`php artisan modules:sync` in die Verwaltung übernommen. Erst danach lassen sich unter
**Verwaltung → Module** die Reihenfolge, der An/Aus-Zustand und vor allem die **Rollen je
Unterpunkt** einstellen.

Wichtig: Ein frisch synchronisierter Menüpunkt hat noch **keine** Rolle und ist deshalb nur
für Administratoren sichtbar. Das ist der sichere Standard – kein Modul geht versehentlich
für alle auf.
