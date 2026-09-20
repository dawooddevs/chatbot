<?php
declare(strict_types=1);

use App\Auth;
use App\Session;
use App\Site;
use App\View;

$site = Site::find((int)(query('id') ?? 0), Auth::id());
if (!$site) {
    Session::flash('error', 'That website was not found.');
    redirect(admin_url('sites'));
}

View::display('admin.preview', ['site' => $site], null);
