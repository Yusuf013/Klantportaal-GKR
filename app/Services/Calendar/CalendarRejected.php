<?php

namespace App\Services\Calendar;

use RuntimeException;

/**
 * De agenda weigert het verzoek blijvend (rechten, configuratie, onbekende mailbox). Opnieuw
 * proberen helpt niet; een beheerder moet iets aanpassen.
 */
class CalendarRejected extends RuntimeException {}
