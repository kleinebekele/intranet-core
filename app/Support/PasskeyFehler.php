<?php

namespace App\Support;

use RuntimeException;

/** Eine Passkey-Antwort des Browsers hat die Prüfung nicht bestanden. */
class PasskeyFehler extends RuntimeException {}
