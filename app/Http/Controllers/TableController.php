<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index()
    {
        return response()->json(Table::all());
    }

    // Create a new table
    public function createTable(Request $request)
    {
        $request->validate(['number' => 'required|unique:tables']);
        $table = Table::create($request->all());

        return response()->json(['message' => 'Table created successfully', 'table' => $table], 201);
    }

    // Update a table
    public function updateTable(Request $request, $id)
    {
        $table = Table::findOrFail($id);
        $table->update($request->all());

        return response()->json(['message' => 'Table updated successfully', 'table' => $table]);
    }

    // Delete a table
    public function deleteTable($id)
    {
        Table::findOrFail($id)->delete();
        return response()->json(['message' => 'Table deleted successfully']);
    }
}
