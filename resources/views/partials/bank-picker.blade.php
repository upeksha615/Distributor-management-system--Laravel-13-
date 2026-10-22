{{--
    Bank + branch selects from the bank master. Branches load when a bank is chosen.
    $banks (collection), optional $bankLabel, $bankId, $branchId, $required
--}}
<div class="contents" x-data="{ bank: @js(old('bank_id', $bankId ?? '')), branch: @js(old('bank_branch_id', $branchId ?? '')), branches: [],
        async load() { this.branches = this.bank ? await (await fetch('{{ url('banks') }}/' + this.bank + '/branch-lookup', { headers: { Accept: 'application/json' } })).json() : []; } }"
     x-init="load()">
    <div>
        <label class="label">{{ $bankLabel ?? 'Bank' }} @if ($required ?? false)<span class="text-red-500">*</span>@endif</label>
        <select name="bank_id" x-model="bank" @change="branch = ''; load()" class="input" @required($required ?? false)>
            <option value="">Select bank...</option>
            @foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
        </select>
        @error('bank_id')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label">Branch</label>
        <select name="bank_branch_id" x-model="branch" class="input">
            <option value="" x-text="branches.length ? 'Select branch...' : 'No branches on file'"></option>
            <template x-for="b in branches" :key="b.id"><option :value="b.id" x-text="b.name + (b.code ? ' (' + b.code + ')' : '')" :selected="b.id == branch"></option></template>
        </select>
    </div>
</div>
