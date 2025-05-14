<?php

namespace PhilTenno\NewsPull;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PhilTennoNewsPullBundle extends Bundle
{
    public function boot(): void
    {
        error_log('NewsPullBundle::boot() wurde aufgerufen: ' . date('c'));
        parent::boot();
    }
}