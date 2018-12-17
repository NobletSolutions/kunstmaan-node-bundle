<?php

namespace NS\KunstmaanNodeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class NodeBundle extends Bundle
{
    public function getParent()
    {
        return 'KunstmaanNodeBundle';
    }
}
