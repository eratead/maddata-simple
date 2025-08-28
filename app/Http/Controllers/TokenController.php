<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function index()
    {
        $tokens = Auth::user()->tokens()->get(['id', 'name', 'created_at', 'expires_at']);
        return view('tokens.index', compact('tokens'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'token_name' => 'required|string|max:255',
        ]);

        $plainToken = Auth::user()->createToken($request->token_name);
        $plainToken->accessToken->expires_at = now()->addDays(30);
        $plainToken->accessToken->save();

        return back()->with('token', $plainToken->plainTextToken);
    }

    public function destroy($id)
    {
        $token = Auth::user()->tokens()->where('id', $id)->firstOrFail();
        $token->delete();

        return back()->with('message', 'Token deleted.');
    }

    public function extend($id)
    {
        $token = Auth::user()->tokens()->where('id', $id)->firstOrFail();

        $token->expires_at = now()->addDays(30);
        $token->save();

        return back()->with('message', 'Token extended by 30 days.');
    }
}
