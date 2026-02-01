<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('customer');
        
        // Search result
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        
        // Filter by status
        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by date
        if ($request->has('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }
        
        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);
        
        $stats = [
            'total' => Invoice::count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'overdue' => Invoice::where('status', 'overdue')->count(),
            'total_amount' => Invoice::sum('total_amount'),
            'paid_amount' => Payment::sum('amount'),
        ];
        
        return view('invoices.index', compact('invoices', 'stats'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get();
        
        $lastInvoice = Invoice::latest()->first();
        $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        return view('invoices.create', compact('customers', 'products', 'invoiceNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);

        // Calculate totals
        $subtotal = 0;
        $tax_total = 0;
        
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            $quantity = $item['quantity'];
            $price = $item['price'];
            $tax_rate = $item['tax_rate'] ?? $product->tax_rate ?? config('app.default_tax_rate', 10);
            
            $item_total = $quantity * $price;
            $item_tax = ($item_total * $tax_rate) / 100;
            
            $subtotal += $item_total;
            $tax_total += $item_tax;
        }
        
        // Apply discount
        $discount = 0;
        if ($request->discount_type && $request->discount_value) {
            if ($request->discount_type == 'percentage') {
                $discount = ($subtotal * $request->discount_value) / 100;
            } else {
                $discount = $request->discount_value;
            }
        }
        
        $total = $subtotal + $tax_total - $discount;
        
        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => $request->invoice_number,
            'customer_id' => $request->customer_id,
            'invoice_date' => $request->invoice_date,
            'due_date' => $request->due_date,
            'status' => 'draft',
            'subtotal' => $subtotal,
            'tax_amount' => $tax_total,
            'discount' => $discount,
            'total_amount' => $total,
            'notes' => $request->notes,
            'terms' => $request->terms,
            'currency' => config('app.default_currency', 'USD'),
            'public_token' => Str::random(32),
        ]);
        
        // Create invoice items
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            $quantity = $item['quantity'];
            $price = $item['price'];
            $tax_rate = $item['tax_rate'] ?? $product->tax_rate ?? config('app.default_tax_rate', 10);
            
            $item_total = $quantity * $price;
            $item_tax = ($item_total * $tax_rate) / 100;
            
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'],
                'description' => $item['description'] ?? $product->description,
                'quantity' => $quantity,
                'price' => $price,
                'tax_rate' => $tax_rate,
                'tax_amount' => $item_tax,
                'total' => $item_total + $item_tax,
            ]);
        }
        
        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully. You can now send it to the customer.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.product', 'payments']);
        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        if ($invoice->status == 'paid') {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Cannot edit a paid invoice.');
        }
        
        $customers = Customer::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get();
        $invoice->load('items');
        
        return view('invoices.edit', compact('invoice', 'customers', 'products'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->status == 'paid') {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Cannot update a paid invoice.');
        }
        
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'status' => 'required|in:draft,sent,pending,paid,overdue,cancelled',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);
        
        $invoice->update($request->only([
            'customer_id', 'invoice_date', 'due_date', 'status', 'notes', 'terms'
        ]));
        
        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status == 'paid') {
            return redirect()->route('invoices.index')
                ->with('error', 'Cannot delete a paid invoice.');
        }
        
        $invoice->items()->delete();
        $invoice->payments()->delete();
        $invoice->delete();
        
        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function download(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.product']);
        
        $pdf = PDF::loadView('invoices.pdf', compact('invoice'));
        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function preview(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.product']);
        
        $pdf = PDF::loadView('invoices.pdf', compact('invoice'));
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function sendEmail(Invoice $invoice)
    {
        $invoice->load('customer');
        
        try {
            Mail::to($invoice->customer->email)
                ->cc($invoice->customer->cc_email ?? null)
                ->send(new InvoiceMail($invoice));
            
            $invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            
            return back()->with('success', 'Invoice sent via email successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    public function markPaid(Invoice $invoice)
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        
        // Create payment record
        Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $invoice->total_amount,
            'payment_method' => 'manual',
            'payment_date' => now(),
            'reference' => 'MANUAL-' . Str::random(8),
            'notes' => 'Marked as paid manually',
        ]);
        
        return back()->with('success', 'Invoice marked as paid successfully.');
    }

    public function duplicate(Invoice $invoice)
    {
        $newInvoice = $invoice->replicate();
        $newInvoice->invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
        $newInvoice->status = 'draft';
        $newInvoice->sent_at = null;
        $newInvoice->paid_at = null;
        $newInvoice->public_token = Str::random(32);
        $newInvoice->save();
        
        // Duplicate items
        foreach ($invoice->items as $item) {
            $newItem = $item->replicate();
            $newItem->invoice_id = $newInvoice->id;
            $newItem->save();
        }
        
        return redirect()->route('invoices.edit', $newInvoice)
            ->with('success', 'Invoice duplicated successfully. You can now edit the new invoice.');
    }

    public function publicView(Invoice $invoice, $token)
    {
        if ($invoice->public_token !== $token) {
            abort(404);
        }
        
        $invoice->load(['customer', 'items.product']);
        return view('invoices.public', compact('invoice'));
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        
        $path = $request->file('logo')->store('logos', 'public');
        
        // Update company logo setting
        setting(['company_logo' => $path])->save();
        
        return response()->json([
            'success' => true,
            'path' => Storage::url($path),
        ]);
    }
}
