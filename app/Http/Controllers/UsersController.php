<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use App\Http\Requests;
use Validator;
use Hash;
use Auth;
use Log;
use Password;
use App\Http\Controllers\Controller;

class UsersController extends Controller
{
    public function getIndex()
    {
        $cuc = new CorporateUserController();
        $data['candidates'] = $cuc->load_candidates();
        return view('profile')->with($data);
    }

    public function postUser(Request $request)
    {
        $this->validate($request, User::$personalRules);
        $userData = $request->only(['email', 'name']);
        Auth::user()->update($userData);

        return response()->json(['status' => true]);
    }

    public function postPassword(Request $request)
    {

        Validator::extend('current_password', function ($attribute, $value) {
            return Hash::check($value, Auth::user()->password);
        });
        $this->validate($request, [
            'password' => 'required|confirmed|min:6|different:current_password',
        ]);

        $newPassword = bcrypt($request->input('password'));

        Auth::user()->update(['password' => $newPassword]);

        return response()->json(['status' => true]);

    }



    public function postUpload(Request $request)
    {
        $image = Input::file('file');
        $validator = Validator::make([$image], ['image' => 'required']);
        if ($validator->fails()) {
            return $this->errors(['message' => 'Not an image.', 'code' => 400]);
        }
        $destinationPath = storage_path() . '/user_images';
        if(!$image->move($destinationPath, $image->getClientOriginalName())) {
            return $this->errors(['message' => 'Error saving the file.', 'code' => 400]);
        }
        Auth::user()->update(['image_location' => $destinationPath."/".$image->getClientOriginalName()]);
        return response()->json(['status' => true]);
    }

    public function postCrop(Request $request)
    {

    }

}
