<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\ErpRecordController;
use App\Http\Controllers\FirebaseMessagingServiceWorkerController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrivilegeManagementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesInstallmentController;
use App\Http\Controllers\SalesShipmentController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/firebase-messaging-sw.js', FirebaseMessagingServiceWorkerController::class)
    ->name('firebase.messaging.service-worker');
Route::post('/integrations/couriers/{courierProvider:code}/tracking', [SalesShipmentController::class, 'webhook'])
    ->name('integrations.couriers.tracking');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified', 'account.active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('privilege:dashboard.view')->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('privilege:notifications.view')->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->middleware('privilege:notifications.view')->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('privilege:notifications.view')->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('privilege:notifications.view')->name('notifications.read-all');
    Route::post('/notifications/fcm-token', [NotificationController::class, 'storeFcmToken'])->middleware('privilege:notifications.view')->name('notifications.fcm-token.store');
    Route::delete('/notifications/fcm-token', [NotificationController::class, 'destroyFcmToken'])->middleware('privilege:notifications.view')->name('notifications.fcm-token.destroy');
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('privilege:payments.view')->name('payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('privilege:payments.view')->name('payments.show');
    Route::get('/accounting/ledger', [AccountingController::class, 'index'])->middleware('privilege:modules.accounting.view')->name('accounting.ledger');
    Route::post('/accounting/accounts', [AccountingController::class, 'storeAccount'])->middleware('privilege:modules.accounting.create')->name('accounting.accounts.store');
    Route::post('/accounting/periods', [AccountingController::class, 'storePeriod'])->middleware('privilege:modules.accounting.create')->name('accounting.periods.store');
    Route::post('/accounting/journals', [AccountingController::class, 'storeJournal'])->middleware('privilege:modules.accounting.create')->name('accounting.journals.store');
    Route::patch('/accounting/journals/{journalEntry}/post', [AccountingController::class, 'postJournal'])->middleware('privilege:modules.accounting.advance')->name('accounting.journals.post');
    Route::post('/accounting/sync-erp', [AccountingController::class, 'syncErpRecords'])->middleware('privilege:modules.accounting.advance')->name('accounting.sync-erp');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('privilege:reports.view')->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('privilege:reports.view')->name('reports.export');
    Route::get('/calendar', [CalendarController::class, 'index'])->middleware('privilege:calendar.view')->name('calendar.index');
    Route::post('/calendar/events', [CalendarController::class, 'store'])->middleware('privilege:calendar.manage')->name('calendar.events.store');
    Route::delete('/calendar/events/{calendarEvent}', [CalendarController::class, 'destroy'])->middleware('privilege:calendar.manage')->name('calendar.events.destroy');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->middleware('privilege:announcements.view')->name('announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->middleware('privilege:announcements.view')->name('announcements.show');
    Route::post('/announcements/{announcement}/acknowledge', [AnnouncementController::class, 'acknowledge'])->middleware('privilege:announcements.view')->name('announcements.acknowledge');
    Route::get('/employee', [EmployeePortalController::class, 'index'])->middleware('privilege:employee.portal')->name('employee.portal');
    Route::patch('/employee/profile', [EmployeePortalController::class, 'updateProfile'])->middleware('privilege:employee.portal')->name('employee.profile.update');
    Route::post('/employee/attendance/clock-in', [EmployeePortalController::class, 'clockIn'])->middleware('privilege:employee.portal')->name('employee.attendance.clock-in');
    Route::patch('/employee/attendance/clock-out', [EmployeePortalController::class, 'clockOut'])->middleware('privilege:employee.portal')->name('employee.attendance.clock-out');
    Route::get('/employee/attendance/location-policy', [EmployeePortalController::class, 'attendanceLocationPolicy'])->middleware('privilege:employee.portal')->name('employee.attendance.location-policy');
    Route::post('/employee/requests', [EmployeePortalController::class, 'storeRequest'])->middleware('privilege:employee.portal')->name('employee.requests.store');
    Route::post('/employee/reimbursements', [EmployeePortalController::class, 'storeReimbursement'])->middleware('privilege:employee.portal')->name('employee.reimbursements.store');
    Route::get('/employee/payrolls/{payroll}/slip', [EmployeePortalController::class, 'payslip'])->middleware('privilege:employee.portal')->name('employee.payrolls.slip');
    Route::post('/employee/attendance/manual', [EmployeePortalController::class, 'storeManualAttendance'])->middleware('privilege:employee.portal')->name('employee.attendance.manual');
    Route::get('/marketing', [MarketingController::class, 'index'])->middleware('privilege:marketing.manage')->name('marketing.index');
    Route::post('/marketing/branches', [MarketingController::class, 'storeBranch'])->middleware('privilege:marketing.manage')->name('marketing.branches.store');
    Route::post('/marketing/employees', [MarketingController::class, 'storeEmployee'])->middleware('privilege:marketing.manage')->name('marketing.employees.store');
    Route::post('/marketing/trackings', [MarketingController::class, 'storeTracking'])->middleware('privilege:marketing.manage')->name('marketing.trackings.store');
    Route::get('/sales/installments', [SalesInstallmentController::class, 'index'])->middleware('privilege:modules.sales.view')->name('sales.installments.index');
    Route::post('/sales/installments', [SalesInstallmentController::class, 'store'])->middleware('privilege:modules.sales.edit')->name('sales.installments.store');
    Route::get('/sales/shipments', [SalesShipmentController::class, 'index'])->middleware('privilege:modules.sales.view')->name('sales.shipments.index');
    Route::post('/sales/shipments', [SalesShipmentController::class, 'store'])->middleware('privilege:modules.sales.edit')->name('sales.shipments.store');
    Route::post('/sales/shipments/{shipment}/tracking', [SalesShipmentController::class, 'storeTrackingEvent'])->middleware('privilege:modules.sales.edit')->name('sales.shipments.tracking.store');
    Route::post('/sales/couriers', [SalesShipmentController::class, 'storeProvider'])->middleware('privilege:modules.sales.edit')->name('sales.couriers.store');
    Route::patch('/sales/couriers/{provider}', [SalesShipmentController::class, 'updateProvider'])->middleware('privilege:modules.sales.edit')->name('sales.couriers.update');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/bom', [ProductController::class, 'storeBom'])->name('products.bom.store');
    Route::delete('/products/{product}/bom/{bomItem}', [ProductController::class, 'destroyBom'])->name('products.bom.destroy');
    Route::post('/products/{product}/adjustments', [ProductController::class, 'storeAdjustment'])->name('products.adjustments.store');
    Route::get('/modules/{module}', [ErpRecordController::class, 'index'])->name('records.index');

    Route::middleware('privilege:payments.create')->group(function () {
        Route::get('/records/{record}/payment', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/records/{record}/payment', [PaymentController::class, 'store'])->name('payments.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/modules/{module}/create', [ErpRecordController::class, 'create'])->name('records.create');
        Route::post('/modules/{module}', [ErpRecordController::class, 'store'])->name('records.store');
        Route::get('/modules/{module}/{record}/edit', [ErpRecordController::class, 'edit'])->name('records.edit');
        Route::put('/modules/{module}/{record}', [ErpRecordController::class, 'update'])->name('records.update');
        Route::patch('/modules/{module}/{record}/advance', [ErpRecordController::class, 'advance'])->name('records.advance');
    });

    Route::delete('/modules/{module}/{record}', [ErpRecordController::class, 'destroy'])->name('records.destroy');

    Route::get('/settings/users', [UserManagementController::class, 'index'])
        ->middleware('privilege:users.manage')
        ->name('settings.users');
    Route::patch('/settings/users/{user}/role', [UserManagementController::class, 'updateRole'])
        ->middleware('privilege:users.manage')
        ->name('settings.users.role');
    Route::patch('/settings/users/{user}/status', [UserManagementController::class, 'updateStatus'])
        ->middleware('privilege:users.manage')
        ->name('settings.users.status');

    Route::get('/settings/privileges', [PrivilegeManagementController::class, 'index'])
        ->middleware('privilege:privileges.manage')
        ->name('settings.privileges');
    Route::patch('/settings/privileges/roles/{role}', [PrivilegeManagementController::class, 'updateRole'])
        ->middleware('privilege:privileges.manage')
        ->name('settings.privileges.roles');
    Route::patch('/settings/privileges/users/{user}', [PrivilegeManagementController::class, 'updateUser'])
        ->middleware('privilege:privileges.manage')
        ->name('settings.privileges.users');
    Route::delete('/settings/privileges/users/{user}', [PrivilegeManagementController::class, 'resetUser'])
        ->middleware('privilege:privileges.manage')
        ->name('settings.privileges.users.reset');

    Route::get('/settings/master-data', [MasterDataController::class, 'index'])
        ->middleware('privilege:master-data.manage')
        ->name('settings.master-data');
    Route::post('/settings/master-data/groups', [MasterDataController::class, 'storeGroup'])
        ->middleware('privilege:master-data.manage')
        ->name('settings.master-data.groups.store');
    Route::patch('/settings/master-data/groups/{group}', [MasterDataController::class, 'updateGroup'])
        ->middleware('privilege:master-data.manage')
        ->name('settings.master-data.groups.update');
    Route::post('/settings/master-data/groups/{group}/items', [MasterDataController::class, 'storeItem'])
        ->middleware('privilege:master-data.manage')
        ->name('settings.master-data.items.store');
    Route::patch('/settings/master-data/items/{item}', [MasterDataController::class, 'updateItem'])
        ->middleware('privilege:master-data.manage')
        ->name('settings.master-data.items.update');
    Route::delete('/settings/master-data/items/{item}', [MasterDataController::class, 'destroyItem'])
        ->middleware('privilege:master-data.manage')
        ->name('settings.master-data.items.destroy');

    Route::get('/settings/announcements', [AnnouncementController::class, 'manage'])
        ->middleware('privilege:announcements.manage')
        ->name('settings.announcements');
    Route::post('/settings/announcements', [AnnouncementController::class, 'store'])
        ->middleware('privilege:announcements.manage')
        ->name('settings.announcements.store');
    Route::patch('/settings/announcements/{announcement}', [AnnouncementController::class, 'update'])
        ->middleware('privilege:announcements.manage')
        ->name('settings.announcements.update');
    Route::patch('/settings/announcements/{announcement}/status', [AnnouncementController::class, 'updateStatus'])
        ->middleware('privilege:announcements.manage')
        ->name('settings.announcements.status');

    Route::get('/settings/employees', [EmployeeManagementController::class, 'index'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees');
    Route::post('/settings/employees', [EmployeeManagementController::class, 'storeEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.store');
    Route::get('/settings/employees/{employeeProfile}/attendance', [EmployeeManagementController::class, 'attendanceEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.attendance');
    Route::get('/settings/employees/{employeeProfile}/payrolls', [EmployeeManagementController::class, 'payrollEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.payrolls');
    Route::get('/settings/employees/{employeeProfile}/requests', [EmployeeManagementController::class, 'requestsEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.requests');
    Route::get('/settings/employees/{employeeProfile}/leaves', [EmployeeManagementController::class, 'leavesEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.leaves');
    Route::get('/settings/employees/{employeeProfile}/overtimes', [EmployeeManagementController::class, 'overtimesEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.overtimes');
    Route::patch('/settings/employees/{employeeProfile}/attendance-location', [EmployeeManagementController::class, 'updateAttendanceLocation'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.attendance-location');
    Route::get('/settings/employees/{employeeProfile}/edit', [EmployeeManagementController::class, 'editEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.edit');
    Route::patch('/settings/employees/{employeeProfile}', [EmployeeManagementController::class, 'updateEmployee'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.update');
    Route::post('/settings/employees/sync-sdm', [EmployeeManagementController::class, 'syncSdm'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.sync-sdm');
    Route::post('/settings/employees/sync-hris', [EmployeeManagementController::class, 'syncHris'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.sync-hris');
    Route::patch('/settings/employees/requests/{employeeRequest}', [EmployeeManagementController::class, 'reviewRequest'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.requests.review');
    Route::patch('/settings/employees/reimbursements/{reimbursement}', [EmployeeManagementController::class, 'reviewReimbursement'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.reimbursements.review');
    Route::post('/settings/employees/payrolls', [EmployeeManagementController::class, 'storePayroll'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.payrolls.store');
    Route::post('/settings/employees/recruitment', [EmployeeManagementController::class, 'storeRecruitment'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.recruitment.store');
    Route::patch('/settings/employees/recruitment/{candidate}', [EmployeeManagementController::class, 'updateRecruitment'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.recruitment.update');
    Route::post('/settings/employees/announcements', [EmployeeManagementController::class, 'storeAnnouncement'])
        ->middleware('privilege:employee.manage')
        ->name('settings.employees.announcements.store');
});
