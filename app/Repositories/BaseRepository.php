<?php

namespace App\Repositories;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Criterias\CriteriaInterface;
use App\Repositories\RepositoryInterface;
use App\Http\Presenters\PresenterInterface;
use Illuminate\Support\Facades\Auth;
use Log;

class BaseRepository implements RepositoryInterface
{
  protected $model;
  protected $queryExpense;
  protected $query;
  protected $included;
  protected $presenter;
  protected $modelInstance;
  protected $password;
  protected $foto;
  protected $total;
  
  public function __construct(string $model)
  {
      $this->reinit($model);
  }

  public function reinit(string $model)
  {
      $this->model = $model;
      $this->modelInstance = null;
      $this->query = null;
      $this->total = 0;
      $this->queryExpense = null;
      $this->included = [];
      $this->password = null;
      $this->foto = null;
  }

  public function getModel()
  {
      if (!$this->modelInstance) {
          $this->modelInstance = app()->make($this->model);
      }

      return $this->modelInstance;
  }

  public function applyCriteria(CriteriaInterface $criteria)
  {
      $this->query = $criteria->apply($this->query);

      return $this;
  }
  public function renderCollection($request)
  {
    if ($request->page) {
      $this->query = $this->query->paginate($request->jum ? $request->jum : 10 );
    }
    else {
      $this->query = $this->query->get();
    }
    return response()->json([
      'status' => true,
      'values' => $this->query,
    ], 200);
  }
  public function render($request)
  {
    $this->query = $this->query->first();
    return response()->json([
        'status' => true,
        'values' => $this->query,
    ], 200);
  }
}
