<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Blade component for the authenticated application shell.
 */
class AppLayout extends Component
{
    /**
     * Render the component.
     *
     * @return View The layouts.app Blade view used as the authenticated-area shell.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
