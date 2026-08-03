<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesSyscacResources;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

abstract class SyscacCrudController extends Controller
{
    use HandlesSyscacResources;

    protected string $modelClass;
    protected string $title;
    protected string $icon = 'fas fa-folder';
    protected string $description = 'Base tecnica del modulo preparada.';
    protected array $with = [];
    protected array $fileFields = [];
    protected array $permissions = [];

    public function __construct()
    {
        $this->middleware('can:' . $this->permissions['index'])->only(['index', 'list']);
        $this->middleware('can:' . $this->permissions['create'])->only(['store']);
        $this->middleware('can:' . $this->permissions['edit'])->only(['update']);
        $this->middleware('can:' . $this->permissions['delete'])->only(['destroy']);

        if (isset($this->permissions['show'])) {
            $this->middleware('can:' . $this->permissions['show'])->only(['show']);
        }
    }

    public function index()
    {
        return view('admin.syscac.index', [
            'title' => $this->title,
            'icon' => $this->icon,
            'description' => $this->description,
        ]);
    }

    public function list()
    {
        $query = $this->modelClass::with($this->with)->orderBy('id', 'desc')->get();

        return DataTables::of($query)->addIndexColumn()->make(true);
    }

    public function store(Request $request)
    {
        $data = $this->prepareData($request, $this->rules());
        $model = $this->modelClass::create($data);

        return response()->json([
            'message' => $this->title . ' registrado correctamente',
            'data' => $model,
        ]);
    }

    public function show(int $id)
    {
        return response()->json($this->findModel($id)->load($this->with));
    }

    public function update(Request $request, int $id)
    {
        $model = $this->findModel($id);
        $data = $this->prepareData($request, $this->rules($model));
        $model->update($data);

        return response()->json([
            'message' => $this->title . ' actualizado correctamente',
            'data' => $model,
        ]);
    }

    public function destroy(int $id)
    {
        $this->findModel($id)->delete();

        return response()->json(['message' => $this->title . ' eliminado correctamente']);
    }

    abstract protected function rules(?Model $model = null): array;

    protected function prepareData(Request $request, array $rules): array
    {
        $data = $request->validate($rules, $this->validationMessages());

        foreach ($this->fileFields as $field => $directory) {
            $this->storeUploadedFile($request, $data, $field, $directory);
        }

        $this->applyAuditFields($data);

        return $this->mutateData($data);
    }

    protected function mutateData(array $data): array
    {
        return $data;
    }

    protected function findModel(int $id): Model
    {
        return $this->modelClass::findOrFail($id);
    }
}
