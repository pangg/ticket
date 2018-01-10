<?php

namespace App\Http\Controllers;

use App\Model\RandCode;
use Illuminate\Routing\Controller;

class ImageController extends Controller
{
    //
    public function showImage($id)
    {
        $randCode = RandCode::find($id);
        if ($randCode == null) {

            return abort(404);
        }
        return view('img',compact('randCode'));
    }
}
