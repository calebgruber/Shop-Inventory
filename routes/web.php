<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\WorkspaceController;
use App\Controllers\InventoryController;
use App\Controllers\OperationsController;
use App\Controllers\SettingsController;
use App\Controllers\ReportsController;
use App\Controllers\SetupController;

$setup = SetupController::class;
shop_route('GET','/setup',[$setup,'show']);
shop_route('POST','/setup',[$setup,'install']);

$auth = AuthController::class;
$workspace = WorkspaceController::class;
$inventory = InventoryController::class;
$operations = OperationsController::class;
$settings = SettingsController::class;
$reports = ReportsController::class;

shop_route('GET','/',fn() => shop_redirect('/dashboard'));
shop_route('GET','/login',[$auth,'login']);
shop_route('POST','/login',[$auth,'password']);
shop_route('GET','/forgot-password',[$auth,'forgot']);
shop_route('POST','/forgot-password',[$auth,'requestReset']);
shop_route('GET','/reset-password',[$auth,'resetForm']);
shop_route('POST','/reset-password',[$auth,'reset']);
shop_route('POST','/passkeys/login/begin',fn() => app($auth)->passkeyBegin(false));
shop_route('POST','/passkeys/login/finish',fn() => app($auth)->passkeyFinish(false));
shop_route('POST','/passkeys/register/begin',fn() => app($auth)->passkeyBegin(true),'authenticated');
shop_route('POST','/passkeys/register/finish',fn() => app($auth)->passkeyFinish(true),'authenticated');
shop_route('POST','/passkeys/{id}/revoke',[$settings,'revokeKey'],'authenticated');
shop_route('POST','/logout',function (): void { App\Services\Auth::logout(); json_response(['redirect'=>'/login']); },'authenticated');

shop_route('GET','/dashboard',[$workspace,'dashboard'],'shows.view');
shop_route('GET','/shows',fn() => app($workspace)->listing('shows','Shows'),'shows.view');
shop_route('GET','/shows/add',fn() => app($workspace)->showForm(),'shows.edit');
shop_route('POST','/shows/add',fn() => app($workspace)->showSave(),'shows.edit');
shop_route('GET','/shows/{id}',[$workspace,'show'],'shows.view');
shop_route('GET','/shows/{id}/edit',[$workspace,'showForm'],'shows.edit');
shop_route('POST','/shows/{id}/edit',[$workspace,'showSave'],'shows.edit');
foreach (['overview','orders','revisions','pulls','returns','files','history','timeline','notes'] as $tab) {
    shop_route('GET','/shows/{id}/' . $tab,fn(int $id) => app($workspace)->show($id,$tab),'shows.view');
}
shop_route('GET','/shows/{id}/orders/add',fn(int $id) => app($workspace)->orderForm($id),'orders.edit');
shop_route('POST','/shows/{id}/orders/add',fn(int $id) => app($workspace)->orderSave($id),'orders.edit');
shop_route('GET','/shows/{id}/orders/{order}/revisions/add',[$workspace,'orderForm'],'orders.edit');
shop_route('POST','/shows/{id}/orders/{order}/revisions/add',[$workspace,'orderSave'],'orders.edit');
shop_route('POST','/shows/{id}/notes',[$operations,'note'],'shows.edit');
shop_route('POST','/shows/{id}/files',[$operations,'file'],'shows.edit');
shop_route('GET','/orders',fn() => app($workspace)->listing('revisions','Shop Orders & Revisions'),'shows.view');
shop_route('GET','/orders/{id}',[$workspace,'order'],'shows.view');
shop_route('GET','/revisions/{id}',[$workspace,'revision'],'shows.view');
shop_route('POST','/revisions/{id}/approval',function (int $id): void {
    App\Services\Orders::approve($id,App\Services\Validation::choice(input()['status'] ?? '',['Approved','Rejected'],'approval'));
    json_response(['redirect'=>'/revisions/' . $id]);
},'orders.approve');
shop_route('GET','/revisions/{id}/pull',[$operations,'pull'],'pulls.edit');
shop_route('POST','/revisions/{id}/pull',[$operations,'pullSave'],'pulls.edit');
shop_route('GET','/shows/{id}/return',[$operations,'returns'],'returns.edit');
shop_route('POST','/shows/{id}/return',[$operations,'returnSave'],'returns.edit');
shop_route('GET','/shows/{id}/transfer',[$operations,'transfer'],'transfers.edit');
shop_route('POST','/shows/{id}/transfer',[$operations,'transferSave'],'transfers.edit');
shop_route('GET','/operations/{id}',[$operations,'operation'],'shows.view');

