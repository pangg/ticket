<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RandCode extends Model
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
