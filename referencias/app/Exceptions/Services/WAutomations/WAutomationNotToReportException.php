<?php

namespace App\Exceptions\Services\WAutomations;

use Exception;


// Hecha para evitar enviar un automation, pero NO reportarlo en Sentry
class WAutomationNotToReportException extends Exception
{
}
