<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    // List all invoices
    public function index()
    {
        return response()->json(Invoice::all());
    }

    // Get a single invoice
    public function show($id)
    {
        $invoice = Invoice::findOrFail($id);
        return response()->json($invoice);
    }

    // Create a new invoice
    public function store(Request $request)
    {
        $data = $request->validate([
            // Customer info
            'customer_name' => 'required|string',
            'customer_identity_number' => 'required|string',
           
            // Branch / Business info
            'branch_name' => 'required|string',
            'business_name' => 'required|string',
            'business_legal_name' => 'nullable|string',
            'business_phone' => 'nullable|string',
            'business_email' => 'nullable|string',
             'branches_phone' => 'nullable|string',
            'province' => 'nullable|string',
            'canton' => 'nullable|string',
            'business_id_type' => 'nullable|string',
            'business_id_number' => 'nullable|string',

            // Cashier
            'cashier_name' => 'required|string',

            // Products
            'products' => 'required|array',
            'products.*.code' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0|max:100',

            // Payment
            'payment_method' => 'required|in:Cash,Card,SINPE',
            'amount_paid' => 'nullable|numeric|min:0',
            'receipt' => 'nullable|string',
        ]);

        // Calculate totals
        $subtotal = 0;
        $totalDiscount = 0;

        foreach ($data['products'] as $p) {
            $subtotal += $p['price'] * $p['quantity'];
            $totalDiscount += ($p['price'] * $p['quantity'] * ($p['discount'] ?? 0) / 100);
        }

        $total = $subtotal - $totalDiscount;
        $taxes = 0; // Ajusta si agregas impuestos

        // Create invoice
        $invoice = Invoice::create([
            'customer_name' => $data['customer_name'],
            'customer_identity_number' => $data['customer_identity_number'],
          
            'branch_name' => $data['branch_name'],
            'business_name' => $data['business_name'],
            'business_legal_name' => $data['business_legal_name'] ?? null,
            'business_phone' => $data['business_phone'] ?? null,
            'business_email' => $data['business_email'] ?? null,
            'branches_phone' => $data['business_phone'] ?? null,
            'province' => $data['province'] ?? null,
            'canton' => $data['canton'] ?? null,
            'business_id_type' => $data['business_id_type'] ?? null,
            'business_id_number' => $data['business_id_number'] ?? null,
            'cashier_name' => $data['cashier_name'],
            'date' => now(),
            'products' => $data['products'],
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'taxes' => $taxes,
            'total' => $total,
            'amount_paid' => $data['amount_paid'] ?? 0,
            'change' => max(0, ($data['amount_paid'] ?? 0) - $total),
            'payment_method' => $data['payment_method'],
            'receipt' => $data['receipt'] ?? ($data['payment_method'] === 'Cash' ? 'N/A' : ''),
        ]);

        return response()->json($invoice, 201);
    }

    // Update an invoice
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $data = $request->validate([
            'payment_method' => 'sometimes|in:Cash,Card,SINPE',
            'amount_paid' => 'sometimes|numeric|min:0',
            'receipt' => 'sometimes|string',
        ]);

        $invoice->update($data);
        return response()->json($invoice);
    }

    // Delete an invoice
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();
        return response()->json(null, 204);
    }
}
