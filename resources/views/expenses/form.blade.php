<x-layouts.app :title="$expense->exists ? 'Edit expense' : 'Record expense'">
    <x-page-header :title="$expense->exists ? 'Edit expense '.$expense->number : 'Record expense'">
        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @if ($expense->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="Date" name="expense_date" type="date" :value="$expense->expense_date?->format('Y-m-d')" required />
            <x-select label="Category" name="expense_category_id" :options="$categories" :value="$expense->expense_category_id" placeholder="Select..." required />
            <x-field label="Amount" name="amount" type="number" step="0.01" min="0.01" :value="$expense->amount" required />
            <x-select label="Paid by" name="method" :options="\App\Models\Expense::METHODS" :value="$expense->method" required />
            <x-field label="Payee" name="payee" :value="$expense->payee" placeholder="Who was paid" />
            <x-field label="Reference" name="reference" :value="$expense->reference" placeholder="Bill / cheque / transfer no" />
            <x-field label="Description" name="description" :value="$expense->description" class="sm:col-span-3" />
        </div>
        <div class="flex items-center gap-3">
            <button class="btn btn-primary">Save expense</button>
            <a href="{{ route('expense-categories.index') }}" class="link text-sm">Manage categories</a>
        </div>
    </form>
</x-layouts.app>
