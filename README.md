# ContaoRequestTokenBundle

Hält Formulare in Contao 4.13 hinter einem Einwilligungs-Tool funktionsfähig. Fehlt einer Seite der Request-Token, holt das Formular ihn direkt vor dem Absenden nach, statt mit „Ungültiges Anfrage-Token“ abzubrechen.

## Der Fehler

Wer direkt auf einer Seite mit Formular landet, über Google, einen Link oder eine Anzeige, bekommt beim Absenden:

> Ungültiges Anfrage-Token. Das Request-Token konnte nicht validiert werden. (HTTP 400)

Wer vorher eine andere Seite der Website aufgerufen hat, kann normal absenden. Im Log steht nichts, Contao protokolliert diese Antwort nicht. Deshalb fällt der Fehler bei Tests über die Startseite selten auf.

## Ursache

1. Der erste Aufruf kommt ohne Cookie. Contao 4.13 entfernt dann den Token aus der Seite, damit sie zwischengespeichert werden kann (`CsrfTokenCookieSubscriber`).
2. Das Einwilligungs-Tool (etwa consentmanager) setzt danach per JavaScript eigene Cookies.
3. Beim Absenden gehen diese Cookies mit. Mit Cookies verlangt Contao den Token, das Feld ist leer.

Den Seitencache abzuschalten ändert daran nichts: Der Token wird bei jeder Anfrage ohne Cookie entfernt. Contaos eigene Abhilfe, „Immer aus dem Cache laden“, fragt den Token am Ende der Seite an. Diese Anfrage geht meist ab, bevor das Einwilligungs-Tool sein Cookie gesetzt hat, und bekommt selbst einen leeren Token.

## Funktionsweise

- Auf jede Frontend-Seite mit einem Feld `REQUEST_TOKEN` kommt vor `</body>` ein kleines Skript, inline, ohne zusätzliche Datei.
- Ist der Token beim Absenden leer, lädt das Skript ihn über Contaos eigene Adresse `/_contao/request_token_script` nach und schickt das Formular danach ab. Diese Anfrage trägt dieselben Cookies wie das Absenden, Contao liefert einen gültigen Token.
- Ist der Token gefüllt, passiert nichts. Contao prüft den Token weiter wie bisher; der Schutz bleibt vollständig.
- Ein Handler, der das Absenden selbst abbricht (`preventDefault`), etwa eine eigene Prüfung, behält das letzte Wort.
- Backend, Unteranfragen und Antworten ohne HTML bleiben unberührt.

## Installation

```bash
composer require kreativsoehne/contao-request-token-bundle
```

Die Contao Managed Edition registriert das Bundle selbst. Es gibt nichts einzustellen.

## Grenzen

- Nur Contao 4.13. Contao 5 und 6 entfernen den Token nicht mehr aus der Seite; dort tritt der Fehler in dieser Form nicht auf.
- Das Skript steht inline. Erlaubt eine Content Security Policy keine Inline-Skripte (`script-src` ohne `'unsafe-inline'`), blockiert der Browser es.

## Entwicklung

```bash
composer test   # PHPUnit
composer lint   # PHP-CS-Fixer, PHPStan (max), Rector
composer fix    # Rector und PHP-CS-Fixer anwenden
```

## Lizenz

MIT, siehe [LICENSE](LICENSE).
