<?php

namespace App\Http\Resources\Views\Reports\SentProposal;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class SentProposalReportResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toarray($request)
    {
        $response = [];
        foreach ($this->collection as $sentemail) {
            $response[] = new SentProposalReportItemResource($sentemail);
        }

        $response = $this->addpaginationinfo($this->resource, $response);
        return $response;
    }

}
