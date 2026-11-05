<?php

namespace App\Modules\Platform\Enums;

enum StoreStatus: string
{
    case OpeningSoon = 'opening_soon'; // password-protected until published (FR-ONB-06)
    case Live = 'live';
    case Suspended = 'suspended';      // by a platform admin (FR-ACC-08)
}
