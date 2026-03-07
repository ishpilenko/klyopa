<?php

declare(strict_types=1);

namespace App\Enum;

enum ToolType: string
{
    case Calculator = 'calculator';
    case Converter = 'converter';
    case Probability = 'probability';
    case Comparison = 'comparison';
    case Checker = 'checker';
}
