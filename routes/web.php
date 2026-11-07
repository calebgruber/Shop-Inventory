<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\WorkspaceController;
use App\Controllers\InventoryController;
use App\Controllers\OperationsController;
use App\Controllers\SettingsController;
use App\Controllers\ReportsController;

$auth = new AuthController();
$workspace = new WorkspaceController();
$inventory = new InventoryController();
$operations = new OperationsController();
$settings = new SettingsController();
$reports = new ReportsController();

$router->add('GET','/',fn() => redirect('/dashboard'));
$router->add('GET','/login',[$auth,'login']);
$router->add('POST','/login',[$auth,'password']);
$router->add('GET','/forgot-password',[$auth,'forgot']);
$router->add('POST','/forgot-password',[$auth,'requestReset']);
$router->add('GET','/reset-password',[$auth,'resetForm']);
$router->add('POST','/reset-password',[$auth,'reset']);
$router->add('POST','/passkeys/login/begin',fn() => $auth->passkeyBegin(false));
$router->add('POST','/passkeys/login/finish',fn() => $auth->passkeyFinish(false));
$router->add('POST','/passkeys/register/begin',fn() => $auth->passkeyBegin(true),'authenticated');
$router->add('POST','/passkeys/register/finish',fn() => $auth->passkeyFinish(true),'authenticated');
$router->add('POST','/passkeys/{id}/revoke',[$settings,'revokeKey'],'authenticated');
$router->add('POST','/logout',function (): void { App\Services\Auth::logout(); json_response(['redirect'=>'/login']); },'authenticated');

$router->add('GET','/dashboard',[$workspace,'dashboard'],'shows.view');
$router->add('GET','/shows',fn() => $workspace->listing('shows','Shows'),'shows.view');
$router->add('GET','/shows/add',fn() => $workspace->showForm(),'shows.edit');
$router->add('POST','/shows/add',fn() => $workspace->showSave(),'shows.edit');
$router->add('GET','/shows/{id}',[$workspace,'show'],'shows.view');
$router->add('GET','/shows/{id}/edit',[$workspace,'showForm'],'shows.edit');
$router->add('POST','/shows/{id}/edit',[$workspace,'showSave'],'shows.edit');
foreach (['overview','orders','revisions','pulls','returns','repairs','files','history','timeline','notes'] as $tab) {
    $router->add('GET','/shows/{id}/' . $tab,fn(int $id) => $workspace->show($id,$tab),'shows.view');
}
$router->add('GET','/shows/{id}/orders/add',fn(int $id) => $workspace->orderForm($id),'orders.edit');
$router->add('POST','/shows/{id}/orders/add',fn(int $id) => $workspace->orderSave($id),'orders.edit');
$router->add('GET','/shows/{id}/orders/{order}/revisions/add',[$workspace,'orderForm'],'orders.edit');
$router->add('POST','/shows/{id}/orders/{order}/revisions/add',[$workspace,'orderSave'],'orders.edit');
$router->add('POST','/shows/{id}/notes',[$operations,'note'],'shows.edit');
$router->add('POST','/shows/{id}/files',[$operations,'file'],'shows.edit');
$router->add('GET','/orders',fn() => $workspace->listing('revisions','Shop Orders & Revisions'),'shows.view');
$router->add('GET','/orders/{id}',[$workspace,'order'],'shows.view');
$router->add('GET','/revisions/{id}',[$workspace,'revision'],'shows.view');
$router->add('POST','/revisions/{id}/approval',function (int $id): void {
    App\Services\Orders::approve($id,App\Services\Validation::choice(input()['status'] ?? '',['Approved','Rejected'],'approval'));
    json_response(['redirect'=>'/revisions/' . $id]);
},'orders.approve');
$router->add('GET','/revisions/{id}/pull',[$operations,'pull'],'pulls.edit');
$router->add('POST','/revisions/{id}/pull',[$operations,'pullSave'],'pulls.edit');
$router->add('GET','/shows/{id}/return',[$operations,'returns'],'returns.edit');
$router->add('POST','/shows/{id}/return',[$operations,'returnSave'],'returns.edit');
$router->add('GET','/shows/{id}/transfer',[$operations,'transfer'],'transfers.edit');
$router->add('POST','/shows/{id}/transfer',[$operations,'transferSave'],'transfers.edit');
$router->add('GET','/operations/{id}',[$operations,'operation'],'shows.view');

