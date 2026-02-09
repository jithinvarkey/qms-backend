<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QualityIssueController extends Controller
{
    public function index()
    {
        return QualityIssue::all();
    }

    public function store(Request $request)
    {
        return QualityIssue::create($request->all());
    }
}

