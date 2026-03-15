<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Parcelamento no cartão
    |--------------------------------------------------------------------------
    |
    | Define a quantidade máxima de parcelas e a taxa de juros (%) para cada
    | número de parcelas. A chave é o número de parcelas (1, 2, 3...) e o
    | valor é a taxa percentual aplicada ao total (ex: 1.99 = 1,99%).
    | Parcela 1 (à vista) geralmente tem taxa 0.
    |
    */

    'card' => [
        'max_installments' => (int) env('CHECKOUT_CARD_MAX_INSTALLMENTS', 12),

        // Taxa de juros (%) por número de parcelas. 1 = à vista (geralmente 0).
        'interest_rates' => [
            1 => 0,
            2 => 1.99,
            3 => 2.99,
            4 => 3.99,
            5 => 4.99,
            6 => 5.99,
            7 => 6.99,
            8 => 7.99,
            9 => 8.99,
            10 => 9.99,
            11 => 10.99,
            12 => 11.99,
        ],
    ],

];
