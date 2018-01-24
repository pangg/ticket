<?php

namespace App\Http\Controllers;

use App\Model\RandCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ImageController extends Controller
{
    //
    public function showImage()
    {
        return view('img');
    }

}
