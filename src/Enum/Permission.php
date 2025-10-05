<?php

namespace App\Enum;

enum Permission: string
{
    case LEAD_READ = 'lead_read';
    case LEAD_WRITE = 'lead_write';
    case CONTACT_READ = 'contact_read';
    case CONTACT_WRITE = 'contact_write';
}
