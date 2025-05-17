<?php

declare(strict_types=1);

namespace PhilTenno\NewsPull;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PhilTennoNewsPullBundle extends AbstractBundle
{
    // Die boot()-Methode ist bei AbstractBundle nicht mehr nötig und wird ignoriert.
    // Wenn du Initialisierungen brauchst, nutze stattdessen EventSubscriber oder Services.

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}