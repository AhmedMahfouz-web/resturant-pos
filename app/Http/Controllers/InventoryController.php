<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    public function storeReceipt(Request $request)
    {
        $validated = $request->validate([
            'materials' => 'required|array|min:1',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0.01',
            'materials.*.unit_cost' => 'required|numeric|min:0'
        ]);

        return DB::transaction(function () use ($validated) {
            foreach ($validated['materials'] as $item) {
                $material = Material::find($item['material_id']);

                InventoryTransaction::create([
                    'material_id' => $material->id,
                    'type' => 'receipt',
                    'quantity' => $item['quantity'],
                    'purchase_price' => $item['unit_cost'],
                    'user_id' => auth()->id()
                ]);

                $material->quantity += $item['quantity'];
                $material->purchase_price = $item['unit_cost'];
                $material->save();
            }

            return response()->json(['message' => 'تم تسجيل الاستلام بنجاح']);
        });
    }

    public function adjustStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'materials' => 'required|array',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.adjustment_type' => 'required|in:add,remove',
            'materials.*.quantity' => 'required|numeric|min:0.01',
            'materials.*.unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::transaction(function () use ($request) {
            foreach ($request->materials as $item) {
                $material = Material::find($item['material_id']);

                $transaction = new InventoryTransaction([
                    'type' => 'adjustment',
                    'quantity' => $item['quantity'] * ($item['adjustment_type'] === 'add' ? 1 : -1),
                    'purchase_price' => $item['unit_cost'] ?? $material->purchase_price,
                    'user_id' => auth()->id(),
                    'note' => $request->reason
                ]);

                $material->transactions()->save($transaction);
            }

            $material->quantity += $item['quantity'] * ($item['adjustment_type'] === 'add' ? 1 : -1);
            $material->purchase_price = $item['unit_cost'] ?? $material->purchase_price;
            $material->save();
        });


        return response()->json(['message' => 'تم التعديل بنجاح']);
    }
}
