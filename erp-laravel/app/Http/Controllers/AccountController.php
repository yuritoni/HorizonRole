<?php

namespace App\Http\Controllers;

use App\Support\ErpViewData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.profile', array_merge(ErpViewData::layout(), [
            'user' => $request->user()->load('employeeProfile'),
        ]));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($request->hasFile('photo')) {
            $directory = public_path('uploads/profiles');
            File::ensureDirectoryExists($directory);

            $file = $request->file('photo');
            $filename = 'user-'.$user->id.'-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);
            $payload['photo_path'] = 'uploads/profiles/'.$filename;
        }

        $user->update($payload);

        return back()->with('success', 'Profil akun berhasil diperbarui.');
    }
}
