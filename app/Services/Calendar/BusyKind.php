<?php

namespace App\Services\Calendar;

enum BusyKind: string
{
    case Busy = 'busy';           // bezet
    case Tentative = 'tentative'; // voorlopig; telt als bezet (geen dubbele boeking)
    case Away = 'away';           // afwezig: vakantie of vrije dag
}
