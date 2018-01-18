<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    //
    /**
     * @return string
     */
    public function getDateFormat()
    {
        return time();
    }
}
