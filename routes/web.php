<?php

use App\Site\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('site.home');

// Documentos legais: endereços fixos porque são citados no app, no painel e nos
// e-mails — mudar a URL quebra link publicado.
Route::get('/privacidade', [SiteController::class, 'privacidade'])->name('site.privacidade');
Route::get('/termos', [SiteController::class, 'termos'])->name('site.termos');