shop_route('GET','/inventory',fn() => app($workspace)->listing('assets','Serialized Inventory'),'inventory.view');
shop_route('GET','/inventory/categories',[$inventory,'categories'],'inventory.view');
shop_route('GET','/inventory/packages',fn() => app($workspace)->listing('packages','Equipment Packages'),'inventory.view');
shop_route('GET','/inventory/rules',fn() => app($workspace)->listing('rules','Equipment Rules'),'inventory.view');
foreach (['category','item-type','package','rule'] as $resource) {
    shop_route('GET','/inventory/' . $resource . '/add',fn() => app($inventory)->form($resource),'inventory.edit');
    shop_route('POST','/inventory/' . $resource . '/add',fn() => app($inventory)->save($resource),'inventory.edit');
}
foreach (['category','item-type'] as $resource) {
    shop_route('GET','/inventory/' . $resource . '/{id}/edit',fn(int $id) => app($inventory)->form($resource,$id),'inventory.edit');
    shop_route('POST','/inventory/' . $resource . '/{id}/edit',fn(int $id) => app($inventory)->save($resource,$id),'inventory.edit');
    shop_route('POST','/inventory/' . $resource . '/{id}/delete',function (int $id) use ($resource): void {
        App\Services\Inventory::remove($resource,$id);
        json_response(['redirect'=>'/inventory/categories']);
    },'inventory.edit');
}
shop_route('POST','/inventory/assets/{id}/action',function (int $id): void {
    App\Services\Inventory::assetAction($id,input());
    json_response(['redirect'=>'/inventory/assets/' . $id]);
},'inventory.edit');
shop_route('POST','/inventory/rules/{id}/status',function (int $id): void {
    $active = App\Services\Validation::choice((string)(input()['active'] ?? ''),['0','1'],'rule status');
    App\Models\Database::transaction(function () use ($id,$active): void {
        $rule = App\Models\Database::one('SELECT * FROM equipment_rules WHERE id=? FOR UPDATE',[$id]) ?? throw new App\Services\HttpException(404,'Rule not found.');
        App\Models\Database::execute('UPDATE equipment_rules SET active=? WHERE id=?',[$active,$id]);
        App\Services\Audit::record('rule.status_changed','rule',$id,['before'=>$rule['active'],'active'=>(int)$active]);
        App\Services\Documents::generate('rules',0);
    });
    json_response(['redirect'=>'/inventory/rules']);
},'inventory.edit');
shop_route('GET','/inventory/import',[$inventory,'importForm'],'inventory.edit');
shop_route('POST','/inventory/import',[$inventory,'import'],'inventory.edit');
shop_route('POST','/inventory/reorder',function (): void { App\Services\Inventory::reorder(input()); json_response(['message'=>'Order saved.']); },'inventory.edit');
shop_route('GET','/inventory/assets/{id}',[$inventory,'asset'],'inventory.view');
shop_route('GET','/inventory/packages/{id}',[$inventory,'package'],'inventory.view');
shop_route('GET','/repairs',fn() => app($workspace)->listing('repairs','Repair Bench'),'reports.view');
shop_route('GET','/repairs/{id}',[$operations,'repair'],'reports.view');
shop_route('POST','/repairs/{id}',[$operations,'repairSave'],'repairs.edit');

shop_route('GET','/reports',[$reports,'index'],'reports.view');
shop_route('GET','/reports/timeline',[$reports,'timeline'],'reports.view');
shop_route('GET','/api/timeline',[$reports,'timelineData'],'reports.view');
shop_route('POST','/reports/generate',[$reports,'generate'],'reports.view');
shop_route('GET','/documents/{id}',[$reports,'download'],'reports.view');
shop_route('GET','/files/{id}',[$reports,'file'],'shows.view');
shop_route('GET','/settings',[$settings,'index'],'settings.edit');
shop_route('POST','/settings',[$settings,'save'],'settings.edit');
foreach (SettingsController::BRANDING as $key) {
    shop_route('GET','/branding/' . $key,fn() => app($settings)->branding($key));
}
shop_route('GET','/profile',[$settings,'profile'],'authenticated');
shop_route('POST','/profile/avatar',[$settings,'regenerate'],'authenticated');
shop_route('POST','/profile/password',[$settings,'password'],'authenticated');
shop_route('GET','/avatars/{id}',[$settings,'avatar'],'authenticated');
shop_route('GET','/users',fn() => app($workspace)->listing('users','Shop Users'),'users.view');
shop_route('GET','/users/add',fn() => app($settings)->userForm(),'users.edit');
shop_route('POST','/users/add',fn() => app($settings)->userSave(),'users.edit');
shop_route('GET','/users/{id}',[$settings,'userForm'],'users.edit');
shop_route('POST','/users/{id}',[$settings,'userSave'],'users.edit');
shop_route('GET','/api/table',[$workspace,'table'],'authenticated');
shop_route('GET','/api/search',[$workspace,'search'],'authenticated');
shop_route('POST','/api/workspaces',[$workspace,'workspaceSave'],'authenticated');
shop_route('GET','/api/views',[$workspace,'savedViews'],'authenticated');
shop_route('POST','/api/views',[$workspace,'saveView'],'authenticated');
