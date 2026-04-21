<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->query('q');

        $skills = Skill::query()
            ->when(is_string($query) && $query !== '', function ($q) use ($query): void {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($query) . '%']);
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'category']);

        return response()->json(['data' => $skills]);
    }
}
