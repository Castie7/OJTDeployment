<?php

return [
    [
        'name' => 'Exact disease phrase',
        'query' => '"sweet potato blight" control',
        'strict' => true,
        'min_results' => 1,
        'expect_any' => ['sweet potato', 'blight'],
    ],
    [
        'name' => 'Broad crop nutrition',
        'query' => 'cassava nutrition journal',
        'strict' => false,
        'min_results' => 1,
        'expect_any' => ['cassava', 'nutrition'],
    ],
    [
        'name' => 'ISBN lookup',
        'query' => 'isbn 978',
        'strict' => true,
        'min_results' => 1,
        'expect_any' => ['isbn', 'issn', '978'],
    ],
    [
        'name' => 'Author search',
        'query' => 'research by juan dela cruz',
        'strict' => false,
        'min_results' => 1,
        'expect_any' => ['juan', 'dela', 'cruz'],
    ],
    [
        'name' => 'Publisher signal',
        'query' => 'publisher agriculture',
        'strict' => false,
        'min_results' => 1,
        'expect_any' => ['publisher', 'agriculture'],
    ],
];

