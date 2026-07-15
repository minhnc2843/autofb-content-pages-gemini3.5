<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use App\Models\Page;

// Update Setting
Setting::setValue('FACEBOOK_PAGE_ID', '1179438675246977');
Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'EAAO44LwTrh4BRxK1Ag5BunUuduXtZAaNin5tRftYZCxE0hGHh7qZAVwphz6AxP3Y5UBr3b56vDLXL1DUJNqWH9ztOIt90455u7W30rN4008YPZC7JDUbuFROZCmnRnZCMbiCVPqQXP41z3TazztML3q6NLnIF2HxjgRzzLtgepp2u6Oi8nCBJZCoZBdzSEqjhZCwvJlZAz27Im', true);

// Update Pages
$defaultPage = Page::where('slug', 'default-facebook-page')->first();
if ($defaultPage) {
    $defaultPage->update([
        'facebook_page_id' => '1179438675246977',
        'access_token' => 'EAAO44LwTrh4BRxK1Ag5BunUuduXtZAaNin5tRftYZCxE0hGHh7qZAVwphz6AxP3Y5UBr3b56vDLXL1DUJNqWH9ztOIt90455u7W30rN4008YPZC7JDUbuFROZCmnRnZCMbiCVPqQXP41z3TazztML3q6NLnIF2HxjgRzzLtgepp2u6Oi8nCBJZCoZBdzSEqjhZCwvJlZAz27Im',
    ]);
    echo "Default Page updated successfully!\n";
} else {
    echo "No default page found in database.\n";
}

echo "Settings updated successfully!\n";
