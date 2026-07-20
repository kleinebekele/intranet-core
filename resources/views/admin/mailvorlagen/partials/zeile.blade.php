{{-- Eine Zeile der Vorlagen-Übersicht. Erwartet $vorlage (VorlagenDefinition). --}}
<li>
    <a href="{{ route('admin.mailvorlagen.edit', $vorlage->schluessel) }}"
       class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 hover:border-indigo-300 hover:bg-indigo-50/40">
        <span>
            <span class="font-medium text-gray-800">{{ $vorlage->titel }}</span>
            <span class="block text-sm text-gray-500">{{ $vorlage->beschreibung }}</span>
        </span>
        <span class="flex items-center gap-2">
            @if (\App\Models\MailVorlage::find($vorlage->schluessel))
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">angepasst</span>
            @else
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Standard</span>
            @endif
            <i class='bx bx-chevron-right text-xl text-gray-400'></i>
        </span>
    </a>
</li>
