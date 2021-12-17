<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use App\Repositories\ItemRepository;
use App\Http\Requests\ItemRequest;

class ItemController extends Controller
{
    public function __construct(ItemRepository $repository)
    {
        $this->repository = $repository;
    }
    public function index(Request $request)
    {
        return $this->repository->browse($request);
    }

    public function store(ItemRequest $request)
    {
        return $this->repository->store($request);
    }

    public function show($id, Request $request)
    {
        return $this->repository->show($id, $request);
    }

    public function update($id, ItemRequest $request)
    {
        return $this->repository->update($id,$request);
    }

    public function destroy($id)
    {
        return $this->repository->destroy($id);
    }
}
