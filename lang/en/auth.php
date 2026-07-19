<?php

/*
|--------------------------------------------------------------------------
| Authentication messages
|--------------------------------------------------------------------------
|
| Laravel ships English defaults for these – except `gesperrt`, which is ours.
| Without this file an instance running on the default locale (`en`) would show
| the raw key "auth.gesperrt" to a locked-out user.
|
*/

return [

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'gesperrt' => 'This account is blocked. Please contact the administration.',

];
