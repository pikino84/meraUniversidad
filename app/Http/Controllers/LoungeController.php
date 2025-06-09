<?php

namespace App\Http\Controllers;

use App\Models\Lounge;
use Illuminate\Http\Request;

class LoungeController extends Controller
{
    public function index()
    {
        $lounges = Lounge::all();
        return view('lounges.index', compact('lounges'));
    }

    public function create()
    {
        return view('lounges.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'terminal' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        Lounge::create($request->all());

        return redirect()->route('lounges.index')->with('success', 'Sala creada correctamente.');
    }

    public function edit(Lounge $lounge)
    {
        return view('lounges.edit', compact('lounge'));
    }

    public function update(Request $request, Lounge $lounge)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'terminal' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        $lounge->update($request->all());

        return redirect()->route('lounges.index')->with('success', 'Sala actualizada correctamente.');
    }

    public function destroy(Lounge $lounge)
    {
        $lounge->delete();

        return redirect()->route('lounges.index')->with('success', 'Sala eliminada correctamente.');
    }
}