$router->add('GET','/inventory',fn() => $workspace->listing('assets','Serialized Inventory'),'inventory.view');
$router->add('GET','/inventory/categories',[$inventory,'categories'],'inventory.view');
$router->add('GET','/inventory/packages',fn() => $workspace->listing('packages','Equipment Packages'),'inventory.view');
$router->add('GET','/inventory/rules',fn() => $workspace->listing('rules','Equipment Rules'),'inventory.view');
foreach (['category','item-type','package','rule'] as $resource) {
    $router->add('GET','/inventory/' . $resource . '/add',fn() => $inventory->form($resource),'inventory.edit');
    $router->add('POST','/inventory/' . $resource . '/add',fn() => $inventory->save($resource),'inventory.edit');
}
foreach (['category','item-type'] as $resource) {
    $router->add('GET','/inventory/' . $resource . '/{id}/edit',fn(int $id) => $inventory->form($resource,$id),'inventory.edit');
    $router->add('POST','/inventory/' . $resource . '/{id}/edit',fn(int $id) => $inventory->save($resource,$id),'inventory.edit');
    $router->add('POST','/inventory/' . $resource . '/{id}/delete',function (int $id) use ($resource): void {
        App\Services\Inventory::remove($resource,$id);
        json_response(['redirect'=>'/inventory/categories']);
    },'inventory.edit');
}
$router->add('POST','/inventory/assets/{id}/action',function (int $id): void {
    App\Services\Inventory::assetAction($id,input());
    json_response(['redirect'=>'/inventory/assets/' . $id]);
},'inventory.edit');
$router->add('POST','/inventory/rules/{id}/status',function (int $id): void {
    $active = App\Services\Validation::choice((string)(input()['active'] ?? ''),['0','1'],'rule status');
    App\Models\Database::transaction(function () use ($id,$active): void {
        $rule = App\Models\Database::one('SELECT * FROM equipment_rules WHERE id=? FOR UPDATE',[$id]) ?? throw new App\Services\HttpException(404,'Rule not found.');
        App\Models\Database::execute('UPDATE equipment_rules SET active=? WHERE id=?',[$active,$id]);
        App\Services\Audit::record('rule.status_changed','rule',$id,['before'=>$rule['active'],'active'=>(int)$active]);
        App\Services\Documents::generate('rules',0);
    });
    json_response(['redirect'=>'/inventory/rules']);
},'inventory.edit');
$router->add('GET','/inventory/import',[$inventory,'importForm'],'inventory.edit');
$router->add('POST','/inventory/import',[$inventory,'import'],'inventory.edit');
$router->add('POST','/inventory/reorder',function (): void { App\Services\Inventory::reorder(input()); json_response(['message'=>'Order saved.']); },'inventory.edit');
$router->add('GET','/inventory/assets/{id}',[$inventory,'asset'],'inventory.view');
$router->add('GET','/inventory/packages/{id}',[$inventory,'package'],'inventory.view');
$router->add('GET','/repairs',fn() => $workspace->listing('repairs','Repair Bench'),'reports.view');
$router->add('GET','/repairs/{id}',[$operations,'repair'],'reports.view');
$router->add('POST','/repairs/{id}',[$operations,'repairSave'],'repairs.edit');

$router->add('GET','/reports',[$reports,'index'],'reports.view');
$router->add('GET','/reports/timeline',[$reports,'timeline'],'reports.view');
$router->add('GET','/api/timeline',[$reports,'timelineData'],'reports.view');
$router->add('POST','/reports/generate',[$reports,'generate'],'reports.view');
$router->add('GET','/documents/{id}',[$reports,'download'],'reports.view');
$router->add('GET','/files/{id}',[$reports,'file'],'shows.view');
$router->add('GET','/settings',[$settings,'index'],'settings.edit');
$router->add('POST','/settings',[$settings,'save'],'settings.edit');
foreach (SettingsController::BRANDING as $key) {
    $router->add('GET','/branding/' . $key,fn() => $settings->branding($key));
}
$router->add('GET','/profile',[$settings,'profile'],'authenticated');
$router->add('POST','/profile/avatar',[$settings,'regenerate'],'authenticated');
$router->add('POST','/profile/password',[$settings,'password'],'authenticated');
$router->add('GET','/avatars/{id}',[$settings,'avatar'],'authenticated');
$router->add('GET','/users',fn() => $workspace->listing('users','Shop Users'),'users.view');
$router->add('GET','/users/add',fn() => $settings->userForm(),'users.edit');
$router->add('POST','/users/add',fn() => $settings->userSave(),'users.edit');
$router->add('GET','/users/{id}',[$settings,'userForm'],'users.edit');
$router->add('POST','/users/{id}',[$settings,'userSave'],'users.edit');
$router->add('GET','/api/table',[$workspace,'table'],'authenticated');
$router->add('GET','/api/search',[$workspace,'search'],'authenticated');
$router->add('POST','/api/workspaces',[$workspace,'workspaceSave'],'authenticated');
$router->add('GET','/api/views',[$workspace,'savedViews'],'authenticated');
$router->add('POST','/api/views',[$workspace,'saveView'],'authenticated');
