<?php

namespace App\Enums;

enum PaymentTypeEnum: string
{
    case SUBSCRIPTION = 'Subscription';
    case TICKET = 'Ticket';
}
