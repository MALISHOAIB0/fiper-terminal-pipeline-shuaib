<?php

namespace App\Http\Controllers;

use App\Models\PageContent;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('pages.home', [
            'content' => PageContent::for('home'),
        ]);
    }
}
