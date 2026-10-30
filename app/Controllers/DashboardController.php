<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class DashboardController
{
    public function index(): void
    {
        View::render('dashboard/index', ['title' => 'Dashboard', 'active' => 'dashboard']);
    }
}
