<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Public Privacy Policy page
Route::view('/privacy-policy', 'legal.privacy-policy')
    ->name('privacy-policy');

// Public Account Deletion page
Route::view('/account-deletion', 'legal.account-deletion')
    ->name('account-deletion');