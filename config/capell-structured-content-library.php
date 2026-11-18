<?php

declare(strict_types=1);

return [
    // Longest time, in seconds, that the public list of records is reused before it is read again.
    // Saving, deleting or reordering a record clears it at once, and a record scheduled to go live
    // (or to expire) inside this window shortens it to that moment.
    'public_cache_ttl_seconds' => (int) env('CAPELL_STRUCTURED_CONTENT_PUBLIC_CACHE_TTL_SECONDS', 300),
];
