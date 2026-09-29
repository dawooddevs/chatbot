<?php
declare(strict_types=1);

use App\Session;
use App\Site;
use App\View;

$site = Site::find((int)(query('id') ?? 0));
if (!$site) {
    Session::flash('error', 'That website was not found.');
    redirect(admin_url('sites'));
}

View::display('admin.preview', ['site' => $site], null);
