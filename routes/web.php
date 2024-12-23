<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

Route::get('/', function () {
    $markdown = new HtmlString(Str::markdown(config('labeler.description')));

    return view('welcome')->with(compact('markdown'));
});
