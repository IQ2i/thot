<?php

namespace App\Enum;

enum MessageType: string
{
    case SYSTEM = 'system';
    case ASSISTANT = 'assistant';
    case USER = 'user';
}
