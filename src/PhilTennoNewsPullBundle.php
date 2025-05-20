<?php

//src/PhilTennoNewsPullBundle.php

namespace PhilTenno\NewsPull;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PhilTennoNewsPullBundle extends Bundle
{
  public function __construct()
    {
        error_log('PhilTennoNewsPullBundle geladen');
    }
}
