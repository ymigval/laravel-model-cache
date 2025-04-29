<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    /**
     * Display a listing of the active users.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // This will use the cached query results
        $users = User::active()->get();
        
        return view('users.index', compact('users'));
    }
    
    /**
     * Display a listing of all users with custom cache duration.
     *
     * @return \Illuminate\Http\Response
     */
    public function all()
    {
        // Cache the results for 2 hours
        $users = User::remember(120)->get();
        
        return view('users.all', compact('users'));
    }
    
    /**
     * Find a specific user by email, using cache.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function findByEmail(Request $request)
    {
        $email = $request->input('email');
        
        // This will use the cached query result or store it if not exists
        $user = User::where('email', $email)->remember()->firstFromCache();
        
        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }
        
        return view('users.show', compact('user'));
    }
    
    /**
     * Update a user, which will automatically invalidate cache.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $user->update($request->validated());
        
        // No need to manually invalidate cache - the HasCachedQueries trait
        // handles this automatically when the model is updated
        
        return redirect()->route('users.show', $user)->with('success', 'User updated.');
    }
}
