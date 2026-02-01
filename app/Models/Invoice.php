<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'invoice_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount',
        'total_amount',
        'currency',
        'notes',
        'terms',
        'public_token',
        'sent_at',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = [
        'status_color',
        'is_overdue',
        'balance_due',
        'days_overdue',
        'formatted_total',
        'formatted_due_date',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'secondary',
            'sent' => 'info',
            'pending' => 'warning',
            'paid' => 'success',
            'overdue' => 'danger',
            'cancelled' => 'dark',
            default => 'secondary',
        };
    }

    public function getIsOverdueAttribute()
    {
        if ($this->status === 'paid' || $this->status === 'cancelled') {
            return false;
        }
        
        return $this->due_date < today();
    }

    public function getBalanceDueAttribute()
    {
        $paid = $this->payments()->sum('amount');
        return max(0, $this->total_amount - $paid);
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->is_overdue) {
            return 0;
        }
        
        return today()->diffInDays($this->due_date);
    }

    public function getFormattedTotalAttribute()
    {
        return $this->currency . ' ' . number_format($this->total_amount, 2);
    }

    public function getFormattedDueDateAttribute()
    {
        return $this->due_date->format('M d, Y');
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
                    ->orWhere(function($q) {
                        $q->where('status', 'pending')
                          ->where('due_date', '<', today());
                    });
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('invoice_date', now()->month)
                    ->whereYear('invoice_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('invoice_date', now()->year);
    }

    // Business logic
    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsPaid($paymentData = [])
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Record payment
        if (!empty($paymentData)) {
            $this->payments()->create(array_merge([
                'customer_id' => $this->customer_id,
                'amount' => $this->total_amount,
                'payment_date' => now(),
            ], $paymentData));
        }
    }

    public function markAsOverdue()
    {
        if ($this->status !== 'paid' && $this->due_date < today()) {
            $this->update(['status' => 'overdue']);
        }
    }

    public function calculateTotals()
    {
        $subtotal = $this->items()->sum('total');
        $tax = $this->items()->sum('tax_amount');
        
        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $tax - $this->discount,
        ]);
    }

    public function sendReminder()
    {
        // Logic to send reminder email
        // This would integrate with your notification system
        return true;
    }

    public function getPublicUrl()
    {
        return route('invoice.public', [
            'invoice' => $this->id,
            'token' => $this->public_token,
        ]);
    }
}
