<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    AdminPanelProvider::class,
];
