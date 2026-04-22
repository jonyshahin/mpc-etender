<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

require __DIR__.'/Feature/Tender/Datasets.php';
