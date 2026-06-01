<?php

namespace App\Exceptions\Services\Automations;

use Exception;


// Hecha para evitar enviar un automation, pero NO reportarlo en Sentry
class AutomationNotToReportException extends Exception
{
}
