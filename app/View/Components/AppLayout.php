<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  bool  $leisteSchmal  Linke Navigation (ab Desktop-Breite) als schmaler
     *                              Symbolstreifen, der beim Darüberfahren überlappend
     *                              aufklappt – für Seiten, die die ganze Breite brauchen
     *                              (z. B. Webmail): <x-app-layout :leiste-schmal="true">
     */
    public function __construct(public bool $leisteSchmal = false) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
