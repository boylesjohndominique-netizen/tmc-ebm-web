<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a database transaction when equipment stock is insufficient.
 * Controllers catch it to roll the transaction back and return a clean JSON 422.
 */
class InsufficientStockException extends RuntimeException
{
}
