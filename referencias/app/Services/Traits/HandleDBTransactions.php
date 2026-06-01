<?php

namespace App\Services\Traits;

use Illuminate\Support\Facades\DB;


trait HandleDBTransactions
{

    protected function beginTransaction(bool $useTransaction): void
    {
        if ($useTransaction) {
            DB::beginTransaction();
        }
    }


    protected function commitTransaction(bool $useTransaction): void
    {
        if ($useTransaction) {
            DB::commit();
        }
    }


    protected function rollBackTransaction(bool $useTransaction): void
    {
        if ($useTransaction) {
            DB::rollBack();
        }
    }

}
