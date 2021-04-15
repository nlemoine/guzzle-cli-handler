<?php

echo json_encode(
    [
        '_ENV' => [
            'a' => getenv('a'),
            'b' => getenv('b'),
        ],
        '_GET' => [
            'G' => [
                'a' => $_GET['G']['a'],
                'b' => $_GET['G']['b'],
            ],
        ],
        '_POST' => [
            'P' => [
                'a' => $_POST['P']['a'],
                'b' => $_POST['P']['b'],
            ],
        ],
        '_COOKIE' => [
            'C[a]' => $_COOKIE['C[a]'],
            'C[b]' => $_COOKIE['C[b]'],
        ],
        '_SERVER' => [
            'REQUEST_URI'     => $_SERVER['REQUEST_URI'],
            'REQUEST_METHOD'  => $_SERVER['REQUEST_METHOD'],
            'HTTP_HOST'       => $_SERVER['HTTP_HOST'],
            'SERVER_NAME'     => $_SERVER['SERVER_NAME'],
            'QUERY_STRING'    => $_SERVER['QUERY_STRING'],
            'PHP_SELF'        => $_SERVER['PHP_SELF'],
            'SCRIPT_NAME'     => $_SERVER['SCRIPT_NAME'],
            'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'],
            'SERVER_PORT'     => $_SERVER['SERVER_PORT'],
            'HTTPS'           => $_SERVER['HTTPS'],
        ],
    ],
    JSON_PRETTY_PRINT
);
