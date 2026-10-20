<?php

namespace App\Enums;

enum ProductType: string
{
    case Single = 'Single Product';
    case Variable = 'Variable Product';
    case Size = 'Size Calculation';
    case Minimum = 'Minimum Qty or Price';
    case LargeFormat = 'Large Format';
    case Offset = 'Offset Service Price';
}
