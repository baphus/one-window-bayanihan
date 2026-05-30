<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvatarRequest;
use App\Models\User;

class UserAvatarController extends Controller
{
    public function __invoke(AvatarRequest $request, User $user)
    {
        $file = $request->file('avatar');
        $filename = 'user-'.$user->id.'-'.time().'.'.$file->extension();
        $path = $file->storeAs('avatars', $filename, 'public');

        $user->update(['avatar_url' => $path]);

        return redirect()->back()->with('success', 'Avatar updated successfully.');
    }
}
