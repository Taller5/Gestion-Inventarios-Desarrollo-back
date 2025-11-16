<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\EnumController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CabysController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\IAHistoryController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\EgressController;

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::prefix('v1')->group(function () {

     // ------------------ Employees (Users) ------------------
    Route::get('employees', [UserController::class, 'index']);
    Route::put('employees/{id}/password', [UserController::class, 'updatePassword']);
    Route::post('employees/{id}/profile-photo', [UserController::class, 'updateProfilePhoto']);
    Route::get('employees/{id}', [UserController::class, 'show']);
    Route::post('employees', [UserController::class, 'store']);
    Route::put('employees/{id}', [UserController::class, 'update']);
    Route::delete('employees/{id}', [UserController::class, 'destroy']); 
    Route::post('employees/recover-password', [UserController::class, 'recoverPassword']);

     // ------------------ Customers ------------------
    Route::get('customers', [CustomerController::class, 'index']);       // Listar todos
    Route::get('customers/{id}', [CustomerController::class, 'show']);  // Mostrar uno
    Route::post('customers', [CustomerController::class, 'store']);     // Crear
    Route::put('customers/{id}', [CustomerController::class, 'update']); // Actualizar
    Route::delete('customers/{id}', [CustomerController::class, 'destroy']); // Eliminar

    // ------------------ Businesses ------------------
    Route::get('businesses', [BusinessController::class, 'index']);
    Route::get('businesses/{id}', [BusinessController::class, 'show']);
    Route::post('businesses', [BusinessController::class, 'store']);
    Route::put('businesses/{id}', [BusinessController::class, 'update']);
    Route::delete('businesses/{id}', [BusinessController::class, 'destroy']);
    Route::post('businesses/{id}/logo', [BusinessController::class, 'updateLogo']);

    // ------------------ Branches ------------------
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{id}', [BranchController::class, 'show']);
    Route::post('branches', [BranchController::class, 'store']);
    Route::put('branches/{id}', [BranchController::class, 'update']);
    Route::delete('branches/{id}', [BranchController::class, 'destroy']);

    // ------------------ Warehouses ------------------
    Route::get('warehouses', [WarehouseController::class, 'index']);
    Route::get('warehouses/{id}', [WarehouseController::class, 'show']);
    Route::post('warehouses', [WarehouseController::class, 'store']);
    Route::put('warehouses/{id}', [WarehouseController::class, 'update']);
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy']);

        // ------------------ Products ------------------
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);
  


    // ------------------ Batch ------------------
    Route::get('batch', [BatchController::class, 'index']);
    Route::get('batch/{id}', [BatchController::class, 'show']);
    Route::post('batch', [BatchController::class, 'store']);
    Route::put('batch/{id}', [BatchController::class, 'update']);
    Route::delete('batch/{id}', [BatchController::class, 'destroy']);
    // Reporte combinado de Hacienda (acepta GET y POST para compatibilidad)
    Route::match(['get', 'post'], 'hacienda-report', [\App\Http\Controllers\HaciendaReportController::class, 'index']);
     // ------------------ Invoices ------------------
    Route::get('invoices', [InvoiceController::class, 'index']);      // List all invoices
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);  // Show single invoice
    Route::post('invoices', [InvoiceController::class, 'store']);     // Create invoice
    Route::put('invoices/{id}', [InvoiceController::class, 'update']); // Update invoice if needed
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy']); // Delete invoice
    Route::get('invoices/{id}/xml', [InvoiceController::class, 'xml']); // Obtener XML de factura electrónica
    Route::get('invoices/{id}/xml-status', [InvoiceController::class, 'xmlStatus']); // Estado de validación/firma del XML
    Route::post('invoices/{id}/submit', [InvoiceController::class, 'submit']);// Enviar a Hacienda
    Route::get('invoices/{id}/status', [InvoiceController::class, 'status']);// Estado en Hacienda
    Route::get('invoices/{id}/response-xml', [InvoiceController::class, 'responseXml']);// Obtener XML de respuesta de Hacienda
        Route::get('invoices/{id}/validate-xml', [InvoiceController::class, 'validateXml']); // Validar XML contra XSD
        // Descarga de XML generado
        Route::get('xml/download/{id}', [\App\Http\Controllers\XmlDownloadController::class, 'downloadXml'])->name('xml.download');
        // Descarga de XML de respuesta Hacienda
        Route::get('responsexml/download/{id}', [\App\Http\Controllers\XmlDownloadController::class, 'downloadResponseXml'])->name('responsexml.download');
    Route::get('invoices/by-business', [InvoiceController::class, 'reportByBusiness']);

    
    // --------- Aliases semánticos para comprobantes ---------
    // Crear tiquete (forzar document_type=04)
    Route::post('tickets', function (Request $request) {
        $request->merge(['document_type' => '04']);
        return app(InvoiceController::class)->store($request);
    });
    // Crear factura (forzar document_type=01)
    Route::post('facturas', function (Request $request) {
        $request->merge(['document_type' => '01']);
        return app(InvoiceController::class)->store($request);
    });
    // Obtener XML tiquete (valida tipo)
    Route::get('tickets/{id}/xml', function ($id) {
        $invoice = \App\Models\Invoice::findOrFail($id);
        if ($invoice->document_type !== '04') {
            return response()->json(['error' => 'El comprobante no es un Tiquete (04)'], 400);
        }
        return app(InvoiceController::class)->xml($id);
    });
    // Obtener XML factura (valida tipo)
    Route::get('facturas/{id}/xml', function ($id) {
        $invoice = \App\Models\Invoice::findOrFail($id);
        if ($invoice->document_type !== '01') {
            return response()->json(['error' => 'El comprobante no es una Factura (01)'], 400);
        }
        return app(InvoiceController::class)->xml($id);
    });

    // Enviar a Hacienda (aliases por tipo)
    Route::post('tickets/{id}/submit', function ($id) {
        $invoice = \App\Models\Invoice::findOrFail($id);
        if ($invoice->document_type !== '04') {
            return response()->json(['error' => 'El comprobante no es un Tiquete (04)'], 400);
        }
        return app(InvoiceController::class)->submit($id);
    });
    Route::post('facturas/{id}/submit', function ($id) {
        $invoice = \App\Models\Invoice::findOrFail($id);
        if ($invoice->document_type !== '01') {
            return response()->json(['error' => 'El comprobante no es una Factura (01)'], 400);
        }
        return app(InvoiceController::class)->submit($id);
    });

    // Consultar estado en Hacienda (aliases por tipo)
    Route::get('tickets/{id}/status', function ($id) {
        $invoice = \App\Models\Invoice::findOrFail($id);
        if ($invoice->document_type !== '04') {
            return response()->json(['error' => 'El comprobante no es un Tiquete (04)'], 400);
        }
        return app(InvoiceController::class)->status($id);
    });
    Route::get('facturas/{id}/status', function ($id) {
        $invoice = \App\Models\Invoice::findOrFail($id);
        if ($invoice->document_type !== '01') {
            return response()->json(['error' => 'El comprobante no es una Factura (01)'], 400);
        }
        return app(InvoiceController::class)->status($id);
    });
    // ------------------ Cash Registers ------------------
    Route::get('cash-registers', [CashRegisterController::class, 'index']);       // Listar todas las cajas
    Route::get('cash-registers/{id}', [CashRegisterController::class, 'show']);   // Mostrar una caja específica
    Route::put('cash-registers/open/{id}', [CashRegisterController::class, 'open']);

   Route::put('cash-registers/reopen/{id}', [CashRegisterController::class, 'reopen']);
   Route::get('/cash-registers/active-user/{sucursalId}/{userId}',
    [CashRegisterController::class, 'activeUserBox']
);
    Route::put('cash-registers/close/{id}', [CashRegisterController::class, 'close']); //  Asegúrate de tener esto
    Route::get('cashbox/active/{sucursalId}', [CashRegisterController::class, 'active']);
    Route::post('cash-register/addCashSale', [CashRegisterController::class, 'addCashSale']);
    Route::post('/cash-registers/create-empty', [CashRegisterController::class, 'createEmpty']);

    // ------------------ IA History ------------------
    Route::get('ia-history', [IAHistoryController::class, 'index']); // Listar todos o filtrar por tipo (?type=diario|anual)
    Route::post('ia-history', [IAHistoryController::class, 'store']); // Crear nuevo historial
    Route::get('ia-history/{type}', [IAHistoryController::class, 'show']); // Mostrar historial por tipo (diario/anual)
    Route::delete('ia-history/{id}', [IAHistoryController::class, 'destroy']); // Eliminar historial por id


    // ------------------ Providers ------------------
    Route::get('providers', [ProviderController::class, 'index']);       // Listar todos
    Route::get('providers/{id}', [ProviderController::class, 'show']);   // Mostrar uno
    Route::post('providers', [ProviderController::class, 'store']);      // Crear
    Route::put('providers/{id}', [ProviderController::class, 'update']); // Actualizar
    Route::delete('providers/{id}', [ProviderController::class, 'destroy']); // Eliminar

    // ------------------ Categories ------------------
    Route::get('/categories', [CategoryController::class, 'index']);       // Listar todas
    Route::get('/categories/{nombre}', [CategoryController::class, 'show']); // Ver categoría por nombre
    Route::post('/categories', [CategoryController::class, 'store']);      // Crear categoría
    Route::put('/categories/{nombre}', [CategoryController::class, 'update']); // Actualizar categoría
    Route::delete('/categories/{nombre}', [CategoryController::class, 'destroy']); // Eliminar

        // ------------------ CABYS ------------------
    Route::get('cabys', [CabysController::class, 'index']); 
    Route::get('cabys/search', [CabysController::class, 'search']);
    Route::get('cabys/{code}', [CabysController::class, 'show']); 
    Route::get('cabys-categories', [CabysController::class, 'categories']);

    // ------------------ Units (Unidades de medida) ------------------
    Route::get('units', [UnitController::class, 'index']);     


    // ------------------ Promotions ------------------
            Route::get('promotions', [PromotionController::class, 'index']);       // Listar todas
         
            Route::post('promotions', [PromotionController::class, 'store']);      // Crear promoción
            Route::put('promotions/{id}', [PromotionController::class, 'update']); // Actualizar promoción
            Route::delete('promotions/{id}', [PromotionController::class, 'destroy']); // Eliminar promoción
            Route::get('promotions/{id}/products', [PromotionController::class, 'products']); // Obtener productos de una promoción específica
            // ------------------ Promotions para SalePages ------------------
            Route::get('promotions/active', [PromotionController::class, 'activePromotions']);//buscar promociones activas
       
            Route::post('promotions/apply', [PromotionController::class, 'applyPromotions']);//aplicar promociones a una venta
            Route::post('promotions/restore', [PromotionController::class, 'restorePromotionStock']);//restaurar cantidad de stock
            Route::get('promotions/{id}', [PromotionController::class, 'show']);   // Mostrar una promoción

            Route::get('egresos', [EgressController::class, 'index']); // Listar todos los egresos
                Route::get('egresos/{id}', [EgressController::class, 'show']); // Mostrar un egreso
                Route::post('egresos', [EgressController::class, 'store']); // Crear un egreso
                Route::delete('egresos/{id}', [EgressController::class, 'destroy']); // Eliminar un egreso
});


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');


Route::options('/{any}', function (Request $request) {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', '*') // o 'http://localhost:5173' si querés más restricción
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*');  