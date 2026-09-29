<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = strtolower(trim($credentials['email']));
        if (! Auth::attempt([
            'email' => fn (Builder $query) => $query->whereRaw('LOWER(email) = ?', [$email]),
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
