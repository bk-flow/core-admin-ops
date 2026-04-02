<?php

namespace App\Core\AdminOps\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('core-auth::cms.admin.dashboard-session.dashboard');
    }
}
