<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CharacterResource;
use App\Models\Character;

class CharacterController extends Controller
{
    public function show(Character $character)
    {
        $character->load([
            'translations',
            'firstAppearanceEvent.translations',
            'deathEvent.translations',
        ]);

        return new CharacterResource($character);
    }
}
