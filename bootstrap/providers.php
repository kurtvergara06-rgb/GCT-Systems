<?php

use App\Providers\AppServiceProvider;
use App\Providers\DataManagementServiceProvider;
use App\Providers\GenericBatchProcessingServiceProvider;

return [
    AppServiceProvider::class,
    DataManagementServiceProvider::class,
    GenericBatchProcessingServiceProvider::class,
];
