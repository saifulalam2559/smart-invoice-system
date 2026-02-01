@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Invoices</h1>
            <p class="text-muted">Manage and track all your invoices</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create New Invoice
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Invoices</h6>
                            <h4 class="mb-0">{{ $stats['total'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary rounded-circle">
                                <i class="bi bi-receipt"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Paid</h6>
                            <h4 class="mb-0">{{ $stats['paid'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success rounded-circle">
                                <i class="bi bi-check-circle"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h4 class="mb-0">{{ $stats['pending'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning rounded-circle">
                                <i class="bi bi-clock"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Overdue</h6>
                            <h4 class="mb-0">{{ $stats['overdue'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-danger rounded-circle">
                                <i class="bi bi-exclamation-circle"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('invoices.index') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search invoices..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" placeholder="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" placeholder="To Date">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                        <tr>
                            <td>
                                <strong>{{ $invoice->invoice_number }}</strong>
                                @if($invoice->status == 'draft')
                                <span class="badge bg-light text-dark ms-1">Draft</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ $invoice->customer->name }}</div>
                                <small class="text-muted">{{ $invoice->customer->email }}</small>
                            </td>
                            <td>{{ $invoice->invoice_date->format('M d, Y') }}</td>
                            <td>
                                <div>{{ $invoice->formatted_due_date }}</div>
                                @if($invoice->is_overdue)
                                <small class="text-danger">{{ $invoice->days_overdue }} days overdue</small>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $invoice->formatted_total }}</strong>
                                @if($invoice->balance_due > 0 && $invoice->status != 'paid')
                                <div class="text-muted small">Balance: {{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $invoice->status_color }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="{{ route('invoices.download', $invoice) }}" class="btn btn-sm btn-outline-success" title="Download PDF">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" title="More actions">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('invoices.preview', $invoice) }}" target="_blank">
                                                    <i class="bi bi-printer"></i> Preview
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="sendInvoice({{ $invoice->id }})">
                                                    <i class="bi bi-envelope"></i> Send Email
                                                </a>
                                            </li>
                                            @if($invoice->status != 'paid')
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="markAsPaid({{ $invoice->id }})">
                                                    <i class="bi bi-check-circle"></i> Mark as Paid
                                                </a>
                                            </li>
                                            @endif
                                            <li>
                                                <a class="dropdown-item" href="{{ route('invoices.duplicate', $invoice) }}">
                                                    <i class="bi bi-copy"></i> Duplicate
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this invoice?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-receipt display-6"></i>
                                    <h5 class="mt-3">No invoices found</h5>
                                    <p>Get started by creating your first invoice</p>
                                    <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create Invoice
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($invoices->hasPages())
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                    Showing {{ $invoices->firstItem() }} to {{ $invoices->lastItem() }} of {{ $invoices->total() }} entries
                </div>
                <nav>
                    {{ $invoices->withQueryString()->links() }}
                </nav>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function sendInvoice(invoiceId) {
    if (confirm('Send this invoice via email to the customer?')) {
        fetch(`/invoices/${invoiceId}/send`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invoice sent successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    }
}

function markAsPaid(invoiceId) {
    if (confirm('Mark this invoice as paid?')) {
        fetch(`/invoices/${invoiceId}/mark-paid`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invoice marked as paid successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    }
}
</script>
@endpush
@endsection
