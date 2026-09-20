<?php
declare(strict_types=1);

use App\Auth;

Auth::logout();
redirect(admin_url('login'));
