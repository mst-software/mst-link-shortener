<?php

namespace App\Enums;

enum TokenResult: string
{
    case Valid = 'valid';
    case Tampered = 'tampered';
    case Reused = 'reused';
    case WrongAgent = 'wrong_agent';
    case WrongStep = 'wrong_step';
}
