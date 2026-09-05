<?php

declare(strict_types=1);

namespace App\Shared\Application\Home;

interface HomeDataProvider
{
    public function forCurrentUser(): HomeView;
}
