<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'Cash';
    case CHEQUE = 'Cheque';
    case TRANSFERT = 'Transfert';
}
