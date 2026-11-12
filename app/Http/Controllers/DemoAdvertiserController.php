<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DemoAdvertiserController extends Controller
{
    private const array TITLES = [
        1 => 'Repotting houseplants without the stress',
        2 => 'Choosing the right pot and soil',
        3 => 'Aftercare: the first weeks in a new pot',
    ];

    public function show(int $page): View
    {
        return view('demo.advertiser', [
            'page' => $page,
            'title' => self::TITLES[$page],
            'titles' => self::TITLES,
        ]);
    }
}
