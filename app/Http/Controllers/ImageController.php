<?php

namespace App\Http\Controllers;

use App\Model\RandCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ImageController extends Controller
{
    //
    public function showImage($id)
    {
        $randCode = RandCode::find($id);
        if ($randCode == null) {

            return abort(404);
        }
        return view('img', compact('randCode', 'id'));
    }

    public function saveAnswer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [

            'answer' => 'required'
        ], [

        ]);
        if (!$validator->passes()) {

            return redirect()->refresh();
        } else {

            $randCode = RandCode::find($id);
            if ($randCode == null) {

                return abort(404);

            } else {

                $randCode->value = $request->input('answer');
                $randCode->save();
                return redirect('image/' . ($id + 1));
            }
        }
    }
}
