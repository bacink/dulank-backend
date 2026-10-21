<?php

namespace App\Http\Resources\Admin;

use App\Models\BankAccount;
use App\Models\BankLedgerEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountStatementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{account: BankAccount, openingBalance: int, totalCredit: int, totalDebit: int, currentBalance: int, entries: Collection<int, BankLedgerEntry>} $statement */
        $statement = $this->resource;

        return [
            'account' => new BankAccountResource($statement['account']),
            'openingBalance' => $statement['openingBalance'],
            'totalCredit' => $statement['totalCredit'],
            'totalDebit' => $statement['totalDebit'],
            'currentBalance' => $statement['currentBalance'],
            'entries' => BankLedgerEntryResource::collection($statement['entries']),
        ];
    }
}
