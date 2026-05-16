<?php

namespace App\Enums;

enum MonitorStatusEnum: string
{
    case PENDING = 'pending';
    case UP = 'up';
    case DOWN = 'down';
}
