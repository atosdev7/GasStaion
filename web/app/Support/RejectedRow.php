<?php

namespace App\Support;

// A pushed row the server will not store. The message goes back to the Smart210 and into sync_errors.
class RejectedRow extends \Exception
{
}
