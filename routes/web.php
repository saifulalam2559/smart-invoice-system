<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Auth::routes();

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'sendEmail'])->name('invoices.send');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    
    // Customers
    Route::resource('customers', CustomerController::class);
    Route::get('/customers/{customer}/invoices', [CustomerController::class, 'invoices'])->name('customers.invoices');
    Route::get('/customers/{customer}/statements', [CustomerController::class, 'statements'])->name('customers.statements');
    
    // Products
    Route::resource('products', ProductController::class);
    Route::get('/products/{product}/history', [ProductController::class, 'history'])->name('products.history');
    
    // Payments
    Route::resource('payments', PaymentController::class);
    Route::post('/payments/bulk', [PaymentController::class, 'bulkStore'])->name('payments.bulk');
    Route::get('/payments/reconcile', [PaymentController::class, 'reconcile'])->name('payments.reconcile');
    
    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/tax', [ReportController::class, 'tax'])->name('reports.tax');
    Route::get('/reports/customer', [ReportController::class, 'customer'])->name('reports.customer');
    Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
    
    // Settings
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings.index');
    
    // Profile
    Route::get('/profile', function () {
        return view('profile.index');
    })->name('profile.index');
});

// Public invoice view (for customers)
Route::get('/invoice/{invoice}/{token}', [InvoiceController::class, 'publicView'])->name('invoice.public');
Route::get('/invoice/{invoice}/{token}/pay', [InvoiceController::class, 'publicPay'])->name('invoice.public.pay');

// API Routes
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/chart-data', [DashboardController::class, 'chartData']);
    Route::post('/upload-invoice-logo', [InvoiceController::class, 'uploadLogo']);
});
