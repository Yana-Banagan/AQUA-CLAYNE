@extends('admin.layouts.app')

@section('title', 'Customer Debt')

@section('content')

<div class="page-header">
    <div>
        <p class="eyebrow">Debt Management</p>
        <h1>Customer Debt</h1>
        <p>Review outstanding debt and record a payment when it is received.</p>
    </div>
</div>

<div class="container-fluid">

    <div class="card mb-4">
        <div class="card-body">

            <h5>
                {{ $customer->user?->name ?? 'Customer' }}
            </h5>

            @if(! $customer->creditAccount)

                <p class="text-muted mb-0">
                    This customer has no outstanding debt.
                </p>

            @else

                <div class="row g-3">

                    <div class="col-md-6">
                        <strong>Outstanding Debt</strong>

                        <div class="fs-4">
                            ₱{{ number_format(
                                $customer->creditAccount->balance,
                                2
                            ) }}
                        </div>
                    </div>

                </div>

                <hr>

                <form
                    method="POST"
                    action="{{ route(
                        'admin.debt-accounts.pay',
                        $customer->creditAccount
                    ) }}"
                >

                    @csrf

                    <label class="form-label">
                        Record Payment
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            ₱
                        </span>

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="amount"
                            class="form-control"
                            required
                        >

                        <button
                            class="btn btn-primary"
                            type="submit"
                        >
                            Apply Payment
                        </button>

                    </div>

                </form>

            @endif

        </div>
    </div>

    @if($customer->creditAccount)

        <div class="card">

            <div class="card-body">

                <h5 class="mb-3">
                    Transaction History
                </h5>

                <table class="table">

                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                    @forelse(
                        $customer
                            ->creditAccount
                            ->transactions
                            ->sortByDesc('created_at')
                        as $transaction
                    )

                        <tr>

                            <td>
                                {{ ucfirst($transaction->type) }}
                            </td>

                            <td>
                                ₱{{ number_format(
                                    $transaction->amount,
                                    2
                                ) }}
                            </td>

                            <td>
                                {{ $transaction->created_at }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="3">
                                No transactions yet.
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    @endif

</div>

@endsection
