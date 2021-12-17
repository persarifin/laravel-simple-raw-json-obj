<?php

namespace App\Http\Criterias;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Criterias\CriteriaInterface;

class SearchCriteria implements CriteriaInterface
{
    protected $request;

    protected $fieldSet = [];
    protected $filterSet = [];
    protected $sortSet = [];
    protected $includeSet = [];

    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->initFilters();
        $this->initSortBy();
    }

    protected function initFilters()
    {
        if ($this->request->filled('filter') && is_array($this->request->input('filter'))) {
            foreach ($this->request->input('filter') as $name => $criteria) {
                if (!is_array($criteria)) {
                    $this->addFilter($name, 'is', $criteria);
                    continue;
                }
                else {
                    foreach ($criteria as $operator => $value) {
                        $this->addFilter($name, $operator, $value);
                    }
                }
            }
        }
    }

    protected function initSortBy()
    {
        if ($this->request->filled('sort')) {
            $sort = explode(',', $this->request->input('sort'));

            foreach ($sort as $sortItem) {
                if (substr($sortItem, 0, 1) === '-') {
                    $this->addSortBy(substr($sortItem, 1), 'DESC');

                    continue;
                }

                $this->addSortBy($sortItem, 'ASC');
            }
        }
    }

    public function addSortBy($column, $direction)
    {
        $this->sortSet += [ $column => $direction ];
    }

    public function addFilter($field, $operator, $value)
    {
        if ($operator === 'in' && is_string($value)) {
            $value = explode(',', $value);
        }else if($operator === 'not_in' && is_string($value)) {
          $value = explode(',', $value);
        }

        $this->filterSet[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
    }

    public function apply($query)
    {
		DB::beginTransaction();
		
        $model = $query->getModel();
        $table = $model->getTable();
        
        foreach ($this->fieldSet as $table => $columns) {
            foreach ($columns as $column) {
                $query->select("{$table}.{$column}");
            }
        }
        if ($this->request->filled('filter') && is_array($this->request->input('filter'))) {
            $log = 0;
            foreach ($this->request->input('filter') as $name => $criteria) {
                if ($name === 'inclusive') {
                    foreach ($criteria as $field => $defined) {
                        if (!is_array($defined)) {
                            $sendRules[] = [
                                'field' => $name.'.'.$field, 
                                'operator' => 'is', 
                                'value' => $defined
                            ];
                            continue;
                        }
                        foreach ($defined as $operator => $value) {
                            $sendRules[] = [
                                'field' => $name.'.'.$field, 
                                'operator' => $operator, 
                                'value' => $value
                            ];
                        }
                    }
                    $query = $query->where(function($query) use($sendRules){
                        $this->addFilterInclusive($query, $sendRules);
                    });
                    continue;
                }
                else{
                    foreach ($this->filterSet as $rule) {
                        if (strpos($rule['field'], '.') !== false) {
                            $explode = explode('.', $rule['field']);                
                            $rules = [
                                'relation' => $explode[0],
                                'field' => $explode[1],
                                'operator' => $rule['operator'],
                                'value' => $rule['value']
                            ];
                                $query = $this->applyRelationCondition($query, $rules);  
                        }
                        else {
                            $table = $query->getModel()->getTable();
                            if ($table == 'submissions' && $rule['field'] == 'submission' && $rule['operator'] == 'like') {
                                if ($log ==0) {
                                    $this->searchSubmission($query, $rule);
                                }
                                $log++;
                            }
                            else {
                                $query = $this->applyCondition($query, $rule);
                            }
                        }
                    }  
                }
            }
        }
        return $query;
    }
    protected function addFilterInclusive($query, $rules){
        foreach ($rules as $rule) {
            if ($rule['operator'] === 'in' && is_string($rule['value'])) {
                $rule['value'] = explode(',', $rule['value']);
            }else if($rule['operator'] === 'not_in' && is_string($rule['value'])) {
                $rule['value'] = explode(',', $rule['value']);
            }
            
            if (strpos($rule['field'], '.') !== false) {
                $explode = explode('.', $rule['field']);
                if (isset($explode[2])) {
                    $rule = [
                        'relation' => $explode[1],
                        'field' => $explode[2],
                        'operator' => $rule['operator'],
                        'value' => $rule['value']
                    ];
                    $query = $this->applyRelationInclusiveCondition($query, $rule);
                } else {
                    $rule = [
                        'field' => $explode[1],
                        'operator' => $rule['operator'],
                        'value' => $rule['value']
                    ];
                    $query = $this->applyInclusive($query, $rule);
                } 
            }
        }
        return $query;
    }
    protected function applyInclusive($query, $rule)
    {
        switch ($rule['operator']) {
            case "is":
                $query->orWhere($rule['field'], $rule['value']);
                break;
            case "lt":
                $query->orWhere($rule['field'], '<', $rule['value']);
                break;
            case "lte":
                $query->orWhere($rule['field'], '<=', $rule['value']);
                break;
            case "gt":
                $query->orWhere($rule['field'], '>', $rule['value']);
                break;
            case "gte":
                $query->orWhere($rule['field'], '>=', $rule['value']);
                break;
            case "not":
                $query->orWhere($rule['field'], '!=', $rule['value']);
                break;
            case "like":
                $query->orWhere($rule['field'], 'ILIKE', '%' . $rule['value'] . '%');
                break;
            case "not_like":
                $query = $query->where($rule['field'], 'NOT ILIKE', '%' . $rule['value'] . '%');
                break;
            case "in":
                if (in_array('null', $rule['value'])) {
                    $null = array_search('null', $rule['value']);
                    unset($rule['value'][$null]);
                    $query->orWhere(function($query) use ($rule) {
                        $query->whereIn($rule['field'],  $rule['value']);
                        $query->orWhereNull($rule['field']);
                    });
                } else {
                    $query->orWhere(function($query)use($rule){
                        $query->whereIn($rule['field'], $rule['value']);
                    });
                }
                break;
            case "not_in":
                if (in_array('null', $rule['value'])) {
                    $null = array_search('null', $rule['value']);
                    unset($rule['value'][$null]);
                    $query->orWhere(function($query) use ($rule) {
                        $query->whereNotIn($rule['field'],  $rule['value']);
                        $query->orWhereNull($rule['field']);
                    });
                } else {
                    $query->whereNotIn($rule['field'], $rule['value']);
                }
                break;
            case "between":
                $values = array_slice(explode(',', $rule['value']), 0, 2);
                $query->orWhere(function($query) use ($rule) {
                    $query->whereBetween($rule['field'], $values );
                });
                break;
            case "null":
                if ($rule['value'] == 'true') {
                    $query->orWhere(function($query) use ($rule) {
                        $query->whereNull($rule['field']);
                    });
                } elseif ($rule['value'] == 'false') {
                    $query->orWhere(function($query) use ($rule) {
                        $query->whereNotNull($rule['field']);
                    });
                }
                break;
            case "date":
                $query->orWhere(function($query) use ($rule) {
                    $query->whereDate($rule['field'], $rule['value']);
                });
                break;
            case "month":
                 $query->orWhere(function($query) use ($rule) {
                    $query->whereMonth($rule['field'], $rule['value']);
                });
                break;
            case "year":
                $query->orWhere(function($query) use ($rule) {
                    $query->whereYear($rule['field'], $rule['value']);
                });
                break;
            default:
        }
        return $query;
    }
    
    protected function searchSubmission($query, $rule)
    {
        $query->leftJoin('items', 'submissions.id','=','items.submission_id')
            ->leftJoin('payment_transactions','payment_transactions.submission_id','=','submissions.id')
            ->leftJoin('company_wallets','payment_transactions.company_wallet_id','=','company_wallets.id')
            ->join('users','users.id','=','submissions.partner_id')
            ->leftJoin('companies','users.company_id' ,'=','companies.id')
            ->select('submissions.*')
            ->where(function($function) use($rule){
            $function->where('submission_name','ILIKE', '%' . $rule['value'] . '%')
            ->orWhere('item_name','ILIKE', '%' . $rule['value'] . '%')
            ->orWhere('wallet_name','ILIKE', '%' . $rule['value'] . '%')
            ->orWhere('users.full_name','ILIKE', '%' . $rule['value'] . '%')
            ->orWhere('companies.legal_name','ILIKE', '%' . $rule['value'] . '%')
            ->orWhere('companies.business_name','ILIKE', '%' . $rule['value'] . '%');
            });
            return $query;
    }
    protected function applyCondition($query, $rule)
    {
        if ($rule['field']== 'visibilited_at') {
            return $query;
        }
        switch ($rule['operator']) {
            case "is":
                $query = $query->where($rule['field'], $rule['value']);
                break;
            case "lt":
                $query = $query->where($rule['field'], '<', $rule['value']);
                break;
            case "lte":
                $query = $query->where($rule['field'], '<=', $rule['value']);
                break;
            case "gt":
                $query = $query->where($rule['field'], '>', $rule['value']);
                break;
            case "gte":
                $query = $query->where($rule['field'], '>=', $rule['value']);
                break;
            case "not":
                $query = $query->where($rule['field'], '!=', $rule['value']);
                break;
            case "like":
                $query = $query->where($rule['field'], 'ILIKE', '%' . $rule['value'] . '%');
                break;
            case "not_like":
                $query = $query->where($rule['field'], 'NOT ILIKE', '%' . $rule['value'] . '%');
                break;
            case "in":
                if (in_array('null', $rule['value'])) {
                    $null = array_search('null', $rule['value']);
                    unset($rule['value'][$null]);
                    $query = $query->where(function($q) use ($rule) {
                        $q->whereIn($rule['field'],  $rule['value']);
                        $q->orWhereNull($rule['field']);
                    });
                } else {
                    $query = $query->whereIn($rule['field'], $rule['value']);
                }
                break;
            case "not_in":
              if (in_array('null', $rule['value'])) {
                  $null = array_search('null', $rule['value']);
                  unset($rule['value'][$null]);
                  $query = $query->where(function($q) use ($rule) {
                      $q->whereNotIn($rule['field'],  $rule['value']);
                      $q->orWhereNull($rule['field']);
                  });
              } else {
                  $query = $query->whereNotIn($rule['field'], $rule['value']);
              }
              break;
            case "between":
                $values = array_slice(explode(',', $rule['value']), 0, 2);
                $query = $query->whereBetween($rule['field'], $values );
                break;
            case "null":
                if ($rule['value'] == 'true') {
                    $query = $query->whereNull($rule['field']);
                } elseif ($rule['value'] == 'false') {
                    $query = $query->whereNotNull($rule['field']);
                }
                break;
            case "date":
                $query = $query->whereDate($rule['field'], $rule['value']);
                break;
            case "month":
                $query = $query->whereMonth($rule['field'], $rule['value']);
                break;
            case "year":
                $query = $query->whereYear($rule['field'], $rule['value']);
                break;
            default:
        }

        return $query;
    }
    
    protected function applyRelationInclusiveCondition($query, $rule)
    {
        $query = $query->orWhere(function($result) use($rule){
            $result->whereHas($rule['relation'], function ($builder) use ($rule) {
                $table = $builder->getModel()->getTable();
                $field = "{$table}.{$rule['field']}";

                switch ($rule['operator']) {
                    case "is":
                        $builder->where($field, $rule['value']);
                        break;
                    case "lt":
                        $builder->where($field, '<', $rule['value']);
                        break;
                    case "lte":
                        $builder->where($field, '<=', $rule['value']);
                        break;
                    case "gt":
                        $builder->where($field, '>', $rule['value']);
                        break;
                    case "gte":
                        $builder->where($field, '>=', $rule['value']);
                        break;
                    case "like":
                        $builder->where($field, 'ILIKE', '%' . $rule['value'] . '%');
                        break;
                    case "not_like":
                        $query = $query->where($rule['field'], 'NOT ILIKE', '%' . $rule['value'] . '%');
                        break;
                    case "in":
                        $builder->whereIn($field, $rule['value']);
                        break;
                    case "not_in":
                        $builder->whereNotIn($field, $rule['value']);
                        break;
                    case "between":
                        $values = array_slice(explode(',', $rule['value']), 0, 2);
                        $builder->whereBetween($field, $values );
                        break;
                    case "null":
                        if ($rule['value'] == 'true') {
                            $builder->whereNull($field);
                        } elseif ($rule['value'] == 'false') {
                            $builder->whereNotNull($field);
                        }
                        break;
                    case "date":
                        $query = $query->whereDate($field, $rule['value']);
                        break;
                    case "month":
                        $query = $query->whereMonth($field, $rule['value']);
                        break;
                    case "year":
                        $query = $query->whereYear($field, $rule['value']);
                        break;
                    default:
                }
            });
        });
        
        return $query;
    }
    protected function applyRelationCondition($query, $rule)
    {
        $query = $query->whereHas($rule['relation'], function ($builder) use ($rule) {
            $table = $builder->getModel()->getTable();
            $field = "{$table}.{$rule['field']}";

            switch ($rule['operator']) {
                case "is":
                    $builder->where($field, $rule['value']);
                    break;
                case "lt":
                    $builder->where($field, '<', $rule['value']);
                    break;
                case "lte":
                    $builder->where($field, '<=', $rule['value']);
                    break;
                case "gt":
                    $builder->where($field, '>', $rule['value']);
                    break;
                case "gte":
                    $builder->where($field, '>=', $rule['value']);
                    break;
                case "like":
                    $builder->where($field, 'ILIKE', '%' . $rule['value'] . '%');
                    break;
                case "not_like":
                    $query = $query->where($rule['field'], 'NOT ILIKE', '%' . $rule['value'] . '%');
                    break;
                case "in":
                    $builder->whereIn($field, $rule['value']);
                    break;
                case "not_in":
                    $builder->whereNotIn($field, $rule['value']);
                    break;
                case "between":
                    $values = array_slice(explode(',', $rule['value']), 0, 2);
                    $builder->whereBetween($field, $values );
                    break;
                case "null":
                    if ($rule['value'] == 'true') {
                        $builder->whereNull($field);
                    } elseif ($rule['value'] == 'false') {
                        $builder->whereNotNull($field);
                    }
                    break;
                case "date":
                    $query = $query->whereDate($field, $rule['value']);
                    break;
                case "month":
                    $query = $query->whereMonth($field, $rule['value']);
                    break;
                case "year":
                    $query = $query->whereYear($field, $rule['value']);
                    break;
                default:
            }
        });
        
        return $query;
    }
}
