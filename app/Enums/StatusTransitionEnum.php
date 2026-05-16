<?php


namespace App\Enums;

enum StatusTransitionEnum: string
{
    case NO_CHANGE = 'no_change';
    case WENT_DOWN = 'went_down';
    case CAME_UP = 'came_up';
}
