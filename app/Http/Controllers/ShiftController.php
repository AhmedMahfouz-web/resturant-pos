<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function startShift($user)
    {
        $shift = Shift::where('end_time', null)->first();
        if ($shift) {
            return $shift->id;
        } else {

            if ($user->can('start shift')) {
                $shift = Shift::create([
                    'user_id' => $user->id,
                    'start_time' => now(),
                ]);
                return $shift->id;
            } else {
                return null;
            }
        }
    }

    public function endShift($user)
    {
        $shift = Shift::where('user_id', $user->id)->where('end_time', null)->first();
        if ($shift) {
            $shift->end_time = now();
            $shift->save();
        }
    }
}
