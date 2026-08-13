<?php

use App\Providers\AppServiceProvider;
use App\Providers\BatchAttendanceServiceProvider;
use App\Providers\DataManagementServiceProvider;
use App\Providers\GenericBatchProcessingServiceProvider;

return [
    AppServiceProvider::class,
    BatchAttendanceServiceProvider::class,
    DataManagementServiceProvider::class,
    GenericBatchProcessingServiceProvider::class,
];
