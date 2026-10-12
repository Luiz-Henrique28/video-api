<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {

        $query = $request->get('q');

        $authUser = $request->user('sanctum');

        $usersQuery = User::query()
            ->select([
                'id',
                'name as label',
                'avatar as image',
                \DB::raw('"user" as type')
            ])
            ->where('name', 'like', "{$query}%")
            ->when($authUser, function ($q) use ($authUser) {
                $q->whereNotIn('id', function ($sub) use ($authUser) {
                    $sub->select('blocked_id')
                        ->from('user_block')
                        ->where('user_id', $authUser->id);
                });
            });

        $tagsQuery = Tag::query()
            ->select([
                'id',
                'name as label',
                \DB::raw('NULL as image'),
                \DB::raw('"tag" as type')
            ])
            ->where('name', 'like', "%{$query}%");

        $result = $usersQuery
            ->unionAll($tagsQuery)
            ->toBase()
            ->limit(15)
            ->get();

        return response()->json($result);
    }
}
