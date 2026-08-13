<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchUpload;
use Illuminate\Http\RedirectResponse;

class GenericBatchModalRedirectController extends Controller
{
    public function __invoke(BatchUpload $batchUpload): RedirectResponse
    {
        if (
            $batchUpload->data_type === 'GPS Trip Records'
            || $batchUpload->status === 'Processed'
        ) {
            return redirect()->route('batch-file-processing', [
                'batch_id' => $batchUpload->id,
            ]);
        }

        return redirect()->route('batch-file-processing', [
            'generic_batch_id' => $batchUpload->id,
        ]);
    }
}
