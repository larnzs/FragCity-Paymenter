<?php
// extensions/Others/ObsidianAboutPage/routes/web.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/about', function () {
    $raw = DB::table('settings')->where('key', 'obsidian.about')->value('value');

    $about = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $about = $decoded;
        }
    }

    return view('obsidian-about-page::front.about', [
        'about' => $about,
    ]);
})->middleware('web')->name('obsidian.about.show');
