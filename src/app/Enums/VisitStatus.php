<?php

namespace App\Enums;

enum VisitStatus: string
{
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case AutoClosed = 'auto_closed';
}
