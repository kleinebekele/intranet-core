{{-- Hilfsfunktionen für Passkeys (WebAuthn): wandelt zwischen den
     Base64url-Texten des Servers und den Binärpuffern des Browsers um.
     Mehrfach eingebunden schadet nicht – es wird nur einmal definiert. --}}
<script>
    (function () {
        if (window.Passkey) {
            return;
        }

        const zuPuffer = (text) => {
            const b64 = text.replace(/-/g, '+').replace(/_/g, '/');
            const bin = atob(b64 + '='.repeat((4 - b64.length % 4) % 4));
            return Uint8Array.from(bin, (z) => z.charCodeAt(0)).buffer;
        };

        const zuText = (puffer) => {
            let bin = '';
            new Uint8Array(puffer).forEach((b) => { bin += String.fromCharCode(b); });
            return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        };

        const post = async (url, daten) => {
            const antwort = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(daten || {}),
            });
            if (antwort.status === 419) {
                throw new Error('Die Seite ist abgelaufen – bitte neu laden.');
            }
            // Kein JSON = umgeleitet (z. B. Sitzung abgelaufen) oder Serverfehler.
            if (!(antwort.headers.get('Content-Type') || '').includes('application/json')) {
                throw new Error('Unerwartete Antwort (' + antwort.status + ') – bitte die Seite neu laden.');
            }

            const json = await antwort.json();

            if (!antwort.ok) {
                throw new Error(json.meldung || json.message || 'Fehler ' + antwort.status);
            }

            return json;
        };

        window.Passkey = {
            post,

            verfuegbar: () => !!window.PublicKeyCredential && !!navigator.credentials,

            async anlegen(o) {
                o.challenge = zuPuffer(o.challenge);
                o.user.id = zuPuffer(o.user.id);
                o.excludeCredentials = (o.excludeCredentials || []).map((c) => ({ ...c, id: zuPuffer(c.id) }));

                const cred = await navigator.credentials.create({ publicKey: o });
                const r = cred.response;
                const schluessel = r.getPublicKey ? r.getPublicKey() : null;

                if (!schluessel) {
                    throw new Error('Dieser Browser liefert den Schlüssel nicht mit – bitte einen aktuellen Browser nutzen.');
                }

                return {
                    id: zuText(cred.rawId),
                    clientDataJSON: zuText(r.clientDataJSON),
                    authenticatorData: zuText(r.getAuthenticatorData()),
                    publicKey: zuText(schluessel),
                    publicKeyAlgorithm: r.getPublicKeyAlgorithm(),
                };
            },

            async anmelden(o, zusatz) {
                o.challenge = zuPuffer(o.challenge);

                const cred = await navigator.credentials.get({ publicKey: o, ...(zusatz || {}) });
                const r = cred.response;

                return {
                    id: zuText(cred.rawId),
                    clientDataJSON: zuText(r.clientDataJSON),
                    authenticatorData: zuText(r.authenticatorData),
                    signature: zuText(r.signature),
                    userHandle: r.userHandle ? zuText(r.userHandle) : null,
                };
            },

            /** Abbruch durch den Benutzer oder durch uns – keine Fehlermeldung wert. */
            abgebrochen: (fehler) => fehler && (fehler.name === 'AbortError' || fehler.name === 'NotAllowedError'),
        };
    })();
</script>
