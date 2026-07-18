<?php

return [
    /*
    | Whether authenticated users must accept the terms and conditions before
    | using the system. On by default; the test suite disables it via phpunit.xml
    | (TermsTest re-enables it explicitly to cover the gate itself).
    */
    'enforced' => env('TERMS_ENFORCED', true),
];
