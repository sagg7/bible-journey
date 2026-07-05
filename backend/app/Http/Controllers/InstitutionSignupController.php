<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstitutionSignupController extends Controller
{
    public function store(Request $request)
    {
        $minSeats = (int) config('services.stripe_institution_min_seats');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'seats' => ['required', 'integer', "min:{$minSeats}"],
            'billing_period' => ['required', Rule::in(['monthly', 'annual'])],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
        ]);

        $existingUser = User::where('email', $data['admin_email'])->first();
        if ($existingUser && $existingUser->institution_id) {
            return back()
                ->withInput()
                ->withErrors(['admin_email' => 'Ese correo ya está asociado a otra institución.']);
        }

        $priceId = $data['billing_period'] === 'annual'
            ? config('services.stripe_institution_price_id_annual')
            : config('services.stripe_institution_price_id_monthly');

        if (! $priceId) {
            return back()->withInput()->withErrors(['seats' => 'La suscripción institucional no está disponible en este momento.']);
        }

        $institution = Institution::create([
            'name' => $data['name'],
            'seats' => $data['seats'],
        ]);

        $generatedPassword = null;
        if ($existingUser) {
            $existingUser->update([
                'institution_id' => $institution->id,
                'is_institution_admin' => true,
            ]);
            $adminUser = $existingUser;
        } else {
            $generatedPassword = Str::random(12);
            $adminUser = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $generatedPassword,
                'institution_id' => $institution->id,
                'is_institution_admin' => true,
            ]);
        }

        $checkout = $institution->newSubscription('default', $priceId)
            ->quantity($institution->seats)
            ->checkout([
                'success_url' => url('/instituciones/gracias'),
                'cancel_url' => url('/#instituciones'),
            ]);

        return view('instituciones-creada', [
            'institution' => $institution,
            'adminUser' => $adminUser,
            'generatedPassword' => $generatedPassword,
            'checkoutUrl' => $checkout->url,
        ]);
    }
}
