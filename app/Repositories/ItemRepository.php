<?php

namespace App\Repositories;

use App\Models\Item;
use App\Http\Criterias\SearchCriteria;
use App\Repositories\BaseRepository;

class ItemRepository extends BaseRepository
{
	public function __construct()
	{
		parent::__construct(Item::class);
	}

	public function browse($request)
	{
		try{
			$this->query= \DB::select("SELECT items.id, items.nama, jsonb_agg(json_build_object(
				'id', taxes.id,
				'nama' , taxes.nama,
			  	'rate', taxes.rate
			)) 
			AS pajak FROM items
		  	INNER JOIN taxes ON taxes.item_id = items.id GROUP BY items.id");

			return response()->json([
				'success' => true,
				'data' => $this->pagination($request),
			], 200);
		}catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage()
			], 400);
		}
	}

	public function show($id, $request)
	{
		try{
			$this->query = $this->getModel()->where(['id' => $id])->with(['tax']);
			$this->applyCriteria(new SearchCriteria($request));

			return $this->render($request);
		}catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage()
			], 400);
		}
	}

	public function store($request)
	{
		try {
			$payload = $request->all();
			$item = Item::create($payload);
			if ($request->filled('taxs')) {
				foreach ($payload['taxs'] as $value) {
					$item->tax()->create($value);
				}
			}

			return $this->show($item->id, $request);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage()
			], 400);
		}
	}

	public function update($id, $request)
	{
		try {
			$payload = $request->all();
			$item = Item::findOrFail($id);
			$item->tax()->delete();
			if ($request->filled('taxs')) {
				foreach ($payload['taxs'] as $value) {
					$item->tax()->create($value);
				}
			}
			$item->update($payload);
			
			return $this->show($id, $request);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage()
			], 400);
		}
	}

	public function destroy($id)
	{
		try {
			$item = Item::findOrFail($id);
			$item->tax()->delete();
			$item->delete();

			return response()->json([
				'success' => true,
				'message' => 'data has been deleted'
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage()
			], 400);
		}
	}
}
