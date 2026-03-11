<?php

namespace FouadFawzi\HistoryLogger\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function show(string $modelType, string $modelId): View
    {
        $modelClass = $this->resolveModelClass($modelType);

        abort_if($modelClass === null, 404, 'Model type is not registered in history-logger route.model_map.');

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($modelId);

        abort_if(! method_exists($model, 'historyLogs'), 400, 'This model does not use LogsHistory trait.');

        $histories = $model->historyLogs()->paginate((int) config('history-logger.route.per_page', 25));

        return view('history-logger::history', [
            'model' => $model,
            'histories' => $histories,
            'modelType' => $modelType,
        ]);
    }

    protected function resolveModelClass(string $modelType): ?string
    {
        $map = (array) config('history-logger.route.model_map', []);
        $modelClass = $map[$modelType] ?? null;

        if (! is_string($modelClass) || $modelClass === '') {
            return null;
        }

        return class_exists($modelClass) ? $modelClass : null;
    }
}
