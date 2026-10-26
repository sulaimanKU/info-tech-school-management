<?php

namespace App\Http\Controllers;

use App\Services\ParentChildren;
use App\Support\Domain;
use Illuminate\Http\Request;

class ParentHomeController extends Controller
{
    public function index(Request $r, ParentChildren $children)
    {
        $items = $children->get($r->user());

        return Domain::response(['guardian' => $r->user()->profile()['guardian'], 'children' => $items])->header('Cache-Control', 'private, no-store');
    }
}
