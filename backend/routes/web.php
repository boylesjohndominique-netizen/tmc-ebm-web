<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn() => response()->json(['system'=>'TMC Equipment Borrowing and Tracking Management System','status'=>'API running']));
