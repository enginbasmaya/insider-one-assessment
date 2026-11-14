<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['service'=>'Insider One Notification API','status'=>'ok','docs'=>'/docs/openapi.yaml']));
Route::get('/docs/openapi.yaml', fn () => response()->file(base_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml']));
