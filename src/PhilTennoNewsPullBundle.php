<?php
////NEWS-PULL -> src/PhilTennoNewsPullBundle.php

namespace PhilTenno\NewsPull;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PhilTennoNewsPullBundle extends Bundle
{
  public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
