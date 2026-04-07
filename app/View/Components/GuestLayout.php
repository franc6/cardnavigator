<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Blade component for the guest/authentication shell.
 */
class GuestLayout extends Component
{
    /**
     * Render the component.
     *
     * @return View The layouts.guest Blade view used as the unauthenticated/auth-screen shell.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
