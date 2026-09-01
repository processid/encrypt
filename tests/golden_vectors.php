<?php

// Golden regression vectors.
// GENERATED FROM THE PRE-FIX LIBRARY CODE (commit a542964) ON PHP 8.3.6.
// These prove that ciphertext produced by older releases still decrypts
// identically after the PHP 8.3 typing changes. DO NOT REGENERATE unless the
// wire format is intentionally changed -- regenerating would defeat their purpose.

return [
    // Test-only keys. Never used to protect real data.
    'key_aes256'  => 'ASNFZ4mrze8BI0VniavN7wEjRWeJq83vASNFZ4mrze8=',
    'key_hash512' => '/ty6mHZUMhD+3LqYdlQyEP7cuph2VDIQ/ty6mHZUMhD+3LqYdlQyEP7cuph2VDIQ/ty6mHZUMhD+3LqYdlQyEA==',
    'file_password' => 'correct horse battery staple',

    'strings' => [
        [
            'method'    => 'aes-256-cbc',
            'name'      => 'EMPTY',
            'plain_sha1'=> 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
            'blob'      => '1dJyCON8goi6218EWzJXvmZwhVWLkankd+m0lA0dB72oLRJSQ8VnliJZU+/Kkj2zOvTLqihW46VZGYHwIG0Yri0OW67NxXHdTHHWbhStPVWLyxDuKar44tlkAEvCf3Xx',
        ],
        [
            'method'    => 'aes-256-cbc',
            'name'      => 'ASCII',
            'plain_sha1'=> '0a0a9f2a6772942557ab5355d76af442f8f65e01',
            'blob'      => '0iEFV3wxkp12W4h19gNRHX2z7zsINldv5UrCEoBwtcr7ISlqpBCvvdC+n4dio2Ax+G2orKAFL627pFg9KrQMx+okEJZteCay72URjnyL0Bqphl81fsgB1stMc62WvhPr',
        ],
        [
            'method'    => 'aes-256-cbc',
            'name'      => 'UTF8',
            'plain_sha1'=> '1b27239c1e9f5a73bfb8734a95013e0e8761afcc',
            'blob'      => 'x7ErV4CSyvwCz6jQTP1yW2cWVz4ODiR1KfGtB0oYYsV+YcSOedz2ild6h5D+Q9rVSuZynC5+eA6Qrur2Br7d29rG+gvPWwFSZYzxofdNhWGqWBfKiQQSSY3KUGFeWgTmUR2Oeav4Aa55Fz+KzpCCYg==',
        ],
        [
            'method'    => 'aes-256-cbc',
            'name'      => 'ZERO',
            'plain_sha1'=> 'b6589fc6ab0dc82cf12099d1c2d40ab994e8410c',
            'blob'      => 'kZ7Vvax66fF1yxaSrLsdZO2DiHCVgZi62KtOgYIOdriBfwi/ZnyzKn+3+3S9xBkS78mUiqa5HThfdCLqZFun8xxyMkQUt038HG2dCFeOVdTC9Gx88km8Aa6cc0fFexla',
        ],
        [
            'method'    => 'aes-256-cbc',
            'name'      => 'BINARY',
            'plain_sha1'=> '4708cdc03fad6b366ea3ca061a05aa1096ef7367',
            'blob'      => 'qMoQ/4PsM9McK6S/9n6xHQyyVQ387G7iwoyyyi8YQGFo3QUYHKg07aMtWFgDBLFR1PDnv/f+nc5kZ6774na0sJAzmrxNIz1EEtZi2VdGeuMjVDCGdFYo9KDwmjCo39waSzuh+mGpFeeDEZajN/HfnQ==',
        ],
        [
            'method'    => 'aes-256-cbc',
            'name'      => 'BLOCK16',
            'plain_sha1'=> '19b1928d58a2030d08023f3d7054516dbc186f20',
            'blob'      => '44kI/57vFIO6MBoM2DJF+99oPOEejcQB8Vhb3nDJF19Wf0CWptdOzf7HiJQrOGE0LQ4no15p0m2s4iMazb/51CfTleXoIDp6kht0dTpm2eIabPVKSJ+aBf4hm+eXEnXC89USrbIRitAZQSOAEC4siA==',
        ],
        [
            'method'    => 'aes-128-cbc',
            'name'      => 'EMPTY',
            'plain_sha1'=> 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
            'blob'      => 'PhjhmhQ3CaOf6nb8JM8+v6XR3Z+ZWWptLV0rW4/DQZf7jIO13jN9LCc7U+W2xOKX2cXeOwnK9dPpG8fMtiYMh9H9BLFKh7HgMn4vPlcgxQtTBaskyuRehQRDgTG8sHei',
        ],
        [
            'method'    => 'aes-128-cbc',
            'name'      => 'ASCII',
            'plain_sha1'=> '0a0a9f2a6772942557ab5355d76af442f8f65e01',
            'blob'      => 'xjwcm1xaJCtJbC3C/3KsI9o4RskIq/x/TOLnQXMxNuu/eNKJLUEiCb6gnVQSW8KTM4TMf8fvDkuqvHUEWLcTlOOMcpQQJ2qwrNuaEZixqoo3DLMrWDeylTnuvBOVD+ry',
        ],
        [
            'method'    => 'aes-128-cbc',
            'name'      => 'UTF8',
            'plain_sha1'=> '1b27239c1e9f5a73bfb8734a95013e0e8761afcc',
            'blob'      => 'F8jIx0pe7VC2WUuKvmxekZAyVAUBK2nJcu8Zm5YCJqPIYm9ea807zhe4iIAD2IbEfFZchX6a6gupMQd6Sq6JWWIPg1gpVj8CPmJQPLYHmHESaORgGe/MqHgPFSQ8yFkg/Gbdav3apfzN5F7HamqyzA==',
        ],
        [
            'method'    => 'aes-128-cbc',
            'name'      => 'ZERO',
            'plain_sha1'=> 'b6589fc6ab0dc82cf12099d1c2d40ab994e8410c',
            'blob'      => '1rnmcR+c7RMEYmeLdLwRFBgsxFRos3yViw5SCXkRIPbvVefxmMQT+N4YnEBGoahHRYcRHHTIvQjbWKdjDgFOSqAGV4tlUIipbpQx3PwTDU6zmE+Ub3w9Cym1AozAX/Y2',
        ],
        [
            'method'    => 'aes-128-cbc',
            'name'      => 'BINARY',
            'plain_sha1'=> '4708cdc03fad6b366ea3ca061a05aa1096ef7367',
            'blob'      => 'ys1TRpzfMtbh3p1MPQZeajD5cKANp38eqO7yS4g5ErpHQqaDPOzyF+kIplnT9iR9Kgm/TmoILkwWfPAaPYgzkCeid/MKcsjtlb9iDuc4eZHjWI9+0ZmRstC1Yo3f2BLKwGVpeWbzl6P6iubHAWuoIQ==',
        ],
        [
            'method'    => 'aes-128-cbc',
            'name'      => 'BLOCK16',
            'plain_sha1'=> '19b1928d58a2030d08023f3d7054516dbc186f20',
            'blob'      => 'WFzqLuZosmh3Cq8OfAE+Q4Rxw/u5aQal7mbZLOfQw4tmO9nMkmGju2gPMl/Sen2ZNJTgb4wWRNmvo9Bwo+mwlxA4bYcnoP14+HHib2aNs4wZoNv9XG4rmd4uM9FcUz+j7Fu+WtPh4ZjfIeLfwjNzqA==',
        ],
    ],

    'file' => [
        'method'     => 'aes-256-cbc',
        'plain_sha1' => 'b2ffb84b4adea74dd9fc296da8338187d48e9952',
        'plain_len'  => 224,
        'plain_b64'  => 'TGUgdmlmIHJlbmFyZCBicnVuIHNhdXRlIHBhci1kZXNzdXMgbGUgY2hpZW4gcGFyZXNzZXV4LgpMZSB2aWYgcmVuYXJkIGJydW4gc2F1dGUgcGFyLWRlc3N1cyBsZSBjaGllbiBwYXJlc3NldXguCkxlIHZpZiByZW5hcmQgYnJ1biBzYXV0ZSBwYXItZGVzc3VzIGxlIGNoaWVuIHBhcmVzc2V1eC4KTGUgdmlmIHJlbmFyZCBicnVuIHNhdXRlIHBhci1kZXNzdXMgbGUgY2hpZW4gcGFyZXNzZXV4Lgo=',
        'cipher_b64' => '633zKdp18UfWxH0ivF9BG3yuv2PSMqcJliFeI4AQeodVtF5kCyxwWjt5TNNl6rN5zWPFUqOLPxS1MRqYYB3Km6IqE1H3qoioCNLlHvbU9Os4afPmNAgz7h2rqDa90prOWTGcr8UtxW+z58Vd/zYlMWBE1IH3rFZGr+zEeoUEEz7Rsei57qwsi539YiieYUFOhjbA/dUQhGbjyTSxiBh+eH9KUGsZEg4AX4QAvlFyyQenAqBkw7hSRH819VKeA2wW4ctYwcYuQPilplPHC6Ay4DtaVshTF+zoWAoqye1rXcOJmVfNeqLWGlz6O22QPjr9hai0IG1anN+WbSbYYWkSkA==',
    ],
];
