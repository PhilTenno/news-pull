<?php

namespace PhilTenno\NewsPull;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class NewsPullBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}