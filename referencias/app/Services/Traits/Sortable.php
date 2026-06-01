<?php

namespace App\Services\Traits;


trait Sortable
{
    private function reOrder($models)
    {
        $order = 0;
        foreach ($models as $model) {
            $model->order = $order;
            $model->saveOrFail();
            $order++;
        }
    }
}
