<?php

/*
|--------------------------------------------------------------------------
| Validierungsmeldungen
|--------------------------------------------------------------------------
|
| Bewusst NICHT vollständig: Laravel greift für jeden fehlenden Schlüssel auf
| die englische Fassung zurück (APP_FALLBACK_LOCALE). Hier stehen die Regeln,
| die in dieser Anwendung tatsächlich vorkommen – der Rest kommt dazu, wenn er
| gebraucht wird, statt eine 120-Zeilen-Datei zu pflegen, die niemand liest.
|
| `attributes` unten sorgt dafür, dass in den Meldungen „E-Mail-Adresse" steht
| und nicht „email".
|
*/

return [

    'accepted' => ':attribute muss akzeptiert werden.',
    'active_url' => ':attribute ist keine gültige Internetadresse.',
    'after' => ':attribute muss ein Datum nach :date sein.',
    'array' => ':attribute muss eine Liste sein.',
    'before' => ':attribute muss ein Datum vor :date sein.',
    'between' => [
        'array' => ':attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
        'string' => ':attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':attribute muss ja oder nein sein.',
    'confirmed' => 'Die Wiederholung von :attribute stimmt nicht überein.',
    'current_password' => 'Das eingegebene Passwort ist nicht korrekt.',
    'date' => ':attribute ist kein gültiges Datum.',
    'different' => ':attribute und :other dürfen nicht gleich sein.',
    'digits' => ':attribute muss :digits Ziffern haben.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'exists' => ':attribute ist ungültig.',
    'file' => ':attribute muss eine Datei sein.',
    'filled' => ':attribute darf nicht leer sein.',
    'image' => ':attribute muss ein Bild sein.',
    'in' => ':attribute ist ungültig.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'max' => [
        'array' => ':attribute darf höchstens :max Einträge haben.',
        'file' => ':attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'mimes' => ':attribute muss eine Datei vom Typ :values sein.',
    'min' => [
        'array' => ':attribute muss mindestens :min Einträge haben.',
        'file' => ':attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'numeric' => ':attribute muss eine Zahl sein.',
    'required' => ':attribute muss ausgefüllt werden.',
    'required_if' => ':attribute muss ausgefüllt werden, wenn :other den Wert :value hat.',
    'same' => ':attribute und :other müssen übereinstimmen.',
    'string' => ':attribute muss Text sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',
    'url' => ':attribute muss eine gültige Internetadresse sein.',

    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute taucht in bekannten Datenlecks auf. Bitte wähle ein anderes.',
    ],

    'custom' => [],

    'attributes' => [
        'aktuelles_passwort' => 'Das aktuelle Passwort',
        'current_password' => 'Das aktuelle Passwort',
        'email' => 'Die E-Mail-Adresse',
        'favicon' => 'Das Favicon',
        'haupttitel' => 'Der Haupttitel',
        'logo' => 'Das Logo',
        'mail_stundenlimit' => 'Das Stundenlimit',
        'name' => 'Der Name',
        'password' => 'Das Passwort',
        'password_confirmation' => 'Die Passwort-Wiederholung',
    ],

];
