<?php

namespace App\Modules\Catalog\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
}
