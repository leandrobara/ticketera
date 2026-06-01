<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Requests\Web\LeadsBulkUploadPageRequest;
use App\Http\Controllers\Controller as BaseController;


class LeadsBulkUploadController extends BaseController
{

    public function show(LeadsBulkUploadPageRequest $req)
    {
        $preLoadedLeadsPreviewArr = $req->getPreLoadedLeadsPreviewArr();
        return view('web.leads-bulk-upload.show', ['preLoadedLeadsPreviewArr' => $preLoadedLeadsPreviewArr]);
    }

}