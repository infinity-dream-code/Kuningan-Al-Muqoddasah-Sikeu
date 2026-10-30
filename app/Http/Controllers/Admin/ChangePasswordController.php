<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CyberKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChangePasswordController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return view('admin.ganti_password', [
            'title' => 'Akun',
            'mainTitle' => 'Ganti Password',
            'dataTitle' => 'Ganti Password',
            'username' => (string) ($user->users ?? $user->name ?? ''),
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password_lama.required' => 'Password lama wajib diisi.',
            'password_baru.required' => 'Password baru wajib diisi.',
            'password_baru.min' => 'Password baru minimal 6 karakter.',
            'password_baru.confirmed' => 'Konfirmasi password baru tidak sama.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        /** @var CyberKey|null $user */
        $user = Auth::user();
        if (!$user) {
            return back()->withErrors(['password_lama' => 'Sesi login tidak ditemukan.']);
        }

        $stored = strtolower(trim((string) $user->password));
        $oldHash = strtolower(md5((string) $request->input('password_lama')));

        if ($stored === '' || $oldHash !== $stored) {
            return back()->withErrors(['password_lama' => 'Password lama tidak sesuai.']);
        }

        $user->password = strtolower(md5((string) $request->input('password_baru')));
        $user->save();

        return back()->with('success', 'Password berhasil diubah.');
    }
}
