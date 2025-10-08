<?php

// app/Enums/UserRole.php
namespace App\Enums;

enum UserRole: string {
    case Standard = 'standard';
    case Corporate = 'corporate';
    case Administrator = 'administrator';
}
