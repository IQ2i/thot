<?php

namespace App\Enum;

enum SourceType: string
{
    case GITLAB = 'gitlab';
    case GOOGLE_DOC = 'google-doc';
    case REDMINE = 'redmine';
}
