{{-- Inhalt und Einbindung einer eigenen Seite. Beides pflegen nur Administratoren
     (Systemeinstellungen → Footer) und wird ungefiltert ausgegeben – die Einbindung
     ist ausdrücklich für Skripte von Dienstleistern gedacht. --}}
@include('seiten.partials.inhalt-stil')

<div class="seiten-inhalt text-gray-700">
    {!! $seite->inhalt !!}
</div>

@if (filled($seite->einbindung))
    <div class="seiten-inhalt mt-6 text-gray-700">
        {!! $seite->einbindung !!}
    </div>
@endif
