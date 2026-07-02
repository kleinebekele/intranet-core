{{-- Umschalter zwischen den Verwaltungs-Bereichen. --}}
<nav class="mb-6 flex gap-1 border-b border-gray-200">
    <a href="{{ route('admin.modules.index') }}"
       @class([
           'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
           'border-indigo-600 text-indigo-700' => request()->routeIs('admin.modules.*'),
           'border-transparent text-gray-500 hover:text-gray-700' => ! request()->routeIs('admin.modules.*'),
       ])>Module</a>

    <a href="{{ route('admin.roles.index') }}"
       @class([
           'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
           'border-indigo-600 text-indigo-700' => request()->routeIs('admin.roles.*'),
           'border-transparent text-gray-500 hover:text-gray-700' => ! request()->routeIs('admin.roles.*'),
       ])>Rollen</a>
</nav>
